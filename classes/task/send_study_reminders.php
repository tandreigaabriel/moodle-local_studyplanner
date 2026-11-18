<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Sends each opted-in student their daily study summary at their preferred local time.
 *
 * @package    local_studyplanner
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_studyplanner\task;

use local_studyplanner\local\workload_calculator;

/**
 * Runs hourly and, for each user whose preferred reminder time falls in the
 * current hour in their own timezone, sends one "today's plan" notification.
 * A local_studyplanner_reminders row tracks the last send so a user is never messaged
 * twice in the same local day.
 */
class send_study_reminders extends \core\task\scheduled_task {
    /**
     * Returns the task's display name, shown in the scheduled task admin list.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task:sendstudyreminders', 'local_studyplanner');
    }

    /**
     * Sends the daily summary to every user whose local reminder hour has arrived.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $sql = "SELECT p.userid, p.remindertime, u.timezone
                  FROM {local_studyplanner_preferences} p
                  JOIN {user} u ON u.id = p.userid
                 WHERE p.remindersenabled = 1
                   AND u.deleted = 0
                   AND u.suspended = 0";
        $rows = $DB->get_records_sql($sql);

        $sent = 0;
        foreach ($rows as $row) {
            if ($this->should_send_now($row) && $this->send_summary((int) $row->userid)) {
                $sent++;
            }
        }

        mtrace("send_study_reminders: {$sent} daily summaries sent.");
    }

    /**
     * Checks whether now falls within the user's preferred reminder hour, and they have not
     * already been sent today's summary in their own local day.
     *
     * @param \stdClass $row Row with userid, remindertime and timezone.
     * @return bool
     */
    private function should_send_now(\stdClass $row): bool {
        global $DB;

        $tzname = \core_date::get_user_timezone($row->timezone);
        $tz = new \DateTimeZone($tzname);
        $now = new \DateTime('now', $tz);

        $preferredhour = (int) floor(((int) $row->remindertime) / 60);
        if ((int) $now->format('G') !== $preferredhour) {
            return false;
        }

        $todaystart = clone $now;
        $todaystart->setTime(0, 0, 0);

        $reminder = $DB->get_record('local_studyplanner_reminders', [
            'userid' => $row->userid,
            'remindertype' => 'dailysummary',
        ]);

        return !$reminder || (int) $reminder->lastsentat < $todaystart->getTimestamp();
    }

    /**
     * Builds and sends one user's daily summary notification.
     *
     * @param int $userid The user id.
     * @return bool True if a message was actually sent.
     */
    private function send_summary(int $userid): bool {
        global $DB;

        $tzname = \core_date::get_user_timezone($DB->get_field('user', 'timezone', ['id' => $userid]));
        $tz = new \DateTimeZone($tzname);
        $now = new \DateTime('now', $tz);
        $todaystart = clone $now;
        $todaystart->setTime(0, 0, 0);
        $todaystamp = $todaystart->getTimestamp();
        $tomorrowstamp = $todaystamp + DAYSECS;

        $todayitems = $DB->get_records_select(
            'local_studyplanner_plan_items',
            'userid = :userid AND status IN (:planned, :missed) AND plannedstart >= :start AND plannedstart < :end',
            [
                'userid' => $userid,
                'planned' => 'planned',
                'missed' => 'missed',
                'start' => $todaystamp,
                'end' => $tomorrowstamp,
            ],
            'plannedstart ASC'
        );

        [$requiredminutes, $totalavailable] = $this->get_week_load($userid, $now->getTimestamp());
        $overloaded = $requiredminutes > $totalavailable;

        if (empty($todayitems) && !$overloaded) {
            return false;
        }

        $body = $this->build_summary_body($todayitems, $requiredminutes, $totalavailable, $overloaded);
        $msgid = $this->dispatch_message($userid, count($todayitems), $body);

        if ($msgid) {
            $this->record_sent($userid, $now->getTimestamp());
        }

        return (bool) $msgid;
    }

