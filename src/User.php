<?php

namespace App;

require_once __DIR__ . '/../bootstrap.php';

class User
{
    private $usersFile;

    public function __construct()
    {
        $this->usersFile = USERS_FILE;
        if (!file_exists($this->usersFile)) {
            file_put_contents($this->usersFile, json_encode([], JSON_PRETTY_PRINT));
        }
    }

    private function getUsers(): array
    {
        $content = file_get_contents($this->usersFile);
        return json_decode($content, true) ?: [];
    }

    private function saveUsers(array $users): void
    {
        file_put_contents($this->usersFile, json_encode($users, JSON_PRETTY_PRINT));
    }

    public function register(string $email, string $password, string $role = 'user', array $extraData = []): array
    {
        if (REGISTRATION_MODE === 'closed') {
            return ['success' => false, 'message' => 'Registration is currently closed.'];
        }

        $users = $this->getUsers();

        // Check if user already exists
        foreach ($users as $user) {
            if ($user['email'] === $email) {
                return ['success' => false, 'message' => 'Email already registered.'];
            }
        }

        $isActive = 1;
        if (REGISTRATION_MODE === 'admin_approval' || REQUIRE_EMAIL_CONFIRMATION) {
            $isActive = 0;
        }

        // Create new user
        $newUser = [
            'id' => uniqid('user_', true),
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'is_active' => $isActive,
            'created_at' => date('Y-m-d H:i:s'),
            'first_name' => $extraData['first_name'] ?? '',
            'last_name' => $extraData['last_name'] ?? '',
            'city' => $extraData['city'] ?? '',
            'state' => $extraData['state'] ?? '',
            'zip_code' => $extraData['zip_code'] ?? ''
        ];

        $users[] = $newUser;
        $this->saveUsers($users);

        $message = 'Registration successful.';
        if ($isActive === 0) {
            $message .= ' Your account is pending approval or email confirmation.';
        }

        return ['success' => true, 'message' => $message, 'user_id' => $newUser['id']];
    }

    public function login(string $email, string $password): array
    {
        $users = $this->getUsers();

        foreach ($users as $user) {
            if ($user['email'] === $email) {
                if (password_verify($password, $user['password_hash'])) {
                    if ($user['is_active'] == 0) {
                        return ['success' => false, 'message' => 'Account is not active or pending approval.'];
                    }
                    
                    // In a real app, you'd set a session here.
                    return [
                        'success' => true, 
                        'message' => 'Login successful', 
                        'user' => [
                            'id' => $user['id'],
                            'email' => $user['email'],
                            'role' => $user['role'],
                            'first_name' => $user['first_name'] ?? '',
                            'last_name' => $user['last_name'] ?? ''
                        ]
                    ];
                } else {
                    return ['success' => false, 'message' => 'Invalid password.'];
                }
            }
        }

        return ['success' => false, 'message' => 'User not found.'];
    }

    public function isAdmin(string $userId): bool
    {
        $users = $this->getUsers();
        foreach ($users as $user) {
            if ($user['id'] === $userId && $user['role'] === 'admin') {
                return true;
            }
        }
        return false;
    }
    
    public function approveUser(string $adminId, string $userIdToApprove): array
    {
        if (!$this->isAdmin($adminId)) {
            return ['success' => false, 'message' => 'Unauthorized. Admin access required.'];
        }
        
        $users = $this->getUsers();
        $found = false;
        
        foreach ($users as &$user) {
            if ($user['id'] === $userIdToApprove) {
                $user['is_active'] = 1;
                $found = true;
                break;
            }
        }
        
        if ($found) {
            $this->saveUsers($users);
            return ['success' => true, 'message' => 'User approved successfully.'];
        }
        
        return ['success' => false, 'message' => 'User not found.'];
    }

    public function updateResumeText(string $userId, string $resumeText, string $filename): bool
    {
        $users = $this->getUsers();
        $found = false;

        foreach ($users as &$user) {
            if ($user['id'] === $userId) {
                $user['resume_text'] = $resumeText;
                $user['resume_filename'] = $filename;
                $found = true;
                break;
            }
        }

        if ($found) {
            $this->saveUsers($users);
            return true;
        }

        return false;
    }

    public function getResumeText(string $userId): ?array
    {
        $users = $this->getUsers();
        foreach ($users as $user) {
            if ($user['id'] === $userId) {
                if (isset($user['resume_text'])) {
                    return [
                        'filename' => $user['resume_filename'] ?? 'resume.pdf',
                        'text' => $user['resume_text']
                    ];
                }
                return null;
            }
        }
        return null;
    }
    public function getUserLocation(string $userId): ?array
    {
        $users = $this->getUsers();
        foreach ($users as $user) {
            if ($user['id'] === $userId) {
                return [
                    'city' => $user['city'] ?? '',
                    'state' => $user['state'] ?? '',
                    'zip_code' => $user['zip_code'] ?? ''
                ];
            }
        }
        return null;
    }
}

