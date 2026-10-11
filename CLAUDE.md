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
- **Bootstrap 5** via CDN for layout and components
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
3. **Sessions for login.** Every protected page starts by including the shared auth
   helper (`includes/auth.php`) and calling the role check, e.g.
   `require_role(['admin']);`. Call `session_regenerate_id(true)` after login.
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

## UI notes from the wireframes
- Mobile-first (360px), then desktop (1280px). Large tap targets; plain language.
- Show bilingual helper text where the wireframes do (English + Filipino),
  e.g. "Find your medicine nearby / Hanapin ang gamot na malapit sa iyo".
- Stock status dots: ● In stock · ● Low stock · ● Out of stock.
- Inline form errors directly under the field.

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

## How to work with the student
- **Work in small steps.** After each step, explain exactly how to test it in the
  browser (URL, account to log in with, what to click, what should happen), then
  **stop and wait**.
- **Use Git and commit after each working feature** with a clear message.
- Explain design decisions in simple terms the student can repeat to the panel.
- Don't add features outside this scope without asking.

## Test accounts (sample data only — `database/sample_data.sql`)
| Role        | Email                         | Password         |
|-------------|-------------------------------|------------------|
| admin       | admin@gabaygamot.test         | Admin@1234       |
| beneficiary | maria.santos@example.com      | Beneficiary@1234 |
| donor       | juan.delacruz@example.com     | Donor@1234       |
| pharmacy    | sagip.cubao@example.com       | Pharmacy@1234    |
| partner     | pcso.coordinator@example.com  | Partner@1234     |

Other sample accounts of the same role share that role's password.