    /**
     * Returns [requiredminutes, availableminutes] for the current week's active plan.
     *
     * @param int $userid The user id.
     * @param int $timestamp Any timestamp within the target week.
     * @return array{0: int, 1: int}
     */
    private function get_week_load(int $userid, int $timestamp): array {
        global $DB;

        $weekstart = workload_calculator::get_week_start($timestamp);
        $plan = $DB->get_record('local_studyplanner_plans', [
            'userid' => $userid,
            'periodstart' => $weekstart,
            'status' => 'active',
        ]);

        $requiredminutes = 0;
        if ($plan) {
            $requiredminutes = (int) $DB->get_field_select(
                'local_studyplanner_plan_items',
                'COALESCE(SUM(estimatedminutes), 0)',
                "planid = :planid AND status IN ('planned', 'missed')",
                ['planid' => $plan->id]
            );
        }

        $weekdaymap = workload_calculator::get_weekday_availability($userid);
        $totalavailable = array_sum(array_column($weekdaymap, 'minutes'));

        return [$requiredminutes, $totalavailable];
    }

    /**
     * Builds the plain-text body of the daily summary message.
     *
     * @param \stdClass[] $todayitems Today's planned/missed plan items, ordered by start time.
     * @param int $requiredminutes Total planned minutes for the week.
     * @param int $totalavailable Total available minutes for the week.
     * @param bool $overloaded Whether required minutes exceed available minutes.
     * @return string
     */
    private function build_summary_body(array $todayitems, int $requiredminutes, int $totalavailable, bool $overloaded): string {
        $lines = [];
        $lines[] = get_string('remindertodaycount', 'local_studyplanner', count($todayitems));

        foreach ($todayitems as $item) {
            $time = userdate((int) $item->plannedstart, get_string('strftimetime', 'langconfig'));
            $lines[] = '- ' . $item->title . ' (' . $time . ')';
        }

        if ($overloaded) {
            $lines[] = get_string('reminderoverload', 'local_studyplanner', $requiredminutes - $totalavailable);
        }

        return implode("\n", $lines);
    }

    /**
     * Sends the notification message itself.
     *
     * @param int $userid The recipient user id.
     * @param int $todaycount Number of items planned for today, used in the small message preview.
     * @param string $body The full message body.
     * @return int|bool The sent message id, or false on failure.
     */
    private function dispatch_message(int $userid, int $todaycount, string $body) {
        $msg = new \core\message\message();
        $msg->component = 'local_studyplanner';
        $msg->name = 'dailysummary';
        $msg->userfrom = \core_user::get_noreply_user();
        $msg->userto = \core_user::get_user($userid);
        $msg->subject = get_string('remindersubject', 'local_studyplanner');
        $msg->fullmessage = $body;
        $msg->fullmessageformat = FORMAT_PLAIN;
        $msg->fullmessagehtml = '<p>' . nl2br(s($body)) . '</p>';
        $msg->smallmessage = get_string('remindertodaycount', 'local_studyplanner', $todaycount);
        $msg->notification = 1;
        $msg->courseid = SITEID;

        return message_send($msg);
    }

    /**
     * Records that the daily summary has now been sent, so it is not sent twice in one local day.
     *
     * @param int $userid The user id.
     * @param int $timestamp The send timestamp.
     * @return void
     */
    private function record_sent(int $userid, int $timestamp): void {
        global $DB;

        $existing = $DB->get_record('local_studyplanner_reminders', ['userid' => $userid, 'remindertype' => 'dailysummary']);
        if ($existing) {
            $existing->lastsentat = $timestamp;
            $existing->timemodified = $timestamp;
            $DB->update_record('local_studyplanner_reminders', $existing);
            return;
        }

        $DB->insert_record('local_studyplanner_reminders', (object) [
            'userid' => $userid,
            'remindertype' => 'dailysummary',
            'lastsentat' => $timestamp,
            'timecreated' => $timestamp,
            'timemodified' => $timestamp,
        ]);
    }
}
