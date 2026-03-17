<?php

namespace App;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            try {
                // Connect to SQLite database
                $dbPath = defined('DB_PATH') ? DB_PATH : __DIR__ . '/../data/database.sqlite';
                self::$instance = new PDO("sqlite:" . $dbPath);
                
                // Enable exceptions for errors
                self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Set default fetch mode to associative array
                self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

                // Initialize tables if they don't exist
                self::initTables();
            } catch (PDOException $e) {
                die("Database Connection failed: " . $e->getMessage());
            }
        }

        return self::$instance;
    }

    private static function initTables()
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_name TEXT NOT NULL,
            job_title TEXT NOT NULL,
            status TEXT DEFAULT 'Applied',
            date_applied DATETIME DEFAULT CURRENT_TIMESTAMP,
            notes TEXT,
            ai_analysis TEXT
        );

        CREATE TABLE IF NOT EXISTS resumes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename TEXT NOT NULL,
            original_name TEXT NOT NULL,
            upload_date DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        ";

        self::$instance->exec($sql);
    }
}
