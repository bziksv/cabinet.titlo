#!/usr/bin/env php
<?php
/**
 * Standalone DB healthcheck for cabinet.titlo.ru (no Laravel boot required).
 *
 * - SELECT 1 to DB from .env
 * - On failure streak: enable static outage page + Telegram to cached admin chat_ids
 * - On recovery: disable outage + Telegram recovery
 * - When DB is up: refresh admin Telegram chat cache (roles admin / Super Admin)
 *
 * Cron (system, not Laravel schedule — schedule itself needs DB):
 *   * * * * * cabinet_titl_usr /opt/php85/bin/php /var/www/.../scripts/db-healthcheck.php >>.../storage/logs/db-healthcheck.log 2>&1
 *
 * Manual:
 *   php scripts/db-healthcheck.php
 *   php scripts/db-healthcheck.php --force-refresh-chats
 */

$appRoot = dirname(__DIR__);
$outageDir = $appRoot . '/storage/app/outage';
$flagFile = $outageDir . '/ENABLED';
$stateFile = $outageDir . '/state.json';
$chatsFile = $outageDir . '/admin_telegram_chats.json';
$proxiesFile = $outageDir . '/telegram_proxies.json';
$logPrefix = '[' . date('Y-m-d H:i:s') . '] ';

if (!is_dir($outageDir)) {
    @mkdir($outageDir, 0775, true);
}

$env = loadEnv($appRoot . '/.env');
$forceRefresh = in_array('--force-refresh-chats', $argv, true);
$failThreshold = max(1, (int) ($env['OUTAGE_FAIL_THRESHOLD'] ?? 2));
$botToken = trim((string) ($env['TELEGRAM_BOT_TOKEN'] ?? ''));

$ok = false;
$error = '';
$latencyMs = null;
$t0 = microtime(true);
$prevTimeout = ini_get('default_socket_timeout');
ini_set('default_socket_timeout', '5');
try {
    $pdo = pdoFromEnv($env);
    $pdo->query('SELECT 1');
    $ok = true;
    $latencyMs = (int) round((microtime(true) - $t0) * 1000);
} catch (Throwable $e) {
    $error = $e->getMessage();
    $latencyMs = (int) round((microtime(true) - $t0) * 1000);
} finally {
    if ($prevTimeout !== false) {
        ini_set('default_socket_timeout', (string) $prevTimeout);
    }
}

$state = readJson($stateFile, [
    'status' => 'unknown',
    'fail_streak' => 0,
    'last_ok_at' => null,
    'last_fail_at' => null,
    'last_notify_at' => null,
    'outage_enabled' => false,
]);

