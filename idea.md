What already exists—and what is missing

There are related Moodle plugins, but they solve different problems.

Study Plans

The local_treestudyplan plugin provides a graphical curriculum view organised into periods and layers, with student results. That is mainly programme and curriculum planning, not daily personal workload scheduling.

Learning Plan

The older block_learning_plan plugin manages structured learning programmes and management reports. Its latest listed release is several years old, and it is not a dynamic personal study scheduler.

Timetable

The Timetable block displays upcoming events by day or week. It improves presentation but does not divide assignments into study sessions or automatically rebalance missed work.

Event Reminders

Event Reminders sends notifications for calendar events. It reminds the student about a deadline but does not construct a preparation plan.

Personalised Study Guide

The Personalised Study Guide format provides adaptive learning paths within a course. It focuses on which course content a student should access, rather than balancing deadlines and study time across all their courses. Its Moodle directory usage also appears relatively limited.

Learning Tools

A newer Learning Tools plugin includes several micro-tools and mentions scheduling study time. This means you would need to investigate it closely before development, but a comprehensive, cross-course, automatically rescheduling institutional study planner still appears differentiated from a collection of smaller tools.

The commercial gap is therefore:

A Moodle-native, cross-course, workload-aware planner that automatically builds and adjusts a personalised weekly study schedule.

Suggested plugin architecture

I would build it as a separate plugin:

local_studyplanner

Optional dashboard block:

block_studyplanner

Optional mobile support through Moodle’s mobile-plugin framework.

Suggested components:

local_studyplanner/
├── classes/
│   ├── local/
│   │   ├── planning_engine.php
│   │   ├── priority_calculator.php
│   │   ├── workload_calculator.php
│   │   ├── duration_estimator.php
│   │   └── rescheduling_service.php
│   ├── task/
│   │   ├── generate_plans.php
│   │   ├── recalculate_plans.php
│   │   └── send_daily_summary.php
│   ├── privacy/
│   │   └── provider.php
│   └── external/
│       └── mobile_api.php
├── dashboard.php
├── calendar.php
├── preferences.php
├── reports.php
└── db/
Suggested database tables
local_sp_preferences
local_sp_availability
local_sp_commitments
local_sp_tasks
local_sp_sessions
local_sp_task_breakdowns
local_sp_activity_estimates
local_sp_checkins
local_sp_reschedule_log
local_sp_notifications
Example study session
id
userid
courseid
cmid
taskid
plannedstart
plannedend
estimatedminutes
actualminutes
status
priority
source
timecreated
timemodified

Possible statuses:

Planned
In progress
Completed
Missed
Rescheduled
Skipped
Cancelled
Required Moodle capabilities
local/studyplanner:viewownplan
local/studyplanner:manageownplan
local/studyplanner:viewstudentplans
local/studyplanner:viewaggregatedworkload
local/studyplanner:manageestimates
local/studyplanner:configureplanner
local/studyplanner:exportreports

Staff should not automatically see personal commitments such as employment, medical appointments or family responsibilities.

A lecturer may only need to see:

Student planned workload: overloaded
Completion of planned sessions: 40%
Support may be required

They should not see:

Tuesday 18:00 — medical appointment
Saturday — family commitment

Privacy separation is important.

Minimum viable product

The first version should avoid AI and complicated prediction.

MVP features
Collect deadlines across all enrolled courses.
Let students define weekly available study time.
Let teachers add estimated activity durations.
Automatically create weekly study sessions.
Prioritise deadlines and assessed work.
Show today’s recommended tasks.
Allow students to mark sessions complete.
Automatically reschedule missed tasks.
Display a workload heatmap.
Send daily or weekly reminders.
Export sessions to Google, Outlook or an ICS calendar.
Connect overdue patterns to Course Insights.

That would already be commercially useful.

Professional version

A paid version could add:

assignment task breakdowns;
examination revision schedules;
workload conflict detection;
spaced revision;
adviser visibility;
confidence check-ins;
programme templates;
automatic recovery plans;
advanced reports;
mobile notifications;
Microsoft 365 and Google Calendar synchronisation;
Course Insights integration;
Intervention Manager integration.
Enterprise version

An enterprise version could include:

SIS timetable integration;
university-wide workload analytics;
multi-campus support;
institutional planning rules;
Microsoft Teams integration;
adviser caseload integration;
anonymous workload reporting;
API access;
custom branding;
scheduled adoption reports;
support for accessibility accommodations;
custom data-retention controls.
Commercial strengths
High student interest

The benefit is immediately understandable:

“Moodle tells me exactly what I should work on today.”

Students do not need to understand analytics or institutional processes.

Easy to demonstrate

A product demonstration could show:

