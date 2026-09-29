#!/usr/bin/env bash
# Lokaler Testserver: SQLite in web/.htdata/dev.sqlite, Adresse http://127.0.0.1:8765
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
export TW_ENV=dev
exec "$PHP_BIN" -S 127.0.0.1:8765 -t "$ROOT/web" "$ROOT/tools/dev-router.php"
