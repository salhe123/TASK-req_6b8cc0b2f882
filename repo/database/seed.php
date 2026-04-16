<?php
/**
 * PHP seeder — run after schema.sql to set proper bcrypt passwords.
 * Usage: php database/seed.php
 */

$host = getenv('DATABASE_HOSTNAME') ?: (getenv('DATABASE.HOSTNAME') ?: '127.0.0.1');
$db   = getenv('DATABASE_DATABASE') ?: (getenv('DATABASE.DATABASE') ?: 'precision_portal');
$user = getenv('DATABASE_USERNAME') ?: (getenv('DATABASE.USERNAME') ?: 'portal_user');
$pass = getenv('DATABASE_PASSWORD') ?: (getenv('DATABASE.PASSWORD') ?: 'portal_secret');
$port = getenv('DATABASE_HOSTPORT') ?: (getenv('DATABASE.HOSTPORT') ?: '3306');

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db}", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "Connected to database.\n";
} catch (PDOException $e) {
    echo "DB connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Update users with proper bcrypt passwords
$users = [
    ['username' => 'admin',        'password' => 'Admin12345!'],
    ['username' => 'planner1',     'password' => 'Planner12345!'],
    ['username' => 'coordinator1', 'password' => 'Coordinator1!'],
    ['username' => 'provider1',    'password' => 'Provider1234!'],
    ['username' => 'reviewer1',      'password' => 'Reviewer1234!'],
    ['username' => 'reviewmanager1', 'password' => 'ReviewMgr1234!'],
    ['username' => 'specialist1',    'password' => 'Specialist123!'],
    ['username' => 'operator1',      'password' => 'Operator1234!'],
    ['username' => 'moderator1',     'password' => 'Moderator123!'],
    ['username' => 'finance1',     'password' => 'Finance12345!'],
];

$stmt = $pdo->prepare("UPDATE pp_users SET password = ? WHERE username = ?");
foreach ($users as $u) {
    $hash = password_hash($u['password'], PASSWORD_BCRYPT);
    $stmt->execute([$hash, $u['username']]);
    echo "  Updated password for {$u['username']}\n";
}

echo "Seed complete.\n";
