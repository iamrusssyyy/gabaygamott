-- =====================================================================
-- GabayGamot — SAMPLE DATA (FOR TESTING AND DEMO ONLY)
--
-- *** THIS IS FAKE DATA. ***
-- All people, pharmacies, phone numbers, emails, license numbers, and
-- requests below are made up. Pharmacy coordinates point to real
-- Metro Manila neighborhoods so the map looks realistic, but the
-- pharmacies themselves are fictional.
-- Do NOT import this file into a real/production database.
--
-- Import AFTER database/schema.sql (phpMyAdmin > Import).
--
-- Test logins (also listed in CLAUDE.md):
--   admin        admin@gabaygamot.test          Admin@1234
--   beneficiary  maria.santos@example.com       Beneficiary@1234
--   donor        juan.delacruz@example.com      Donor@1234
--   pharmacy     sagip.cubao@example.com        Pharmacy@1234
--   partner      pcso.coordinator@example.com   Partner@1234
-- Every other sample account uses the same password as its role.
--
-- Dates use NOW() / CURDATE() so "Updated 25 min ago" and near-expiry
-- alerts look right no matter which day you import this file.
-- =====================================================================

USE gabaygamot;

-- ---------------------------------------------------------------------
-- Partner programs (real program names, from Chapter 1)
-- ---------------------------------------------------------------------
INSERT INTO partner_programs (id, name, short_name, description, requirements, website) VALUES
(1, 'Philippine Cancer Society', 'PCS',
    'Non-profit organization that provides medicine assistance to cancer patients.',
    'Medical prescription, barangay certificate of indigency, valid ID.',
    'https://www.philcancer.org.ph'),
(2, 'PCSO Medical Access Program', 'PCSO MAP',
    'Philippine Charity Sweepstakes Office program that helps patients pay for medicines.',
    'Medical prescription, certificate of indigency, valid ID.',
    'https://www.pcso.gov.ph'),
(3, 'DSWD Assistance to Individuals in Crisis Situation', 'DSWD AICS',
    'DSWD program that gives medical, burial, and other assistance to individuals in crisis.',
    'Medical prescription, certificate of indigency, valid ID.',
    'https://www.dswd.gov.ph'),
(4, 'Malasakit Centers', 'Malasakit',
    'One-stop shops in DOH hospitals and PGH for medical and financial assistance (RA 11463).',
    'Medical prescription, certificate of indigency, valid ID.',
    'https://malasakit.doh.gov.ph');

