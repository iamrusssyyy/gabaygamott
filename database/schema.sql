-- =====================================================================
-- GabayGamot — Database Schema
-- Medicine Locator and Donation Management System (Metro Manila)
--
-- HOW TO IMPORT (phpMyAdmin):
--   1. Open http://localhost/phpmyadmin
--   2. Click the "Import" tab, choose this file, click "Import".
--      (This file creates the database `gabaygamot` by itself.)
--   3. Then import database/sample_data.sql the same way (optional).
--
-- WARNING: Importing this file DROPS and re-creates the database.
--          All existing GabayGamot data will be erased.
--
-- Notes for the panel:
--   * ENGINE=InnoDB  -> supports FOREIGN KEYs and transactions.
--   * utf8mb4        -> stores any character (ñ, ₱, emoji) correctly.
--   * FOREIGN KEY    -> links a row to a row in another table, so we can
--                       never point to something that doesn't exist.
--   * ENUM           -> a column that only accepts values from a fixed list.
-- =====================================================================

DROP DATABASE IF EXISTS gabaygamot;
CREATE DATABASE gabaygamot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gabaygamot;

-- ---------------------------------------------------------------------
-- 1. partner_programs
--    The assistance programs a request can be matched to
--    (PCS, PCSO MAP, DSWD AICS, Malasakit Centers).
-- ---------------------------------------------------------------------
CREATE TABLE partner_programs (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(120) NOT NULL,           -- e.g. "PCSO Medical Access Program"
    short_name    VARCHAR(40)  NOT NULL,           -- e.g. "PCSO MAP" (for badges)
    description   TEXT         NULL,
    requirements  TEXT         NULL,               -- what documents the program asks for
    website       VARCHAR(255) NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1, -- 1 = shown, 0 = hidden
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_program_name (name)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. users
--    One row per account. `role` decides what pages the user can open.
--    Pharmacy and partner accounts start as 'pending' until an admin
--    approves them.
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role                ENUM('beneficiary','donor','pharmacy','partner','admin') NOT NULL,
    full_name           VARCHAR(120) NOT NULL,
    email               VARCHAR(190) NOT NULL,     -- used to log in
    password_hash       VARCHAR(255) NOT NULL,     -- from password_hash(), never plain text
    phone               VARCHAR(20)  NULL,
    city                VARCHAR(60)  NULL,         -- one of the 17 Metro Manila LGUs
    organization_name   VARCHAR(150) NULL,         -- donor org / partner office name (optional)
    partner_program_id  INT UNSIGNED NULL,         -- ONLY for role = 'partner'
    status              ENUM('pending','active','rejected','disabled') NOT NULL DEFAULT 'active',
    status_reason       VARCHAR(255) NULL,         -- why an account was rejected/disabled
    privacy_consent_at  DATETIME     NOT NULL,     -- RA 10173: when the user agreed
    last_login_at       DATETIME     NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role_status (role, status),
    CONSTRAINT fk_users_program FOREIGN KEY (partner_program_id)
        REFERENCES partner_programs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. pharmacies
--    The store profile of a pharmacy account: address, map location,
--    hours. One pharmacy account manages exactly one pharmacy branch.
-- ---------------------------------------------------------------------
CREATE TABLE pharmacies (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,         -- the pharmacy account that manages it
    name            VARCHAR(150) NOT NULL,         -- e.g. "Sagip Generics"
    branch          VARCHAR(100) NULL,             -- e.g. "Cubao"
    address         VARCHAR(255) NOT NULL,
    city            VARCHAR(60)  NOT NULL,         -- used by the City filter
    latitude        DECIMAL(10,7) NOT NULL,        -- map pin + distance
    longitude       DECIMAL(10,7) NOT NULL,
    phone           VARCHAR(20)  NULL,             -- for the "Call" button
    opening_hours   VARCHAR(100) NULL,             -- e.g. "Mon–Sat 8 AM–9 PM"
    fda_license_no  VARCHAR(50)  NULL,             -- FDA License to Operate, checked by admin
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pharmacy_user (user_id),
    KEY idx_pharmacies_city (city),
    CONSTRAINT fk_pharmacies_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. medicines
--    The shared medicine catalog (managed by admin). One row per product:
--    generic name + brand + strength + form.
--    brand_name = '' means an unbranded generic product.
--    Search looks in BOTH generic_name and brand_name.
-- ---------------------------------------------------------------------
CREATE TABLE medicines (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    generic_name  VARCHAR(120) NOT NULL,           -- e.g. "Losartan Potassium"
    brand_name    VARCHAR(120) NOT NULL DEFAULT '',-- e.g. "Cozaar"; '' = generic
    strength      VARCHAR(40)  NOT NULL,           -- e.g. "50 mg"
    dosage_form   VARCHAR(40)  NOT NULL,           -- e.g. "tablet", "capsule", "inhaler"
    unit          VARCHAR(20)  NOT NULL DEFAULT 'tablet', -- price is "per unit"
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Prevents the same product being added twice:
    UNIQUE KEY uq_medicine (generic_name, brand_name, strength, dosage_form),
    KEY idx_medicines_brand (brand_name)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. pharmacy_stock
--    What each pharmacy has: quantity + price per unit + when it was
--    last updated. Stock status is computed, not stored:
--      quantity = 0                       -> Out of stock
--      quantity <= low_stock_threshold    -> Low stock
--      otherwise                          -> In stock
--    last_updated_at is set by our PHP code every time the pharmacy saves
--    (even if the numbers didn't change), so "Last updated" stays honest.
-- ---------------------------------------------------------------------
CREATE TABLE pharmacy_stock (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pharmacy_id          INT UNSIGNED NOT NULL,
    medicine_id          INT UNSIGNED NOT NULL,
    quantity             INT UNSIGNED NOT NULL DEFAULT 0,
    price                DECIMAL(10,2) NOT NULL,   -- pesos per unit
    low_stock_threshold  INT UNSIGNED NOT NULL DEFAULT 20,
    last_updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- A pharmacy can list each medicine only once:
    UNIQUE KEY uq_stock_item (pharmacy_id, medicine_id),
    KEY idx_stock_medicine (medicine_id),
    CONSTRAINT fk_stock_pharmacy FOREIGN KEY (pharmacy_id)
        REFERENCES pharmacies(id) ON DELETE CASCADE,
    CONSTRAINT fk_stock_medicine FOREIGN KEY (medicine_id)
        REFERENCES medicines(id) ON DELETE RESTRICT,
    CONSTRAINT chk_stock_price CHECK (price >= 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. assistance_requests
--    A beneficiary's request for medicine help (the 4-step wizard).
--    Step 1 = patient_* columns, Step 2 = medicine columns,
--    Step 3 = request_documents table, Step 4 = submit (status 'submitted').
-- ---------------------------------------------------------------------
CREATE TABLE assistance_requests (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_no          VARCHAR(20)  NULL,       -- e.g. GG-2026-000001 (set right after insert)
    beneficiary_id        INT UNSIGNED NOT NULL,   -- the account that submitted it

    -- Step 1: patient details (patient may be a family member)
    patient_name          VARCHAR(120) NOT NULL,
    patient_birthdate     DATE         NOT NULL,
    patient_sex           ENUM('female','male') NOT NULL,
    relationship          ENUM('self','parent','child','spouse','sibling','other') NOT NULL DEFAULT 'self',
    patient_phone         VARCHAR(20)  NULL,
    patient_address       VARCHAR(255) NOT NULL,
    patient_city          VARCHAR(60)  NOT NULL,
    diagnosis             VARCHAR(255) NULL,       -- condition, as written on the prescription

    -- Step 2: medicine needed
    medicine_id           INT UNSIGNED NOT NULL,
    quantity_per_month    INT UNSIGNED NOT NULL,   -- e.g. 1 tablet a day = 30
    duration              ENUM('one_time','1_month','3_months','6_months','ongoing') NOT NULL,
    supply_left           ENUM('none','lt_1_week','1_4_weeks','gt_1_month') NOT NULL, -- used for priority
    prescribing_doctor    VARCHAR(120) NOT NULL,

    -- Processing (filled in by admin / partner)
    status                ENUM('submitted','verified','matched','ready_to_claim',
                               'claimed','action_needed','rejected') NOT NULL DEFAULT 'submitted',
    status_note           VARCHAR(255) NULL,       -- reason shown to beneficiary (action_needed / rejected)
    partner_program_id    INT UNSIGNED NULL,       -- program the request was matched to
    claim_pharmacy_id     INT UNSIGNED NULL,       -- where to claim the medicine
    documents_consent_at  DATETIME     NOT NULL,   -- RA 10173: consent at document upload
    submitted_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_request_ref (reference_no),
    KEY idx_requests_status (status),
    KEY idx_requests_beneficiary (beneficiary_id),
    CONSTRAINT fk_requests_beneficiary FOREIGN KEY (beneficiary_id)
        REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_requests_medicine FOREIGN KEY (medicine_id)
        REFERENCES medicines(id) ON DELETE RESTRICT,
    CONSTRAINT fk_requests_program FOREIGN KEY (partner_program_id)
        REFERENCES partner_programs(id) ON DELETE SET NULL,
    CONSTRAINT fk_requests_claim_pharmacy FOREIGN KEY (claim_pharmacy_id)
        REFERENCES pharmacies(id) ON DELETE SET NULL,
    CONSTRAINT chk_request_qty CHECK (quantity_per_month > 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. request_status_history
--    Every status change of a request. Powers the "Track request" timeline
--    and shows who changed what (accountability).
-- ---------------------------------------------------------------------
CREATE TABLE request_status_history (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id   INT UNSIGNED NOT NULL,
    status       ENUM('submitted','verified','matched','ready_to_claim',
                      'claimed','action_needed','rejected') NOT NULL,
    note         VARCHAR(255) NULL,
    changed_by   INT UNSIGNED NULL,                -- user who made the change
    changed_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_history_request (request_id),
    CONSTRAINT fk_history_request FOREIGN KEY (request_id)
        REFERENCES assistance_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_history_user FOREIGN KEY (changed_by)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. request_documents
--    Uploaded files for a request. The FILE itself lives OUTSIDE the web
--    folder (C:\xampp\gabaygamot_uploads\); this table only remembers its
--    random stored name. Files are shown only through files/view.php.
-- ---------------------------------------------------------------------
CREATE TABLE request_documents (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id     INT UNSIGNED NOT NULL,
    doc_type       ENUM('prescription','indigency_certificate','valid_id') NOT NULL,
    original_name  VARCHAR(255) NOT NULL,          -- name on the user's device (display only)
    stored_name    VARCHAR(100) NOT NULL,          -- random name on disk, e.g. 9f3c...a1.pdf
    mime_type      VARCHAR(50)  NOT NULL,          -- image/jpeg, image/png, application/pdf
    file_size      INT UNSIGNED NOT NULL,          -- bytes (max 5 MB, checked in PHP)
    uploaded_by    INT UNSIGNED NOT NULL,
    uploaded_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_doc_stored (stored_name),
    KEY idx_docs_request (request_id),
    CONSTRAINT fk_docs_request FOREIGN KEY (request_id)
        REFERENCES assistance_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_docs_user FOREIGN KEY (uploaded_by)
        REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. donations
--    A donor's listed medicine. Admin verifies it with the checklist
--    (check_* columns), then approves or rejects it with a reason.
--    "Remaining quantity" = quantity - SUM(donation_allocations.quantity).
--    Expiry rule (checked in PHP): expiry_date must be at least
--    90 days from today when the donation is listed.
-- ---------------------------------------------------------------------
CREATE TABLE donations (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    donor_id              INT UNSIGNED NOT NULL,   -- donor, pharmacy, or partner account
    medicine_id           INT UNSIGNED NOT NULL,
    quantity              INT UNSIGNED NOT NULL,   -- number of units (tablets, inhalers...)
    expiry_date           DATE         NOT NULL,
    lot_number            VARCHAR(50)  NULL,       -- batch/lot printed on the box
    donor_notes           VARCHAR(255) NULL,

    -- Packaging photo (required by the PHP form; stored outside the web folder)
    photo_original_name   VARCHAR(255) NULL,
    photo_stored_name     VARCHAR(100) NULL,
    photo_mime_type       VARCHAR(50)  NULL,

    status                ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',

    -- Admin verification checklist (1 = checked OK)
    check_sealed          TINYINT(1)   NOT NULL DEFAULT 0, -- unopened, sealed packaging
    check_label_legible   TINYINT(1)   NOT NULL DEFAULT 0, -- name, strength, lot readable
    check_expiry_valid    TINYINT(1)   NOT NULL DEFAULT 0, -- expiry on box matches listing
    check_matches_photo   TINYINT(1)   NOT NULL DEFAULT 0, -- item matches the photo
    rejection_reason      VARCHAR(255) NULL,
    reviewed_by           INT UNSIGNED NULL,       -- admin who approved/rejected
    reviewed_at           DATETIME     NULL,

    created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_donation_photo (photo_stored_name),
    KEY idx_donations_status (status),
    KEY idx_donations_expiry (expiry_date),
    CONSTRAINT fk_donations_donor FOREIGN KEY (donor_id)
        REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_donations_medicine FOREIGN KEY (medicine_id)
        REFERENCES medicines(id) ON DELETE RESTRICT,
    CONSTRAINT fk_donations_reviewer FOREIGN KEY (reviewed_by)
        REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT chk_donation_qty CHECK (quantity > 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10. donation_allocations
--     "Routing": how many units of which donation go to which request.
--     One donation can be split across several requests, and one request
--     can be filled from several donations (many-to-many).
-- ---------------------------------------------------------------------
CREATE TABLE donation_allocations (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    donation_id   INT UNSIGNED NOT NULL,
    request_id    INT UNSIGNED NOT NULL,
    quantity      INT UNSIGNED NOT NULL,
    allocated_by  INT UNSIGNED NULL,               -- admin who routed it
    allocated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_alloc_donation (donation_id),
    KEY idx_alloc_request (request_id),
    CONSTRAINT fk_alloc_donation FOREIGN KEY (donation_id)
        REFERENCES donations(id) ON DELETE RESTRICT,
    CONSTRAINT fk_alloc_request FOREIGN KEY (request_id)
        REFERENCES assistance_requests(id) ON DELETE RESTRICT,
    CONSTRAINT fk_alloc_admin FOREIGN KEY (allocated_by)
        REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT chk_alloc_qty CHECK (quantity > 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 11. notifications
--     Simple in-app alerts (no email/SMS): e.g. "Your request is ready to
--     claim", "Donation #12 expires in 30 days", "Low stock: Losartan".
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,             -- who sees it
    message     VARCHAR(255) NOT NULL,
    link        VARCHAR(255) NULL,                 -- page to open, e.g. beneficiary/request_view.php?id=3
    is_read     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notif_user_read (user_id, is_read),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
