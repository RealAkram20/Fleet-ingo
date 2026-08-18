<?php

/*
 * Standalone deployment health check for InGo Fleet Log.
 * -----------------------------------------------------------------------------
 * A plain PHP file that first reports environment facts WITHOUT booting Laravel
 * (so it answers even when the app is dying), and then tries to boot Laravel and
 * handle a request, capturing the real exception — including fatals — so the
 * blank "500 / 0 bytes" case stops being a mystery.
 *
 * Reach it at:  https://fleet.ingo.co.zw/healthcheck.php
 *
 * SECURITY: this exposes environment facts, so DELETE IT once the site is up.
 * Gate it meanwhile with HEALTHCHECK_TOKEN=... in .env, then /healthcheck.php?token=...
 * It never prints the DB password or APP_KEY.
 * -----------------------------------------------------------------------------
 */

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

// Buffer everything so the HTTP status can be set from the result — and so a
// fatal during the Laravel-boot probe still flushes what we gathered.
ob_start();

$GLOBALS['hc_fail'] = 0;
$root = dirname(__DIR__);

// If a fatal kills us mid-probe, still emit the buffer and a clear note.
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        echo "\n  [XX] FATAL during Laravel boot:\n";
        echo '       '.$e['message']."\n";
        echo '       at '.$e['file'].':'.$e['line']."\n";
        echo "       => this is almost always an incomplete vendor/ upload. Re-upload vendor/.\n";
        $GLOBALS['hc_fail']++;
    }
    if ($GLOBALS['hc_fail'] > 0 && ! headers_sent()) {
        http_response_code(500);
    }
    echo "\n".str_repeat('-', 70)."\n";
    echo ($GLOBALS['hc_fail'] === 0)
        ? "RESULT: ALL HARD CHECKS PASSED. Delete this file (public/healthcheck.php).\n"
        : "RESULT: {$GLOBALS['hc_fail']} check(s) failed — fix the [XX] lines, then reload. Delete this file after.\n";
    ob_end_flush();
});

/* --- minimal .env reader (Laravel not loaded yet) -------------------------- */
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
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
            $value = substr($value, 1, -1);
        }
        $env[$key] = $value;
    }
    return $env;
}

$env = read_env($root.'/.env');

$token = $env['HEALTHCHECK_TOKEN'] ?? '';
if ($token !== '' && (($_GET['token'] ?? '') !== $token)) {
    http_response_code(403);
    echo "Health check is token-protected. Append ?token=... to the URL.\n";
    ob_end_flush();
    exit;
}

$line = str_repeat('-', 70);

function ok(string $label, bool $pass, string $detail = ''): void
{
    if (! $pass) {
        $GLOBALS['hc_fail']++;
    }
    printf("  [%s] %-42s %s\n", $pass ? 'OK' : 'XX', $label, $detail);
}
function note(string $label, bool $good, string $detail = ''): void
{
    printf("  [%s] %-42s %s\n", $good ? 'OK' : '..', $label, $detail);
}

echo "InGo Fleet Log — deployment health check\n$line\n";
echo 'When:   '.date('Y-m-d H:i:s')."\n";
echo 'Root:   '.$root."\n";
echo 'DocRoot:'.($_SERVER['DOCUMENT_ROOT'] ?? '?')."\n$line\n";

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
ok('APP_KEY is set', ! empty($env['APP_KEY']), empty($env['APP_KEY']) ? 'MISSING — key:generate' : 'present');
note('APP_DEBUG is off', ($env['APP_DEBUG'] ?? 'false') === 'false', $env['APP_DEBUG'] ?? '(unset)');
note('APP_URL is https', str_starts_with($env['APP_URL'] ?? '', 'https://'), $env['APP_URL'] ?? '(unset)');
echo "$line\n";