-- ---------------------------------------------------------------------
-- Users  (password_hash values were made with PHP password_hash())
--   Admin@1234        -> $2y$10$1Ava0e...
--   Beneficiary@1234  -> $2y$10$8tzfD7...
--   Donor@1234        -> $2y$10$SrbRQz...
--   Pharmacy@1234     -> $2y$10$oe/fa6...
--   Partner@1234      -> $2y$10$Axf4Bx...
-- ---------------------------------------------------------------------
INSERT INTO users (id, role, full_name, email, password_hash, phone, city, organization_name, partner_program_id, status, status_reason, privacy_consent_at, created_at) VALUES
-- admin
(1,  'admin',       'GabayGamot Admin',  'admin@gabaygamot.test',        '$2y$10$1Ava0eWQjLxB9bmxUHfS2OISvrPY2U5h4nos0oX3vMRXz1eyJuF.e', NULL,            'Quezon City', NULL, NULL, 'active', NULL, NOW() - INTERVAL 90 DAY, NOW() - INTERVAL 90 DAY),
-- beneficiaries
(2,  'beneficiary', 'Maria Santos',      'maria.santos@example.com',     '$2y$10$8tzfD7nRYnOyYHUi2rewwudAu/VApwLjvU6OdfAZOhJPp9Qe.BAla', '0900-000-0002', 'Quezon City', NULL, NULL, 'active', NULL, NOW() - INTERVAL 30 DAY, NOW() - INTERVAL 30 DAY),
(3,  'beneficiary', 'Roberto Garcia',    'roberto.garcia@example.com',   '$2y$10$8tzfD7nRYnOyYHUi2rewwudAu/VApwLjvU6OdfAZOhJPp9Qe.BAla', '0900-000-0003', 'Manila',      NULL, NULL, 'active', NULL, NOW() - INTERVAL 12 DAY, NOW() - INTERVAL 12 DAY),
-- donors (one individual, one organization)
(4,  'donor',       'Juan Dela Cruz',    'juan.delacruz@example.com',    '$2y$10$SrbRQzC4aa/YKH0pqUjkDuQ5gDNKT9g38Jy6y7RDSwZ4y/4hYRJhK', '0900-000-0004', 'Pasig',       NULL, NULL, 'active', NULL, NOW() - INTERVAL 60 DAY, NOW() - INTERVAL 60 DAY),
(5,  'donor',       'Ana Reyes',         'ana.reyes@example.com',        '$2y$10$SrbRQzC4aa/YKH0pqUjkDuQ5gDNKT9g38Jy6y7RDSwZ4y/4hYRJhK', '0900-000-0005', 'Makati',      'Kapwa Ko Foundation (sample)', NULL, 'active', NULL, NOW() - INTERVAL 75 DAY, NOW() - INTERVAL 75 DAY),
-- partners (coordinators of a program)
(6,  'partner',     'Liza Mendoza',      'pcso.coordinator@example.com', '$2y$10$Axf4BxPOhb3zZK2sb/gjXuTIyx4V.njEEtBlKDPhpcgUEMFep.dBu', '0900-000-0006', 'Mandaluyong', 'PCSO Main Office (sample)', 2, 'active', NULL, NOW() - INTERVAL 80 DAY, NOW() - INTERVAL 80 DAY),
(7,  'partner',     'Carlo Bautista',    'pcs.coordinator@example.com',  '$2y$10$Axf4BxPOhb3zZK2sb/gjXuTIyx4V.njEEtBlKDPhpcgUEMFep.dBu', '0900-000-0007', 'Manila',      'PCS Patient Support (sample)', 1, 'active', NULL, NOW() - INTERVAL 80 DAY, NOW() - INTERVAL 80 DAY),
-- pharmacy accounts (one per pharmacy below)
(8,  'pharmacy',    'Sagip Generics Cubao Staff',     'sagip.cubao@example.com',        '$2y$10$oe/fa6q1o9UwZnmJpaJYYePnM59uJxkrcnbm8p8pKpgaea8wb5E3e', '0900-000-0008', 'Quezon City', NULL, NULL, 'active', NULL, NOW() - INTERVAL 85 DAY, NOW() - INTERVAL 85 DAY),
(9,  'pharmacy',    'Botika ng Bayan Kamuning Staff', 'botika.kamuning@example.com',    '$2y$10$oe/fa6q1o9UwZnmJpaJYYePnM59uJxkrcnbm8p8pKpgaea8wb5E3e', '0900-000-0009', 'Quezon City', NULL, NULL, 'active', NULL, NOW() - INTERVAL 85 DAY, NOW() - INTERVAL 85 DAY),
(10, 'pharmacy',    'Kalinga Drugstore Staff',        'kalinga.sampaloc@example.com',   '$2y$10$oe/fa6q1o9UwZnmJpaJYYePnM59uJxkrcnbm8p8pKpgaea8wb5E3e', '0900-000-0010', 'Manila',      NULL, NULL, 'active', NULL, NOW() - INTERVAL 84 DAY, NOW() - INTERVAL 84 DAY),
(11, 'pharmacy',    'Lunas Pharmacy Staff',           'lunas.ermita@example.com',       '$2y$10$oe/fa6q1o9UwZnmJpaJYYePnM59uJxkrcnbm8p8pKpgaea8wb5E3e', '0900-000-0011', 'Manila',      NULL, NULL, 'active', NULL, NOW() - INTERVAL 84 DAY, NOW() - INTERVAL 84 DAY),
(12, 'pharmacy',    'Bayanihan Drug Staff',           'bayanihan.santolan@example.com', '$2y$10$oe/fa6q1o9UwZnmJpaJYYePnM59uJxkrcnbm8p8pKpgaea8wb5E3e', '0900-000-0012', 'Pasig',       NULL, NULL, 'active', NULL, NOW() - INTERVAL 83 DAY, NOW() - INTERVAL 83 DAY),
(13, 'pharmacy',    'Ginhawa Pharmacy Staff',         'ginhawa.makati@example.com',     '$2y$10$oe/fa6q1o9UwZnmJpaJYYePnM59uJxkrcnbm8p8pKpgaea8wb5E3e', '0900-000-0013', 'Makati',      NULL, NULL, 'active', NULL, NOW() - INTERVAL 83 DAY, NOW() - INTERVAL 83 DAY),
(14, 'pharmacy',    'Kalusugan Botika Staff',         'kalusugan.caloocan@example.com', '$2y$10$oe/fa6q1o9UwZnmJpaJYYePnM59uJxkrcnbm8p8pKpgaea8wb5E3e', '0900-000-0014', 'Caloocan',    NULL, NULL, 'active', NULL, NOW() - INTERVAL 82 DAY, NOW() - INTERVAL 82 DAY),
(15, 'pharmacy',    'Mabuhay Generics Staff',         'mabuhay.alabang@example.com',    '$2y$10$oe/fa6q1o9UwZnmJpaJYYePnM59uJxkrcnbm8p8pKpgaea8wb5E3e', '0900-000-0015', 'Muntinlupa',  NULL, NULL, 'active', NULL, NOW() - INTERVAL 82 DAY, NOW() - INTERVAL 82 DAY),
-- waiting for admin approval (use these to test the approval page)
(16, 'pharmacy',    'Pag-asa Drugstore Staff',        'pagasa.marikina@example.com',    '$2y$10$oe/fa6q1o9UwZnmJpaJYYePnM59uJxkrcnbm8p8pKpgaea8wb5E3e', '0900-000-0016', 'Marikina',    NULL, NULL, 'pending', NULL, NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 1 DAY),
(17, 'partner',     'Joy Navarro',                    'dswd.coordinator@example.com',   '$2y$10$Axf4BxPOhb3zZK2sb/gjXuTIyx4V.njEEtBlKDPhpcgUEMFep.dBu', '0900-000-0017', 'Quezon City', 'DSWD NCR Field Office (sample)', 3, 'pending', NULL, NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY);

