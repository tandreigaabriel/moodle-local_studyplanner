# local_studyplanner — Enterprise Build Spec

Working document to carry over to the machine that has the Moodle plugin skill.
Consolidates `idea.md` into a buildable spec, focused on the **Enterprise** tier,
and flags the gaps/inconsistencies found in the original brainstorm.

---

## 1. Product summary

Moodle-native, cross-course, workload-aware personal study planner. Reads
deadlines across all a student's courses, combines them with declared weekly
availability, and produces a daily/weekly study schedule with automatic
rescheduling of missed sessions.

Kept deliberately **separate** from Course Insights (not yet stable) and from
any staff-visible intervention tooling — this plugin is student-facing first.

Frankenstyle component name: `local_studyplanner`
Optional companion: `block_studyplanner` (dashboard widget, thin wrapper over
the same API — build after the local plugin's data layer is stable).

---

## 2. What idea.md got right (keep)

- MVP explicitly excludes AI/prediction — rule-based priority scoring only.
- Privacy separation: staff see aggregated/anonymised workload, never personal
  commitments (medical, family, etc.) or session-level detail.
- Non-hostile licensing: expired subscription = feature-frozen, not disabled.
- Reschedule-on-miss is the actual differentiator vs. Moodle's stock calendar.
- Teacher involvement stays minimal (duration estimates, priority flags only).

## 3. Inconsistencies to resolve before building

`idea.md` contains **two different schema proposals** written at different
times (lines ~68–93 and ~450–475). They don't agree with each other or with
the feature list. Pick one before writing `db/install.xml`:

| Concept | Proposal A | Proposal B |
|---|---|---|
| Session container | `local_sp_sessions` (flat) | `local_sp_plans` + `local_sp_plan_items` (grouped by weekly plan) |
| Personal (non-Moodle) tasks | folded into `local_sp_tasks` | separate `local_sp_personal_tasks` |
| Sharing with staff | not modeled | `local_sp_shares` |
| Reschedule audit | `local_sp_reschedule_log` | not modeled (status field only) |

**Recommendation:** use Proposal B's `plans` → `plan_items` grouping (a plan
is a unit you can regenerate/version per student per week) but keep Proposal
A's `local_sp_reschedule_log` for audit trail — enterprise customers will ask
"why did this move" during procurement due diligence. See §6 for the merged
schema.

## 4. Enterprise-tier scope (this build)

Everything in Starter + Professional, plus:

- **Multi-campus / multi-tenant support** — a `local_sp_institutions` (or
  reuse Moodle cohorts/categories) scoping layer so workload rules, branding,
  and reporting can differ per campus within one Moodle instance.
- **SIS / timetable integration** — inbound feed only for v1 (see §7), not a
  live bidirectional sync.
- **University-wide workload analytics** — aggregated, anonymised, no
  individual drill-down without explicit student opt-in (`local_sp_shares`).
- **Institutional planning rules** — admin-configurable ceilings (max daily
  study minutes, blackout periods, exam-period overrides) applied globally or
  per campus/programme.
- **Microsoft Teams integration** — notification channel only for v1 (post
  "today's plan" to a Teams webhook/bot), not a Teams app surface.
- **Adviser caseload integration** — read-only aggregated view for advisers
  tied to Moodle's existing cohort/mentor role, not a new relationship model.
- **API access** — Moodle external functions (`db/services.php`) exposing
  plan read, session complete/reschedule, and anonymised reporting endpoints,
  token-scoped per capability.
- **Custom branding** — theme-level override points (logo, colour tokens),
  not a full white-label templating engine in v1.
- **Scheduled adoption reports** — a scheduled task emails/exports the
  anonymised institutional metrics on a cron (weekly/monthly).
- **Accessibility accommodations** — extended session length / extra
  break time multipliers keyed off Moodle's existing accessibility profile
  data where available, otherwise a manual per-student override.
- **Custom data-retention controls** — admin-configurable retention window
  per table, wired into `classes/privacy/provider.php` (Moodle Privacy API),
  not a bespoke retention engine.

Deliberately **out of scope for v1 enterprise**, even though idea.md lists
them: live bidirectional SIS sync, full Google/Microsoft calendar write-back,
custom branding as a multi-tenant theming platform. These are expensive and
not needed to close the first institutional deal — flag them as roadmap.

## 5. Capability list (extends idea.md's list)

