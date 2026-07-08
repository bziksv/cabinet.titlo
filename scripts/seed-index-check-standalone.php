<?php

/**
 * Применить миграцию index-check без Laravel (PHP 8+ локально).
 * Запуск: php scripts/seed-index-check-standalone.php
 */

$root = dirname(__DIR__);

foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if ($line[0] === '#' || strpos($line, '=') === false) {
        continue;
    }
    [$k, $v] = explode('=', $line, 2);
    $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
}

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = $_ENV['DB_PORT'] ?? '3306';
$db = $_ENV['DB_DATABASE'] ?? '';
$user = $_ENV['DB_USERNAME'] ?? '';
$pass = $_ENV['DB_PASSWORD'] ?? '';

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

const HTTP_HEADERS_PROJECT_ID = 11;
const MIGRATION = '2026_07_08_200000_add_index_check_module';
const TARIFF_LIMITS = [
    'Free' => 5,
    'Optimal' => 600,
    'Ultimate' => 1500,
    'Maximum' => 2400,
];

function tableExists(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare('SHOW TABLES LIKE ?');
    $st->execute([$table]);

    return (bool) $st->fetchColumn();
}

function migrationDone(PDO $pdo): bool
{
    if (! tableExists($pdo, 'migrations')) {
        return false;
    }
    $st = $pdo->prepare('SELECT 1 FROM migrations WHERE migration = ? LIMIT 1');
    $st->execute([MIGRATION]);

    return (bool) $st->fetchColumn();
}

function positionsContainId(array $positions, int $searchId): bool
{
    foreach ($positions as $item) {
        if (isset($item[0]) && is_array($item[0]) && ! empty($item[0]['dir'])) {
            foreach ($item as $entry) {
                if (isset($entry['id']) && (int) $entry['id'] === $searchId) {
                    return true;
                }
            }
            continue;
        }
        if (isset($item['id']) && (int) $item['id'] === $searchId) {
            return true;
        }
    }

    return false;
}

function insertAfterIdInPositions(array $positions, int $afterId, int $newId, bool &$changed): array
{
    $result = [];
    foreach ($positions as $item) {
        if (isset($item[0]) && is_array($item[0]) && ! empty($item[0]['dir'])) {
            $group = [];
            $groupChanged = false;
            foreach ($item as $entry) {
                if (isset($entry['dir'])) {
                    $group[] = $entry;
                    continue;
                }
                $group[] = $entry;
                if (isset($entry['id']) && (int) $entry['id'] === $afterId) {
                    $group[] = ['id' => $newId];
                    $groupChanged = true;
                    $changed = true;
                }
            }
            if (count($group) > 1) {
                $result[] = $group;
            } elseif ($groupChanged) {
                $result[] = $group;
            }
            continue;
        }
        $result[] = $item;
        if (isset($item['id']) && (int) $item['id'] === $afterId) {
            $result[] = ['id' => $newId];
            $changed = true;
        }
    }

    return $result;
}

if (migrationDone($pdo)) {
    echo "Migration already applied.\n";
    exit(0);
}

