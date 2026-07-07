# VPS `155.212.171.103` (s3.prime-ltd.su) — память и MySQL

Документ фиксирует инцидент **07.07.2026** (зависание → ручной reboot) и меры по RAM/MySQL на shared VPS FastPanel.

**Связанные сервисы на этом хосте:** cabinet.titlo.ru, cabinet.datagon.ru, medmarket.su (Bitrix), p.datagon.ru, prime-ltd.su и др.

**БД titlo:** удалённая `178.250.157.140` (`lk_redbox_su_db`). Локальный MySQL на VPS — для других tenant'ов, но **всё равно резервирует RAM** (buffer pool).

---

## Железо (на момент аудита 07.07.2026)

| Параметр | Значение |
|----------|----------|
| CPU | 8 vCPU (AMD EPYC 7763) |
| RAM | 9.6 GB |
| Swap | 4 GB **zram** (после reboot быстро заполнялся) |
| Диск | 140 GB, ~84% занято |
| MySQL datadir | ~14 GB на диске (~9 GB данных) |
| MySQL binlog | ~31 GB в `/var/log/mysql/` (319 файлов) |

---

## Симптомы и причина

1. **Хронический OOM** — kernel journal неоднократно убивал локальный `mysqld` (май–июль 2026).
2. **07.07 ~01:04 MSK** — зависание VPS (~9 мин без логов) → **ручной reboot** (не auto-recovery хостера).
3. Titlo: gap в `supervisor-position.log`, deadlock'и на таблице `jobs` после старта.

**Корень:** на 10 GB RAM одновременно:

- локальный MySQL с **innodb_buffer_pool_size = 4G**;
- **54** supervisor worker'а cabinet.titlo (удалённая БД, но RAM на app-сервере);
- php-cgi нескольких сайтов (Bitrix, prime, titlo…);
- Node (p.datagon).

Номинально **8+ GB** только под MySQL + PHP/workers → swap thrashing → OOM или hard hang.

---

## Кто потребляет память (ориентир)

| Компонент | RSS (ориентир) |
|-----------|----------------|
| mysqld (локальный) | ~1 GB runtime, **4 GB buffer pool** (до правки) |
| supervisor titlo | ~53 процесса, **~1.2 GB** |
| php-cgi (все сайты) | **~1.2 GB** |
| прочее (nginx, node, spamd…) | **~0.5–1 GB** |

---

## MySQL: конфликт конфигов (FastPanel)

Два слоя:

- `/etc/mysql/conf.d/mysql.cnf` — ручные правки (Bitrix/datagon, комментарии про OOM).
- `/etc/mysql/my.cnf.fastpanel/99-fastpanel.cnf` — **побеждает** (подключается последним из `/etc/mysql/my.cnf`).

| Параметр | mysql.cnf | 99-fastpanel.cnf | Факт до 07.07 |
|----------|-----------|------------------|---------------|
| innodb_buffer_pool_size | 4G | 4G | 4G |
| log-bin | disable_log_bin | log-bin ON | **binlog ON** |
| bind-address | 127.0.0.1 | * | **слушает снаружи** |
| wait_timeout | 28800 | 28800 | 8 ч |

**Править production:** только `99-fastpanel.cnf` (бэкап перед изменениями).

---

## Применённые изменения (07.07.2026)

| Параметр | Было | Стало | Зачем |
|----------|------|-------|-------|
| `innodb-buffer-pool-size` | 4G | **2G** | ~2 GB RAM для PHP/workers/ОС |
| `innodb-buffer-pool-instances` | 4 | **2** | под размер pool |
| `wait_timeout` | 28800 (8 ч) | **3600 (1 ч)** | меньше «висящих» conn от PHP/cron |
| `interactive_timeout` | 28800 | **3600** | в паре с wait_timeout |

После правок: `systemctl restart mysql` (краткий даун локальных БД, titlo remote DB не затронута).

Бэкап конфига: `/etc/mysql/my.cnf.fastpanel/99-fastpanel.cnf.bak.20260707`

---

## Supervisor titlo (54 процесса) — справка

```
default: 6, child_cluster: 6, main_cluster: 3, cluster_wait: 6,
position: 6, relevance: 6, monitoring_helper: 5,
monitoring_change: 2, monitoring_wait: 2, competitors_stat: 1,
competitor_analyse: 5, ai_generation: 5, websockets: 1
```

Все worker'ы — `queue:work database` → нагрузка на **178.250.157.140**, RAM только на app-VPS.

---

## Предложения (не применены / backlog)

### P0 — высокий приоритет

1. **Purge binlog** — ~25–30 GB диска (`PURGE BINARY LOGS` или `RESET MASTER` если репликации нет); `binlog-expire-logs-seconds = 172800` (2 дня).
2. **Supervisor titlo 54 → 24–30** — position/relevance/cluster/default по 3, ai/competitor по 2 (экономия ~600–800 MB, меньше deadlock на `jobs`).
3. **bind-address = 127.0.0.1** — titlo локальный MySQL не использует.

### P1 — средний

4. **RAM 16 GB** или file swap 4–8 GB (zram — симптом, не решение).
5. **Свести mysql.cnf** — один источник правды (`99-fastpanel.cnf`).
6. **Slow query log** — отключить `log_queries_not_using_indexes` на Bitrix или поднять `long_query_time`.

### P2 — архитектура

7. Throttle `AutoUpdateMonitoringPositions` / parse queue.
8. Вынести titlo workers или medmarket/datagon на отдельный VPS.
9. Ротация supervisor-логов, бэкапы FastPanel (диск 84%).

### Оценка RAM после полного P0

| | До | После P0 |
|--|-----|----------|
| MySQL buffer pool | 4 GB | 2 GB ✓ |
| Titlo workers | 1.2 GB | ~0.6 GB |
| PHP-CGI | 1.2 GB | 1.2 GB |
| Итого | ~7.4 GB | ~4.8 GB запас |

---

## Полезные команды

```bash
# RAM / swap
free -h && swapon --show

# Топ по памяти
ps aux --sort=-%mem | head -20

# MySQL
mysql -e "SHOW VARIABLES WHERE Variable_name IN ('innodb_buffer_pool_size','wait_timeout','max_connections');"
mysql -e "SHOW GLOBAL STATUS LIKE 'Threads_connected';"

# OOM в journal
journalctl -k | grep -i "out of memory" | tail -20

# Размер binlog
du -sh /var/log/mysql/
```

---

*Обновлено: 07.07.2026*
