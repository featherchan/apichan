<?php

namespace App\Addons\apichan\chat;

use App\Chat\Database;

class ApichanRemoteServerChat
{
    private static string $table = 'featherpanel_apichan_remote_servers';

    public static function getByUserId(int $userId): array
    {
        $pdo  = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'SELECT r.*, s.name AS source_name, s.type AS source_type, s.url AS source_url, s.api_key, s.timeout
             FROM ' . self::$table . ' r
             JOIN featherpanel_apichan_sources s ON s.id = r.source_id
             WHERE r.user_id = :user_id AND s.deleted = \'false\'
             ORDER BY r.created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function getByIdAndUser(int $id, int $userId): ?array
    {
        $pdo  = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'SELECT r.*, s.name AS source_name, s.type AS source_type, s.url AS source_url, s.api_key, s.timeout
             FROM ' . self::$table . ' r
             JOIN featherpanel_apichan_sources s ON s.id = r.source_id
             WHERE r.id = :id AND r.user_id = :user_id AND s.deleted = \'false\'
             LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function create(array $data): int|false
    {
        $pdo  = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO ' . self::$table . ' (user_id, source_id, remote_server_id, remote_server_identifier, name)
             VALUES (:user_id, :source_id, :remote_server_id, :remote_server_identifier, :name)'
        );
        if ($stmt->execute([
            'user_id'                  => $data['user_id'],
            'source_id'                => $data['source_id'],
            'remote_server_id'         => $data['remote_server_id'],
            'remote_server_identifier' => $data['remote_server_identifier'] ?? null,
            'name'                     => $data['name'],
        ])) {
            return (int) $pdo->lastInsertId();
        }
        return false;
    }

    public static function delete(int $id, int $userId): bool
    {
        $pdo  = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'DELETE FROM ' . self::$table . ' WHERE id = :id AND user_id = :user_id'
        );
        return $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }
}
