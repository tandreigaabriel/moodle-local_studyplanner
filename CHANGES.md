# Changes

## 1.0.1 (2026-11-25)

- Copyright holder: TAG Digital Solutions Ltd (trading as TAG Web Design & Development); author Andrei Toma. No functional change.


## 1.0.0 (2026-11-18)

- Security: personal tasks can only be opened, edited or deleted by their owner; deleting asks for confirmation.
- The calendar feed address can be reset, which revokes old links immediately.
- Sessions for activities a student can no longer see (unenrolled, hidden or deleted) are no longer shown in Today, This week or the calendar feed.
- Privacy API declares every stored field; PHPUnit tests for privacy, the calendar token, web services and visibility.
- Built JavaScript is now included in the package.
- Plans made in the middle of the week no longer put sessions on days or hours that are already over; rescheduling a missed session uses the same rule.
- This week shows the Personal tasks button, like Today.

## 0.1.0

- Initial release: availability preferences, automatic weekly scheduling, Today and This week views, mark complete/skip/reschedule, personal tasks, workload warnings, daily reminders, teacher-set activity estimates.