-- ---------------------------------------------------------------------
-- Pharmacies (fictional stores, realistic Metro Manila coordinates)
-- ---------------------------------------------------------------------
INSERT INTO pharmacies (id, user_id, name, branch, address, city, latitude, longitude, phone, opening_hours, fda_license_no) VALUES
(1,  8, 'Sagip Generics',    'Cubao',     'P. Tuazon Blvd., Cubao',                  'Quezon City', 14.6195000, 121.0560000, '(02) 8000-0101', 'Daily 7 AM–9 PM',      'SAMPLE-LTO-0001'),
(2,  9, 'Botika ng Bayan',   'Kamuning',  'K-1st St., Kamuning',                     'Quezon City', 14.6290000, 121.0390000, '(02) 8000-0102', 'Mon–Sat 8 AM–6 PM',    'SAMPLE-LTO-0002'),
(3, 10, 'Kalinga Drugstore', 'Sampaloc',  'España Blvd., Sampaloc',                  'Manila',      14.6090000, 120.9890000, '(02) 8000-0103', 'Daily 24 hours',       'SAMPLE-LTO-0003'),
(4, 11, 'Lunas Pharmacy',    'Ermita',    'Taft Ave. near Padre Faura St., Ermita',  'Manila',      14.5780000, 120.9850000, '(02) 8000-0104', 'Mon–Sun 8 AM–10 PM',   'SAMPLE-LTO-0004'),
(5, 12, 'Bayanihan Drug',    'Santolan',  'Amang Rodriguez Ave., Santolan',          'Pasig',       14.6110000, 121.0860000, '(02) 8000-0105', 'Mon–Sat 8 AM–8 PM',    'SAMPLE-LTO-0005'),
(6, 13, 'Ginhawa Pharmacy',  'Poblacion', 'J.P. Rizal St., Poblacion',               'Makati',      14.5650000, 121.0300000, '(02) 8000-0106', 'Daily 9 AM–9 PM',      'SAMPLE-LTO-0006'),
(7, 14, 'Kalusugan Botika',  'Monumento', 'Rizal Ave. Ext., Grace Park',             'Caloocan',    14.6570000, 120.9840000, '(02) 8000-0107', 'Mon–Sat 7 AM–7 PM',    'SAMPLE-LTO-0007'),
(8, 15, 'Mabuhay Generics',  'Alabang',   'Montillano St., Alabang',                 'Muntinlupa',  14.4230000, 121.0420000, '(02) 8000-0108', 'Daily 8 AM–8 PM',      'SAMPLE-LTO-0008'),
(9, 16, 'Pag-asa Drugstore', 'Concepcion','Bayan-Bayanan Ave., Concepcion Uno',      'Marikina',    14.6510000, 121.1030000, '(02) 8000-0109', 'Mon–Sat 8 AM–7 PM',    'SAMPLE-LTO-0009');

