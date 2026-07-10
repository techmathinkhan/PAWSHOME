<?php
/**
 * PawsHome — MySQL Database Configuration for XAMPP
 *
 * If your phpMyAdmin/MySQL has a password, change DB_PASS below.
 * Default XAMPP install: root user, no password.
 */

define('DB_HOST',    'localhost');
define('DB_USER',    'root');
define('DB_PASS',    '');        // ← Change this if you set a MySQL password in phpMyAdmin
define('DB_NAME',    'pawshome');
define('DB_CHARSET', 'utf8mb4');

/**
 * OPTIONAL — AI Chatbot Upgrade
 * Leave this empty ('') to use the built-in rule-based PawsBot engine,
 * which works fully offline using your live pet database — no API key needed.
 *
 * If you DO have an Anthropic API key (https://console.anthropic.com),
 * paste it below to upgrade PawsBot to full conversational AI:
 */
define('ANTHROPIC_API_KEY', '');   // e.g. 'sk-ant-api03-...'

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'DB connection failed — make sure XAMPP MySQL is running '
                     . 'and you imported pawshome_database.sql in phpMyAdmin. '
                     . 'Details: ' . $e->getMessage()
        ]);
        exit;
    }
    return $pdo;
}
