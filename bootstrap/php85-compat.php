<?php

/**
 * Laravel 6 + старый vendor на PHP 8.1+ сыпят E_DEPRECATED (nullable params, ArrayAccess),
 * а HandleExceptions превращает их в ErrorException → 500.
 * На локалке (и любом PHP ≥ 8.1) глушим deprecations до boot.
 * Прод сейчас на 7.4 — файл безвреден.
 */
if (PHP_VERSION_ID >= 80100) {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
}
