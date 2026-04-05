<?php

require_once __DIR__ . '/src/Database.php';

use App\Database;

$dbPath = defined('DB_PATH') ? DB_PATH : __DIR__ . '/data/database.sqlite';

if (!file_exists($dbPath)) {
    echo "No database.sqlite found. The initialization logic in Database.php will create it when needed. No migration required.\n";
    exit;
}

try {
    $db = Database::getConnection();
    
    // Check if column exists
    $stmt = $db->query("PRAGMA table_info(jobs)");
    $columns = $stmt->fetchAll();
    
    $hasAiAnalysis = false;
    foreach ($columns as $column) {
        if ($column['name'] === 'ai_analysis') {
            $hasAiAnalysis = true;
            break;
        }
    }
    
    if (!$hasAiAnalysis) {
        $db->exec("ALTER TABLE jobs ADD COLUMN ai_analysis TEXT");
        echo "Successfully added 'ai_analysis' column to 'jobs' table.\n";
    } else {
        echo "Column 'ai_analysis' already exists in 'jobs' table.\n";
    }
} catch (Exception $e) {
    echo "Error upgrading database: " . $e->getMessage() . "\n";
}
