<?php
require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = $_ENV['DB_PORT'] ?? '3306';
$user = $_ENV['DB_USERNAME'] ?? 'root';
$pass = $_ENV['DB_PASSWORD'] ?? '';

$tablesToCheck = [
    'users',
    'grammar_topics',
    'grammar_rules',
    'grammar_exercises',
    'lessons',
    'questions',
    'vocab_lists',
    'vocab_items',
    'writing_tasks',
    'mock_tests',
];

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname=information_schema", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "DB connect failed: {$e->getMessage()}\n");
    exit(1);
}

$placeholders = rtrim(str_repeat('?,', count($tablesToCheck)), ',');
$sql = "SELECT TABLE_SCHEMA, TABLE_NAME, TABLE_ROWS
        FROM TABLES
        WHERE TABLE_NAME IN ({$placeholders})
        ORDER BY TABLE_SCHEMA, TABLE_NAME";

$stmt = $pdo->prepare($sql);
$stmt->execute($tablesToCheck);
$rows = $stmt->fetchAll();

$byDb = [];
foreach ($rows as $row) {
    $db = $row['TABLE_SCHEMA'];
    $table = $row['TABLE_NAME'];
    $count = (int) $row['TABLE_ROWS'];
    if (!isset($byDb[$db])) {
        $byDb[$db] = [];
    }
    $byDb[$db][$table] = $count;
}

// Only show DBs that have at least one of these tables and any rows > 0.
foreach ($byDb as $db => $tables) {
    $hasData = false;
    foreach ($tables as $count) {
        if ($count > 0) {
            $hasData = true;
            break;
        }
    }
    if (!$hasData) {
        continue;
    }
    echo "DB: {$db}\n";
    foreach ($tablesToCheck as $tableName) {
        if (array_key_exists($tableName, $tables)) {
            echo "  {$tableName}: {$tables[$tableName]}\n";
        }
    }
    echo "\n";
}
