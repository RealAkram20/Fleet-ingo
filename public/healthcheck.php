<?php

/*
 * Standalone deployment health check for InGo Fleet Log.
 * -----------------------------------------------------------------------------
 * A plain PHP file that does NOT boot Laravel, so it still answers when the app
 * itself is dying during bootstrap (the blank "500 / 0 bytes" case). It reports
 * exactly which folders are not writable, whether the required PHP extensions
 * are present, and whether the database connects.
 *
 * Reach it at:  https://fleet.ingo.co.zw/healthcheck.php
 *
 * SECURITY: this exposes environment facts, so DELETE IT once the site is up.
 * To gate it meanwhile, set HEALTHCHECK_TOKEN=something in .env and then visit
 * /healthcheck.php?token=something. It never prints the DB password or APP_KEY.
 * -----------------------------------------------------------------------------
 */

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

// Buffer everything so the HTTP status can be set from the result, after the
// checks have run but before a single byte goes to the client.
ob_start();

$root = dirname(__DIR__);

/* --- minimal .env reader (Laravel is not available here) ------------------- */
function read_env(string $path): array
{
    $env = [];
    if (! is_file($path) || ! is_readable($path)) {
        return $env;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = ltrim($line);
        if ($line === '' || $line[0] === '#' || ! str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // strip one layer of matching quotes
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
            $value = substr($value, 1, -1);
        }
        $env[$key] = $value;
    }
    return $env;
}

$env = read_env($root.'/.env');

/* --- optional token gate --------------------------------------------------- */
$token = $env['HEALTHCHECK_TOKEN'] ?? '';
if ($token !== '' && (($_GET['token'] ?? '') !== $token)) {
    http_response_code(403);
    echo "Health check is token-protected. Append ?token=... to the URL.\n";
    exit;
}

$fail = 0;
$line = str_repeat('-', 70);

// A hard check: failing one means the site cannot serve, so it sets HTTP 500.
function ok(string $label, bool $pass, string $detail = ''): void
{
    global $fail;
    if (! $pass) {
        $fail++;
    }
    printf("  [%s] %-42s %s\n", $pass ? 'OK' : 'XX', $label, $detail);
}

// An advisory: worth flagging (e.g. debug left on) but not a reason to 500.
function note(string $label, bool $good, string $detail = ''): void
{
    printf("  [%s] %-42s %s\n", $good ? 'OK' : '..', $label, $detail);
}

echo "InGo Fleet Log — deployment health check\n";
echo "$line\n";
echo 'When:   '.date('Y-m-d H:i:s')."\n";
echo 'Root:   '.$root."\n";
echo 'DocRoot:'.($_SERVER['DOCUMENT_ROOT'] ?? '?')."\n";
echo "$line\n";

/* --- PHP ------------------------------------------------------------------- */
echo "PHP\n";
ok('Version >= 8.2', PHP_VERSION_ID >= 80200, PHP_VERSION);
foreach (['pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'gd', 'ctype', 'tokenizer', 'xml', 'dom', 'bcmath'] as $ext) {
    ok("ext: $ext", extension_loaded($ext));
}
echo "$line\n";

/* --- .env ------------------------------------------------------------------ */
echo ".env\n";
ok('.env exists and is readable', is_file($root.'/.env') && is_readable($root.'/.env'));
ok('APP_KEY is set', ! empty($env['APP_KEY']), empty($env['APP_KEY']) ? 'MISSING — run key:generate' : 'present');
note('APP_DEBUG is off', ($env['APP_DEBUG'] ?? 'false') === 'false', $env['APP_DEBUG'] ?? '(unset)');
note('APP_URL is https', str_starts_with($env['APP_URL'] ?? '', 'https://'), $env['APP_URL'] ?? '(unset)');
echo "$line\n";

/* --- writable folders (actually try to write) ------------------------------ */
echo "Writable folders  (the usual cause of a blank 500)\n";
$dirs = [
    'bootstrap/cache',
    'storage',
    'storage/framework',
    'storage/framework/cache',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
    'public/branding',
];
foreach ($dirs as $rel) {
    $dir = $root.'/'.$rel;
    if (! is_dir($dir)) {
        ok($rel, false, 'MISSING — create it');
        continue;
    }
    $probe = $dir.'/.hc_'.bin2hex(random_bytes(4));
    $wrote = @file_put_contents($probe, 'x') !== false;
    if ($wrote) {
        @unlink($probe);
    }
    ok($rel, $wrote, $wrote ? 'writable' : 'NOT writable — chmod 755 (or 775)');
}
echo "$line\n";

/* --- database -------------------------------------------------------------- */
echo "Database\n";
$driver = $env['DB_CONNECTION'] ?? 'mysql';
$host = $env['DB_HOST'] ?? '127.0.0.1';
$port = $env['DB_PORT'] ?? '3306';
$name = $env['DB_DATABASE'] ?? '';
$user = $env['DB_USERNAME'] ?? '';
$pass = $env['DB_PASSWORD'] ?? '';
echo "  Target: {$user}@{$host}:{$port}/{$name}  (password ".($pass === '' ? 'EMPTY' : 'set, '.strlen($pass).' chars').")\n";

if ($driver !== 'mysql') {
    ok('Connection', false, "DB_CONNECTION is '$driver', expected mysql");
} else {
    try {
        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$name}",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
        );
        ok('Connection', true, 'connected');

        $have = [];
        foreach ($pdo->query('SHOW TABLES') as $r) {
            $have[strtolower($r[0])] = true;
        }
        foreach (['users', 'settings', 'sessions', 'cache', 'migrations', 'bikes', 'riders', 'readings'] as $t) {
            ok("table: $t", isset($have[$t]), isset($have[$t]) ? '' : 'MISSING — import the SQL');
        }
        if (isset($have['users'])) {
            $n = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            ok('has at least one account', $n > 0, "$n user(s)");
        }
    } catch (Throwable $e) {
        // Never echo the raw DSN/password; the message alone is enough to diagnose.
        ok('Connection', false, 'FAILED: '.$e->getMessage());
    }
}
echo "$line\n";

if ($fail === 0) {
    echo "RESULT: ALL HARD CHECKS PASSED. Any [..] lines above are advisories.\n";
    echo "Delete this file now (public/healthcheck.php).\n";
} else {
    echo "RESULT: $fail check(s) failed — fix the [XX] lines above, then reload.\n";
    echo "Once the site is up, DELETE this file (public/healthcheck.php).\n";
}

// Nothing has been sent yet (everything is buffered), so the status still sets.
if ($fail > 0) {
    http_response_code(500);
}
ob_end_flush();
