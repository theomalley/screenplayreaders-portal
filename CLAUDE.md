# CLAUDE.md — screenplayreaders-portal

> **IMPORTANT FOR FUTURE SESSIONS:** Read this file fully before touching any code.
> It describes the full architecture, feature spec, conventions, and the relationship
> to the companion `screenplayreaders-theme` WordPress/WooCommerce repo.

---

## What This Is

**screenplayreaders-portal** (hereafter "PORTAL") is a standalone Laravel application for
[Screenplay Readers](https://screenplayreaders.com) — a screenplay coverage and script registration
service. It is built alongside (not replacing) the existing `screenplayreaders-theme` WordPress/WooCommerce
repo, which handles all customer-facing purchasing. PORTAL handles the internal workflow and eventual
public-facing coverage directory.

The Laravel app lives in the `screenplayreaders-portal/` subdirectory of this repo.

---

## Relationship to screenplayreaders-theme (WordPress repo)

- The WordPress theme handles all customer purchases, WooCommerce orders, and the existing
  script-upload system (`sr-upload-system.php`).
- PORTAL receives data FROM the WordPress side (order completions, uploaded scripts) and
  manages the internal workflow that follows.
- **Both systems must remain fully operational in parallel during all PORTAL development.**
  Never disable, break, or deprecate WordPress-side features until the PORTAL replacement
  has been proven to work perfectly in production.
- When PORTAL features replace WordPress/Zapier features, the replacement flow runs as a
  **new, separate** set of code, files, and Zaps until proven. The old ones are then turned off.
- Code that bridges the two systems should be clearly commented:
  `// PORTAL INTEGRATION: <description>` on the WordPress side,
  and reference the WordPress function or hook name in the PORTAL codebase.

---

## Technology Stack

- **Framework:** Laravel (PHP) — app is in `screenplayreaders-portal/` subdirectory
- **Database:** MySQL — Laravel migrations + Eloquent
- **File storage:** Google Drive API (scripts as PDFs, coverage as Google Docs) — see File Strategy
- **Auth:** Laravel Breeze or Jetstream — email + password for all roles (decide at scaffold time)
- **Frontend:** Blade templates + Alpine.js + Tailwind CSS — no Vue/React
- **PDF manipulation:** Google Drive export API; local PDF library (e.g. spatie/pdf-to-image) for title page removal
- **Queue/jobs:** Laravel queues for all Google Drive API calls and Zapier webhooks
- **Email:** Laravel Mail; HelpScout integration for customer-facing delivery

---

## Development Phases

### Phase 1 — Assignments Module (current focus)

Internal tool: admins/editors create and manage script assignments, readers accept assignments
and write coverage, admins/editors QC coverage and deliver to customers via HelpScout.
No public-facing output in Phase 1.

### Phase 2 — Public Listings Module (future, do not build yet)

A public-facing (membership-gated) searchable, genre-sortable directory of scripts and coverage.
Writers and Producers access it with role-appropriate views. Phase 2 slots onto the Phase 1
data model — leave `is_public` / `public_opt_in` fields open for it, but do not implement
Phase 2 logic during Phase 1 work.

---

## User Roles

Five roles. Laravel Gates/Policies enforce all access — no inline role checks in controllers.

| Role | Access |
|------|--------|
| `admin` | Full access: create/edit/delete assignments, manage users, set any status |
| `editor` | Same as admin for Assignments Module; cannot manage users |
| `reader` | View assignment list, accept/cancel own assignments, view scripts (Drive view-only), submit coverage |
| `writer` | Phase 2 only — public listings (script + coverage excerpts). No Phase 1 access |
| `producer` | Phase 2 only — public listings (different subset than writers). No Phase 1 access |

All roles: login/password auth, profile avatar, change email, change password.

### Reader Profiles (`reader_profiles` table, 1:1 with users)

| Field | Type | Notes |
|-------|------|-------|
| `initials` | string | e.g. `JF`, `TZ` — primary display identifier throughout app |
| `first_name` | string | |
| `last_name` | string | |
| `photo` | string | URL/path |
| `max_concurrent_assignments` | int | Hard cap enforced at accept time |
| `paypal_email` | string | |

---

## Core Data Model (Phase 1)

### `assignments` table

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `order_number` | string | From WooCommerce |
| `script_title` | string | |
| `author_first_initial` | char(1) | |
| `author_last_name` | string | |
| `page_count` | int | |
| `requested_reader_id` | FK → users, nullable | Customer-requested reader |
| `rush` | boolean | 24h turnaround from `unassigned_at` |
| `pay_rate` | decimal(8,2) | |
| `notes` | text, nullable | |
| `status` | enum | See statuses below |
| `drive_script_file_id` | string | Google Drive file ID of script PDF |
| `drive_coverage_doc_id` | string, nullable | Google Drive Doc ID of coverage |
| `drive_coverage_pdf_id` | string, nullable | Google Drive file ID of coverage PDF |
| `assigned_reader_id` | FK → users, nullable | |
| `vendor` | enum: `sr`, `wd` | SR = Screenplay Readers; WD = Writers Digest. Determines which coverage form is presented to the reader. |
| `assignment_type` | string, nullable | Set by admin at creation. SR values: `script_coverage`, `notes_only`, `short`, `deep_dive`, `budget`, `book`. WD values: `coverage`, `development_notes`. Determines coverage form layout and word-count requirements. |
| `public_opt_in` | boolean | Customer consent for Phase 2 public listing |
| `unassigned_at` | timestamp, nullable | When status set to Unassigned (rush deadline base) |
| `accepted_at` | timestamp, nullable | |
| `submitted_at` | timestamp, nullable | |
| `completed_at` | timestamp, nullable | |
| `created_at` / `updated_at` | timestamps | |

### Assignment Statuses

| Status | Meaning | Reader visibility |
|--------|---------|------------------|
| `incoming` | Created; not yet reviewed. | Hidden from readers |
| `unassigned` | Reviewed and published; available to accept. | Visible to all readers |
| `assigned` | A reader has accepted. | Hidden from other readers; visible to admin/editor |
| `completed` | Reader submitted coverage. | Reader sees own; hidden from others |
| `qc` | Admin/editor reviewing coverage. | Same as completed |
| `cancelled` | Cancelled. | Hidden from readers |
| `on_hold` | On hold. | Hidden from readers |

#### Colour coding

- `unassigned` → yellow/amber
- `assigned`, `completed`, `qc` → green
- `cancelled`, `on_hold` → red
- Rush + `unassigned` → bold amber border or prominent badge — must be visually unmistakable

### `coverage_submissions` table

Linked 1:1 to an assignment. Vendor determines which columns are populated; all vendor-specific columns are nullable.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `assignment_id` | FK → assignments, unique | |
| `vendor` | enum: `sr`, `wd` | Mirrors assignment.vendor |
| `writer_name` | string | Pre-filled from assignment; reader may correct |
| `genre` | string | |
| `time_period` | string | |
| `locations` | string | |
| `estimated_budget` | string | "low" / "medium" / "high" |
| `quality_checked` | boolean | Final quality-check gate — both forms |
| **SR-only metadata** | | |
| `sr_assignment_type` | string, nullable | script_coverage / notes_only / short / deep_dive / budget / book |
| `sr_number_of_readers` | string, nullable | "1 Reader" / "2 Readers" / "3 Readers" / "other". Hidden for deep_dive / short / book / budget. |
| `sr_reader_request` | boolean, nullable | Was there a reader request? Hidden for deep_dive. |
| `sr_proofreading` | boolean, nullable | Shown for all except book and short. |
| `sr_net15` | boolean, nullable | |
| `sr_custom_oversized_fee` | decimal(8,2), nullable | Shown when page_count > 160 and not book. |
| `sr_book_pay_rate` | decimal(8,2), nullable | Shown only for book coverage. |
| **SR content** | | |
| `sr_logline` | text, nullable | Max 50 words. Shown for: script_coverage / short / deep_dive / book. |
| `sr_synopsis` | text, nullable | Min 600 words. Shown for: script_coverage / book ONLY. |
| `sr_notes` | text, nullable | Always shown. Min word counts vary by type (see Coverage Form spec). |
| **SR scoresheet — 22 integer scores, range 50–100** | | |
| `sr_score_concept` | smallint, nullable | Concept is strong / has a buzzworthy hook |
| `sr_score_opening_pages` | smallint, nullable | Opening pages are compelling |
| `sr_score_theme` | smallint, nullable | Theme is well-executed / interweaved |
| `sr_score_story_logic` | smallint, nullable | Story/plot logic is clear and easy to follow |
| `sr_score_story_element` | smallint, nullable | Every story element feels essential |
| `sr_score_setting` | smallint, nullable | Setting/world is easy to understand/follow |
| `sr_score_story_bogged` | smallint, nullable | Story is not bogged down by exposition |
| `sr_score_scenes_impact` | smallint, nullable | Scenes and moments cause/impact later scenes |
| `sr_score_stakes` | smallint, nullable | Stakes are clear / conflict is compelling |
| `sr_score_tension` | smallint, nullable | Tension builds/escalates throughout |
| `sr_score_characters_interesting` | smallint, nullable | Characters are interesting/entertaining |
| `sr_score_characters_choices` | smallint, nullable | Characters' choices drive the story |
| `sr_score_characters_motivations` | smallint, nullable | Characters' motivations/wants/obstacles clear |
| `sr_score_characters_different` | smallint, nullable | Easy to tell who's who — characters distinct |
| `sr_score_antagonistic` | smallint, nullable | Antagonistic forces are difficult to overcome |
| `sr_score_dialogue` | smallint, nullable | Dialogue is strong/colorful/impactful |
| `sr_score_action_text` | smallint, nullable | Action/description text is visual/concise/vivid |
| `sr_score_climax` | smallint, nullable | Climax/resolution is entertaining/satisfying |
| `sr_score_work_feels` | smallint, nullable | Work feels as strong as it can be |
| `sr_score_target_audience` | smallint, nullable | Target audience/demographic is clear |
| `sr_score_content` | smallint, nullable | Content likely to be strategically appealing to buyers |
| `sr_score_format` | smallint, nullable | Format/spelling/presentation isn't distracting |
| **SR meta** | | |
| `sr_bechdel` | string, nullable | "Not applicable" / "Yes" / "No" |
| `sr_diversity` | string, nullable | "Not applicable" / "Diverse" / "Moderately Diverse" / "Could use more Diversity" |
| `sr_recommendation` | string, nullable | "Pass" / "Consider" / "Consider with Reservations" / "Recommend" |
| **WD-only metadata** | | |
| `wd_assignment_type` | string, nullable | "Coverage" / "Development Notes" |
| `wd_form` | string, nullable | "Screenplay" / "Treatment" / "Pilot" / "Short" / free text |
| `wd_mpaa_rating` | string, nullable | Imagined MPAA rating if produced |
| `wd_request` | boolean, nullable | Was there a reader request? |
| `wd_script_recommendations` | text, nullable | Titles of scripts/films with similar vibe |
| **WD content** | | |
| `wd_logline` | text, nullable | |
| `wd_synopsis` | text, nullable | Required for "Coverage" type only; omitted for "Development Notes". |
| **WD notes — 7 sections, each with a categorical score + textarea** | | |
| `wd_score_concept` | string, nullable | "Poor" / "Fair" / "Good" / "Excellent" |
| `wd_notes_concept` | text, nullable | |
| `wd_score_plot` | string, nullable | Plot/Structure |
| `wd_notes_plot` | text, nullable | |
| `wd_score_pacing` | string, nullable | |
| `wd_notes_pacing` | text, nullable | |
| `wd_score_format` | string, nullable | |
| `wd_notes_format` | text, nullable | |
| `wd_score_characters` | string, nullable | |
| `wd_notes_characters` | text, nullable | |
| `wd_score_dialogue` | string, nullable | |
| `wd_notes_dialogue` | text, nullable | |
| `wd_score_overall` | string, nullable | |
| `wd_notes_overall` | text, nullable | |
| **WD recommendations** | | |
| `wd_recommend_writer` | string, nullable | "Pass" / "Consider" / "Recommend" |
| `wd_recommend_material` | string, nullable | "Pass" / "Consider" / "Recommend" |
| `created_at` / `updated_at` | timestamps | |

---

## Assignments Module — Detailed Behaviour

### Assignment List View

One row per assignment where practical. Columns:

1. Age — timestamp + elapsed time (d/h/m since `unassigned_at`)
2. Order #
3. Script title
4. Author (first initial + last name, e.g. "J. Smith")
5. Page count
6. Requested reader indicator — initials, or blank
7. Rush / Regular badge
8. Pay rate
9. Notes (truncated; expand on hover/click)
10. Status badge (colour-coded)
11. Assigned reader initials — **admin/editor view only**
12. Action buttons — role-dependent

**Reader view:** Split-screen — "Available" (unassigned) on left, "My Assignments" on right.
Readers cannot see who holds other assignments.

**Admin/editor view:** All assignments regardless of status; all reader assignments visible.

### Reader List Panel

Sidebar/top bar on the assignment list screen. Shows all readers by initials.
Click initials → reader profile + their currently accepted assignments.

### Accept / Race Condition Prevention

1. Optimistic UI — disable Accept button immediately on click.
2. Server-side: DB transaction with `SELECT ... FOR UPDATE` on the assignment row.
3. If `status !== 'unassigned'` at lock time → conflict error, refresh list.
4. If reader is at `max_concurrent_assignments` → block with error.
5. On success: `status = 'assigned'`, set `assigned_reader_id` and `accepted_at`.

Readers can cancel their own accepted assignment (reverts to `unassigned`).

### Create Assignment (admin/editor only)

"Create Assignment" button → form/panel for a manual assignment. All fields editable.
Status defaults to `incoming` or `unassigned` (admin's choice).
File upload: drag-and-drop or file picker → push to Google Drive → store `drive_script_file_id`.

### Edit Assignment (admin/editor only)

Click row or Edit button → edit panel with all fields + status editable.
Provide an inline status dropdown on the list row for quick status changes without opening the panel.

### Script Viewing

All scripts in Google Drive as view-only links (no download, no print) via Drive sharing settings.
Readers open Drive viewer in a new tab.
Admins/editors can view and download.

### Title Page Removal

Admins/editors may need to strip a title page before publishing.
UI: "Remove page 1 and re-upload to Drive" — use a PHP PDF library to rewrite the file,
then PUT to Drive in place, keeping the same `drive_script_file_id`.

### Coverage Form (reader)

Accessible after a reader accepts an assignment. Which form is shown depends on `assignment.vendor`:
- `sr` → SR Coverage Form
- `wd` → WD Coverage Form

Both forms are multi-section, single-page (no GravityForms-style pagination in PORTAL).
WYSIWYG for all textarea fields: bold, italic, underline minimum (TipTap or Quill recommended).
The final submit button is disabled until the quality-check confirmation is ticked.

Fields pre-filled and displayed as read-only info (not editable by reader):
- Script title, author, order number (from assignment)
- Reader initials (from logged-in user's readerProfile)
- Rush status (from assignment)

#### SR Coverage Form

**Assignment metadata** (reader fills in):

| Field | Type | Conditional |
|-------|------|-------------|
| Assignment Type | radio: Script Coverage / Notes Only Coverage / Short Coverage / Deep-Dive Development Notes / Budget Script Coverage / Book Coverage | Always shown. Drives most other conditional logic below. |
| Writer's Name | text, required | Always |
| Page Count | number, required | Hidden when type = Book Coverage |
| Custom Oversized Fee | number, optional | Shown when page_count > 160 AND type ≠ Book Coverage |
| Genre | text, required | Always |
| Time Period | text, required | Always |
| Location(s) | text, required | Always |
| Estimated Budget | text, required | Always. Help text: "low / medium / high" |
| Number of Readers | radio: 1 Reader / 2 Readers / 3 Readers / other | Hidden for: Deep-Dive / Short / Book / Budget |
| Reader Request | radio: No / Yes | Hidden for: Deep-Dive |
| Proofreading | radio: No / Yes | Shown for all EXCEPT Book and Short |
| NET15 | radio: No / Yes | Always |
| Book Pay Rate | number, required | Shown ONLY for Book Coverage |

**Content sections:**

| Section | Field | Conditional | Constraints |
|---------|-------|-------------|-------------|
| Logline | textarea | Shown for: Script Coverage / Short / Deep-Dive / Book | Max 50 words |
| Synopsis | textarea | Shown for: Script Coverage / Book ONLY | Min 600 words |
| Notes | textarea | Always shown | Min word counts: Script Coverage 1200w / Deep-Dive or Book 4100w / Short or Treatment 600w / Budget 150–200w |

**Scoresheet** (22 numeric scores, each 50–100, all required):

Rendered as range sliders. PORTAL implementation uses native `<input type="range" min="50" max="100" step="1">` + Alpine.js (no jQuery UI). Each slider shows its current value on the handle. Color: hue = `(value - 50) / 50 * 120` → red at 50, yellow ~77, green at 100 (`hsl({hue}, 100%, 42%)`).

"Randomize scores" field (optional number input, anchor): when changed, all 22 score sliders snap to a random value within ±5 of the anchor value. Implemented via an Alpine `x-data` block wrapping the entire scoresheet.

Source reference: `screenplayreaders-theme/generatepress_child/assets/js/gravityforms_sliders.js`

| # | Label |
|---|-------|
| 1 | Concept is strong and/or material has a buzzworthy hook |
| 2 | Opening pages/chapters are compelling |
| 3 | Theme is well-executed/interweaved well |
| 4 | Story/plot/story logic is clear and easy to follow |
| 5 | Every story element feels essential |
| 6 | Setting/world is easy to understand/follow |
| 7 | Story is not bogged down by exposition |
| 8 | Scenes and moments cause or impact later scenes and moments |
| 9 | Stakes are clear/conflict is strong and/or compelling |
| 10 | Tension builds/escalates throughout |
| 11 | Characters are interesting/entertaining/fun to follow |
| 12 | Characters' choices and actions drive the story forward |
| 13 | Characters' motivations/wants/obstacles are clearly defined |
| 14 | It's easy to tell who's who — Characters are different from one another |
| 15 | Antagonistic forces are difficult for protagonist/s to overcome |
| 16 | Dialogue is strong/colorful/entertaining/impactful |
| 17 | Action/description text is visual/concise/vivid |
| 18 | Climax/resolution is entertaining/satisfying |
| 19 | Work feels as if it's as strong/funny/dramatic/entertaining as it can be |
| 20 | Target audience/demographic is clear |
| 21 | Content/subject matter is likely to be strategically appealing to buyers |
| 22 | Format/spelling/presentation isn't distracting |

**Final fields:**

| Field | Type | Notes |
|-------|------|-------|
| Bechdel Test | select: Not applicable / Yes / No | |
| Diversity | select: Not applicable / Diverse / Moderately Diverse / Could use more Diversity | |
| Recommendation | radio: Pass / Consider / Consider with Reservations / Recommend | Required |
| Quality check | checkbox/radio: Yes / No | Must be Yes to enable submit |

#### WD Coverage Form

**Assignment metadata** (reader fills in):

| Field | Type | Conditional |
|-------|------|-------------|
| WD Assignment Type | radio: Coverage / Development Notes | Always. Drives synopsis visibility. |
| Genre | text, required | Always |
| Time Period | text, required | Always |
| Location(s) | text, required | Always |
| Estimated Budget | text, required | Always. Help text: "e.g. low, medium, high" |
| Request? | select: No / Yes | Always |
| Form | text, required | "Screenplay / Treatment / Pilot / Short / or describe the form of the material" |
| Rating | text, required | "What MPAA rating you imagine this material would receive if produced" |

**Content sections:**

| Section | Field | Conditional | Notes |
|---------|-------|-------------|-------|
| Logline | textarea | Always | |
| Synopsis | textarea | Shown ONLY when type = Coverage (not Development Notes) | Min 450 words |

**Notes — 7 sections** (all always shown; word count totals across all 7 sections):
- Coverage: total ≥ 1,200 words
- Development Notes: total ≥ 3,700 words
- Shorts/Treatments: total ≥ 600 words

Each section has: score (select: Poor / Fair / Good / Excellent) + notes (textarea):

| Section |
|---------|
| Concept |
| Plot/Structure |
| Pacing |
| Format |
| Characters |
| Dialogue |
| Overall |

**Final fields:**

| Field | Type | Notes |
|-------|------|-------|
| Script Recommendations | text, required | "Titles of scripts or films this shares a vibe with" |
| Recommend Writer? | select: Pass / Consider / Recommend | |
| Recommend Material? | select: Pass / Consider / Recommend | |
| Quality check | checkbox/radio: Yes / No | Must be Yes to enable submit |

#### On submit (both forms):
1. Validate all required fields and word-count minimums server-side.
2. Save to `coverage_submissions`.
3. Dispatch a queued job to create a Google Doc via Docs API with formatted coverage content.
4. Store `drive_coverage_doc_id` on assignment.
5. Set `assignment.status = 'qc'`.

### QC Flow (admin/editor)

On a `qc`-status assignment:
- **View PDF** → opens `drive_coverage_pdf_id` in new tab
- **Open Google Doc** → opens `drive_coverage_doc_id` for editing
- **Create / Regenerate PDF** → exports Google Doc to PDF via Drive API, saves to Drive,
  updates `drive_coverage_pdf_id`
- Admin marks `completed` when done

### HelpScout Delivery

On completed assignments, provide a one-click PDF download so it can be attached to a
HelpScout ticket. Stretch goal: direct drag-to-HelpScout-ticket-draft via HelpScout API.
Document whichever approach is implemented and update this file.

---

## File Storage Strategy

**Use Google Drive** — handles large PDFs, view-only sharing, and Google Docs editing natively.
Database stores only Drive file IDs and metadata, never binary content.

Service class: `App\Services\GoogleDriveService` wraps all Drive API calls.

Drive folder structure:
```
Screenplay Readers Portal/
  scripts/
    {assignment_id}/
      script.pdf
  coverage/
    {assignment_id}/
      coverage.gdoc
      coverage.pdf
```

---

## Integration Points

### WooCommerce → PORTAL (incoming assignments)

When a customer completes an order + uploads their script on the WordPress site, a webhook
POSTs to: `POST /api/incoming-assignment`

This is a **new, separate** webhook from any existing Zapier flows. Existing flows continue
unchanged until PORTAL's incoming flow is proven. Full payload spec TBD — design alongside
`sr-upload-system.php` in the theme repo.

PORTAL creates an `assignment` with `status = incoming`.

### Zapier

New Zap workflows for PORTAL are kept entirely separate from existing Zaps.
Existing Zaps are not modified until PORTAL replacements are proven.
PORTAL Zapier webhook endpoints: `POST /api/zap/{event}` (events TBD).

The existing reader-assignment Zapier step
(`sr-reader-assignments/step-03-reader-assignment-processing.js`) and its pay-rate logic
(`RATES` object with sr/wd rates, oversized fees, rush/request/proof rates) must be
replicated in PORTAL's pay calculation logic when that feature is built. Do not modify
the existing Zap step while PORTAL is in development.

### HelpScout

Customer email delivery. Integration approach TBD (see Coverage Delivery above).

---

## Coding Conventions

- Laravel conventions throughout: Eloquent, Policies, Form Requests, Resource Controllers.
- Migrations for every schema change — never alter tables manually.
- Policies for all authorization — no inline role checks scattered through controllers.
- Service classes for all external API calls (`GoogleDriveService`, `HelpScoutService`, etc.).
- Jobs/queues for anything calling an external API — never block a web request on these.
- Rate limiting on all API endpoints via Laravel's `RateLimiter`.
- Input validation via Form Request classes. Sanitize everything at the boundary.
- CSRF on all forms. API endpoints authenticated via Laravel Sanctum.
- Version-comment each major class file: version header + short changelog at the top.
- Comments only when the WHY is non-obvious. Do not narrate what the code does.
- Do not write Phase 2 code during Phase 1 work.

---

## Deployment

- **Server:** DigitalOcean droplet managed by Laravel Forge
- **URL:** https://portal.screenplayreaders.com
- **GitHub repo:** https://github.com/theomalley/screenplayreaders-portal
- **Deploy trigger:** Push to `main` → Forge auto-deploys

### Forge deploy script (set in Forge → Site → Deployment)

Laravel app lives at the site root (`/home/forge/portal.screenplayreaders.com/`).
Nginx config has `root /home/forge/portal.screenplayreaders.com/current/public` — `current` is a
symlink that must point to the site root. The deploy script re-affirms this on every deploy.
forge user has no passwordless sudo, so OPcache is reset via a temporary HTTP-hit PHP file.

```bash
cd /home/forge/portal.screenplayreaders.com
ln -sfn /home/forge/portal.screenplayreaders.com /home/forge/portal.screenplayreaders.com/current
git pull origin $FORGE_SITE_BRANCH
$FORGE_COMPOSER install --no-interaction --prefer-dist --optimize-autoloader
npm install --no-package-lock
npm run build
$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan storage:link
$FORGE_PHP artisan view:clear
$FORGE_PHP artisan config:clear
$FORGE_PHP artisan route:clear
TOKEN=$(openssl rand -hex 16)
echo "<?php opcache_reset();" > public/opcache-${TOKEN}.php
curl -sf "https://portal.screenplayreaders.com/opcache-${TOKEN}.php" || true
rm -f public/opcache-${TOKEN}.php
```

### One-time setup commands (run once in Forge → Site → Quick Commands)

Run these **once** after the first successful deploy, then never again:

```bash
php artisan breeze:install blade --no-interaction
npm run build
```

After running, SSH into the server and push the generated Breeze files back to GitHub:

```bash
cd /home/forge/portal.screenplayreaders.com
git add -A
git commit -m "Add Breeze auth scaffold"
git push origin main
```

Then pull locally so the IDE stays in sync.

---

## Outstanding Decisions (update this list as decisions are made)

- [x] Coverage form field spec — complete. SR + WD forms fully specced above. Slider JS must be sourced from `screenplayreaders-theme` repo before building scoresheet UI.
- [x] Laravel auth scaffold choice — **Breeze (Blade stack)**
- [ ] HelpScout delivery approach — download button vs direct API attachment
- [ ] WooCommerce → PORTAL webhook full payload spec
- [ ] Google Drive folder structure + service account setup
- [ ] PORTAL Zapier webhook event list

---

## Key Files (update as the project is built)

| Path (relative to `screenplayreaders-portal/`) | Responsibility |
|------|---------------|
| `app/Models/Assignment.php` | Core assignment model |
| `app/Models/ReaderProfile.php` | Extended reader profile |
| `app/Services/GoogleDriveService.php` | All Google Drive API calls |
| `app/Policies/AssignmentPolicy.php` | Role-based access |
| `app/Http/Controllers/AssignmentController.php` | CRUD + status transitions |
| `app/Http/Controllers/Api/IncomingAssignmentController.php` | Webhook from WordPress |
| `database/migrations/` | All schema migrations |
| `resources/views/assignments/` | Blade views |

---

*Last updated: 2026-05-17 — coverage form field spec complete (SR + WD); vendor/assignment_type added to assignments table; coverage_submissions table fully specced; deploy script corrected (OPcache via HTTP); Nginx uses current/ symlink pointing to site root — deploy script re-affirms this. storage:link added to deploy script (required for logo uploads).*
