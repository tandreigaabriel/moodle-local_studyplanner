# Study Planner for Moodle

`local_studyplanner`

Maintained by Andrei Toma

## Description

A Moodle-native, cross-course, workload-aware personal study planner. It
reads deadlines across all of a student's enrolled courses, combines them
with the student's declared weekly availability, and automatically builds a
daily/weekly study schedule that reschedules itself when a session is
missed.

This is a student-facing tool, deliberately kept separate from
`local_courseinsights` and from any staff intervention workflow. Teachers can
optionally set how long an activity should take to study and how important
it is; they do not see students' personal plans unless a student chooses to
share them (a feature reserved for a future release).

## Features

- Collects deadlines from enrolled courses (assignments, quizzes, choices,
  workshops, lessons) automatically.
- Lets each student declare weekly availability: minutes per day and a
  preferred start time.
- Builds a rule-based, prioritised weekly plan — no AI, fully explainable:
  overdue and near-due work is prioritised over optional reading.
- Splits long tasks into multiple study sessions across the week when a
  single day cannot hold the whole thing.
- **Today** and **This Week** views, the latter with a per-day workload
  heatmap.
- Mark a session complete or skipped, or reschedule a missed session into
  the next available slot — with a full audit trail.
- Personal (non-Moodle) tasks: "buy textbook", "contact tutor", and so on.
- Workload overload warning when planned work exceeds available study time.
- Daily reminder notification via Moodle's messaging system, sent at each
  student's own preferred local time.
- Teachers can set per-activity study duration and importance
  (essential/recommended/optional) from a course page.
- Full Moodle Privacy API support: every personal data table is declared,
  exportable and deletable.

## Requirements

- Moodle 4.5 or later
- PHP 8.1 or later

## Installation

### Option A — Moodle admin interface

1. Log in as admin.
2. Go to Site administration → Plugins → Install plugins.
3. Upload the ZIP file.
4. Follow the upgrade prompts.

### Option B — Manual installation

1. Copy this plugin's contents into `<moodle>/local/studyplanner`.
2. Go to Site administration → Notifications.
3. Click "Upgrade Moodle database now".

## Configuration

Site administration → Plugins → Local plugins → Study Planner:

- **Enable study planner** — turns the whole plugin on or off site-wide.
- **Default study durations** — fallback minutes for assignments, quizzes,
  choice activities, workshops and lessons, used whenever a teacher has not
  set a specific estimate for that activity.

## Usage

### Students

- `today.php` — today's planned sessions, with mark complete / skip /
  reschedule actions.
- `week.php` — the current week at a glance, with a workload heatmap.
- `preferences.php` — weekly availability, session length, break length,
  scheduling intensity and reminder settings.
- `personaltasks.php` — add, edit and delete personal (non-Moodle) tasks.

### Teachers

- `courseestimates.php?courseid=X` — set estimated study duration and
  importance for every activity in a course.

## Troubleshooting

**A student's plan looks stale after they change their availability or add a
task.** The relevant pages queue an immediate ad-hoc `build_user_plan` task,
processed by the next cron run — make sure Moodle cron is running
(`php admin/cli/cron.php`).

**No reminder email/notification arrives.** Reminders are sent by the
`send_study_reminders` scheduled task, which runs hourly and only sends to a
student whose local time currently matches their configured reminder hour.
Confirm the task is enabled (Site administration → Server → Scheduled
tasks) and that the student has "Send me reminders" enabled in their
preferences.

## Support

Developed and maintained by Andrei Toma.

## License

GNU GPL v3 or later.

## Changelog

### 0.1.0

- Initial MVP release: availability preferences, automatic weekly
  scheduling, Today/This Week views, mark complete/skip/reschedule,
  personal tasks, workload warnings, daily reminders, teacher-set activity
  estimates, and full Privacy API support.