Five upcoming deadlines.
Twelve available study hours.
One click.
A complete weekly plan.

That is much easier to sell than a complicated backend integration.

Suitable for institutions and individuals

You could potentially offer:

institutional Moodle licence;
premium student version;
Moodle
i dont want to integrate with course insights the course inshits nstill need more work 

That makes sense. Keep the Personal Study Planner completely separate from Course Insights. Course Insights should first be stabilised and finished before you expand it.

The Personal Study Planner could be a new plugin, for example:

local_studyplanner

Its purpose would be student-facing rather than staff-facing.

What problem it solves

Moodle already gives students:

a calendar;
upcoming deadlines;
activity completion;
course progress;
dashboard timelines.

But it does not really tell the student:

“Based on all my courses, deadlines, available time and current progress, what should I study today?”

The planner would turn Moodle activities into a realistic personal schedule.

Student experience

When the student opens the planner for the first time, it asks:

how many hours they can study each week;
which days they are available;
preferred study times;
maximum session length;
whether weekends are available;
whether they want lighter or more intensive scheduling.

For example:

Monday: 2 hours
Tuesday: unavailable
Wednesday: 3 hours
Thursday: 1 hour
Friday: 2 hours
Saturday: 4 hours
Sunday: unavailable

The plugin then reads their Moodle enrolments and identifies:

assignment deadlines;
quiz closing dates;
lessons;
pages and resources;
required activities;
course completion conditions;
estimated study duration;
overdue work.

It generates a weekly plan such as:

Monday
18:00–18:45 — Review Digestive System lecture
18:45–19:00 — Break
19:00–20:00 — Work on Assignment 2

Wednesday
18:00–18:30 — Complete Week 4 quiz
18:30–19:15 — Read Brain-Gut Axis resources
19:30–20:30 — Continue Assignment 2
Main features
Today view

A simple page showing:

Your study plan for today

1. Complete Week 4 quiz — 30 minutes
2. Review lecture notes — 45 minutes
3. Work on Assignment 2 — 60 minutes

Each item would link directly to the Moodle activity.

Automatic scheduling

The plugin should allocate activities according to:

due date;
estimated duration;
assignment weighting;
activity priority;
prerequisite activities;
student availability;
overdue status.

A major assignment due tomorrow should take priority over optional reading due next month.

Estimated activity duration

Teachers could optionally set an estimated duration when configuring an activity:

Estimated study time: 45 minutes

Where no estimate exists, the plugin could use defaults:

Page: 15 minutes
File: 20 minutes
Quiz: 30 minutes
Assignment: 120 minutes
Lesson: 45 minutes
Forum task: 30 minutes

Students could correct the estimate for their own plan without changing the course activity.

Break large tasks into sessions

A four-hour assignment should not appear as one massive task.

The planner could divide it into:

Understand the assignment — 30 minutes
Research — 60 minutes
Draft section 1 — 60 minutes
Draft section 2 — 60 minutes
Review and submit — 30 minutes

The first version does not need AI to do this. It could simply split work into equal sessions.

Automatic rescheduling

When the student misses a planned session, the system asks:

You did not complete this study session.

Reschedule
Mark complete
Skip

Selecting Reschedule moves it into the next available slot before the deadline.

This is one of the main differences between the planner and Moodle’s normal calendar.

Workload warnings

The planner could warn students when their commitments exceed their available study time:

You have approximately 14 hours of work due this week,
but only 8 available study hours.

You may need to increase your availability or contact your tutor.

It should not promise that its estimates are exact. They are planning estimates.

Assignment preparation timeline

Instead of only displaying the final deadline, the plugin could create internal milestones:

Assignment due: 30 August

20 August — Review instructions
21–23 August — Research
24 August — Create outline
25–27 August — Draft
28 August — Edit
29 August — Final review
30 August — Submit

These milestones would be private to the student and would not alter the official Moodle assignment.

Personal tasks

Students should also be able to create tasks that do not come from Moodle:

Attend revision session
Buy textbook
Contact tutor
Review feedback
Prepare questions for class
Calendar display

Useful views would include:

Today;
This week;
Calendar;
Upcoming deadlines;
Overdue;
Completed;
Workload.
Teacher features

Teachers should have limited involvement.

They could:

add estimated study duration to activities;
mark activities as essential, recommended or optional;
suggest when students should start an assignment;
define standard preparation stages;
view anonymised workload totals for the course.

Teachers should not automatically see every student’s personal study plan. That could feel intrusive.

A student could voluntarily share their plan with a tutor or adviser.

Administrator features

Administrators could configure:

default durations by activity type;
permitted planning hours;
reminder frequency;
supported activity types;
whether calendar synchronisation is enabled;
privacy and retention settings;
maximum daily study time;
default break length;
whether students can share plans with staff.
Plugin architecture

