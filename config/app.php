<?php
// =====================================================================
// config/app.php — general app settings (no passwords, safe to commit).
// =====================================================================

define('APP_NAME', 'GabayGamot');

// The URL path where the app lives: http://localhost/gabaygamot
define('BASE_URL', '/gabaygamot');

// Uploaded documents are saved OUTSIDE htdocs so nobody can open them
// by typing a URL. dirname(__DIR__, 3) goes up 3 folders:
//   config -> gabaygamot -> htdocs -> C:\xampp
// so files go to C:\xampp\gabaygamot_uploads
define('UPLOAD_DIR', dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'gabaygamot_uploads');
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5 MB

// Donation rules (Chapter 1: "expiry date and quantity validation")
define('MIN_DONATION_SHELF_DAYS', 90);   // donated medicine must expire at least 90 days from today
define('MAX_DONATION_QUANTITY', 10000);  // sanity limit for one donation
define('NEAR_EXPIRY_DAYS', 60);          // alert when approved donations expire within 60 days

// Help line shown on the request wizard. SAMPLE NUMBER — replace with the real hotline.
define('HELP_PHONE', '8-123-4567');

// The 17 local government units (LGUs) of Metro Manila, for City dropdowns.
const METRO_MANILA_CITIES = [
    'Caloocan', 'Las Piñas', 'Makati', 'Malabon', 'Mandaluyong', 'Manila',
    'Marikina', 'Muntinlupa', 'Navotas', 'Parañaque', 'Pasay', 'Pasig',
    'Pateros', 'Quezon City', 'San Juan', 'Taguig', 'Valenzuela',
];

// Use Philippine time for date() and the "Last updated" labels.
date_default_timezone_set('Asia/Manila');
