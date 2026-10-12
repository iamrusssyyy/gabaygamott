# GabayGamot — Project Rules for Claude

GabayGamot is a school capstone project: a web-based **Medicine Locator and Donation
Management System** for Metro Manila, Philippines. It is built by **one student** who
must explain every line of code to a thesis panel. Keep code simple, readable, and
well-commented.

Reference documents (read these before major work):
- `docs/Group2_GabayGamot_Chapter1_Revised.pdf` — objectives, scope, and limitations
- `docs/GabayGamot-Wireframes.pdf` — screens and flows (mobile 360px + desktop 1280px)
- `database/schema.sql` — the database design (source of truth for table/column names)

---

## Tech stack (FIXED — never switch)

- Plain **PHP 8** (no framework), **MySQL/MariaDB**, HTML, CSS, vanilla JavaScript
- **Bootstrap 5** via CDN for layout and components (plus **Bootstrap Icons** via CDN)
- **Leaflet + OpenStreetMap** for maps, **Chart.js** for charts (both via CDN)
- Font: **IBM Plex Sans** (Google Fonts)
- Runs locally on **XAMPP** at `http://localhost/gabaygamot`
  (XAMPP here = PHP 8.2, MariaDB 10.4)

Never use Laravel, React, Vue, Node, npm, Composer packages, bundlers, or any build
tool. The student only knows **HTML, CSS, JavaScript, and PHP**. If something new
would genuinely help, **ask first**. When a concept may be new (PDO, prepared
statements, sessions, CSRF, Haversine distance, `fetch()`), explain it briefly in a
code comment.

## Security rules (always, on every page)

1. **PDO with prepared statements** for every query. Never put user input directly
   in SQL — not even in `ORDER BY` (use a whitelist of allowed column names instead).
2. **`password_hash()` / `password_verify()`** for passwords. Never store plain text.
3. **Sessions for login.** Every page starts with
   `require_once __DIR__ . '/../includes/init.php';` (it loads config, the database,
   helpers, CSRF, and the auth helper `includes/auth.php`, and starts the session).
   Protected pages then call the role check, e.g. `require_role(['admin']);`.
   Call `session_regenerate_id(true)` after login. Log out only via a POST form
   with a CSRF token.