-- ---------------------------------------------------------------------
-- Medicines catalog  (brand_name '' = unbranded generic)
-- ---------------------------------------------------------------------
INSERT INTO medicines (id, generic_name, brand_name, strength, dosage_form, unit) VALUES
(1,  'Losartan Potassium',      '',           '50 mg',        'tablet',  'tablet'),
(2,  'Losartan Potassium',      'Losarvin',   '50 mg',        'tablet',  'tablet'),
(3,  'Losartan Potassium',      'Cardiotan',  '50 mg',        'tablet',  'tablet'),
(4,  'Metformin Hydrochloride', '',           '500 mg',       'tablet',  'tablet'),
(5,  'Metformin Hydrochloride', 'Glucophage', '500 mg',       'tablet',  'tablet'),
(6,  'Amlodipine Besilate',     '',           '5 mg',         'tablet',  'tablet'),
(7,  'Amlodipine Besilate',     'Norvasc',    '5 mg',         'tablet',  'tablet'),
(8,  'Atorvastatin Calcium',    '',           '20 mg',        'tablet',  'tablet'),
(9,  'Atorvastatin Calcium',    'Lipitor',    '20 mg',        'tablet',  'tablet'),
(10, 'Paracetamol',             '',           '500 mg',       'tablet',  'tablet'),
(11, 'Paracetamol',             'Biogesic',   '500 mg',       'tablet',  'tablet'),
(12, 'Amoxicillin Trihydrate',  '',           '500 mg',       'capsule', 'capsule'),
(13, 'Salbutamol Sulfate',      'Ventolin',   '100 mcg/dose', 'inhaler', 'inhaler'),
(14, 'Tamoxifen Citrate',       '',           '20 mg',        'tablet',  'tablet'),
(15, 'Metoprolol Tartrate',     '',           '50 mg',        'tablet',  'tablet'),
(16, 'Gliclazide',              '',           '80 mg',        'tablet',  'tablet');