if ($ok) {
    $state['fail_streak'] = 0;
    $state['last_ok_at'] = gmdate('c');
    $wasDown = ($state['status'] === 'down') || file_exists($flagFile);

    // Refresh admin chat + telegram proxy cache while DB is healthy.
    try {
        $chats = refreshAdminChats($pdo, $env, $chatsFile);
        $proxies = refreshTelegramProxies($pdo, $env, $proxiesFile);
        echo $logPrefix . "OK {$latencyMs}ms; admin_chats=" . count($chats) . "; proxies=" . count($proxies) . "\n";
    } catch (Throwable $e) {
        echo $logPrefix . "OK {$latencyMs}ms; cache refresh failed: " . $e->getMessage() . "\n";
    }

    if ($wasDown) {
        @unlink($flagFile);
        $state['status'] = 'up';
        $state['outage_enabled'] = false;
        $msg = "🟢 <b>Titlo БД снова доступна</b>\n"
            . "Хост: <code>" . htmlspecialchars((string) ($env['DB_HOST'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</code>\n"
            . "Проверка: {$latencyMs} мс\n"
            . "Страница техработ выключена.";
        $sent = notifyAdmins($botToken, resolveChatIds($env, $chatsFile), $msg, resolveProxies($env, $proxiesFile));
        $state['last_notify_at'] = gmdate('c');
        $state['last_notify_status'] = 'up';
        echo $logPrefix . "RECOVERY: outage disabled; telegram=" . ($sent ? 'ok' : 'fail') . "\n";
    } else {
        $state['status'] = 'up';
        $state['outage_enabled'] = false;
    }

    if ($forceRefresh) {
        echo $logPrefix . "force refresh done\n";
    }

    writeJson($stateFile, $state);
    exit(0);
}

// DB down
$state['fail_streak'] = (int) ($state['fail_streak'] ?? 0) + 1;
$state['last_fail_at'] = gmdate('c');
$state['status'] = 'down';
echo $logPrefix . "FAIL streak={$state['fail_streak']}/{$failThreshold} {$latencyMs}ms: {$error}\n";

if ($state['fail_streak'] >= $failThreshold) {
    $already = file_exists($flagFile);
    $needNotify = empty($state['last_notify_at'])
        || (($state['last_notify_status'] ?? '') !== 'down');
    if (!$already) {
        file_put_contents($flagFile, gmdate('c') . "\n" . $error . "\n");
        @chmod($flagFile, 0664);
        $state['outage_enabled'] = true;
        echo $logPrefix . "OUTAGE ENABLED\n";
        $needNotify = true;
    } else {
        $state['outage_enabled'] = true;
        echo $logPrefix . "outage already enabled\n";
    }
    if ($needNotify) {
        $msg = "🔴 <b>Titlo БД недоступна</b>\n"
            . "Хост: <code>" . htmlspecialchars((string) ($env['DB_HOST'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</code>\n"
            . "Ошибка: <code>" . htmlspecialchars(mb_substr($error, 0, 300), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</code>\n"
            . "Включена статическая страница техработ на cabinet.titlo.ru\n"
            . "Проверок подряд: {$state['fail_streak']}";
        $sent = notifyAdmins($botToken, resolveChatIds($env, $chatsFile), $msg, resolveProxies($env, $proxiesFile));
        if ($sent) {
            $state['last_notify_at'] = gmdate('c');
            $state['last_notify_status'] = 'down';
            echo $logPrefix . "admins notified\n";
        } else {
            echo $logPrefix . "admins NOT notified (no chats or all telegram fails)\n";
        }
    }
}

writeJson($stateFile, $state);
exit(1);

// --- helpers ---

function loadEnv(string $path): array
{
    if (!is_readable($path)) {
        throw new RuntimeException('.env not readable: ' . $path);
    }
    $out = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if ($v !== '' && (($v[0] === '"' && substr($v, -1) === '"') || ($v[0] === "'" && substr($v, -1) === "'"))) {
            $v = substr($v, 1, -1);
        }
        $out[$k] = $v;
    }

    return $out;
}

function pdoFromEnv(array $env): PDO
{
    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $port = $env['DB_PORT'] ?? '3306';
    $db = $env['DB_DATABASE'] ?? '';
    $user = $env['DB_USERNAME'] ?? '';
    $pass = $env['DB_PASSWORD'] ?? '';
    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    // mysqlnd may ignore ATTR_TIMEOUT for connect; ini helps a bit
    return $pdo;
}

function readJson(string $path, array $default): array
{
    if (!is_readable($path)) {
        return $default;
    }
    $data = json_decode((string) file_get_contents($path), true);

    return is_array($data) ? array_merge($default, $data) : $default;
}

function writeJson(string $path, array $data): void
{
    file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
}

/**
 * @return list<array{chat_id:string,email:?string,name:?string}>
 */
function refreshAdminChats(PDO $pdo, array $env, string $chatsFile): array
{
    $modelType = 'App\\User';
    $sql = "
        SELECT DISTINCT u.id, u.email, u.name, u.chat_id
        FROM users u
        INNER JOIN model_has_roles mhr
            ON mhr.model_id = u.id
            AND mhr.model_type = ?
        INNER JOIN roles r ON r.id = mhr.role_id
        WHERE r.name IN ('admin', 'Super Admin')
          AND u.telegram_bot_active = 1
          AND u.chat_id IS NOT NULL
          AND CAST(u.chat_id AS CHAR) <> ''
          AND CAST(u.chat_id AS CHAR) <> '0'
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$modelType]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $chats = [];
    foreach ($rows as $row) {
        $chats[] = [
            'chat_id' => (string) $row['chat_id'],
            'email' => $row['email'] ?? null,
            'name' => $row['name'] ?? null,
            'user_id' => (int) ($row['id'] ?? 0),
        ];
    }
    foreach (parseChatIdList($env['OUTAGE_TELEGRAM_CHAT_IDS'] ?? '') as $id) {
        $exists = false;
        foreach ($chats as $c) {
            if ($c['chat_id'] === $id) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $chats[] = ['chat_id' => $id, 'email' => null, 'name' => 'env', 'user_id' => 0];
        }
    }
    writeJson($chatsFile, [
        'updated_at' => gmdate('c'),
        'chats' => $chats,
    ]);

    return $chats;
}

/**
 * @return list<string>
 */
function resolveChatIds(array $env, string $chatsFile): array
{
    $ids = parseChatIdList($env['OUTAGE_TELEGRAM_CHAT_IDS'] ?? '');
    $cached = readJson($chatsFile, ['chats' => []]);
    foreach ($cached['chats'] as $c) {
        if (!empty($c['chat_id'])) {
            $ids[] = (string) $c['chat_id'];
        }
    }

    return array_values(array_unique($ids));
}

/**
 * @return list<string>
 */
function parseChatIdList(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }
    $parts = preg_split('/[\s,;]+/', $raw) ?: [];
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '' && preg_match('/^-?\d+$/', $p)) {
            $out[] = $p;
        }
    }

    return $out;
}

/**
 * @return list<string>
 */
function refreshTelegramProxies(PDO $pdo, array $env, string $proxiesFile): array
{
    $urls = [];
    try {
        $stmt = $pdo->query('SELECT url FROM telegram_proxies WHERE enabled = 1 ORDER BY priority ASC, id ASC');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $u = trim((string) ($row['url'] ?? ''));
            if ($u !== '') {
                $urls[] = $u;
            }
        }
    } catch (Throwable $e) {
        // table may differ — keep env proxies
    }
    foreach ([$env['OUTAGE_TELEGRAM_PROXY'] ?? '', $env['TELEGRAM_PROXY'] ?? ''] as $extra) {
        $extra = trim((string) $extra);
        if ($extra !== '' && !in_array($extra, $urls, true)) {
            $urls[] = $extra;
        }
    }
    writeJson($proxiesFile, [
        'updated_at' => gmdate('c'),
        'proxies' => $urls,
    ]);

    return $urls;
}

/**
 * @return list<string>
 */
function resolveProxies(array $env, string $proxiesFile): array
{
    $urls = [];
    $cached = readJson($proxiesFile, ['proxies' => []]);
    foreach ($cached['proxies'] as $u) {
        $u = trim((string) $u);
        if ($u !== '') {
            $urls[] = $u;
        }
    }
    foreach ([$env['OUTAGE_TELEGRAM_PROXY'] ?? '', $env['TELEGRAM_PROXY'] ?? ''] as $extra) {
        $extra = trim((string) $extra);
        if ($extra !== '' && !in_array($extra, $urls, true)) {
            $urls[] = $extra;
        }
    }
    // empty string = try direct as last resort
    $urls[] = '';

    return array_values(array_unique($urls));
}

/**
 * @param list<string> $chatIds
 * @param list<string> $proxies
 */
function notifyAdmins(string $botToken, array $chatIds, string $html, array $proxies = ['']): bool
{
    if ($botToken === '' || $chatIds === []) {
        fwrite(STDERR, "notify skipped: token empty or no chat ids (set OUTAGE_TELEGRAM_CHAT_IDS or refresh cache while DB up)\n");

        return false;
    }
    if ($proxies === []) {
        $proxies = [''];
    }
    $any = false;
    foreach ($chatIds as $chatId) {
        $ok = false;
        foreach ($proxies as $proxy) {
            if (telegramSend($botToken, $chatId, $html, $proxy)) {
                $ok = true;
                echo '  telegram chat ' . $chatId . ': ok via ' . ($proxy !== '' ? 'proxy' : 'direct') . "\n";
                break;
            }
        }
        if (!$ok) {
            echo '  telegram chat ' . $chatId . ": fail (all proxies)\n";
        }
        $any = $any || $ok;
    }

    return $any;
}

function telegramSend(string $botToken, string $chatId, string $html, string $proxy = ''): bool
{
    $url = 'https://api.telegram.org/bot' . $botToken . '/sendMessage';
    $payload = http_build_query([
        'chat_id' => $chatId,
        'text' => $html,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => 1,
    ]);
    if (!function_exists('curl_init')) {
        return false;
    }
    $ch = curl_init($url);
    $opts = [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
    ];
    if ($proxy !== '') {
        $opts[CURLOPT_PROXY] = $proxy;
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $code >= 200 && $code < 300;
}