4. **`htmlspecialchars()` on all user output** (use the shared `e()` helper).
5. **CSRF token on every POST form**, checked before any processing.
6. **Uploaded documents** (prescriptions, certificates of indigency, IDs, packaging
   photos) are saved **outside the public web folder** (default
   `C:\xampp\gabaygamot_uploads\`) with random file names, and are only served
   through a PHP script (`files/view.php`) that checks the viewer is the **owner**,
   an **admin**, or the **partner matched to that request**. Validate file type
   (check real MIME type with `finfo`, allow only JPG, PNG, PDF) and size (max 5 MB).
7. **Database credentials live only in `config/db.php`**, which is in `.gitignore`.
   `config/db.example.php` is the committed template.
8. **Data Privacy Act of 2012 (RA 10173):** a required consent checkbox at
   registration and at document upload; store the time consent was given
   (`users.privacy_consent_at`, `assistance_requests.documents_consent_at`).
   Collect only the data the feature needs.
9. Folders that must not be browsed directly (`config/`, `includes/`, `database/`)
   get an `.htaccess` with `Require all denied`.

## Roles

`beneficiary`, `donor`, `pharmacy`, `partner`, `admin`

- Beneficiary and donor accounts are active right after registration.
- **Pharmacy and partner accounts start as `pending`** and cannot log in until an
  admin approves them (`users.status = 'active'`).
- Admin accounts are created only by seeding the database, never by public sign-up.

## Modules (from Chapter 1 — stay inside this scope)

### 1. Beneficiary Assistance
- 4-step request wizard: **patient details → medicine needed → documents → review & submit**.
- Document upload: prescription and certificate of indigency (required), valid ID (optional).
- Track request by reference number (e.g. `GG-2026-000001`) with a status timeline.
- Statuses: `submitted`, `verified`, `matched`, `ready_to_claim`, `claimed`,
  `action_needed`, `rejected`.
- Partner programs: Philippine Cancer Society, PCSO Medical Access Program,
  DSWD AICS, Malasakit Centers.

### 2. Medicine Locator
- Pharmacies update **stock quantity and price per item**; each update saves a
  "last updated" time.
- Public search by **brand or generic name**; filter by **city** and **price**;
  **in-stock only**; sort by **nearest** or **lowest price**; **list / map** view.
- "Best picks": cheapest in stock, nearest in stock, most recently updated.
- **Always show "Last updated"** and a notice that stock is **not live**
  ("Stock is as of each pharmacy's last update — not live. Call before you go.").

### 3. Donation Management
- Donors list donations with **quantity and expiry validation** and a
  **packaging photo**.
- Admin verifies with a **checklist**, approves or rejects **with a reason**, and
  **routes** (allocates) approved donations to matched requests.
- Admin dashboard: KPIs, Chart.js charts, **near-expiry** and **low-stock** alerts,
  and **printable reports** (donation flow, request fulfillment rate, beneficiaries
  served, medicines distributed).

Out of scope (Chapter 1.5): physical delivery/courier logistics, payment processing,
medical consultation or prescribing. Do not add features outside this scope without
asking.

## Decisions made with the student (2026-10-12)
- **Language:** English UI with Filipino **helper lines only** (as in the
  wireframes). No full EN/FIL translation toggle.
- **Track request:** **login required**; a beneficiary sees only their own requests.
- **One medicine per request** (the wizard asks for a single medicine).
- **Wireframe directions to build:** landing **1b/2b "Two doors"**, search results
  **1e/2e "List + filter sidebar"**, request form **1h/2h "4-step wizard"**.

## UI notes (design follows the high-fidelity prototype, 2026-10-12)
- The student's hi-fi prototype ("GabayGamot Prototype" PNGs: desktop + mobile
  screens) is the visual reference; it refines the wireframes.
- Theme lives in `assets/css/style.css` as CSS variables: deep green `--gg-green`,
  mint `--gg-mint`, amber accent, light gray-green page, white rounded cards,
  dark footer. Admins get a dark green sidebar layout; everyone else a white header.
- Status labels are soft **pills** (`pill()`, `request_badge()`, `stock_pill()` in
  `includes/labels.php`): green = good, amber = needs attention, red = stopped,
  blue = in progress, gray = finished. Stock: "In stock · 240" etc.
- Icons: **Bootstrap Icons** via CDN (`<i class="bi bi-search"></i>`).
- Reference numbers use IBM Plex Mono (`.mono`).
- Mobile-first (360px), then desktop (1280px). Large tap targets; plain language.
- Show bilingual helper text where the prototype does (English + Filipino),
  e.g. "Find medicine / Maghanap ng gamot".
- Inline form errors directly under the field; red `*` (`.req`) on required labels.
- Prototype extras NOT built (out of scope or need a decision): EN/FIL toggle,
  "preferred program" question in the wizard, admin "System settings" panel,
  emailed alerts, expiring document links, date-range filter on the dashboard.

## Coding conventions
- One PHP page per screen; shared pieces in `includes/` (`header.php`, `footer.php`,
  `auth.php`, `csrf.php`, `helpers.php`, `upload.php`).
- Database connection comes from one function (`db()`), never duplicated.
- Use `snake_case` for database columns and PHP variables; comment each section of
  a page (1. check login, 2. handle POST, 3. load data, 4. show HTML).
- Prices are stored as `DECIMAL(10,2)` in pesos; dates as `DATE` / `DATETIME`.
- Use the exact table and column names in `database/schema.sql`. If the schema must
  change, update `schema.sql` (and `sample_data.sql`) and tell the student.
- No inline SQL string concatenation, no `eval`, no `extract()`.
- PDO sends `?` values as text. Don't compare two `?` values with each other in SQL
  (`'100' > '20'` is false as text); compare columns, or compare in PHP.
- Never write the PHP closing tag `?>` inside a `//` or `#` comment — PHP ends the
  code block there and prints the rest of the file as text.

## How to work with the student
- **Work in small steps.** After each step, explain exactly how to test it in the
  browser (URL, account to log in with, what to click, what should happen), then
  **stop and wait**.
  - *Update 2026-10-12:* the student asked Claude to build the remaining modules
    through to the end without stopping for permission between steps. Still commit
    after each working feature and give browser test instructions at the end.
- **Use Git and commit after each working feature** with a clear message.
- Explain design decisions in simple terms the student can repeat to the panel.
- Don't add features outside this scope without asking.

## Project status and file map (as of 2026-10-12)
All three modules are built. Where things live:

| Path | What it does |
|------|--------------|
| `index.php` | Home page, wireframe 1b/2b "Two doors" |
| `login.php`, `logout.php`, `register.php`, `privacy.php`, `notifications.php` | Accounts, consent notice, in-app notifications |
| `config/app.php` | Base URL, upload folder, Metro Manila cities, donation rules, help line |
| `includes/init.php` | Loaded first by every page; loads everything below and starts the session |
| `includes/db.php`, `helpers.php`, `csrf.php`, `auth.php` | `db()`, `e()`/`url()`/`notify()`…, CSRF, `require_role()` |
| `includes/labels.php` | Status names/colors, dropdown choices, badge helpers |
| `includes/upload.php` | `save_upload()` (finfo type check, 5 MB, random names, outside htdocs) |
| `includes/requests.php` | Request loading, allowed status transitions, `process_request_action()` |
| `includes/request_details.php`, `request_actions.php` | Shared request details (admin/partner) and the one-form action panel |
| `includes/status_history.php` | "Status history" stepper used by beneficiary, partner, and admin pages |
| `includes/my_requests_list.php`, `my_request_detail.php` | Beneficiary "My requests" list and detail (with "Your medicine is ready") |
| `includes/alerts.php` | Automated near-expiry and low-stock alerts (`run_alert_checks()`) |
| `locator/search.php` + `assets/js/locator.js` | Public Medicine Locator, wireframe 2e (list/map, filters, best picks) |
| `beneficiary/` | `request_new.php` 4-step wizard (2h), `request_view.php` tracking, `dashboard.php` |
| `donor/` | `donation_new.php`, `donation_view.php`, `dashboard.php` (pharmacies may donate too) |
| `pharmacy/` | `stock.php` "Stock & prices" table (home page for pharmacies), `dashboard.php` "Overview" |
| `partner/` | `dashboard.php`, `request_view.php` (only requests matched to their program) |
| `admin/` | `dashboard.php` (KPIs, alerts, Chart.js), `requests.php`, `request_view.php` (actions and donation routing), `donations.php`, `donation_view.php` (checklist), `users.php` (approvals), `medicines.php` (catalog), `reports.php` (printable) |
| `files/view.php` | The only way to open uploaded files (permission check) |

Uploaded files are saved in `C:\xampp\gabaygamot_uploads\requests` and `\donations`.
To reset the data: re-import `database/schema.sql`, then `database/sample_data.sql`
(this deletes every account and record created since the last import).

## Test accounts (sample data only — `database/sample_data.sql`)
| Role        | Email                         | Password         |
|-------------|-------------------------------|------------------|
| admin       | admin@gabaygamot.test         | Admin@1234       |
| beneficiary | maria.santos@example.com      | Beneficiary@1234 |
| donor       | juan.delacruz@example.com     | Donor@1234       |
| pharmacy    | sagip.cubao@example.com       | Pharmacy@1234    |
| partner     | pcso.coordinator@example.com  | Partner@1234     |

Other sample accounts of the same role share that role's password.