A sensible structure would be:

local_studyplanner/
├── classes/
│   ├── local/
│   │   ├── plan_generator.php
│   │   ├── scheduling_service.php
│   │   ├── activity_provider.php
│   │   ├── workload_calculator.php
│   │   └── rescheduling_service.php
│   ├── task/
│   │   ├── refresh_student_plans.php
│   │   └── send_study_reminders.php
│   └── privacy/
│       └── provider.php
├── dashboard.php
├── today.php
├── calendar.php
├── preferences.php
├── reports.php
├── db/
├── templates/
└── amd/
Possible database tables
local_sp_preferences
local_sp_plans
local_sp_plan_items
local_sp_availability
local_sp_activity_estimates
local_sp_personal_tasks
local_sp_reminders
local_sp_shares

For example, local_sp_plan_items could store:

id
userid
courseid
cmid
title
plannedstart
plannedend
estimatedminutes
priority
status
duedate
source
timecreated
timemodified
Scheduling logic

The first version should use normal rules rather than AI.

A simple priority score could consider:

Deadline proximity
Overdue status
Activity importance
Percentage of task remaining
Course priority
Available time before deadline

Example logic:

Overdue activity                  +100 priority
Due within 24 hours               +80
Due within 3 days                 +60
Required completion activity      +30
Major assignment                  +30
Optional activity                 -20
Already partially completed       +10

The planner would schedule the highest-priority work into the next available study slots.

Notifications

Possible reminders:

Your first study session starts at 18:00.

You have three planned activities today.

Assignment 2 requires approximately four more study hours.

You missed yesterday’s session. It has been rescheduled to Wednesday.

You may not have enough available time to complete this week’s work.

Students must be able to reduce or disable reminders.

Calendar integrations

Calendar integrations should come later, not in the first release.

Possible future integrations:

Moodle calendar;
Google Calendar;
Microsoft Outlook;
downloadable .ics calendar feed.

For version one, keep planning inside Moodle and optionally create normal Moodle calendar events.

MVP version

The first useful version only needs:

Student weekly availability.
Reading Moodle deadlines.
Activity-duration estimates.
Automatic weekly planning.
Today and This Week pages.
Mark complete, skip and reschedule.
Personal tasks.
Workload warnings.
Moodle reminders.
Teacher-defined duration and priority.

Do not initially build:

AI-generated study advice;
Google and Outlook integrations;
staff intervention workflows;
predictive analytics;
complex grade analysis;
mobile application;
study-group matching.
Commercial model
Free version
one-week plan;
manual personal tasks;
basic deadline import;
default duration estimates;
Today view.
Professional version
automatic rescheduling;
assignment milestone generation;
workload warnings;
custom availability;
reminders;
teacher duration settings;
calendar views.
Institutional version
programme workload reporting;
custom branding;
Microsoft and Google calendar integration;
configurable planning rules;
institutional templates;
APIs;
priority support.
Biggest commercial challenge

Students may like the idea, but students are usually not the direct purchaser. The plugin must also demonstrate institutional benefits:

fewer missed deadlines;
improved completion;
better time management;
fewer extension requests;
improved student satisfaction;
early visibility of unrealistic course workload.

So the plugin should remain independent, but it should include anonymised institutional reporting such as:

Average estimated weekly workload
Weeks with excessive deadline concentration
Activities frequently postponed
Courses with unrealistic workload estimates
Percentage of students using the planner

That gives a university a reason to pay while keeping individual student plans private.

and how much i can charge for this plugin
Worked for 59s

For a well-built Personal Study Planner, I would charge by Moodle site and institution size—not sell it cheaply as a £50 plugin.

Recommended launch pricing
Licence	Institution size	Annual price
Starter	Up to 500 active students	£495/year
Professional	Up to 2,500 active students	£1,250/year
Institution	Up to 10,000 active students	£2,750/year
Enterprise	More than 10,000 students	From £5,000/year

For your first three to five customers, offer an early-adopter price:

Small institution: £350–£500 per year
Medium institution: £900–£1,500 per year
University: £2,000–£3,500 per year

In exchange, they provide structured feedback, testing and a testimonial.

What each version should include
Starter — £495/year
One Moodle production site
Student availability settings
Automatic weekly planner
Today and This Week views
Moodle deadline import
Personal tasks
Basic rescheduling
Email support
Updates for supported Moodle versions
Professional — £1,250/year

Everything in Starter, plus:

automatic workload warnings;
assignment milestone breakdowns;
teacher-defined duration estimates;
configurable activity priorities;
reminder rules;
workload heatmap;
advanced rescheduling;
Moodle calendar synchronisation;
basic institutional usage reports;
staging or test-site entitlement.
Institution — £2,750/year