-- ---------------------------------------------------------------------
-- Pharmacy stock: quantity, price per unit (₱), last update time.
-- quantity 0 = Out of stock; quantity <= threshold = Low stock.
-- ---------------------------------------------------------------------
INSERT INTO pharmacy_stock (pharmacy_id, medicine_id, quantity, price, low_stock_threshold, last_updated_at) VALUES
-- 1 Sagip Generics · Cubao
(1, 1,  240,   6.50, 20, NOW() - INTERVAL 25 MINUTE),
(1, 2,   60,  18.00, 20, NOW() - INTERVAL 25 MINUTE),
(1, 4,  300,   2.50, 20, NOW() - INTERVAL 25 MINUTE),
(1, 6,  150,   4.00, 20, NOW() - INTERVAL 25 MINUTE),
(1, 8,    0,  12.00, 20, NOW() - INTERVAL 25 MINUTE),
(1, 10, 500,   1.50, 50, NOW() - INTERVAL 25 MINUTE),
(1, 11, 200,   4.75, 50, NOW() - INTERVAL 25 MINUTE),
-- 2 Botika ng Bayan · Kamuning
(2, 1,   18,   5.75, 20, NOW() - INTERVAL 2 HOUR),
(2, 4,  120,   2.00, 20, NOW() - INTERVAL 2 HOUR),
(2, 6,   90,   3.50, 20, NOW() - INTERVAL 2 HOUR),
(2, 10, 400,   1.25, 50, NOW() - INTERVAL 2 HOUR),
(2, 12, 150,   5.00, 20, NOW() - INTERVAL 2 HOUR),
(2, 15,  80,   3.00, 20, NOW() - INTERVAL 2 HOUR),
-- 3 Kalinga Drugstore · Sampaloc
(3, 1,  512,   7.20, 20, NOW() - INTERVAL 5 HOUR),
(3, 5,  100,   9.50, 20, NOW() - INTERVAL 5 HOUR),
(3, 7,   60,  42.00, 20, NOW() - INTERVAL 5 HOUR),
(3, 8,  200,  11.00, 20, NOW() - INTERVAL 5 HOUR),
(3, 13,  12, 450.00,  5, NOW() - INTERVAL 5 HOUR),
(3, 14,  90,  18.50, 20, NOW() - INTERVAL 5 HOUR),
-- 4 Lunas Pharmacy · Ermita
(4, 1,    0,   6.90, 20, NOW() - INTERVAL 1 DAY),
(4, 3,  100,  22.00, 20, NOW() - INTERVAL 1 DAY),
(4, 11, 300,   4.50, 50, NOW() - INTERVAL 1 DAY),
(4, 12,  10,   6.50, 20, NOW() - INTERVAL 1 DAY),
(4, 14,  40,  16.00, 20, NOW() - INTERVAL 1 DAY),
(4, 16, 120,   4.25, 20, NOW() - INTERVAL 1 DAY),
-- 5 Bayanihan Drug · Santolan
(5, 1,   22,   6.25, 25, NOW() - INTERVAL 1 DAY),
(5, 4,  250,   2.75, 20, NOW() - INTERVAL 1 DAY),
(5, 6,  300,   3.25, 20, NOW() - INTERVAL 1 DAY),
(5, 9,   30,  58.00, 20, NOW() - INTERVAL 1 DAY),
(5, 15,   0,   3.50, 20, NOW() - INTERVAL 1 DAY),
-- 6 Ginhawa Pharmacy · Poblacion
(6, 1,  180,   8.00, 20, NOW() - INTERVAL 3 DAY),
(6, 5,  150,  10.25, 20, NOW() - INTERVAL 3 DAY),
(6, 7,   45,  45.00, 20, NOW() - INTERVAL 3 DAY),
(6, 9,   50,  62.00, 20, NOW() - INTERVAL 3 DAY),
(6, 10, 600,   1.75, 50, NOW() - INTERVAL 3 DAY),
(6, 13,   8, 465.00,  5, NOW() - INTERVAL 3 DAY),
-- 7 Kalusugan Botika · Monumento
(7, 1,   75,   6.00, 20, NOW() - INTERVAL 5 HOUR),
(7, 4,   15,   2.25, 20, NOW() - INTERVAL 5 HOUR),
(7, 6,  110,   3.75, 20, NOW() - INTERVAL 5 HOUR),
(7, 12, 200,   5.25, 20, NOW() - INTERVAL 5 HOUR),
(7, 16,  60,   3.90, 20, NOW() - INTERVAL 5 HOUR),
-- 8 Mabuhay Generics · Alabang
(8, 2,   90,  17.50, 20, NOW() - INTERVAL 6 DAY),
(8, 8,  140,  10.50, 20, NOW() - INTERVAL 6 DAY),
(8, 11, 250,   4.60, 50, NOW() - INTERVAL 6 DAY),
(8, 14,  25,  17.00, 20, NOW() - INTERVAL 6 DAY),
(8, 15, 100,   4.00, 20, NOW() - INTERVAL 6 DAY);

-- ---------------------------------------------------------------------
-- Assistance requests (one in each status)
-- NOTE: no request_documents rows are included, because the sample
-- files don't exist on disk. Upload real test files through the wizard.
-- ---------------------------------------------------------------------
INSERT INTO assistance_requests
(id, reference_no, beneficiary_id, patient_name, patient_birthdate, patient_sex, relationship, patient_phone, patient_address, patient_city, diagnosis,
 medicine_id, quantity_per_month, duration, supply_left, prescribing_doctor,
 status, status_note, partner_program_id, claim_pharmacy_id, documents_consent_at, submitted_at) VALUES
(1, 'GG-2026-000001', 2, 'Maria Santos',   '1961-03-14', 'female', 'self',   '0900-000-0002', '12 Kamias Rd., Brgy. Pinyahan',  'Quezon City', 'Hypertension',
 1,  60, 'ongoing',  'lt_1_week',  'Dr. Ramon Villanueva',
 'claimed',        NULL, 2, 1,    NOW() - INTERVAL 20 DAY, NOW() - INTERVAL 20 DAY),
(2, 'GG-2026-000002', 2, 'Lourdes Santos', '1938-09-02', 'female', 'parent', '0900-000-0002', '12 Kamias Rd., Brgy. Pinyahan',  'Quezon City', 'Type 2 diabetes',
 4,  60, '3_months', '1_4_weeks',  'Dr. Ramon Villanueva',
 'ready_to_claim', 'Claim at Botika ng Bayan · Kamuning. Bring a valid ID.', 4, 2, NOW() - INTERVAL 10 DAY, NOW() - INTERVAL 10 DAY),