```
local/studyplanner:viewownplan
local/studyplanner:manageownplan
local/studyplanner:viewstudentplans          (adviser, opt-in only)
local/studyplanner:viewaggregatedworkload    (teacher/dept, anonymised)
local/studyplanner:manageestimates           (teacher)
local/studyplanner:configureplanner          (admin, site-level)
local/studyplanner:exportreports             (admin/adviser)
local/studyplanner:configureinstitution      (enterprise: per-campus rules)
local/studyplanner:manageapikeys             (enterprise: API access)
local/studyplanner:viewinstitutionalanalytics (enterprise: cross-campus)
local/studyplanner:manageretention           (enterprise: data-retention config)
```

## 6. Merged database schema (proposal)

```
local_sp_preferences        -- per-user weekly availability + planning prefs
local_sp_institutions        -- [enterprise] campus/tenant scoping, optional
local_sp_availability         -- per-user per-weekday available minutes
local_sp_activity_estimates   -- per-cmid duration override (teacher-set)
local_sp_plans                 -- one row per student per planning cycle (week)
local_sp_plan_items            -- individual scheduled sessions within a plan
local_sp_personal_tasks       -- non-Moodle student-created tasks
local_sp_reschedule_log        -- audit trail: old slot -> new slot, reason
local_sp_shares                -- student -> adviser/tutor visibility grants
local_sp_reminders            -- reminder preferences / delivery log
local_sp_institutional_rules  -- [enterprise] admin-set ceilings/blackouts
local_sp_api_tokens            -- [enterprise] scoped API access
```

`local_sp_plan_items` (core table) fields:
```
id, userid, courseid, cmid, planid, title,
plannedstart, plannedend, estimatedminutes, actualminutes,
priority, status, duedate, source, timecreated, timemodified
```
`status` enum: planned, in_progress, completed, missed, rescheduled, skipped,
cancelled — matches idea.md's list, keep as-is.

## 7. SIS/timetable integration — recommended v1 approach

Idea.md just says "SIS timetable integration" with no mechanism. For a first
enterprise release, avoid building bespoke connectors per SIS vendor:

- **Inbound only**: scheduled task ingests a CSV/SFTP or REST feed of
  timetabled commitments (lectures, labs, placements) that count against a
  student's *available* time, reducing planner capacity on those slots.
- Model it as a `source = 'sis'` row type in `local_sp_availability`
  overrides, not a new commitments engine.
- Defer true bidirectional sync and vendor-specific adapters (Banner, SITS,
  PowerCampus, etc.) to paid custom-integration work (idea.md already prices
  this separately at £2,500–£10,000+ — keep that line item, don't build it
  speculatively).

## 8. Architecture notes for the enterprise build

- **Scheduling engine** stays rule-based (priority score formula in idea.md
  §"Scheduling logic" is fine as a v1 baseline) — keep it swappable behind an
  interface (`priority_calculator.php`) so a future paid AI-assisted mode can
  replace it without touching the rest of the pipeline.
- **Adhoc/scheduled tasks** (`\local_studyplanner\task\*`) should be the only
  place plans are (re)generated — never generate on page load for a whole
  cohort; use a per-user adhoc task queued on relevant events (deadline
  changed, availability changed, session marked missed).
- **Reporting** (institutional analytics) must aggregate server-side with a
  minimum cohort size threshold (e.g. suppress any breakdown < 5 students) to
  avoid re-identifying individuals — this is a common institutional
  procurement/DPA requirement, not just nice-to-have.
- **Privacy API**: implement `classes/privacy/provider.php` from day one,
  even for MVP tables — retrofitting this later is expensive and it's an
  installation blocker for GDPR-conscious institutions.
- **Multi-campus**: don't build a new tenancy model — scope by Moodle
  category/cohort and let `local_sp_institutions` just be metadata
  (branding, rules) attached to a category, not a parallel user system.

## 9. Open questions (answer before/while building on the other machine)

1. Target Moodle versions: 4.5 LTS + 5.x, or wider back-compat?
2. Is the companion `block_studyplanner` in scope for v1, or later?
3. API access (§4) — REST via Moodle web services token auth, or do you want
   OAuth2/LTI-style auth for enterprise API consumers?
4. Teams integration: incoming webhook (simple, v1-appropriate) vs. full
   Teams app — confirm webhook is enough for launch.
5. Should `local_sp_institutions` be built now (even if only one campus
   exists at launch) so early customers don't need a schema migration later,
   or deferred until the first multi-campus customer signs?
6. Licensing/entitlement check — idea.md rules out hostile disabling, but
   doesn't say *how* tier entitlement is checked (local config value set
   during install/license activation? phone-home license server? fully
   trust-based with no technical enforcement?). This affects whether an
   `classes/local/licensing.php` service needs to exist at all in v1.

---

*Source: distilled from `idea.md` (existing brainstorm/pricing notes).
No code written yet — this is the spec to hand to the build machine.*
