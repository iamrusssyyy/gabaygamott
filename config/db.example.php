<?php
// =====================================================================
// config/db.example.php — TEMPLATE for the database settings.
//
// HOW TO USE:
//   1. Copy this file and name the copy  config/db.php
//   2. Change the values below if your MySQL is different.
//
// Why two files? config/db.php holds the REAL password, so it is listed
// in .gitignore and never uploaded to Git. This example file has no
// secrets, so it is safe to commit.
// =====================================================================

define('DB_HOST', 'localhost');   // MySQL server (XAMPP runs it on this computer)
define('DB_NAME', 'gabaygamot');  // database created by database/schema.sql
define('DB_USER', 'root');        // XAMPP's default MySQL user
define('DB_PASS', '');            // XAMPP's default root password is empty
