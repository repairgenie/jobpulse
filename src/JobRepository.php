<?php

namespace App;

use PDO;
use Exception;

class JobRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("SELECT * FROM jobs ORDER BY date_applied DESC");
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM jobs WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO jobs (company_name, job_title, status, date_applied, notes)
            VALUES (:company, :title, :status, :date, :notes)
        ");

        $stmt->execute([
            'company' => $data['company_name'] ?? '',
            'title'   => $data['job_title'] ?? '',
            'status'  => $data['status'] ?? 'Applied',
            'date'    => $data['date_applied'] ?? date('Y-m-d H:i:s'),
            'notes'   => $data['notes'] ?? ''
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function saveJobAnalysis(array $data): int
    {
        // For backwards compatibility if DB alter didn't run, handle gracefully
        // We will try inserting with ai_analysis
        try {
            $stmt = $this->db->prepare("
                INSERT INTO jobs (company_name, job_title, status, date_applied, notes, ai_analysis)
                VALUES (:company, :title, :status, :date, :notes, :ai_analysis)
            ");
            
            $stmt->execute([
                'company'     => $data['company_name'] ?? '',
                'title'       => $data['job_title'] ?? '',
                'status'      => $data['status'] ?? 'Analyzed',
                'date'        => date('Y-m-d H:i:s'),
                'notes'       => $data['notes'] ?? '',
                'ai_analysis' => $data['ai_analysis'] ?? ''
            ]);
            return (int) $this->db->lastInsertId();
        } catch (\PDOException $e) {
            // Fallback to inserting without ai_analysis if column missing
            $stmt = $this->db->prepare("
                INSERT INTO jobs (company_name, job_title, status, date_applied, notes)
                VALUES (:company, :title, :status, :date, :notes)
            ");
            
            $notes = ($data['notes'] ?? '') . "\n\nAI Analysis: " . ($data['ai_analysis'] ?? '');
            
            $stmt->execute([
                'company' => $data['company_name'] ?? '',
                'title'   => $data['job_title'] ?? '',
                'status'  => $data['status'] ?? 'Analyzed',
                'date'    => date('Y-m-d H:i:s'),
                'notes'   => $notes
            ]);
            return (int) $this->db->lastInsertId();
        }
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE jobs 
            SET company_name = :company, job_title = :title, status = :status, notes = :notes
            WHERE id = :id
        ");

        return $stmt->execute([
            'id'      => $id,
            'company' => $data['company_name'] ?? '',
            'title'   => $data['job_title'] ?? '',
            'status'  => $data['status'] ?? 'Applied',
            'notes'   => $data['notes'] ?? ''
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM jobs WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
