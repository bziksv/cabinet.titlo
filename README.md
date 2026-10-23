# cabinet.titlo.ru (Laravel / кабинет)

Локальная папка workspace: **`cabinet.datagon.ru`** (рядом с маркетингом **`datagon.ru`** / **titlo**).

**Git (целевой):** [github.com/bziksv/cabinet.titlo](https://github.com/bziksv/cabinet.titlo)  
**Legacy:** [cabinet.datagon.ru](https://github.com/bziksv/cabinet.datagon.ru)

Деплой и серверы — в маркетинг-репо:

- **[cabinet-titlo-deploy.md](../titlo.ru/docs/cabinet-titlo-deploy.md)** — `cabinet.titlo.ru`, порт **3004**
- [cabinet-servers.md](../datagon.ru/docs/cabinet-servers.md) · [cabinet-deploy.md](../datagon.ru/docs/cabinet-deploy.md) (legacy)

**Prod deploy:** `./scripts/deploy-titlo.sh` (git pull + cache + **supervisor restart**)

**Инфраструктура VPS (RAM/OOM):** [docs/infrastructure/s3-prime-ltd-memory.md](docs/infrastructure/s3-prime-ltd-memory.md)

| | Legacy | Titlo (целевой) |
|---|--------|-----------------|
| Домен | cabinet.datagon.ru / lk.redbox.su | **cabinet.titlo.ru** |
| VPS | `155.212.171.103` | тот же |
| Путь | `/var/www/cabinet_data_usr/.../cabinet.datagon.ru` | `/var/www/cabinet_titl_usr/.../cabinet.titlo.ru` |
| Порт | **3002** | **3004** |
| БД | `178.250.157.140` (пока общая) | та же |

## Локальный запуск (Mac)

1. `.env` с VPS (имя файла **`.env`**, не `env`).
2. PHP **8.5**: локалка `/opt/homebrew/opt/php@8.5/bin`, прод `/opt/php85/bin/php`. Откат локалки: `CABINET_PHP_PREFIX=/opt/homebrew/opt/php@7.4 ./scripts/dev-local.sh`.
3. `composer install --no-dev`
4. `./scripts/dev-local.sh` → http://localhost:3002

В `.env`: **`DB_HOST=178.250.157.140`**, **`APP_URL=http://localhost:3002`**.

Маркетинг titlo: `npm run dev:titlo` → :3003, ссылки на кабинет `:3002`.

## Эталон UI

http://localhost:3002/html/ (AdminLTE 4) — см. [cabinet-reference.md](../datagon.ru/docs/cabinet-reference.md).