Everything in Professional, plus:

up to 10,000 active students;
programme-level workload reports;
department configuration;
institutional planning templates;
staff roles and permissions;
Google or Microsoft calendar integration;
custom reminder templates;
API access;
priority support;
production, staging and development installations.
Enterprise — from £5,000/year

This should be quoted individually and include:

unlimited or negotiated active students;
multiple Moodle sites;
multi-campus deployment;
SIS or timetable integration;
onboarding assistance;
custom branding;
security documentation;
service-level agreement;
priority upgrades;
dedicated support hours.

University scheduling products can cost around $10,000–$12,000 annually, although those products usually cover much broader academic scheduling than this proposed planner. That shows universities will pay institutional prices when a system solves a meaningful planning problem.

Installation and onboarding fees

Do not include unlimited setup work in the annual licence.

Charge separately:

Service	Suggested price
Standard remote installation	£250–£400
Installation and configuration	£600–£1,000
Staff training session	£350–£600
Custom branding	£300–£750
Calendar integration setup	£750–£1,500
SIS or custom integration	£2,500–£10,000+
Custom development	£500–£700 per day

For example, a medium college could purchase:

Professional annual licence: £1,250
Installation and configuration: £750
Training: £450

First-year total: £2,450
Renewal from year two: £1,250

That is easier to justify than charging £2,450 every year without separating implementation work.

Beta-stage pricing

Do not charge the full institutional rate before the plugin is proven.

Private beta

Charge approximately:

£0–£300

Use one or two institutions as development partners. They must accept that features may change and provide proper testing.

Stable MVP

Once these features work reliably:

weekly availability;
deadline collection;
automatic planning;
rescheduling;
Today view;
workload warnings;
reminders;

you could charge:

£350–£750 per site per year
Mature version

Once you have:

automated tests;
Moodle 4.5 and 5.x support;
privacy implementation;
accessibility testing;
institutional reports;
documentation;
upgrade paths;
responsive support;

move to the full £495–£5,000+ structure.

One-time licence option

Some Moodle customers prefer one-time purchases. A commercial Moodle plugin currently advertises a proctoring product at approximately $1,199 for a lifetime licence, showing that this model exists in the market.

However, I would avoid offering unlimited lifetime updates. Moodle changes regularly, and every major version creates ongoing development and support work.

A safer option is:

Perpetual licence: £2,500
Includes: 12 months of updates and support
Annual maintenance after year one: £750

For a larger institution:

Perpetual institutional licence: £5,000
Annual maintenance: £1,250

The customer may continue using the version they received, but updates, support and compatibility releases require active maintenance.

Do not use per-student monthly pricing initially

A price such as:

£1 per student per month

sounds attractive, but for a university with 10,000 students it becomes £120,000 annually. That would put you in competition with major enterprise platforms and create much higher expectations around availability, support, security and procurement.

A capped site licence will be easier to sell:

Up to 500 students
Up to 2,500 students
Up to 10,000 students
Enterprise

The official Moodle App provides a useful lower-market reference: its Premium institutional plan is listed at €499 per year, but it is a broadly distributed official product with a much larger potential customer base. Your planner would be a specialised institutional workflow product, so it can reasonably cost more once mature.

Important Moodle licensing consideration

Moodle plugin files that implement the Moodle interface generally need to be licensed under GPL v3 or later. GPL does not prevent you from charging money, but recipients generally receive rights to use, modify and redistribute the code.

Therefore, your commercial value should come from:

access to official releases;
updates;
Moodle-version compatibility;
support;
documentation;
installation;
integrations;
security fixes;
hosted services where applicable;
enterprise contracts.

Do not design a hostile licence check that disables students’ planners immediately when a subscription expires. Universities will dislike that, and it can create operational risk.

A better approach is:

Expired subscription:
• Existing installed version continues working
• No access to new versions
• No compatibility updates
• No technical support
• No premium cloud integrations
My recommended price for your first release

For your actual position as a new independent developer, I would launch with:

Personal Study Planner

Early Adopter — £495/year
Up to 2,000 active students
One production site plus one test site
All current MVP features
Updates and email support

Then, after three successful deployments:

Starter — £595/year
Professional — £1,495/year
Institution — £2,995/year
Enterprise — from £5,000/year

Your first goal should not be maximising the licence price. It should be obtaining:

one real production deployment;
evidence of student adoption;
evidence that students complete planned work;
a testimonial;
a case study;
a stable upgrade process.

Once you can demonstrate that the planner reduces missed deadlines or improves completion, £2,000–£5,000 annually per institution becomes realistic.