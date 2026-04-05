<?php
session_start();
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/Security.php';

use App\Security;

header('Content-Type: application/json');
echo json_encode(['token' => Security::generateCsrfToken()]);
