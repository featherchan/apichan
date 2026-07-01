<?php

namespace App\Addons\apichan\controllers;

use App\Helpers\ApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Addons\apichan\chat\ApichanRemoteServerChat;
use App\Addons\apichan\services\ExternalApiClient;

class RemoteServerController
{
    private function currentUserId(Request $request): int
    {
        return (int) ($request->attributes->get('user')['id'] ?? 0);
    }

    private function makeClient(array $record): ExternalApiClient
    {
        return new ExternalApiClient(
            $record['source_type'],
            $record['source_url'],
            $record['api_key'],
            (int) $record['timeout']
        );
    }

    private function getRecord(int $id, int $userId): ?array
    {
        return ApichanRemoteServerChat::getByIdAndUser($id, $userId);
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $userId  = $this->currentUserId($request);
        $servers = ApichanRemoteServerChat::getByUserId($userId);
        $result  = array_map(fn ($r) => [
            'id'                       => $r['id'],
            'source_id'                => $r['source_id'],
            'source_name'              => $r['source_name'],
            'source_type'              => $r['source_type'],
            'remote_server_id'         => $r['remote_server_id'],
            'remote_server_identifier' => $r['remote_server_identifier'],
            'name'                     => $r['name'],
            'created_at'               => $r['created_at'],
        ], $servers);
        return ApiResponse::success(['servers' => $result], 'Remote servers retrieved', 200);
    }

    public function add(Request $request): Response
    {
        $userId = $this->currentUserId($request);
        if ($userId === 0) {
            return ApiResponse::error('Authentication required', 'AUTH_REQUIRED', 401);
        }
        $data     = json_decode($request->getContent(), true);
        $sourceId = (int) ($data['source_id'] ?? 0);
        $serverId = trim((string) ($data['remote_server_id'] ?? ''));
        $name     = trim((string) ($data['name'] ?? ''));
        $ident    = trim((string) ($data['remote_server_identifier'] ?? '')) ?: null;
        if ($sourceId === 0 || $serverId === '' || $name === '') {
            return ApiResponse::error('source_id, remote_server_id, and name are required', 'MISSING_FIELD', 400);
        }
        $id = ApichanRemoteServerChat::create([
            'user_id'                  => $userId,
            'source_id'                => $sourceId,
            'remote_server_id'         => $serverId,
            'remote_server_identifier' => $ident,
            'name'                     => $name,
        ]);
        if ($id === false) {
            return ApiResponse::error('Failed to add remote server', 'CREATE_FAILED', 500);
        }
        return ApiResponse::success(['id' => $id], 'Remote server added', 201);
    }

    public function remove(Request $request, int $id): Response
    {
        $userId = $this->currentUserId($request);
        $ok = ApichanRemoteServerChat::delete($id, $userId);
        if (!$ok) {
            return ApiResponse::error('Not found', 'NOT_FOUND', 404);
        }
        return ApiResponse::success(null, 'Remote server removed', 200);
    }

    // ── Proxy helpers ──────────────────────────────────────────────────────────