(3, 'GG-2026-000003', 3, 'Elena Garcia',   '1972-11-20', 'female', 'spouse', '0900-000-0003', '45 Dimasalang St., Sampaloc',    'Manila',      'Breast cancer',
 14, 30, '6_months', 'none',       'Dr. Teresa Lim',
 'matched',        NULL, 1, NULL, NOW() - INTERVAL 6 DAY,  NOW() - INTERVAL 6 DAY),
(4, 'GG-2026-000004', 3, 'Roberto Garcia', '1969-05-08', 'male',   'self',   '0900-000-0003', '45 Dimasalang St., Sampaloc',    'Manila',      'Hypertension',
 6,  30, 'ongoing',  'gt_1_month', 'Dr. Teresa Lim',
 'verified',       NULL, NULL, NULL, NOW() - INTERVAL 3 DAY,  NOW() - INTERVAL 3 DAY),
(5, 'GG-2026-000005', 2, 'Maria Santos',   '1961-03-14', 'female', 'self',   '0900-000-0002', '12 Kamias Rd., Brgy. Pinyahan',  'Quezon City', 'High cholesterol',
 8,  30, '1_month',  '1_4_weeks',  'Dr. Ramon Villanueva',
 'action_needed',  'Your certificate of indigency is blurry. Please upload a clearer photo.', NULL, NULL, NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY),
(6, 'GG-2026-000006', 3, 'Paolo Garcia',   '2014-07-15', 'male',   'child',  '0900-000-0003', '45 Dimasalang St., Sampaloc',    'Manila',      'Bacterial infection',
 12, 21, 'one_time', 'none',       'Dr. Teresa Lim',
 'submitted',      NULL, NULL, NULL, NOW() - INTERVAL 1 HOUR, NOW() - INTERVAL 1 HOUR),
(7, 'GG-2026-000007', 2, 'Maria Santos',   '1961-03-14', 'female', 'self',   '0900-000-0002', '12 Kamias Rd., Brgy. Pinyahan',  'Quezon City', 'Fever',
 11, 20, 'one_time', 'none',       'Dr. Ramon Villanueva',
 'rejected',       'The prescription is more than 6 months old. Please submit a new request with a recent prescription.', NULL, NULL, NOW() - INTERVAL 15 DAY, NOW() - INTERVAL 15 DAY);

-- Status timeline for each request (what the "Track request" page shows)
INSERT INTO request_status_history (request_id, status, note, changed_by, changed_at) VALUES
(1, 'submitted',      NULL, 2, NOW() - INTERVAL 20 DAY),
(1, 'verified',       'Documents complete.', 1, NOW() - INTERVAL 19 DAY),
(1, 'matched',        'Matched with PCSO Medical Access Program and Donation #1.', 1, NOW() - INTERVAL 18 DAY),
(1, 'ready_to_claim', 'Claim at Sagip Generics · Cubao.', 6, NOW() - INTERVAL 17 DAY),
(1, 'claimed',        'Medicine received by patient.', 1, NOW() - INTERVAL 15 DAY),
(2, 'submitted',      NULL, 2, NOW() - INTERVAL 10 DAY),
(2, 'verified',       'Documents complete.', 1, NOW() - INTERVAL 9 DAY),
(2, 'matched',        'Matched with Malasakit Centers and Donation #2.', 1, NOW() - INTERVAL 8 DAY),
(2, 'ready_to_claim', 'Claim at Botika ng Bayan · Kamuning. Bring a valid ID.', 1, NOW() - INTERVAL 7 DAY),
(3, 'submitted',      NULL, 3, NOW() - INTERVAL 6 DAY),
(3, 'verified',       'Documents complete.', 1, NOW() - INTERVAL 5 DAY),
(3, 'matched',        'Matched with Philippine Cancer Society and Donation #3.', 1, NOW() - INTERVAL 5 DAY),
(4, 'submitted',      NULL, 3, NOW() - INTERVAL 3 DAY),
(4, 'verified',       'Documents complete.', 1, NOW() - INTERVAL 2 DAY),
(5, 'submitted',      NULL, 2, NOW() - INTERVAL 2 DAY),
(5, 'action_needed',  'Your certificate of indigency is blurry. Please upload a clearer photo.', 1, NOW() - INTERVAL 1 DAY),
(6, 'submitted',      NULL, 3, NOW() - INTERVAL 1 HOUR),
(7, 'submitted',      NULL, 2, NOW() - INTERVAL 15 DAY),
(7, 'rejected',       'The prescription is more than 6 months old. Please submit a new request with a recent prescription.', 1, NOW() - INTERVAL 14 DAY);

