# Site Audit — Nu Html Checker (vnu) для режима HTML5

Чекер **HTML5 (Nu)** в форме запуска шлёт HTML страниц на локальный
[Nu Html Checker](https://github.com/validator/validator) (тот же движок, что
[validator.w3.org/nu](https://validator.w3.org/nu/)).

Без этого сервиса в UI опция HTML5 **disabled**, краулы идут через **libxml**.

## Env

```env
SITE_AUDIT_VNU_URL=http://127.0.0.1:8877/
# опционально:
# SITE_AUDIT_HTML_CHECKER=html5
# SITE_AUDIT_VNU_TIMEOUT=8
# SITE_AUDIT_VNU_CONNECT_TIMEOUT=2
# SITE_AUDIT_VNU_MAX_BYTES=1500000
```

Ставить на **той же машине, где крутятся `site_audit` воркеры** (сейчас cabinet;
proxy2 — позже по этапности).

> На prod cabinet порт **8888 занят nginx (fastpanel)** — используем **8877**.

## Установка (кратко)

1. Java 17+.
2. Скачать `vnu.jar` или runtime-image с
   [releases validator](https://github.com/validator/validator/releases).
3. HTTP-сервис (пример; стек **≥ 2m**, иначе StackOverflowError на схемах):

```bash
java -Xss2m -Xmx768m -cp /opt/vnu/vnu.jar nu.validator.servlet.Main 8877
```

Проверка:

```bash
curl -sS -H 'Content-Type: text/html; charset=utf-8' \
  --data-binary '<!doctype html><title>t</title><p>ok' \
  'http://127.0.0.1:8877/?out=json' | head
```

## Supervisor (пример unit)

Файл вроде `/etc/supervisor/conf.d/cabinet-titlo-vnu.conf`:

```ini
[program:cabinet-titlo-vnu]
command=/usr/lib/jvm/java-17-openjdk-amd64/bin/java -Xss2m -Xmx768m -cp /opt/vnu/vnu.jar nu.validator.servlet.Main 8877
directory=/opt/vnu
user=root
autostart=true
autorestart=true
startsecs=8
stdout_logfile=/var/log/supervisor/cabinet-titlo-vnu.log
stderr_logfile=/var/log/supervisor/cabinet-titlo-vnu.err.log
```

После старта: `SITE_AUDIT_VNU_URL=http://127.0.0.1:8877/` в `.env` кабинета → `config:cache` →
`queue:restart` / `supervisorctl restart cabinet-titlo-site-audit:*`.

## Поведение краула

- Выбор `html_checker=html5|libxml` в settings краула.
- HTML5: только сообщения Nu уровня **error/fatal** (не info/warning).
- Если vnu недоступен при parse → **fallback на libxml** + `checker_fallback` в meta finding.
- Не вызывать `java -jar` на каждую страницу: только long-running servlet.
