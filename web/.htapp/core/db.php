<?php
/*
 * Datenbank. Auf dem Server MySQL (CloudPanel), lokal SQLite. Die Abfragen sind
 * so gehalten, dass sie auf beiden laufen: Zeiten als Unix-Sekunden, Texte in
 * UTF-8, Namen und Adressen klein gespeichert statt Kollationen zu vertrauen.
 * Wo sich die Dialekte unterscheiden (Upsert, DDL), entscheidet db_driver().
 */

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = config('db');
    if (($c['driver'] ?? 'mysql') === 'sqlite') {
        ensure_data_dir();
        $pdo = new PDO('sqlite:' . $c['path'], null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
    } else {
        $pass = (string) ($c['pass'] ?? '');
        if ($pass === '' || $pass === 'REPLACE_WITH_DB_PASSWORD') {
            throw new SetupMissing('Datenbank-Passwort fehlt in .htdata/config.php');
        }
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], (int) $c['port'], $c['name']);
        $pdo = new PDO($dsn, (string) $c['user'], $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
    }
    db_migrate($pdo);
    return $pdo;
}

function db_driver(): string
{
    return (string) config('db.driver', 'mysql') === 'sqlite' ? 'sqlite' : 'mysql';
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute(array_values($params));
    return $st;
}

function q1(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function qall(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function qval(string $sql, array $params = []): mixed
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

/** Tabellen- und Spaltennamen kommen nur aus dem Code, nie von aussen. */
function db_ident(string $name): string
{
    if (!preg_match('/^[a-z_][a-z0-9_]*$/', $name)) {
        throw new InvalidArgumentException('Ungueltiger Bezeichner: ' . $name);
    }
    return $name;
}

function db_insert(string $table, array $row): int
{
    $cols = array_map('db_ident', array_keys($row));
    $sql = 'INSERT INTO ' . db_ident($table) . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')';
    q($sql, array_values($row));
    return (int) db()->lastInsertId();
}

function db_update(string $table, array $set, string $where, array $params = []): int
{
    $parts = [];
    foreach (array_keys($set) as $col) {
        $parts[] = db_ident($col) . ' = ?';
    }
    $sql = 'UPDATE ' . db_ident($table) . ' SET ' . implode(', ', $parts) . ' WHERE ' . $where;
    return q($sql, array_merge(array_values($set), $params))->rowCount();
}

/**
 * Einfuegen oder aktualisieren. $update: Spalte => SQL-Ausdruck mit "old." fuer
 * den alten Wert, oder null fuer "neuen Wert uebernehmen".
 * Beispiel: ['hits' => 'old.hits + 1']
 */
function db_upsert(string $table, array $row, array $keys, array $update): void
{
    $t = db_ident($table);
    $cols = array_map('db_ident', array_keys($row));
    $place = implode(', ', array_fill(0, count($row), '?'));
    $sets = [];
    if (db_driver() === 'sqlite') {
        foreach ($update as $col => $expr) {
            $e = $expr === null ? 'excluded.' . db_ident($col) : str_replace('old.', $t . '.', $expr);
            $sets[] = db_ident($col) . ' = ' . $e;
        }
        $sql = "INSERT INTO $t (" . implode(', ', $cols) . ") VALUES ($place) ON CONFLICT(" . implode(', ', array_map('db_ident', $keys)) . ') DO UPDATE SET ' . implode(', ', $sets);
    } else {
        foreach ($update as $col => $expr) {
            $e = $expr === null ? 'VALUES(' . db_ident($col) . ')' : str_replace('old.', $t . '.', $expr);
            $sets[] = db_ident($col) . ' = ' . $e;
        }
        $sql = "INSERT INTO $t (" . implode(', ', $cols) . ") VALUES ($place) ON DUPLICATE KEY UPDATE " . implode(', ', $sets);
    }
    q($sql, array_values($row));
}

function db_tx(callable $fn): mixed
{
    $pdo = db();
    if ($pdo->inTransaction()) {
        return $fn();
    }
    $pdo->beginTransaction();
    try {
        $out = $fn();
        $pdo->commit();
        return $out;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Migrationen aus .htapp/migrations, je Version eine Datei pro Dialekt:
 * 001_init.mysql.sql und 001_init.sqlite.sql. Laeuft nur, wenn eine fehlt.
 */
function db_migrate(PDO $pdo): void
{
    $driver = db_driver();
    $files = glob(TW_APP . '/migrations/*.' . $driver . '.sql') ?: [];
    sort($files);
    $versions = [];
    foreach ($files as $f) {
        $versions[(int) basename($f)] = $f;
    }
    if (!$versions) {
        return;
    }
    $latest = max(array_keys($versions));

    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version INTEGER NOT NULL PRIMARY KEY, applied_at BIGINT NOT NULL)');
    $done = (int) $pdo->query('SELECT COALESCE(MAX(version), 0) FROM schema_migrations')->fetchColumn();
    if ($done >= $latest) {
        return;
    }

    $locked = false;
    if ($driver === 'mysql') {
        $locked = (bool) $pdo->query("SELECT GET_LOCK('thewall_migrate', 20)")->fetchColumn();
    }
    try {
        $done = (int) $pdo->query('SELECT COALESCE(MAX(version), 0) FROM schema_migrations')->fetchColumn();
        foreach ($versions as $v => $file) {
            if ($v <= $done) {
                continue;
            }
            $sql = (string) file_get_contents($file);
            foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $stmt) {
                $stmt = trim(preg_replace('/^\s*--.*$/m', '', $stmt));
                if ($stmt !== '') {
                    $pdo->exec($stmt);
                }
            }
            $st = $pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)');
            $st->execute([$v, time()]);
        }
    } finally {
        if ($locked) {
            $pdo->query("SELECT RELEASE_LOCK('thewall_migrate')");
        }
    }
}