-- ---------------------------------------------------------------------
-- Donations (photo columns are NULL because sample photo files don't
-- exist; the real donation form will require a photo)
-- ---------------------------------------------------------------------
INSERT INTO donations
(id, donor_id, medicine_id, quantity, expiry_date, lot_number, donor_notes, status,
 check_sealed, check_label_legible, check_expiry_valid, check_matches_photo,
 rejection_reason, reviewed_by, reviewed_at, created_at) VALUES
-- approved, partly allocated to request 1
(1, 4,  1,  200, CURDATE() + INTERVAL 14 MONTH, 'LS2409A', 'Extra boxes from my late father''s prescription.', 'approved',
 1, 1, 1, 1, NULL, 1, NOW() - INTERVAL 24 DAY, NOW() - INTERVAL 25 DAY),
-- approved, NEAR EXPIRY (45 days left) -> should trigger an alert
(2, 5,  4,  500, CURDATE() + INTERVAL 45 DAY,   'MF2311C', 'Bulk donation from foundation drive.', 'approved',
 1, 1, 1, 1, NULL, 1, NOW() - INTERVAL 58 DAY, NOW() - INTERVAL 60 DAY),
-- approved, mostly allocated to request 3
(3, 5,  14, 200, CURDATE() + INTERVAL 10 MONTH, 'TX2502B', NULL, 'approved',
 1, 1, 1, 1, NULL, 1, NOW() - INTERVAL 6 DAY,  NOW() - INTERVAL 7 DAY),
-- pending: waiting for admin verification
(4, 4,  6,  100, CURDATE() + INTERVAL 8 MONTH,  'AM2501D', 'Sealed, unused.', 'pending',
 0, 0, 0, 0, NULL, NULL, NULL, NOW() - INTERVAL 1 DAY),
-- donated by a pharmacy account, approved, NEAR EXPIRY, not yet allocated
(5, 15, 10, 1000, CURDATE() + INTERVAL 50 DAY,  'PC2405E', 'Overstock from our branch.', 'approved',
 1, 1, 1, 1, NULL, 1, NOW() - INTERVAL 40 DAY, NOW() - INTERVAL 42 DAY),
-- rejected with a reason
(6, 4,  12,  30, CURDATE() + INTERVAL 6 MONTH,  NULL, NULL, 'rejected',
 0, 1, 1, 1, 'Packaging is already opened. We can only accept sealed medicines.', 1, NOW() - INTERVAL 9 DAY, NOW() - INTERVAL 10 DAY),
-- pending: waiting for admin verification
(7, 5,  8,  300, CURDATE() + INTERVAL 11 MONTH, 'AT2506F', NULL, 'pending',
 0, 0, 0, 0, NULL, NULL, NULL, NOW() - INTERVAL 3 HOUR);

-- Routing: which donation units went to which request
INSERT INTO donation_allocations (donation_id, request_id, quantity, allocated_by, allocated_at) VALUES
(1, 1,  60, 1, NOW() - INTERVAL 18 DAY),
(2, 2, 180, 1, NOW() - INTERVAL 8 DAY),
(3, 3, 180, 1, NOW() - INTERVAL 5 DAY);

-- ---------------------------------------------------------------------
-- In-app notifications
-- ---------------------------------------------------------------------
INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES
(2, 'Your request GG-2026-000002 is ready to claim at Botika ng Bayan · Kamuning.', 'beneficiary/request_view.php?id=2', 0, NOW() - INTERVAL 7 DAY),
(2, 'Action needed on GG-2026-000005: please upload a clearer certificate of indigency.', 'beneficiary/request_view.php?id=5', 0, NOW() - INTERVAL 1 DAY),
(3, 'Your request GG-2026-000003 was matched with the Philippine Cancer Society.', 'beneficiary/request_view.php?id=3', 1, NOW() - INTERVAL 5 DAY),
(4, 'Your donation #6 was not accepted: packaging is already opened.', 'donor/donation_view.php?id=6', 0, NOW() - INTERVAL 9 DAY),
(9, 'Low stock: Losartan Potassium 50 mg (18 left). Please update your stock.', 'pharmacy/stock.php', 0, NOW() - INTERVAL 2 HOUR);
