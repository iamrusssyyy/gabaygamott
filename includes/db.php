<?php
// =====================================================================
// includes/db.php — the ONE place where we connect to MySQL.
//
// PDO (PHP Data Objects) is PHP's built-in way to talk to databases.
// We always use PREPARED STATEMENTS with it:
//
//     $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
//     $stmt->execute([$email]);
//
// The "?" is a placeholder. MySQL receives the SQL and the value
// SEPARATELY, so whatever the user types is treated as plain data and
// can never change the SQL command. This blocks "SQL injection".
// =====================================================================

function db(): PDO
{
    // "static" keeps $pdo between calls, so each page connects only once.
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // stop with an error if a query fails
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // rows come back as ['column' => value]
                PDO::ATTR_EMULATE_PREPARES   => false,                  // use MySQL's real prepared statements
            ]);
        } catch (PDOException $ex) {
            // Don't show the technical error (it can reveal server details).
            http_response_code(500);
            exit('Cannot connect to the database. Is MySQL running in the XAMPP Control Panel?');
        }

        // Make MySQL's NOW() use Philippine time (UTC+8), same as PHP.
        $pdo->exec("SET time_zone = '+08:00'");
    }

    return $pdo;
}
