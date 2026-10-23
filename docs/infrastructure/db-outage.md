# Авария БД: статическая страница + healthcheck + Telegram админам

При недоступности удалённой MySQL (`DB_HOST`, сейчас `178.250.157.140`) кабинет не может отдать нормальные страницы. Решение не зависит от Laravel schedule (он сам ходит в БД).

## Что есть

| Компонент | Путь |
|-----------|------|
| Страница для пользователей | `public/outage.html` |
| Флаг включения | `storage/app/outage/ENABLED` |
| Nginx snippet | `scripts/nginx-cabinet-outage.inc` |
| Вкл/выкл вручную | `scripts/outage-enable.sh` / `outage-disable.sh` |
| Healthcheck | `scripts/db-healthcheck.php` |
| Кэш chat_id админов | `storage/app/outage/admin_telegram_chats.json` |

## Разовая установка на prod (app-сервер)

```bash
cd /var/www/cabinet_titl_usr/data/www/cabinet.titlo.ru
# после git pull:
sudo bash scripts/outage-enable.sh --install-nginx-only   # прописывает if (-f ENABLED) в includes + reload nginx
chmod +x scripts/outage-enable.sh scripts/outage-disable.sh scripts/db-healthcheck.php
```

Cron от **system** (не `schedule:run`):

```cron
* * * * * cabinet_titl_usr /opt/php85/bin/php /var/www/cabinet_titl_usr/data/www/cabinet.titlo.ru/scripts/db-healthcheck.php >> /var/www/cabinet_titl_usr/data/www/cabinet.titlo.ru/storage/logs/db-healthcheck.log 2>&1
```

## Поведение healthcheck

1. `SELECT 1` к БД из `.env` (таймаут ~5 с).
2. Пока БД жива — обновляет список Telegram chat_id пользователей с ролями `admin` / `Super Admin` (бот подключён).
3. После `OUTAGE_FAIL_THRESHOLD` (по умолчанию 2) подряд неудач:
   - создаёт `ENABLED` → nginx отдаёт `outage.html` (без reload);
   - шлёт Telegram админам (кэш + `OUTAGE_TELEGRAM_CHAT_IDS`).
4. При восстановлении — снимает флаг и шлёт «БД снова доступна».

## .env

```env
# Опционально: запасные chat_id на случай пустого кэша (через запятую)
OUTAGE_TELEGRAM_CHAT_IDS=
# Порог подряд неудачных проверок перед включением страницы
OUTAGE_FAIL_THRESHOLD=2
# Прокси для Telegram, если не задан — берётся TELEGRAM_PROXY
# OUTAGE_TELEGRAM_PROXY=socks5h://user:pass@host:port
```

Пока БД лежит, список админов из БД нечитаем — поэтому кэш нужно хотя бы раз успеть заполнить на живой БД, либо прописать `OUTAGE_TELEGRAM_CHAT_IDS`.

## Локально (php -S / без nginx)

При ошибке соединения с БД Exception Handler отдаёт тот же `outage.html` и ставит флаг `ENABLED`
(чтобы следующие запросы не ждали таймаут). После восстановления БД снимите флаг:

```bash
rm -f storage/app/outage/ENABLED
# или
bash scripts/outage-disable.sh
```
