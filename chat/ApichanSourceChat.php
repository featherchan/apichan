<?php

namespace App\Addons\apichan\chat;

use App\Chat\Database;

class ApichanSourceChat
{
    private static string $table = '`featherpanel_apichan_sources`';

    public static function getAll(?int $createdBy = null): array
    {
        $pdo = Database::getPdoConnection();
        $sql = 'SELECT `id`, `name`, `type`, `url`, `timeout`, `created_by`, `created_at`, `updated_at` FROM ' . self::$table . ' WHERE `deleted` = \'false\'';
        $params = [];

        if ($createdBy !== null) {
            $sql .= ' AND `created_by` = :created_by';
            $params['created_by'] = $createdBy;
        }

        $sql .= ' ORDER BY `created_at` DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function getById(int $id, ?int $createdBy = null): ?array
    {
        $pdo = Database::getPdoConnection();
        $sql = 'SELECT * FROM ' . self::$table . ' WHERE `id` = :id AND `deleted` = \'false\'';
        $params = ['id' => $id];

        if ($createdBy !== null) {
            $sql .= ' AND `created_by` = :created_by';
            $params['created_by'] = $createdBy;
        }

        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function create(array $data): int|false
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO ' . self::$table . ' (`name`, `type`, `url`, `api_key`, `timeout`, `created_by`) VALUES (:name, :type, :url, :api_key, :timeout, :created_by)'
        );

        if ($stmt->execute([
            'name'       => $data['name'],
            'type'       => $data['type'],
            'url'        => $data['url'],
            'api_key'    => $data['api_key'],
            'timeout'    => $data['timeout'] ?? 15,
            'created_by' => $data['created_by'],
        ])) {
            return (int) $pdo->lastInsertId();
        }

        return false;
    }

    public static function update(int $id, array $data, ?int $createdBy = null): bool
    {
        $pdo = Database::getPdoConnection();
        $set = [];
        $params = ['id' => $id];

        foreach (['name', 'type', 'url', 'api_key', 'timeout'] as $field) {
            if (isset($data[$field])) {
                $set[] = "`$field` = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($set)) {
            return false;
        }

        $where = 'WHERE `id` = :id AND `deleted` = \'false\'';
        if ($createdBy !== null) {
            $where .= ' AND `created_by` = :created_by';
            $params['created_by'] = $createdBy;
        }

        $stmt = $pdo->prepare('UPDATE ' . self::$table . ' SET ' . implode(', ', $set) . ' ' . $where);

        return $stmt->execute($params);
    }

    public static function delete(int $id, ?int $createdBy = null): bool
    {
        $pdo = Database::getPdoConnection();
        $sql = 'UPDATE ' . self::$table . ' SET `deleted` = \'true\' WHERE `id` = :id';
        $params = ['id' => $id];

        if ($createdBy !== null) {
            $sql .= ' AND `created_by` = :created_by';
            $params['created_by'] = $createdBy;
        }

        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }
}
