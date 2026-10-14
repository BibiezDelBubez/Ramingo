<?php
declare(strict_types=1);

namespace Ramingo\Core;

use PDO;

/**
 * Wrapper sottile su PDO/SQLite. Tutte le query passano da qui,
 * sempre con parametri preparati (niente concatenazione di input utente).
 */
final class Database
{
    private PDO $pdo;
    private ?bool $fts5 = null;

    public function __construct(string $file)
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $this->pdo = new PDO('sqlite:' . $file, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // WAL: letture concorrenti degli utenti mentre la sync scrive.
        $this->pdo->exec('PRAGMA journal_mode = WAL');
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA busy_timeout = 5000');
    }

    public function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** Prima colonna di tutte le righe. */
    public function column(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function value(string $sql, array $params = []): mixed
    {
        $v = $this->run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public function exec(string $sql): void
    {
        $this->pdo->exec($sql);
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /** Esegue $fn in transazione; rollback automatico in caso di eccezione. */
    public function transaction(callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $fn($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * INSERT ... ON CONFLICT DO UPDATE generico, usato da tutti i repository (DRY).
     * $keys       = colonne che identificano la riga.
     * $insertOnly = colonne scritte solo alla prima INSERT (es. first_seen_at).
     */
    public function upsert(string $table, array $row, array $keys, array $insertOnly = []): void
    {
        $cols    = array_keys($row);
        $place   = array_map(static fn ($c) => ':' . $c, $cols);
        $updates = array_map(static fn ($c) => "$c = excluded.$c", array_diff($cols, $keys, $insertOnly));
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) ON CONFLICT (%s) DO %s',
            $table,
            implode(', ', $cols),
            implode(', ', $place),
            implode(', ', $keys),
            $updates ? 'UPDATE SET ' . implode(', ', $updates) : 'NOTHING'
        );
        $this->run($sql, $row);
    }

    /** "?, ?, ?" per clausole IN. */
    public static function placeholders(array $values): string
    {
        return implode(', ', array_fill(0, max(1, count($values)), '?'));
    }

    public function hasFts5(): bool
    {
        if ($this->fts5 === null) {
            try {
                $this->pdo->exec('CREATE VIRTUAL TABLE IF NOT EXISTS temp._fts5_probe USING fts5(x)');
                $this->pdo->exec('DROP TABLE IF EXISTS temp._fts5_probe');
                $this->fts5 = true;
            } catch (\PDOException) {
                $this->fts5 = false;
            }
        }
        return $this->fts5;
    }
}
