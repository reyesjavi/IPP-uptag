<?php
// models/Model.php — Clase base para acceso a datos

abstract class Model
{
    protected PDO $pdo;
    protected string $table      = '';
    protected string $primaryKey = 'id';

    public function __construct()
    {
        $this->pdo = getDB();
    }

    public function findById(int $id): array|false
    {
        return $this->row(
            "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1",
            [':id' => $id]
        );
    }

    public function findAll(string $where = '', array $params = [], string $order = ''): array
    {
        $sql = "SELECT * FROM {$this->table}";
        if ($where) $sql .= " WHERE $where";
        if ($order) $sql .= " ORDER BY $order";
        return $this->query($sql, $params);
    }

    public function delete(int $id): bool
    {
        return $this->execute(
            "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id",
            [':id' => $id]
        );
    }

    // ── Helpers internos ─────────────────────────────────────

    protected function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    protected function row(string $sql, array $params = []): array|false
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    protected function scalar(string $sql, array $params = []): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    protected function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    protected function lastId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }
}