    private function withClient(Request $request, int $id, callable $fn): Response
    {
        $userId = $this->currentUserId($request);
        $record = $this->getRecord($id, $userId);
        if ($record === null) {
            return ApiResponse::error('Remote server not found', 'NOT_FOUND', 404);
        }
        try {
            $client = $this->makeClient($record);
            $result = $fn($client, $record);
            return ApiResponse::success($result, 'OK', 200);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'EXTERNAL_API_ERROR', 502);
        }
    }

    // ── Control endpoints ──────────────────────────────────────────────────────

    public function status(Request $request, int $id): Response
    {
        return $this->withClient($request, $id, function (ExternalApiClient $client, array $r) {
            return ['resources' => $client->getResources($r['remote_server_id'], $r['remote_server_identifier'])];
        });
    }

    public function power(Request $request, int $id): Response
    {
        $data   = json_decode($request->getContent(), true);
        $action = trim((string) ($data['action'] ?? ''));
        if (!in_array($action, ['start', 'stop', 'restart', 'kill'], true)) {
            return ApiResponse::error('action must be start|stop|restart|kill', 'INVALID_ACTION', 400);
        }
        return $this->withClient($request, $id, function (ExternalApiClient $client, array $r) use ($action) {
            $client->powerAction($r['remote_server_id'], $r['remote_server_identifier'], $action);
            return ['action' => $action];
        });
    }

    public function command(Request $request, int $id): Response
    {
        $data    = json_decode($request->getContent(), true);
        $command = trim((string) ($data['command'] ?? ''));
        if ($command === '') {
            return ApiResponse::error('command is required', 'MISSING_FIELD', 400);
        }
        return $this->withClient($request, $id, function (ExternalApiClient $client, array $r) use ($command) {
            $client->sendCommand($r['remote_server_id'], $r['remote_server_identifier'], $command);
            return ['sent' => true];
        });
    }

    public function files(Request $request, int $id): Response
    {
        $dir = (string) ($request->query->get('directory') ?? '/');
        return $this->withClient($request, $id, function (ExternalApiClient $client, array $r) use ($dir) {
            return ['files' => $client->listFiles($r['remote_server_id'], $r['remote_server_identifier'], $dir)];
        });
    }

    public function fileContent(Request $request, int $id): Response
    {
        $file = (string) ($request->query->get('file') ?? '');
        if ($file === '') {
            return ApiResponse::error('file query param is required', 'MISSING_FIELD', 400);
        }
        $userId = $this->currentUserId($request);
        $record = $this->getRecord($id, $userId);
        if ($record === null) {
            return ApiResponse::error('Remote server not found', 'NOT_FOUND', 404);
        }
        try {
            $content = $this->makeClient($record)->getFileContents($record['remote_server_id'], $record['remote_server_identifier'], $file);
            return ApiResponse::success(['content' => $content], 'OK', 200);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'EXTERNAL_API_ERROR', 502);
        }
    }

    public function writeFile(Request $request, int $id): Response
    {
        $data    = json_decode($request->getContent(), true);
        $file    = trim((string) ($data['file'] ?? ''));
        $content = (string) ($data['content'] ?? '');
        if ($file === '') {
            return ApiResponse::error('file is required', 'MISSING_FIELD', 400);
        }
        return $this->withClient($request, $id, function (ExternalApiClient $client, array $r) use ($file, $content) {
            $client->writeFile($r['remote_server_id'], $r['remote_server_identifier'], $file, $content);
            return ['saved' => true];
        });
    }

    public function allocations(Request $request, int $id): Response
    {
        return $this->withClient($request, $id, function (ExternalApiClient $client, array $r) {
            return ['allocations' => $client->listAllocations($r['remote_server_id'], $r['remote_server_identifier'])];
        });
    }

    public function schedules(Request $request, int $id): Response
    {
        return $this->withClient($request, $id, function (ExternalApiClient $client, array $r) {
            return ['schedules' => $client->listSchedules($r['remote_server_id'], $r['remote_server_identifier'])];
        });
    }

    public function listBackups(Request $request, int $id): Response
    {
        return $this->withClient($request, $id, function (ExternalApiClient $client, array $r) {
            return ['backups' => $client->listBackups($r['remote_server_id'], $r['remote_server_identifier'])];
        });
    }

    public function createBackup(Request $request, int $id): Response
    {
        return $this->withClient($request, $id, function (ExternalApiClient $client, array $r) {
            return ['backup' => $client->createBackup($r['remote_server_id'], $r['remote_server_identifier'])];
        });
    }

    public function deleteBackup(Request $request, int $id, string $backupId): Response
    {
        return $this->withClient($request, $id, function (ExternalApiClient $client, array $r) use ($backupId) {
            $client->deleteBackup($r['remote_server_id'], $r['remote_server_identifier'], $backupId);
            return ['deleted' => true];
        });
    }

    public function databases(Request $request, int $id): Response
    {
        return $this->withClient($request, $id, function (ExternalApiClient $client, array $r) {
            return ['databases' => $client->listDatabases($r['remote_server_id'], $r['remote_server_identifier'])];
        });
    }

    public function deleteFiles(Request $request, int $id): Response
    {
        $data  = json_decode($request->getContent(), true);
        $root  = (string) ($data['root'] ?? '/');
        $files = (array) ($data['files'] ?? []);
        if (empty($files)) {
            return ApiResponse::error('files is required', 'MISSING_FIELD', 400);
        }
        return $this->withClient($request, $id, function (ExternalApiClient $client, array $r) use ($root, $files) {
            $client->deleteFiles($r['remote_server_id'], $r['remote_server_identifier'], $root, $files);
            return ['deleted' => true];
        });
    }

    public function renameFile(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);
        $root = (string) ($data['root'] ?? '/');
        $from = trim((string) ($data['from'] ?? ''));
        $to   = trim((string) ($data['to'] ?? ''));
        if ($from === '' || $to === '') {
            return ApiResponse::error('from and to are required', 'MISSING_FIELD', 400);
        }
        return $this->withClient($request, $id, function (ExternalApiClient $client, array $r) use ($root, $from, $to) {
            $client->renameFile($r['remote_server_id'], $r['remote_server_identifier'], $root, $from, $to);
            return ['renamed' => true];
        });
    }

    public function createFolder(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);
        $root = (string) ($data['root'] ?? '/');
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return ApiResponse::error('name is required', 'MISSING_FIELD', 400);
        }
        return $this->withClient($request, $id, function (ExternalApiClient $client, array $r) use ($root, $name) {
            $client->createFolder($r['remote_server_id'], $r['remote_server_identifier'], $root, $name);
            return ['created' => true];
        });
    }

    public function compressFiles(Request $request, int $id): Response
    {
        $data  = json_decode($request->getContent(), true);
        $root  = (string) ($data['root'] ?? '/');
        $files = (array) ($data['files'] ?? []);
        if (empty($files)) {
            return ApiResponse::error('files is required', 'MISSING_FIELD', 400);
        }
        return $this->withClient($request, $id, function (ExternalApiClient $client, array $r) use ($root, $files) {
            return ['file' => $client->compressFiles($r['remote_server_id'], $r['remote_server_identifier'], $root, $files)];
        });
    }

    public function decompressFile(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);
        $root = (string) ($data['root'] ?? '/');
        $file = trim((string) ($data['file'] ?? ''));
        if ($file === '') {
            return ApiResponse::error('file is required', 'MISSING_FIELD', 400);
        }
        return $this->withClient($request, $id, function (ExternalApiClient $client, array $r) use ($root, $file) {
            $client->decompressFile($r['remote_server_id'], $r['remote_server_identifier'], $root, $file);
            return ['decompressed' => true];
        });
    }

    public function getStartup(Request $request, int $id): Response
    {
        return $this->withClient($request, $id, function (ExternalApiClient $client, array $r) {
            return $client->getStartup($r['remote_server_id'], $r['remote_server_identifier']);
        });
    }

    public function updateStartupVariable(Request $request, int $id): Response
    {
        $data  = json_decode($request->getContent(), true);
        $key   = trim((string) ($data['key'] ?? ''));
        $value = (string) ($data['value'] ?? '');
        if ($key === '') {
            return ApiResponse::error('key is required', 'MISSING_FIELD', 400);
        }
        return $this->withClient($request, $id, function (ExternalApiClient $client, array $r) use ($key, $value) {
            return $client->updateStartupVariable($r['remote_server_id'], $r['remote_server_identifier'], $key, $value);
        });
    }

    public function websocket(Request $request, int $id): Response
    {
        return $this->withClient($request, $id, function (ExternalApiClient $client, array $r) {
            return $client->getWebsocket($r['remote_server_id'], $r['remote_server_identifier']);
        });
    }
}
