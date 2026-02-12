<?php
$envPath = __DIR__ . '/../.env';
if (!file_exists($envPath)) {
    fwrite(STDERR, ".env not found at {$envPath}\n");
    exit(1);
}

$vars = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }
    if (!str_contains($line, '=')) {
        continue;
    }
    [$key, $value] = explode('=', $line, 2);
    $key = trim($key);
    $value = trim($value);
    if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
        $quote = $value[0];
        if (substr($value, -1) === $quote) {
            $value = substr($value, 1, -1);
        }
    }
    $vars[$key] = $value;
}

$host = $vars['DB_HOST'] ?? '127.0.0.1';
$port = $vars['DB_PORT'] ?? '3306';
$user = $vars['DB_USERNAME'] ?? 'root';
$pass = $vars['DB_PASSWORD'] ?? '';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};port={$port};charset={$charset}";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "DB connect failed: {$e->getMessage()}\n");
    exit(1);
}

$systemDbs = ['information_schema', 'mysql', 'performance_schema', 'sys'];
$targets = ['users', 'lessons', 'grammar_topics', 'vocab_items', 'writing_tasks', 'attempts', 'grammar_attempts'];

$placeholders = implode(',', array_fill(0, count($systemDbs), '?'));
$stmt = $pdo->prepare("SELECT schema_name FROM information_schema.schemata WHERE schema_name NOT IN ($placeholders)");
$stmt->execute($systemDbs);
$dbs = $stmt->fetchAll(PDO::FETCH_COLUMN);

$results = [];
$existsStmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ? LIMIT 1");

foreach ($dbs as $db) {
    foreach ($targets as $table) {
        $existsStmt->execute([$db, $table]);
        if (!$existsStmt->fetchColumn()) {
            continue;
        }
        $safeDb = str_replace('`', '``', $db);
        $safeTable = str_replace('`', '``', $table);
        $count = $pdo->query("SELECT COUNT(*) AS c FROM `{$safeDb}`.`{$safeTable}`")->fetchColumn();
        if ((int) $count > 0) {
            $results[$db][$table] = (int) $count;
        }
    }
}

if (!$results) {
    echo "No non-empty target tables found in any database.\n";
    exit(0);
}

foreach ($results as $db => $tables) {
    echo $db . PHP_EOL;
    foreach ($tables as $table => $count) {
        echo "  {$table}: {$count}" . PHP_EOL;
    }
}
