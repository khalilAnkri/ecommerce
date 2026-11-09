<?php
// ------------------------------------------------------------
// includes/db.php
// Creates ONE database connection, stored in the variable $pdo.
// Every page includes this file once at the top.
// ------------------------------------------------------------

$db_host = 'localhost';
$db_name = 'poilu_and_co';
$db_user = 'root';
$db_pass = '';  

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // show SQL errors as exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // rows come back as arrays
            PDO::ATTR_EMULATE_PREPARES   => false,                  // real prepared statements
        ]
    );
} catch (PDOException $error) {

    error_log('Database connection failed: ' . $error->getMessage());
    $pdo = null;
}