/* --- writable folders ------------------------------------------------------ */
echo "Writable folders\n";
foreach (['bootstrap/cache', 'storage', 'storage/framework', 'storage/framework/cache', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'public/branding'] as $rel) {
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

/* --- vendor completeness --------------------------------------------------- */
echo "vendor/\n";
$autoload = $root.'/vendor/autoload.php';
ok('vendor/autoload.php exists', is_file($autoload), is_file($autoload) ? '' : 'MISSING — re-upload vendor/');
$vendorFiles = null;
if (is_dir($root.'/vendor')) {
    $vendorFiles = iterator_count(new FilesystemIterator($root.'/vendor', FilesystemIterator::SKIP_DOTS));
    echo "  vendor/ top-level entries: $vendorFiles (a healthy install has dozens)\n";
}
echo "$line\n";

/* --- database -------------------------------------------------------------- */
echo "Database\n";
$host = $env['DB_HOST'] ?? '127.0.0.1';
$port = $env['DB_PORT'] ?? '3306';
$name = $env['DB_DATABASE'] ?? '';
$user = $env['DB_USERNAME'] ?? '';
$pass = $env['DB_PASSWORD'] ?? '';
echo "  Target: {$user}@{$host}:{$port}/{$name}  (password ".($pass === '' ? 'EMPTY' : 'set, '.strlen($pass).' chars').")\n";
try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name}", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
    ok('Connection', true, 'connected');
    $have = [];
    foreach ($pdo->query('SHOW TABLES') as $r) {
        $have[strtolower($r[0])] = true;
    }
    foreach (['users', 'settings', 'sessions', 'cache', 'migrations'] as $t) {
        ok("table: $t", isset($have[$t]), isset($have[$t]) ? '' : 'MISSING — import the SQL');
    }
} catch (Throwable $e) {
    ok('Connection', false, 'FAILED: '.$e->getMessage());
}
echo "$line\n";

/* --- newest Laravel log (the real error, if it logged one) ----------------- */
echo "Newest Laravel log\n";
$logs = glob($root.'/storage/logs/*.log') ?: [];
if (! $logs) {
    echo "  (no log files yet)\n";
} else {
    usort($logs, fn ($a, $b) => filemtime($b) <=> filemtime($a));
    $newest = $logs[0];
    echo '  '.basename($newest).' — last lines:'."\n";
    $tail = array_slice(file($newest, FILE_IGNORE_NEW_LINES), -18);
    foreach ($tail as $l) {
        echo '  | '.substr($l, 0, 200)."\n";
    }
}
echo "$line\n";

/* --- boot Laravel and handle /login, capturing the real exception ---------- */
echo "Laravel boot probe\n";
if (! is_file($autoload)) {
    ok('boot', false, 'no autoloader to boot with');
} else {
    require $autoload;
    // Interfaces need interface_exists; classes need class_exists. A missing one
    // means vendor did not upload completely.
    ok('class Illuminate\Foundation\Application', class_exists(\Illuminate\Foundation\Application::class), '');
    ok('iface Illuminate\Contracts\Http\Kernel', interface_exists(\Illuminate\Contracts\Http\Kernel::class), '');
    ok('class Dotenv\Dotenv', class_exists(\Dotenv\Dotenv::class), '');

    try {
        /** @var \Illuminate\Foundation\Application $app */
        $app = require $root.'/bootstrap/app.php';
        ok('bootstrap/app.php builds app', $app instanceof \Illuminate\Foundation\Application);

        // handle() runs the bootstrappers (env, config, providers, routing) — the
        // same path a real web request takes, so it reproduces the real failure.
        $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
        $request = \Illuminate\Http\Request::create(rtrim($env['APP_URL'] ?? 'http://localhost', '/').'/login', 'GET');
        $response = $kernel->handle($request);
        $code = $response->getStatusCode();
        ok('kernel handles /login', $code < 500, 'HTTP '.$code);

        if ($code >= 500) {
            echo "  -> /login returned $code; the exception is in the log lines above.\n";
        }
        $kernel->terminate($request, $response);
    } catch (Throwable $e) {
        ok('Laravel boot', false, get_class($e).': '.$e->getMessage());
        echo '       at '.$e->getFile().':'.$e->getLine()."\n";
    }
}
// The registered shutdown function prints the RESULT line and flushes.
