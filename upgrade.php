<?php

require_once __DIR__ . '/src/Database.php';

use App\Database;

$dbPath = defined('DB_PATH') ? DB_PATH : __DIR__ . '/data/database.sqlite';

try {
    $db = Database::getConnection();
    
    // Check if column exists
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $hasAiAnalysis = false;
    
    if ($driver === 'sqlite') {
        $stmt = $db->query("PRAGMA table_info(jobs)");
        $columns = $stmt->fetchAll();
        foreach ($columns as $column) {
            if ($column['name'] === 'ai_analysis') {
                $hasAiAnalysis = true;
                break;
            }
        }
    } else {
        // MySQL
        $stmt = $db->query("SHOW COLUMNS FROM jobs LIKE 'ai_analysis'");
        if ($stmt->fetch()) {
            $hasAiAnalysis = true;
        }
    }
    
    if (!$hasAiAnalysis) {
        $textType = $driver === 'mysql' ? 'LONGTEXT' : 'TEXT';
        $db->exec("ALTER TABLE jobs ADD COLUMN ai_analysis $textType");
        echo "Successfully added 'ai_analysis' column to 'jobs' table.\n";
    } else {
        echo "Column 'ai_analysis' already exists in 'jobs' table.\n";
    }

    // Migrate from history.json
    $historyFile = __DIR__ . '/data/history.json';
    if (file_exists($historyFile)) {
        echo "Found history.json. Identifying data to migrate...\n";
        $data = json_decode(file_get_contents($historyFile), true);
        if ($data) {
            $insertedJobs = 0;
            $insertedResumes = 0;
            $resumeMap = [];

            foreach ($data as $item) {
                if (!empty($item['resume_name']) && empty($resumeMap[$item['resume_name']])) {
                    $stmt = $db->prepare("SELECT id FROM resumes WHERE original_name = :title");
                    $stmt->execute(['title' => $item['resume_name']]);
                    if (!$stmt->fetch()) {
                        $stmt = $db->prepare("INSERT INTO resumes (filename, original_name, upload_date) VALUES (:filename, :original_name, :upload_date)");
                        $dateStr = date('Y-m-d H:i:s', $item['timestamp'] ?? time());
                        $stmt->execute([
                            'filename' => $item['resume_name'],
                            'original_name' => $item['resume_name'],
                            'upload_date' => $dateStr
                        ]);
                        $resumeMap[$item['resume_name']] = true;
                        $insertedResumes++;
                    } else {
                        $resumeMap[$item['resume_name']] = true;
                    }
                }

                $company = $item['job_company'] ?? 'Unknown Company';
                $title = $item['job_title'] ?? 'Unknown Title';
                $date_applied = date('Y-m-d H:i:s', $item['timestamp'] ?? time());

                $stmt = $db->prepare("SELECT id FROM jobs WHERE company_name = :company AND job_title = :title AND date_applied = :date");
                $stmt->execute(['company' => $company, 'title' => $title, 'date' => $date_applied]);
                if (!$stmt->fetch()) {
                    $notes = json_encode($item['notes'] ?? []);
                    $aiOptions = [
                        'strategy' => $item['strategy'] ?? '',
                        'perfect_matches' => $item['perfect_matches'] ?? [],
                        'cover_letter' => $item['cover_letter'] ?? '',
                        'optimized_resume_text' => $item['optimized_resume_text'] ?? ''
                    ];
                    $ai_analysis = json_encode($aiOptions);

                    $insertJob = $db->prepare("INSERT INTO jobs (company_name, job_title, status, date_applied, notes, ai_analysis) VALUES (:company, :title, 'Applied', :date, :notes, :ai)");
                    $insertJob->execute([
                        'company' => $company,
                        'title' => $title,
                        'date' => $date_applied,
                        'notes' => $notes,
                        'ai' => $ai_analysis
                    ]);
                    $insertedJobs++;
                }
            }
            echo "Migrated $insertedJobs jobs and $insertedResumes resumes from history.json.\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error upgrading database: " . $e->getMessage() . "\n";
}