try {
    if (! tableExists($pdo, 'index_check_usages')) {
        $pdo->exec(<<<'SQL'
CREATE TABLE `index_check_usages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `period` varchar(7) NOT NULL,
  `used` int unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `index_check_usages_user_id_period_unique` (`user_id`,`period`),
  KEY `index_check_usages_period_index` (`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        echo "Created index_check_usages\n";
    }

    $st = $pdo->query("SELECT id FROM tariff_settings WHERE code = 'IndexCheck' LIMIT 1");
    $settingId = $st->fetchColumn();
    if (! $settingId && tableExists($pdo, 'tariff_settings')) {
        $now = date('Y-m-d H:i:s');
        $pdo->prepare(
            'INSERT INTO tariff_settings (name, code, description, message, created_at, updated_at) VALUES (?,?,?,?,?,?)'
        )->execute([
            'Проверка индексации (лимит в месяц)',
            'IndexCheck',
            '1 URL в одной поисковой системе = 1 лимит. Яндекс и Google считаются отдельно.',
            'Лимит проверок индексации исчерпан ({VALUE} в месяц). Увеличьте тариф или дождитесь нового периода.',
            $now,
            $now,
        ]);
        $settingId = $pdo->lastInsertId();
        foreach (TARIFF_LIMITS as $tariff => $value) {
            $pdo->prepare(
                'INSERT INTO tariff_setting_values (tariff_setting_id, tariff, value, sort, created_at, updated_at) VALUES (?,?,?,?,?,?)'
            )->execute([$settingId, $tariff, $value, 500, $now, $now]);
        }
        echo "Seeded tariff IndexCheck\n";
    }

    $st = $pdo->query("SELECT id FROM main_projects WHERE link LIKE '%/index-check%' LIMIT 1");
    $projectId = $st->fetchColumn();
    if (! $projectId && tableExists($pdo, 'main_projects')) {
        $parent = $pdo->query('SELECT * FROM main_projects WHERE id = ' . HTTP_HEADERS_PROJECT_ID)->fetch(PDO::FETCH_ASSOC);
        if ($parent) {
            $now = date('Y-m-d H:i:s');
            $pdo->prepare(
                'INSERT INTO main_projects (`access`, `controller`, `color`, `title`, `description`, `link`, `icon`, `show`, `position`, `buttons`, `created_at`, `updated_at`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $parent['access'],
                "IndexCheckController@index\r\n",
                '#3d8bfd',
                'Index check',
                'Проверка индексации страницы в Яндексе и Google.',
                'https://lk.redbox.su/index-check',
                '<i class="fas fa-magnifying-glass-chart"></i>',
                1,
                ((int) $parent['position']) + 1,
                $parent['buttons'] ?? '[]',
                $now,
                $now,
            ]);
            $projectId = (int) $pdo->lastInsertId();
            echo "Created main_projects id={$projectId}\n";
        }
    } else {
        $projectId = (int) $projectId;
        echo "main_projects already exists id={$projectId}\n";
    }

    $st = $pdo->query("SELECT id FROM permissions WHERE name = 'Index check' LIMIT 1");
    $permId = $st->fetchColumn();
    if (! $permId && tableExists($pdo, 'permissions')) {
        $now = date('Y-m-d H:i:s');
        $pdo->prepare('INSERT INTO permissions (name, guard_name, created_at, updated_at) VALUES (?,?,?,?)')
            ->execute(['Index check', 'web', $now, $now]);
        $permId = (int) $pdo->lastInsertId();
        echo "Created permission Index check id={$permId}\n";
    } else {
        $permId = (int) $permId;
    }

    if ($permId && tableExists($pdo, 'role_has_permissions')) {
        $httpPermId = $pdo->query("SELECT id FROM permissions WHERE name = 'Http headers' LIMIT 1")->fetchColumn();
        if ($httpPermId) {
            $roles = $pdo->query(
                'SELECT role_id FROM role_has_permissions WHERE permission_id = ' . (int) $httpPermId
            )->fetchAll(PDO::FETCH_COLUMN);
            foreach ($roles as $roleId) {
                $chk = $pdo->prepare('SELECT 1 FROM role_has_permissions WHERE permission_id = ? AND role_id = ?');
                $chk->execute([$permId, $roleId]);
                if (! $chk->fetchColumn()) {
                    $pdo->prepare('INSERT INTO role_has_permissions (permission_id, role_id) VALUES (?,?)')
                        ->execute([$permId, $roleId]);
                }
            }
            echo 'Assigned permission to ' . count($roles) . " roles\n";
        }
    }

    if ($projectId && tableExists($pdo, 'menu_items_position')) {
        $rows = $pdo->query('SELECT id, positions FROM menu_items_position ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $updatedMenus = 0;
        foreach ($rows as $row) {
            if (empty($row['positions'])) {
                continue;
            }
            $positions = json_decode($row['positions'], true);
            if (! is_array($positions) || positionsContainId($positions, $projectId)) {
                continue;
            }
            $changed = false;
            $updated = insertAfterIdInPositions($positions, HTTP_HEADERS_PROJECT_ID, $projectId, $changed);
            if ($changed) {
                $pdo->prepare('UPDATE menu_items_position SET positions = ? WHERE id = ?')
                    ->execute([json_encode($updated), $row['id']]);
                $updatedMenus++;
            }
        }
        echo "Updated {$updatedMenus} user menu configs\n";
    }

    if (tableExists($pdo, 'migrations')) {
        $batch = (int) $pdo->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations')->fetchColumn();
        $pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (?,?)')->execute([MIGRATION, $batch]);
    }

    echo "Done. Open /index-check after clearing session (re-login).\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
