<?php

namespace App;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;
    private static string $driver = 'sqlite';

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            try {
                // Try reading from config first, fallback to defined constants
                $configPath = __DIR__ . '/../config.php';
                $config = file_exists($configPath) ? require $configPath : [];
                self::$driver = $config['DB_CONNECTION'] ?? 'sqlite';

                if (self::$driver === 'mysql') {
                    $host = $config['DB_HOST'] ?? '127.0.0.1';
                    $port = $config['DB_PORT'] ?? '3306';
                    $database = $config['DB_DATABASE'] ?? 'jobpulse';
                    $username = $config['DB_USERNAME'] ?? 'root';
                    $password = $config['DB_PASSWORD'] ?? '';
                    
                    self::$instance = new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4", $username, $password);
                } else {
                    $dbPath = $config['DB_PATH'] ?? (defined('DB_PATH') ? DB_PATH : __DIR__ . '/../data/database.sqlite');
                    self::$instance = new PDO("sqlite:" . $dbPath);
                }
                
                self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

                self::initTables();
            } catch (PDOException $e) {
                die("Database Connection failed: " . $e->getMessage());
            }
        }

        return self::$instance;
    }

    public static function initTables()
    {
        $pkDef = self::$driver === 'mysql' ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $dateTimeDef = self::$driver === 'mysql' ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'DATETIME DEFAULT CURRENT_TIMESTAMP';
        $textType = self::$driver === 'mysql' ? 'LONGTEXT' : 'TEXT';
        
        $sql = "
        CREATE TABLE IF NOT EXISTS jobs (
            id $pkDef,
            company_name $textType NOT NULL,
            job_title $textType NOT NULL,
            status VARCHAR(100) DEFAULT 'Applied',
            date_applied $dateTimeDef,
            notes $textType,
            ai_analysis $textType
        );

        CREATE TABLE IF NOT EXISTS resumes (
            id $pkDef,
            filename $textType NOT NULL,
            original_name $textType NOT NULL,
            upload_date $dateTimeDef
        );
        ";

        self::$instance->exec($sql);
    }
}
