<?php
/**
 * OEMS one-click installer (browser or CLI)
 */
declare(strict_types=1);

$isCli = PHP_SAPI === 'cli';

function out(string $msg, bool $cli, string $cls = ''): void
{
    if ($cli) {
        echo $msg . PHP_EOL;
        return;
    }
    $c = $cls ? " class=\"{$cls}\"" : '';
    echo "<p{$c}>" . htmlspecialchars($msg) . '</p>';
}

function run_sql_file(PDO $pdo, string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException("Cannot read {$path}");
    }
    // Remove line comments
    $lines = preg_split('/\R/', $sql) ?: [];
    $buf = '';
    foreach ($lines as $line) {
        if (preg_match('/^\s*--/', $line)) {
            continue;
        }
        $buf .= $line . "\n";
    }
    $parts = array_filter(array_map('trim', explode(';', $buf)));
    foreach ($parts as $stmt) {
        if ($stmt === '') continue;
        $pdo->exec($stmt);
    }
}

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OEMS Installer</title>
    <style>body{font-family:Manrope,system-ui,sans-serif;max-width:720px;margin:40px auto;padding:0 16px;line-height:1.55;color:#0b1220;background:#eef2f6}
    .card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:28px;box-shadow:0 8px 24px rgba(15,23,42,.06)}
    .ok{color:#047857}.err{color:#b91c1c}code{background:#f1f5f9;padding:2px 6px;border-radius:4px}
    a.btn{display:inline-block;margin-top:12px;background:#0c4a6e;color:#fff;text-decoration:none;padding:10px 16px;border-radius:10px;font-weight:600}</style></head><body><div class="card"><h1>OEMS Installer</h1>';
}

$host = getenv('OEMS_DB_HOST') ?: '127.0.0.1';
$port = getenv('OEMS_DB_PORT') ?: '3306';
$user = getenv('OEMS_DB_USER') ?: 'root';
$pass = getenv('OEMS_DB_PASS') ?: '';

try {
    $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    out("Connected to MySQL at {$host}:{$port}", $isCli, 'ok');

    run_sql_file($pdo, __DIR__ . '/database/schema.sql');
    out('Database schema imported.', $isCli, 'ok');

    // Seed via include (uses config/database.php which expects DB to exist)
    ob_start();
    require __DIR__ . '/database/seed.php';
    $seedOut = ob_get_clean();
    out('Demo data seeded.', $isCli, 'ok');
    if ($isCli) {
        echo $seedOut;
    }

    if (!is_dir(__DIR__ . '/storage')) {
        mkdir(__DIR__ . '/storage', 0755, true);
    }
    if (!is_dir(__DIR__ . '/backups')) {
        mkdir(__DIR__ . '/backups', 0755, true);
    }
    file_put_contents(__DIR__ . '/storage/installed.lock', date('c'));

    out('Installation complete.', $isCli, 'ok');
    out('Demo password for all users: password123', $isCli);
    out('Accounts: admin | teacher1 | student1', $isCli);

    if (!$isCli) {
        echo '<a class="btn" href="auth/login.php">Open login</a>';
        echo '<p class="err" style="margin-top:18px">For security, delete or restrict <code>install.php</code> after setup.</p>';
    }
} catch (Throwable $e) {
    out('ERROR: ' . $e->getMessage(), $isCli, 'err');
    if (!$isCli) {
        echo '<p>Ensure MySQL is running (XAMPP/WAMP) and credentials in <code>config/app.php</code> are correct.</p>';
    }
    if (!$isCli) echo '</div></body></html>';
    exit(1);
}

if (!$isCli) {
    echo '</div></body></html>';
}
