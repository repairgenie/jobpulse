<?php
$hash = password_hash('demo123', PASSWORD_DEFAULT);
$users = [
    [
        'id' => 'user_test123',
        'email' => 'demo@jobpulse.local',
        'password_hash' => $hash,
        'is_active' => 1,
        'role' => 'admin'
    ]
];
file_put_contents('data/users.json', json_encode($users, JSON_PRETTY_PRINT));
echo "User created\n";
