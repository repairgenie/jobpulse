<?php
namespace App;

class Security {
    public static function generateCsrfToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrfToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function checkCsrf() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'DELETE') {
            $headers = function_exists('getallheaders') ? getallheaders() : [];
            $token = $_POST['csrf_token'] ?? $headers['X-CSRF-Token'] ?? '';

            // For JSON payloads
            if (empty($token)) {
                $input = json_decode(file_get_contents('php://input'), true);
                if (is_array($input)) {
                    $token = $input['csrf_token'] ?? '';
                }
            }

            if (!self::verifyCsrfToken($token)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Invalid or missing CSRF token.']);
                exit;
            }
        }
    }

    public static function rateLimit($identifier, $limit = 10, $windowInSeconds = 60) {
        $rateLimitFile = __DIR__ . '/../data/rate_limits.json';
        if (!file_exists(dirname($rateLimitFile))) {
            mkdir(dirname($rateLimitFile), 0777, true);
        }

        $limits = [];
        if (file_exists($rateLimitFile)) {
            $json = file_get_contents($rateLimitFile);
            $limits = json_decode($json, true) ?: [];
        }

        $key = md5($identifier);
        $currentTime = time();

        if (!isset($limits[$key])) {
            $limits[$key] = [
                'count' => 1,
                'start_time' => $currentTime
            ];
            file_put_contents($rateLimitFile, json_encode($limits));
            return true;
        }

        $elapsedTime = $currentTime - $limits[$key]['start_time'];

        if ($elapsedTime < $windowInSeconds) {
            if ($limits[$key]['count'] >= $limit) {
                return false; // Rate limit exceeded
            }
            $limits[$key]['count']++;
        } else {
            // Reset the window
            $limits[$key] = [
                'count' => 1,
                'start_time' => $currentTime
            ];
        }

        // Clean up old limits
        foreach ($limits as $k => $v) {
            if ($currentTime - $v['start_time'] > 3600) {
                unset($limits[$k]);
            }
        }

        file_put_contents($rateLimitFile, json_encode($limits));
        return true;
    }
}
