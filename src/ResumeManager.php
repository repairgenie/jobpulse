<?php
namespace App;

require_once __DIR__ . '/../bootstrap.php';

class ResumeManager
{
    private $baseDir;

    public function __construct()
    {
        $this->baseDir = DATA_DIR . '/resumes';
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0777, true);
        }
    }

    private function getUserDir(string $userId): string
    {
        $dir = $this->baseDir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $userId);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }

    public function saveResume(string $userId, string $filename, string $extractedText, string $category = 'General'): array
    {
        $userDir = $this->getUserDir($userId);
        
        // If this is the user's first resume, make it primary automatically
        $existingResumes = $this->getResumes($userId);
        $isPrimary = empty($existingResumes) ? true : false;
        
        $resumeId = uniqid('res_');
        $filePath = $userDir . '/' . $resumeId . '.json';
        
        $data = [
            'id' => $resumeId,
            'filename' => $filename,
            'category' => $category,
            'is_primary' => $isPrimary,
            'upload_date' => date('Y-m-d H:i:s'),
            'extracted_text' => $extractedText
        ];
        
        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
        
        return $data;
    }

    public function getResumes(string $userId): array
    {
        $userDir = $this->getUserDir($userId);
        $resumes = [];
        
        $files = glob($userDir . '/*.json');
        if ($files) {
            foreach ($files as $file) {
                $content = file_get_contents($file);
                $data = json_decode($content, true);
                if ($data) {
                    // Don't leak the massive extracted_text blob when just listing them
                    unset($data['extracted_text']);
                    $resumes[] = $data;
                }
            }
        }
        
        // Sort newest first
        usort($resumes, function($a, $b) {
            return strtotime($b['upload_date']) - strtotime($a['upload_date']);
        });
        
        return $resumes;
    }

    public function getResumeFull(string $userId, string $resumeId): ?array
    {
        $userDir = $this->getUserDir($userId);
        $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '', $resumeId);
        $filePath = $userDir . '/' . $safeId . '.json';
        
        if (file_exists($filePath)) {
            return json_decode(file_get_contents($filePath), true);
        }
        
        return null;
    }

    public function setPrimaryResume(string $userId, string $resumeIdToPrimary): bool
    {
        $userDir = $this->getUserDir($userId);
        $files = glob($userDir . '/*.json');
        
        if (!$files) return false;
        
        $success = false;
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                if ($data['id'] === $resumeIdToPrimary) {
                    $data['is_primary'] = true;
                    $success = true;
                } else {
                    $data['is_primary'] = false;
                }
                file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
            }
        }
        
        return $success;
    }

    public function deleteResume(string $userId, string $resumeId): bool
    {
        $userDir = $this->getUserDir($userId);
        $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '', $resumeId);
        $filePath = $userDir . '/' . $safeId . '.json';
        
        if (file_exists($filePath)) {
            unlink($filePath);
            
            // If we deleted the primary resume, make the newest one primary
            $resumes = $this->getResumes($userId);
            $hasPrimary = false;
            foreach($resumes as $res) {
                if ($res['is_primary']) $hasPrimary = true;
            }
            
            if (!$hasPrimary && !empty($resumes)) {
                $this->setPrimaryResume($userId, $resumes[0]['id']);
            }
            
            return true;
        }
        
        return false;
    }
}

