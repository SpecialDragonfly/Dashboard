<?php

$projectRoot = dirname(__DIR__);
$dbPath = $projectRoot . '/var/data.db';

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("
    CREATE TABLE IF NOT EXISTS migrations_applied (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        filename TEXT NOT NULL UNIQUE,
        applied_at TEXT NOT NULL DEFAULT (datetime('now'))
    )
");

$applied = array_flip(
    $pdo->query('SELECT filename FROM migrations_applied')->fetchAll(PDO::FETCH_COLUMN)
);

$files = glob($projectRoot . '/migrations/*.sql');
sort($files);

foreach ($files as $file) {
    $filename = basename($file);
    if (isset($applied[$filename])) {
        echo "Skipping:  $filename\n";
        continue;
    }
    echo "Applying:  $filename ... ";
    $pdo->exec(file_get_contents($file));
    $pdo->prepare('INSERT INTO migrations_applied (filename) VALUES (?)')->execute([$filename]);
    echo "done\n";
}

echo "Migrations complete.\n";
