<?php

namespace App\Addons\apichan\services;

class ExternalApiClient
{
    private string $baseUrl;
    private string $apiKey;
    private string $type;
    private int $timeout;

    public function __construct(string $type, string $baseUrl, string $encryptedApiKey, int $timeout = 15)
    {
        $this->type    = $type;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey  = EncryptionHelper::decrypt($encryptedApiKey);
        $this->timeout = $timeout;
    }

    public function listServers(int $page = 1): array
    {
        return match ($this->type) {
            'pterodactyl'  => $this->pterodactylListServers($page),
            'featherpanel' => $this->featherpanelListServers($page),
            'pelican'      => $this->pelicanListServers($page),
            'calagopus'    => $this->calagopusListServers($page),
            default        => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function getServer(string|int $serverId): array
    {
        return match ($this->type) {
            'pterodactyl'  => $this->pterodactylGetServer($serverId),
            'featherpanel' => $this->featherpanelGetServer($serverId),
            'pelican'      => $this->pelicanGetServer($serverId),
            'calagopus'    => $this->calagopusGetServer($serverId),
            default        => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    // ── Remote control facade ──────────────────────────────────────────────────

    public function powerAction(string|int $serverId, ?string $identifier, string $action): void
    {
        match ($this->type) {
            'pterodactyl', 'pelican' => $this->clientPost(
                "/api/client/servers/{$this->clientId($serverId, $identifier)}/power",
                ['signal' => $action]
            ),
            'featherpanel' => $this->featherpanelPowerAction($serverId, $action),
            'calagopus'    => $this->calagopusClientPost(
                "/api/client/servers/{$this->clientId($serverId, $identifier)}/power",
                ['signal' => $action],
                "/api/admin/servers/{$serverId}/power",
                ['action' => $action]
            ),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function getResources(string|int $serverId, ?string $identifier): array
    {
        return match ($this->type) {
            'pterodactyl', 'pelican' => $this->normalizePterodactylResources(
                $this->request('GET', "/api/client/servers/{$this->clientId($serverId, $identifier)}/resources")
            ),
            'featherpanel' => $this->featherpanelGetResources($serverId),
            'calagopus'    => $this->normalizePterodactylResources(
                $this->calagopusClientGet(
                    "/api/client/servers/{$this->clientId($serverId, $identifier)}/resources",
                    "/api/admin/servers/{$serverId}/resources"
                )
            ),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function sendCommand(string|int $serverId, ?string $identifier, string $command): void
    {
        match ($this->type) {
            'pterodactyl', 'pelican' => $this->clientPost(
                "/api/client/servers/{$this->clientId($serverId, $identifier)}/command",
                ['command' => $command]
            ),
            'featherpanel' => $this->featherpanelSendCommand($serverId, $command),
            'calagopus'    => $this->calagopusClientPost(
                "/api/client/servers/{$this->clientId($serverId, $identifier)}/command",
                ['command' => $command],
                "/api/admin/servers/{$serverId}/command",
                ['command' => $command]
            ),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function listFiles(string|int $serverId, ?string $identifier, string $directory = '/'): array
    {
        return match ($this->type) {
            'pterodactyl', 'pelican' => $this->normalizePterodactylFiles(
                $this->request('GET', "/api/client/servers/{$this->clientId($serverId, $identifier)}/files/list?directory=" . urlencode($directory))
            ),
            'featherpanel' => $this->featherpanelListFiles($serverId, $directory),
            'calagopus'    => $this->normalizeCalagopusFiles(
                $this->request('GET', "/api/client/servers/{$this->clientId($serverId, $identifier)}/files/list?directory=" . urlencode($directory))
            ),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function getFileContents(string|int $serverId, ?string $identifier, string $file): string
    {
        return match ($this->type) {
            'pterodactyl', 'pelican' => $this->requestText('GET', "/api/client/servers/{$this->clientId($serverId, $identifier)}/files/contents?file=" . urlencode($file)),
            'featherpanel' => $this->featherpanelGetFileContents($serverId, $file),
            'calagopus'    => $this->requestText('GET', "/api/client/servers/{$this->clientId($serverId, $identifier)}/files/contents?file=" . urlencode($file)),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function writeFile(string|int $serverId, ?string $identifier, string $file, string $content): void
    {
        match ($this->type) {
            'pterodactyl', 'pelican', 'calagopus' => $this->requestRaw(
                'POST',
                "/api/client/servers/{$this->clientId($serverId, $identifier)}/files/write?file=" . urlencode($file),
                $content
            ),
            'featherpanel' => $this->featherpanelWriteFile($serverId, $file, $content),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function listAllocations(string|int $serverId, ?string $identifier): array
    {
        return match ($this->type) {
            'pterodactyl', 'pelican' => $this->normalizePterodactylAllocations(
                $this->request('GET', "/api/client/servers/{$this->clientId($serverId, $identifier)}/network/allocations")
            ),
            'featherpanel' => $this->featherpanelListAllocations($serverId),
            'calagopus'    => $this->normalizeCalagopusAllocations(
                $this->request('GET', "/api/client/servers/{$this->clientId($serverId, $identifier)}/allocations")
            ),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function listSchedules(string|int $serverId, ?string $identifier): array
    {
        return match ($this->type) {
            'pterodactyl', 'pelican' => $this->normalizePterodactylSchedules(
                $this->request('GET', "/api/client/servers/{$this->clientId($serverId, $identifier)}/schedules")
            ),
            'featherpanel' => $this->featherpanelListSchedules($serverId),
            'calagopus'    => $this->normalizePterodactylSchedules(
                $this->calagopusClientGet(
                    "/api/client/servers/{$this->clientId($serverId, $identifier)}/schedules",
                    null
                )
            ),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function listBackups(string|int $serverId, ?string $identifier): array
    {
        return match ($this->type) {
            'pterodactyl', 'pelican' => $this->normalizePterodactylBackups(
                $this->request('GET', "/api/client/servers/{$this->clientId($serverId, $identifier)}/backups")
            ),
            'featherpanel' => $this->featherpanelListBackups($serverId),
            'calagopus'    => $this->normalizePterodactylBackups(
                $this->calagopusClientGet(
                    "/api/client/servers/{$this->clientId($serverId, $identifier)}/backups",
                    null
                )
            ),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function createBackup(string|int $serverId, ?string $identifier): array
    {
        return match ($this->type) {
            'pterodactyl', 'pelican' => $this->normalizePterodactylBackupItem(
                $this->requestPost("/api/client/servers/{$this->clientId($serverId, $identifier)}/backups", [])
            ),
            'featherpanel' => $this->featherpanelCreateBackup($serverId),
            'calagopus'    => $this->normalizePterodactylBackupItem(
                $this->calagopusClientPost(
                    "/api/client/servers/{$this->clientId($serverId, $identifier)}/backups",
                    [],
                    null,
                    null,
                    true
                )
            ),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function deleteBackup(string|int $serverId, ?string $identifier, string $backupId): void
    {
        match ($this->type) {
            'pterodactyl', 'pelican', 'calagopus' => $this->requestDelete(
                "/api/client/servers/{$this->clientId($serverId, $identifier)}/backups/{$backupId}"
            ),
            'featherpanel' => $this->featherpanelDeleteBackup($serverId, $backupId),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function deleteFiles(string|int $serverId, ?string $identifier, string $root, array $files): void
    {
        $cid = $this->clientId($serverId, $identifier);
        match ($this->type) {
            'pterodactyl', 'pelican', 'calagopus' => $this->requestPost(
                "/api/client/servers/{$cid}/files/delete",
                ['root' => $root, 'files' => $files]
            ),
            'featherpanel' => $this->requestPost(
                "/api/user/servers/{$serverId}/files/delete",
                ['root' => $root, 'files' => $files]
            ),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function renameFile(string|int $serverId, ?string $identifier, string $root, string $from, string $to): void
    {
        $cid = $this->clientId($serverId, $identifier);
        match ($this->type) {
            'pterodactyl', 'pelican', 'calagopus' => $this->clientPut(
                "/api/client/servers/{$cid}/files/rename",
                ['root' => $root, 'files' => [['from' => $from, 'to' => $to]]]
            ),
            'featherpanel' => $this->clientPut(
                "/api/user/servers/{$serverId}/files/rename",
                ['root' => $root, 'files' => [['from' => $from, 'to' => $to]]]
            ),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function createFolder(string|int $serverId, ?string $identifier, string $root, string $name): void
    {
        $cid = $this->clientId($serverId, $identifier);
        match ($this->type) {
            'pterodactyl', 'pelican' => $this->requestPost(
                "/api/client/servers/{$cid}/files/create-folder",
                ['root' => $root, 'name' => $name]
            ),
            'calagopus' => $this->requestPost(
                "/api/client/servers/{$cid}/files/create-directory",
                ['root' => $root, 'name' => $name]
            ),
            'featherpanel' => $this->requestPost(
                "/api/user/servers/{$serverId}/files/create-folder",
                ['root' => $root, 'name' => $name]
            ),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function compressFiles(string|int $serverId, ?string $identifier, string $root, array $files): array
    {
        $cid = $this->clientId($serverId, $identifier);
        return match ($this->type) {
            'pterodactyl', 'pelican' => $this->normalizePterodactylFileItem(
                $this->requestPost("/api/client/servers/{$cid}/files/compress", ['root' => $root, 'files' => $files])
            ),
            'calagopus' => $this->normalizeCalagopusFileItem(
                $this->requestPost("/api/client/servers/{$cid}/files/compress", ['root' => $root, 'files' => $files, 'format' => 'tar_gz'])
            ),
            'featherpanel' => $this->normalizePterodactylFileItem(
                $this->requestPost("/api/user/servers/{$serverId}/files/compress", ['root' => $root, 'files' => $files])
            ),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function decompressFile(string|int $serverId, ?string $identifier, string $root, string $file): void
    {
        $cid = $this->clientId($serverId, $identifier);
        match ($this->type) {
            'pterodactyl', 'pelican', 'calagopus' => $this->requestPost(
                "/api/client/servers/{$cid}/files/decompress",
                ['root' => $root, 'file' => $file]
            ),
            'featherpanel' => $this->requestPost(
                "/api/user/servers/{$serverId}/files/decompress",
                ['root' => $root, 'file' => $file]
            ),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function getStartup(string|int $serverId, ?string $identifier): array
    {
        $cid = $this->clientId($serverId, $identifier);
        return match ($this->type) {
            'pterodactyl', 'pelican' => $this->normalizePterodactylStartup(
                $this->request('GET', "/api/client/servers/{$cid}/startup")
            ),
            'calagopus' => (function () use ($cid) {
                // Calagopus has no /startup endpoint; startup command lives in server info
                $data   = $this->request('GET', "/api/client/servers/{$cid}");
                $server = $data['server'] ?? $data;
                return [
                    'startup_command' => $server['startup'] ?? '',
                    'variables'       => [],
                ];
            })(),
            'featherpanel' => $this->normalizeFeatherpanelStartup(
                $this->request('GET', "/api/user/servers/{$serverId}/startup")
            ),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function updateStartupVariable(string|int $serverId, ?string $identifier, string $key, string $value): array
    {
        $cid = $this->clientId($serverId, $identifier);
        return match ($this->type) {
            'pterodactyl', 'pelican' => $this->normalizePterodactylVariable(
                $this->clientPut("/api/client/servers/{$cid}/startup/variable", ['key' => $key, 'value' => $value])
            ),
            'calagopus' => throw new \RuntimeException('Startup variable editing is not supported on Calagopus panels'),
            'featherpanel' => $this->normalizePterodactylVariable(
                $this->clientPut("/api/user/servers/{$serverId}/startup/variable", ['key' => $key, 'value' => $value])
            ),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function listDatabases(string|int $serverId, ?string $identifier): array
    {
        return match ($this->type) {
            'pterodactyl', 'pelican' => $this->normalizePterodactylDatabases(
                $this->request('GET', "/api/client/servers/{$this->clientId($serverId, $identifier)}/databases")
            ),
            'featherpanel' => $this->featherpanelListDatabases($serverId),
            'calagopus'    => $this->normalizePterodactylDatabases(
                $this->calagopusClientGet(
                    "/api/client/servers/{$this->clientId($serverId, $identifier)}/databases",
                    null
                )
            ),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function getWebsocket(string|int $serverId, ?string $identifier): array
    {
        $cid = $this->clientId($serverId, $identifier);
        return match ($this->type) {
            'pterodactyl', 'pelican' => (function () use ($cid) {
                $resp = $this->request('GET', "/api/client/servers/{$cid}/websocket");
                return [
                    'token'  => $resp['data']['token'] ?? '',
                    'socket' => $resp['data']['socket'] ?? '',
                ];
            })(),
            'calagopus' => (function () use ($cid) {
                // Calagopus returns { "token": "...", "url": "wss://..." } (flat, no 'data' wrapper, key is 'url')
                $resp = $this->request('GET', "/api/client/servers/{$cid}/websocket");
                return [
                    'token'  => $resp['token'] ?? '',
                    'socket' => $resp['url'] ?? $resp['socket'] ?? '',
                ];
            })(),
            'featherpanel' => (function () use ($serverId) {
                $resp = $this->request('GET', "/api/user/servers/{$serverId}/websocket");
                return [
                    'token'  => $resp['data']['token'] ?? ($resp['token'] ?? ''),
                    'socket' => $resp['data']['socket'] ?? ($resp['socket'] ?? $resp['url'] ?? ''),
                ];
            })(),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    // ── Remote control helpers ─────────────────────────────────────────────────

    private function clientId(string|int $serverId, ?string $identifier): string
    {
        return (string) ($identifier ?? $serverId);
    }

    private function clientPost(string $path, array $body): array
    {
        return $this->requestPost($path, $body);
    }

    private function clientPut(string $path, array $body): array
    {
        $url = $this->baseUrl . $path;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
        ]);
        $body_raw = curl_exec($ch);
        $errno    = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($errno !== 0 || $body_raw === false) {
            throw new \RuntimeException("cURL error {$errno}");
        }
        if ($httpCode === 204 || trim($body_raw) === '') return [];
        $decoded = json_decode($body_raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            if ($httpCode >= 400) throw new \RuntimeException("External API error HTTP {$httpCode}");
            return [];
        }
        if ($httpCode >= 400) {
            $errs = $decoded['errors'] ?? null;
            $msg = is_array($errs) && !empty($errs)
                ? (is_string($errs[0]) ? $errs[0] : ($errs[0]['detail'] ?? json_encode($errs[0])))
                : ($decoded['error'] ?? $decoded['message'] ?? "HTTP {$httpCode}");
            throw new \RuntimeException("External API error: {$msg} (HTTP {$httpCode})");
        }
        return $decoded;
    }

    private function calagopusClientGet(string $clientPath, ?string $adminPath): array
    {
        try {
            return $this->request('GET', $clientPath);
        } catch (\RuntimeException $e) {
            if ($adminPath !== null && (stripos($e->getMessage(), 'unauthorized') !== false || stripos($e->getMessage(), '403') !== false)) {
                return $this->request('GET', $adminPath);
            }
            throw $e;
        }
    }

    private function calagopusClientPost(string $clientPath, array $clientBody, ?string $adminPath, ?array $adminBody, bool $returnResult = false): array
    {
        try {
            $result = $this->requestPost($clientPath, $clientBody);
            return $returnResult ? $result : [];
        } catch (\RuntimeException $e) {
            if ($adminPath !== null && (stripos($e->getMessage(), 'unauthorized') !== false || stripos($e->getMessage(), '403') !== false)) {
                $result = $this->requestPost($adminPath, $adminBody ?? $clientBody);
                return $returnResult ? $result : [];
            }
            throw $e;
        }
    }

    private function normalizePterodactylResources(array $data): array
    {
        $attrs = $data['attributes'] ?? $data;
        $res   = $attrs['resources'] ?? $attrs;
        return [
            'state'     => $attrs['current_state'] ?? $data['current_state'] ?? 'unknown',
            'cpu'       => round((float) ($res['cpu_absolute'] ?? 0), 2),
            'memory_mb' => round((int) ($res['memory_bytes'] ?? 0) / 1048576, 1),
            'disk_mb'   => round((int) ($res['disk_bytes'] ?? 0) / 1048576, 1),
            'net_rx_mb' => round((int) ($res['network_rx_bytes'] ?? 0) / 1048576, 2),
            'net_tx_mb' => round((int) ($res['network_tx_bytes'] ?? 0) / 1048576, 2),
            'uptime'    => (int) ($res['uptime'] ?? 0),
        ];
    }

    private function normalizePterodactylFiles(array $data): array
    {
        $files = [];
        foreach ($data['data'] ?? [] as $item) {
            $a = $item['attributes'] ?? $item;
            $files[] = [
                'name'        => $a['name'] ?? '',
                'mode'        => $a['mode'] ?? '',
                'size'        => (int) ($a['size'] ?? 0),
                'is_file'     => (bool) ($a['is_file'] ?? true),
                'is_symlink'  => (bool) ($a['is_symlink'] ?? false),
                'mimetype'    => $a['mimetype'] ?? 'application/octet-stream',
                'created_at'  => $a['created_at'] ?? '',
                'modified_at' => $a['modified_at'] ?? '',
            ];
        }
        return $files;
    }

    private function normalizePterodactylAllocations(array $data): array
    {
        $items = [];
        foreach ($data['data'] ?? [] as $item) {
            $a = $item['attributes'] ?? $item;
            $items[] = [
                'id'          => $a['id'] ?? null,
                'ip'          => $a['ip'] ?? '',
                'ip_alias'    => $a['ip_alias'] ?? null,
                'port'        => $a['port'] ?? 0,
                'notes'       => $a['notes'] ?? null,
                'is_default'  => (bool) ($a['is_default'] ?? false),
            ];
        }
        return $items;
    }

    private function normalizePterodactylSchedules(array $data): array
    {
        $items = [];
        foreach ($data['data'] ?? [] as $item) {
            $a = $item['attributes'] ?? $item;
            $items[] = [
                'id'         => $a['id'] ?? null,
                'name'       => $a['name'] ?? '',
                'cron_day_of_week'  => $a['cron']['day_of_week'] ?? $a['cron_day_of_week'] ?? '*',
                'cron_month'        => $a['cron']['month'] ?? $a['cron_month'] ?? '*',
                'cron_day_of_month' => $a['cron']['day_of_month'] ?? $a['cron_day_of_month'] ?? '*',
                'cron_hour'         => $a['cron']['hour'] ?? $a['cron_hour'] ?? '*',
                'cron_minute'       => $a['cron']['minute'] ?? $a['cron_minute'] ?? '*',
                'is_active'  => (bool) ($a['is_active'] ?? true),
                'is_processing' => (bool) ($a['is_processing'] ?? false),
                'last_run_at' => $a['last_run_at'] ?? null,
                'next_run_at' => $a['next_run_at'] ?? null,
            ];
        }
        return $items;
    }

    private function normalizePterodactylBackups(array $data): array
    {
        $items = [];
        foreach ($data['data'] ?? [] as $item) {
            $items[] = $this->normalizePterodactylBackupItem($item);
        }
        return $items;
    }

    private function normalizePterodactylBackupItem(array $item): array
    {
        $a = $item['attributes'] ?? $item;
        return [
            'uuid'         => $a['uuid'] ?? $a['id'] ?? null,
            'name'         => $a['name'] ?? 'Backup',
            'bytes'        => (int) ($a['bytes'] ?? 0),
            'sha256_hash'  => $a['sha256_hash'] ?? null,
            'is_successful' => (bool) ($a['is_successful'] ?? true),
            'is_locked'    => (bool) ($a['is_locked'] ?? false),
            'created_at'   => $a['created_at'] ?? '',
            'completed_at' => $a['completed_at'] ?? null,
        ];
    }

    private function normalizePterodactylFileItem(array $data): array
    {
        $a = $data['attributes'] ?? $data;
        return [
            'name'        => $a['name'] ?? '',
            'mode'        => $a['mode'] ?? '',
            'size'        => (int) ($a['size'] ?? 0),
            'is_file'     => (bool) ($a['is_file'] ?? true),
            'is_symlink'  => (bool) ($a['is_symlink'] ?? false),
            'mimetype'    => $a['mimetype'] ?? 'application/octet-stream',
            'created_at'  => $a['created_at'] ?? '',
            'modified_at' => $a['modified_at'] ?? '',
        ];
    }

    private function normalizeCalagopusFiles(array $data): array
    {
        $files = [];
        foreach ($data['entries']['data'] ?? [] as $item) {
            $files[] = [
                'name'        => $item['name'] ?? '',
                'mode'        => $item['mode'] ?? '',
                'size'        => (int) ($item['size'] ?? 0),
                'is_file'     => (bool) ($item['file'] ?? !($item['directory'] ?? false)),
                'is_symlink'  => (bool) ($item['symlink'] ?? false),
                'mimetype'    => $item['mime'] ?? 'application/octet-stream',
                'created_at'  => $item['created'] ?? '',
                'modified_at' => $item['modified'] ?? '',
            ];
        }
        return $files;
    }

    private function normalizeCalagopusAllocations(array $data): array
    {
        $items = [];
        foreach ($data['allocations']['data'] ?? [] as $item) {
            $items[] = [
                'id'         => null,
                'ip'         => $item['ip'] ?? '',
                'ip_alias'   => null,
                'port'       => (int) ($item['port'] ?? 0),
                'notes'      => null,
                'is_default' => (bool) ($item['is_primary'] ?? false),
            ];
        }
        return $items;
    }

    private function normalizeCalagopusFileItem(array $data): array
    {
        // Calagopus compress response uses same field names as file list entries
        $a = $data['attributes'] ?? $data;
        return [
            'name'        => $a['name'] ?? '',
            'mode'        => $a['mode'] ?? '',
            'size'        => (int) ($a['size'] ?? 0),
            'is_file'     => (bool) ($a['file'] ?? $a['is_file'] ?? true),
            'is_symlink'  => (bool) ($a['symlink'] ?? $a['is_symlink'] ?? false),
            'mimetype'    => $a['mime'] ?? $a['mimetype'] ?? 'application/octet-stream',
            'created_at'  => $a['created'] ?? $a['created_at'] ?? '',
            'modified_at' => $a['modified'] ?? $a['modified_at'] ?? '',
        ];
    }

    private function normalizePterodactylStartup(array $data): array
    {
        $d         = $data['data'] ?? $data;
        $vars      = [];
        foreach ($d['variables']['data'] ?? ($d['variables'] ?? []) as $item) {
            $a = $item['attributes'] ?? $item;
            $vars[] = [
                'name'          => $a['name'] ?? '',
                'description'   => $a['description'] ?? '',
                'env_variable'  => $a['env_variable'] ?? '',
                'default_value' => $a['default_value'] ?? '',
                'server_value'  => $a['server_value'] ?? '',
                'is_editable'   => (bool) ($a['is_editable'] ?? true),
                'rules'         => $a['rules'] ?? '',
            ];
        }
        $meta = $d['meta'] ?? [];
        return [
            'startup_command' => $meta['startup_command'] ?? ($data['startup'] ?? ''),
            'variables'       => $vars,
        ];
    }

    private function normalizeFeatherpanelStartup(array $data): array
    {
        $d    = $data['data'] ?? $data;
        $vars = [];
        foreach ($d['variables'] ?? [] as $v) {
            $vars[] = [
                'name'          => $v['name'] ?? '',
                'description'   => $v['description'] ?? '',
                'env_variable'  => $v['env_variable'] ?? ($v['key'] ?? ''),
                'default_value' => $v['default_value'] ?? '',
                'server_value'  => $v['value'] ?? ($v['server_value'] ?? ''),
                'is_editable'   => (bool) ($v['is_editable'] ?? true),
                'rules'         => $v['rules'] ?? '',
            ];
        }
        return [
            'startup_command' => $d['startup_command'] ?? ($d['startup'] ?? ''),
            'variables'       => $vars,
        ];
    }

    private function normalizePterodactylVariable(array $data): array
    {
        $a = $data['attributes'] ?? $data;
        return [
            'env_variable' => $a['env_variable'] ?? ($a['key'] ?? ''),
            'server_value' => $a['server_value'] ?? ($a['value'] ?? ''),
        ];
    }

    private function normalizePterodactylDatabases(array $data): array
    {
        $items = [];
        foreach ($data['data'] ?? [] as $item) {
            $a = $item['attributes'] ?? $item;
            $items[] = [
                'id'           => $a['id'] ?? null,
                'name'         => $a['name'] ?? '',
                'username'     => $a['username'] ?? '',
                'host'         => $a['host'] ?? '',
                'port'         => (int) ($a['port'] ?? 3306),
                'connections_from' => $a['connections_from'] ?? '%',
            ];
        }
        return $items;
    }

    // ── FeatherPanel remote control ────────────────────────────────────────────

    private function featherpanelPowerAction(string|int $serverId, string $action): void
    {
        try {
            $this->requestPost("/api/user/servers/{$serverId}/power", ['signal' => $action]);
        } catch (\RuntimeException $e) {
            $this->requestPost("/api/admin/servers/{$serverId}/power", ['action' => $action]);
        }
    }

    private function featherpanelGetResources(string|int $serverId): array
    {
        try {
            $data = $this->request('GET', "/api/user/servers/{$serverId}/resources");
            return $this->normalizePterodactylResources($data);
        } catch (\RuntimeException $e) {
            return ['state' => 'unknown', 'cpu' => 0, 'memory_mb' => 0, 'disk_mb' => 0, 'net_rx_mb' => 0, 'net_tx_mb' => 0, 'uptime' => 0];
        }
    }

    private function featherpanelSendCommand(string|int $serverId, string $command): void
    {
        $this->requestPost("/api/user/servers/{$serverId}/command", ['command' => $command]);
    }

    private function featherpanelListFiles(string|int $serverId, string $directory): array
    {
        $data = $this->request('GET', "/api/user/servers/{$serverId}/files/list?directory=" . urlencode($directory));
        return $this->normalizePterodactylFiles($data);
    }

    private function featherpanelGetFileContents(string|int $serverId, string $file): string
    {
        return $this->requestText('GET', "/api/user/servers/{$serverId}/files/contents?file=" . urlencode($file));
    }

    private function featherpanelWriteFile(string|int $serverId, string $file, string $content): void
    {
        $this->requestRaw('POST', "/api/user/servers/{$serverId}/files/write?file=" . urlencode($file), $content);
    }

    private function featherpanelListAllocations(string|int $serverId): array
    {
        try {
            $data = $this->request('GET', "/api/user/servers/{$serverId}/network/allocations");
            return $this->normalizePterodactylAllocations($data);
        } catch (\RuntimeException $e) {
            return [];
        }
    }

    private function featherpanelListSchedules(string|int $serverId): array
    {
        try {
            $data = $this->request('GET', "/api/user/servers/{$serverId}/schedules");
            return $this->normalizePterodactylSchedules($data);
        } catch (\RuntimeException $e) {
            return [];
        }
    }

    private function featherpanelListBackups(string|int $serverId): array
    {
        try {
            $data = $this->request('GET', "/api/user/servers/{$serverId}/backups");
            return $this->normalizePterodactylBackups($data);
        } catch (\RuntimeException $e) {
            return [];
        }
    }

    private function featherpanelCreateBackup(string|int $serverId): array
    {
        try {
            $data = $this->requestPost("/api/user/servers/{$serverId}/backups", []);
            return $this->normalizePterodactylBackupItem($data);
        } catch (\RuntimeException $e) {
            return [];
        }
    }

    private function featherpanelDeleteBackup(string|int $serverId, string $backupId): void
    {
        $this->requestDelete("/api/user/servers/{$serverId}/backups/{$backupId}");
    }

    private function featherpanelListDatabases(string|int $serverId): array
    {
        try {
            $data = $this->request('GET', "/api/user/servers/{$serverId}/databases");
            return $this->normalizePterodactylDatabases($data);
        } catch (\RuntimeException $e) {
            return [];
        }
    }

    // ── Pterodactyl ────────────────────────────────────────────────────────────

    private function pterodactylListServers(int $page): array
    {
        $data = $this->request('GET', "/api/application/servers?page={$page}&per_page=50");
        $servers = [];
        foreach ($data['data'] ?? [] as $item) {
            $servers[] = $this->normalizePterodactylServer($item['attributes'] ?? $item);
        }
        return [
            'servers'      => $servers,
            'total'        => $data['meta']['pagination']['total'] ?? count($servers),
            'current_page' => $data['meta']['pagination']['current_page'] ?? $page,
            'total_pages'  => $data['meta']['pagination']['total_pages'] ?? 1,
        ];
    }

    private function pterodactylGetServer(string|int $id): array
    {
        $data = $this->request('GET', "/api/application/servers/{$id}");
        return $this->normalizePterodactylServer($data['attributes'] ?? $data);
    }

    private function normalizePterodactylServer(array $attrs): array
    {
        return [
            'id'          => $attrs['id'] ?? null,
            'name'        => $attrs['name'] ?? 'Unknown',
            'identifier'  => $attrs['identifier'] ?? null,
            'description' => $attrs['description'] ?? '',
            'node'        => $attrs['node'] ?? null,
            'memory'      => $attrs['limits']['memory'] ?? 0,
            'disk'        => $attrs['limits']['disk'] ?? 0,
            'cpu'         => $attrs['limits']['cpu'] ?? 0,
            'swap'        => $attrs['limits']['swap'] ?? 0,
            'io'          => $attrs['limits']['io'] ?? 500,
            'threads'     => $attrs['limits']['threads'] ?? null,
            'egg_id'      => $attrs['egg'] ?? null,
            'nest_id'     => $attrs['nest'] ?? null,
            'docker_image' => $attrs['container']['image'] ?? '',
            'startup'     => $attrs['container']['startup_command'] ?? ($attrs['container']['environment']['STARTUP'] ?? ''),
            'status'      => $attrs['status'] ?? 'unknown',
            'user'        => $attrs['user'] ?? null,
            'allocation'  => $attrs['allocation'] ?? null,
            'environment' => $attrs['container']['environment'] ?? [],
        ];
    }

    // ── FeatherPanel ───────────────────────────────────────────────────────────

    private function featherpanelListServers(int $page): array
    {
        $data = $this->request('GET', "/api/admin/servers?page={$page}&limit=50");
        $servers = [];
        foreach ($data['data']['servers'] ?? $data['data'] ?? [] as $item) {
            $servers[] = $this->normalizeFeatherpanelServer($item);
        }
        return [
            'servers'      => $servers,
            'total'        => $data['data']['pagination']['total_records'] ?? count($servers),
            'current_page' => $data['data']['pagination']['current_page'] ?? $page,
            'total_pages'  => $data['data']['pagination']['total_pages'] ?? 1,
        ];
    }

    private function featherpanelGetServer(string|int $id): array
    {
        $data = $this->request('GET', "/api/admin/servers/{$id}");
        return $this->normalizeFeatherpanelServer($data['data']['server'] ?? $data['data'] ?? $data);
    }

    private function normalizeFeatherpanelServer(array $s): array
    {
        return [
            'id'          => $s['id'] ?? null,
            'name'        => $s['name'] ?? 'Unknown',
            'identifier'  => $s['uuid_short'] ?? $s['identifier'] ?? null,
            'description' => $s['description'] ?? '',
            'node'        => $s['node_id'] ?? null,
            'memory'      => $s['memory'] ?? 0,
            'disk'        => $s['disk'] ?? 0,
            'cpu'         => $s['cpu'] ?? 0,
            'swap'        => $s['swap'] ?? 0,
            'io'          => $s['io'] ?? 500,
            'threads'     => $s['threads'] ?? null,
            'egg_id'      => $s['spell_id'] ?? $s['egg_id'] ?? null,
            'nest_id'     => null,
            'docker_image' => $s['image'] ?? $s['docker_image'] ?? '',
            'startup'     => $s['startup'] ?? '',
            'status'      => $s['status'] ?? 'unknown',
            'user'        => $s['owner_id'] ?? $s['user'] ?? null,
            'allocation'  => $s['allocation_id'] ?? null,
            'environment' => $s['environment'] ?? [],
        ];
    }

    // ── Pelican (same API shape as Pterodactyl v1) ─────────────────────────────

    private function pelicanListServers(int $page): array
    {
        return $this->pterodactylListServers($page);
    }

    private function pelicanGetServer(string|int $id): array
    {
        return $this->pterodactylGetServer($id);
    }

    // ── Calagopus ─────────────────────────────────────────────────────────────

    private function calagopusListServers(int $page): array
    {
        try {
            // Admin API returns all servers; fall back to client API if not admin
            $data = $this->request('GET', "/api/admin/servers?page={$page}&per_page=50");
            $meta = $data['servers'] ?? [];
            $servers = [];
            foreach ($meta['data'] ?? [] as $item) {
                $servers[] = $this->normalizeCalagopusAdminServer($item);
            }
            $total   = $meta['total'] ?? count($servers);
            $perPage = $meta['per_page'] ?? 50;
            return [
                'servers'      => $servers,
                'total'        => $total,
                'current_page' => $meta['page'] ?? $page,
                'total_pages'  => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
            ];
        } catch (\RuntimeException $e) {
            // "unauthorized" = account is not admin → use client API instead
            if (stripos($e->getMessage(), 'unauthorized') !== false) {
                return $this->calagopusClientListServers($page);
            }
            throw $e;
        }
    }

    private function calagopusClientListServers(int $page): array
    {
        $data = $this->request('GET', "/api/client/servers?page={$page}&per_page=50&other=false");
        $meta = $data['servers'] ?? [];
        $servers = [];
        foreach ($meta['data'] ?? [] as $item) {
            $servers[] = $this->normalizeCalagopusClientServer($item);
        }
        $total   = $meta['total'] ?? count($servers);
        $perPage = $meta['per_page'] ?? 50;
        return [
            'servers'      => $servers,
            'total'        => $total,
            'current_page' => $meta['page'] ?? $page,
            'total_pages'  => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
        ];
    }

    private function calagopusGetServer(string|int $id): array
    {
        try {
            $data = $this->request('GET', "/api/admin/servers/{$id}");
            return $this->normalizeCalagopusAdminServer($data['server'] ?? $data);
        } catch (\RuntimeException $e) {
            if (stripos($e->getMessage(), 'unauthorized') !== false) {
                $data = $this->request('GET', "/api/client/servers/{$id}");
                return $this->normalizeCalagopusClientServer($data['server'] ?? $data);
            }
            throw $e;
        }
    }

    private function normalizeCalagopusAdminServer(array $s): array
    {
        $status = $s['status'] ?? null;
        if ($status === null) {
            $status = ($s['is_suspended'] ?? false) ? 'suspended' : 'running';
        }
        return [
            'id'           => $s['uuid'] ?? null,
            'name'         => $s['name'] ?? 'Unknown',
            'identifier'   => $s['uuid_short'] ?? null,
            'description'  => $s['description'] ?? '',
            'node'         => is_array($s['node'] ?? null) ? ($s['node']['name'] ?? null) : null,
            'memory'       => $s['limits']['memory'] ?? 0,
            'disk'         => $s['limits']['disk'] ?? 0,
            'cpu'          => $s['limits']['cpu'] ?? 0,
            'swap'         => $s['limits']['swap'] ?? 0,
            'io'           => $s['limits']['io_weight'] ?? 500,
            'threads'      => null,
            'egg_id'       => is_array($s['egg'] ?? null) ? ($s['egg']['uuid'] ?? null) : null,
            'nest_id'      => is_array($s['nest'] ?? null) ? ($s['nest']['uuid'] ?? null) : null,
            'docker_image' => $s['image'] ?? '',
            'startup'      => $s['startup'] ?? '',
            'status'       => (string) $status,
            'user'         => is_array($s['owner'] ?? null) ? ($s['owner']['email'] ?? null) : null,
            'allocation'   => null,
            'environment'  => [],
        ];
    }

    private function normalizeCalagopusClientServer(array $s): array
    {
        $status = $s['status'] ?? null;
        if ($status === null) {
            $status = ($s['is_suspended'] ?? false) ? 'suspended' : 'unknown';
        }
        return [
            'id'           => $s['uuid'] ?? null,
            'name'         => $s['name'] ?? 'Unknown',
            'identifier'   => $s['uuid_short'] ?? null,
            'description'  => $s['description'] ?? '',
            'node'         => $s['node_name'] ?? null,
            'memory'       => $s['limits']['memory'] ?? 0,
            'disk'         => $s['limits']['disk'] ?? 0,
            'cpu'          => $s['limits']['cpu'] ?? 0,
            'swap'         => $s['limits']['swap'] ?? 0,
            'io'           => 500,
            'threads'      => null,
            'egg_id'       => is_array($s['egg'] ?? null) ? ($s['egg']['uuid'] ?? null) : null,
            'nest_id'      => null,
            'docker_image' => $s['image'] ?? '',
            'startup'      => $s['startup'] ?? '',
            'status'       => (string) $status,
            'user'         => null,
            'allocation'   => null,
            'environment'  => [],
        ];
    }

    // ── HTTP core ──────────────────────────────────────────────────────────────

    private function requestPost(string $path, array $body): array
    {
        $url = $this->baseUrl . $path;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
        ]);
        $body_raw = curl_exec($ch);
        $errno    = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($errno !== 0 || $body_raw === false) {
            throw new \RuntimeException("cURL error {$errno} connecting to {$url}");
        }
        if ($httpCode === 204 || trim($body_raw) === '') {
            return [];
        }
        $decoded = json_decode($body_raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            if ($httpCode >= 400) {
                throw new \RuntimeException("External API error HTTP {$httpCode}");
            }
            return [];
        }
        if ($httpCode >= 400) {
            $errs = $decoded['errors'] ?? null;
            if (is_array($errs) && !empty($errs)) {
                $first = $errs[0];
                $msg = is_string($first) ? $first : ($first['detail'] ?? json_encode($first));
            } else {
                $msg = $decoded['error'] ?? $decoded['message'] ?? "HTTP {$httpCode}";
            }
            throw new \RuntimeException("External API error: {$msg} (HTTP {$httpCode})");
        }
        return $decoded;
    }

    private function requestDelete(string $path): void
    {
        $url = $this->baseUrl . $path;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body     = curl_exec($ch);
        $errno    = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($errno !== 0 || $body === false) {
            throw new \RuntimeException("cURL error {$errno} connecting to {$url}");
        }
        if ($httpCode >= 400) {
            $decoded = json_decode($body, true);
            $msg = ($decoded['error'] ?? $decoded['message'] ?? "HTTP {$httpCode}");
            throw new \RuntimeException("External API error: {$msg} (HTTP {$httpCode})");
        }
    }

    private function requestRaw(string $method, string $path, string $rawBody): void
    {
        $url = $this->baseUrl . $path;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => $rawBody,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: text/plain',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body     = curl_exec($ch);
        $errno    = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($errno !== 0 || $body === false) {
            throw new \RuntimeException("cURL error {$errno} connecting to {$url}");
        }
        if ($httpCode >= 400) {
            $decoded = json_decode($body, true);
            $msg = is_array($decoded) ? ($decoded['error'] ?? $decoded['message'] ?? "HTTP {$httpCode}") : "HTTP {$httpCode}";
            throw new \RuntimeException("External API error: {$msg} (HTTP {$httpCode})");
        }
    }

    private function requestText(string $method, string $path): string
    {
        $url = $this->baseUrl . $path;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body     = curl_exec($ch);
        $errno    = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($errno !== 0 || $body === false) {
            throw new \RuntimeException("cURL error {$errno} connecting to {$url}");
        }
        if ($httpCode >= 400) {
            throw new \RuntimeException("External API error HTTP {$httpCode} fetching file");
        }
        return (string) $body;
    }

    private function request(string $method, string $path): array
    {
        $url = $this->baseUrl . $path;
        $ch  = curl_init($url);

        $authHeader = 'Authorization: Bearer ' . $this->apiKey;

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
                $authHeader,
            ],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CUSTOMREQUEST  => $method,
        ]);

        $body     = curl_exec($ch);
        $errno    = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || $body === false) {
            throw new \RuntimeException("cURL error {$errno} connecting to {$url}");
        }

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Invalid JSON response from {$url} (HTTP {$httpCode})");
        }

        if ($httpCode >= 400) {
            // Calagopus: { "errors": ["string", ...] }
            // Pterodactyl: { "errors": [{"detail": "..."}] }
            $errs = $decoded['errors'] ?? null;
            if (is_array($errs) && !empty($errs)) {
                $first = $errs[0];
                $msg = is_string($first) ? $first : ($first['detail'] ?? json_encode($first));
            } else {
                $msg = $decoded['error'] ?? $decoded['message'] ?? "HTTP {$httpCode}";
            }
            throw new \RuntimeException("External API error: {$msg} (HTTP {$httpCode})");
        }

        return $decoded;
    }

    public function getServerDetails(string|int $serverId, ?string $identifier): array
    {
        $cid = $this->clientId($serverId, $identifier);
        return match ($this->type) {
            'pterodactyl', 'pelican' => $this->request('GET', "/api/client/servers/{$cid}"),
            'featherpanel' => $this->request('GET', "/api/user/servers/{$serverId}"),
            'calagopus' => $this->request('GET', "/api/client/servers/{$cid}"),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function restoreBackup(string|int $serverId, ?string $identifier, string $backupUuid, bool $truncate = false): void
    {
        $cid = $this->clientId($serverId, $identifier);
        match ($this->type) {
            'pterodactyl', 'pelican' => $this->clientPost(
                "/api/client/servers/{$cid}/backups/{$backupUuid}/restore",
                ['truncate' => $truncate]
            ),
            'featherpanel' => $this->requestPost(
                "/api/user/servers/{$serverId}/backups/{$backupUuid}/restore",
                ['truncate' => $truncate]
            ),
            'calagopus' => $this->clientPost(
                "/api/client/servers/{$cid}/backups/{$backupUuid}/restore",
                ['truncate' => $truncate]
            ),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function toggleBackupLock(string|int $serverId, ?string $identifier, string $backupUuid): void
    {
        $cid = $this->clientId($serverId, $identifier);
        match ($this->type) {
            'pterodactyl', 'pelican' => $this->clientPost(
                "/api/client/servers/{$cid}/backups/{$backupUuid}/lock",
                []
            ),
            'featherpanel' => $this->requestPost(
                "/api/user/servers/{$serverId}/backups/{$backupUuid}/lock",
                []
            ),
            'calagopus' => $this->clientPost(
                "/api/client/servers/{$cid}/backups/{$backupUuid}/lock",
                []
            ),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function getBackupDownloadUrl(string|int $serverId, ?string $identifier, string $backupUuid): string
    {
        $cid = $this->clientId($serverId, $identifier);
        return match ($this->type) {
            'pterodactyl', 'pelican' => (function () use ($cid, $backupUuid) {
                $resp = $this->request('GET', "/api/client/servers/{$cid}/backups/{$backupUuid}/download");
                return $resp['attributes']['url'] ?? '';
            })(),
            'featherpanel' => (function () use ($serverId, $backupUuid) {
                $resp = $this->request('GET', "/api/user/servers/{$serverId}/backups/{$backupUuid}/download");
                return $resp['data']['url'] ?? '';
            })(),
            'calagopus' => (function () use ($cid, $backupUuid) {
                $resp = $this->request('GET', "/api/client/servers/{$cid}/backups/{$backupUuid}/download");
                return $resp['attributes']['url'] ?? $resp['url'] ?? '';
            })(),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function createDatabase(string|int $serverId, ?string $identifier, string $database, string $remote = '%'): void
    {
        $cid = $this->clientId($serverId, $identifier);
        match ($this->type) {
            'pterodactyl', 'pelican' => $this->clientPost(
                "/api/client/servers/{$cid}/databases",
                ['database' => $database, 'remote' => $remote]
            ),
            'featherpanel' => $this->requestPost(
                "/api/user/servers/{$serverId}/databases",
                ['database' => $database, 'remote' => $remote]
            ),
            'calagopus' => $this->clientPost(
                "/api/client/servers/{$cid}/databases",
                ['database' => $database, 'remote' => $remote]
            ),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function deleteDatabase(string|int $serverId, ?string $identifier, string $databaseId): void
    {
        $cid = $this->clientId($serverId, $identifier);
        match ($this->type) {
            'pterodactyl', 'pelican' => $this->request('DELETE', "/api/client/servers/{$cid}/databases/{$databaseId}"),
            'featherpanel' => $this->request('DELETE', "/api/user/servers/{$serverId}/databases/{$databaseId}"),
            'calagopus' => $this->request('DELETE', "/api/client/servers/{$cid}/databases/{$databaseId}"),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function rotateDatabasePassword(string|int $serverId, ?string $identifier, string $databaseId): void
    {
        $cid = $this->clientId($serverId, $identifier);
        match ($this->type) {
            'pterodactyl', 'pelican' => $this->clientPost(
                "/api/client/servers/{$cid}/databases/{$databaseId}/rotate-password",
                []
            ),
            'featherpanel' => $this->requestPost(
                "/api/user/servers/{$serverId}/databases/{$databaseId}/rotate-password",
                []
            ),
            'calagopus' => $this->clientPost(
                "/api/client/servers/{$cid}/databases/{$databaseId}/rotate-password",
                []
            ),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function createSchedule(string|int $serverId, ?string $identifier, string $name, string $minute, string $hour, string $dayOfMonth, string $month, string $dayOfWeek, bool $isActive, bool $onlyWhenOnline): array
    {
        $cid = $this->clientId($serverId, $identifier);
        return match ($this->type) {
            'pterodactyl', 'pelican' => $this->clientPost(
                "/api/client/servers/{$cid}/schedules",
                [
                    'name' => $name,
                    'minute' => $minute,
                    'hour' => $hour,
                    'day_of_month' => $dayOfMonth,
                    'month' => $month,
                    'day_of_week' => $dayOfWeek,
                    'is_active' => $isActive,
                    'only_when_online' => $onlyWhenOnline,
                ]
            ),
            'featherpanel' => $this->requestPost(
                "/api/user/servers/{$serverId}/schedules",
                [
                    'name' => $name,
                    'minute' => $minute,
                    'hour' => $hour,
                    'day_of_month' => $dayOfMonth,
                    'month' => $month,
                    'day_of_week' => $dayOfWeek,
                    'is_active' => $isActive,
                    'only_when_online' => $onlyWhenOnline,
                ]
            ),
            'calagopus' => $this->clientPost(
                "/api/client/servers/{$cid}/schedules",
                [
                    'name' => $name,
                    'minute' => $minute,
                    'hour' => $hour,
                    'day_of_month' => $dayOfMonth,
                    'month' => $month,
                    'day_of_week' => $dayOfWeek,
                    'is_active' => $isActive,
                    'only_when_online' => $onlyWhenOnline,
                ]
            ),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function updateSchedule(string|int $serverId, ?string $identifier, int $scheduleId, array $data): array
    {
        $cid = $this->clientId($serverId, $identifier);
        return match ($this->type) {
            'pterodactyl', 'pelican' => $this->clientPost(
                "/api/client/servers/{$cid}/schedules/{$scheduleId}",
                $data
            ),
            'featherpanel' => $this->requestPost(
                "/api/user/servers/{$serverId}/schedules/{$scheduleId}",
                $data
            ),
            'calagopus' => $this->clientPost(
                "/api/client/servers/{$cid}/schedules/{$scheduleId}",
                $data
            ),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function deleteSchedule(string|int $serverId, ?string $identifier, int $scheduleId): void
    {
        $cid = $this->clientId($serverId, $identifier);
        match ($this->type) {
            'pterodactyl', 'pelican' => $this->request('DELETE', "/api/client/servers/{$cid}/schedules/{$scheduleId}"),
            'featherpanel' => $this->request('DELETE', "/api/user/servers/{$serverId}/schedules/{$scheduleId}"),
            'calagopus' => $this->request('DELETE', "/api/client/servers/{$cid}/schedules/{$scheduleId}"),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function executeSchedule(string|int $serverId, ?string $identifier, int $scheduleId): void
    {
        $cid = $this->clientId($serverId, $identifier);
        match ($this->type) {
            'pterodactyl', 'pelican' => $this->clientPost(
                "/api/client/servers/{$cid}/schedules/{$scheduleId}/execute",
                []
            ),
            'featherpanel' => $this->requestPost(
                "/api/user/servers/{$serverId}/schedules/{$scheduleId}/execute",
                []
            ),
            'calagopus' => $this->clientPost(
                "/api/client/servers/{$cid}/schedules/{$scheduleId}/execute",
                []
            ),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function createScheduleTask(string|int $serverId, ?string $identifier, int $scheduleId, string $action, string $payload, int $timeOffset, bool $continueOnFailure): array
    {
        $cid = $this->clientId($serverId, $identifier);
        return match ($this->type) {
            'pterodactyl', 'pelican' => $this->clientPost(
                "/api/client/servers/{$cid}/schedules/{$scheduleId}/tasks",
                [
                    'action' => $action,
                    'payload' => $payload,
                    'time_offset' => $timeOffset,
                    'continue_on_failure' => $continueOnFailure,
                ]
            ),
            'featherpanel' => $this->requestPost(
                "/api/user/servers/{$serverId}/schedules/{$scheduleId}/tasks",
                [
                    'action' => $action,
                    'payload' => $payload,
                    'time_offset' => $timeOffset,
                    'continue_on_failure' => $continueOnFailure,
                ]
            ),
            'calagopus' => $this->clientPost(
                "/api/client/servers/{$cid}/schedules/{$scheduleId}/tasks",
                [
                    'action' => $action,
                    'payload' => $payload,
                    'time_offset' => $timeOffset,
                    'continue_on_failure' => $continueOnFailure,
                ]
            ),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function updateScheduleTask(string|int $serverId, ?string $identifier, int $scheduleId, int $taskId, array $data): array
    {
        $cid = $this->clientId($serverId, $identifier);
        return match ($this->type) {
            'pterodactyl', 'pelican' => $this->clientPost(
                "/api/client/servers/{$cid}/schedules/{$scheduleId}/tasks/{$taskId}",
                $data
            ),
            'featherpanel' => $this->requestPost(
                "/api/user/servers/{$serverId}/schedules/{$scheduleId}/tasks/{$taskId}",
                $data
            ),
            'calagopus' => $this->clientPost(
                "/api/client/servers/{$cid}/schedules/{$scheduleId}/tasks/{$taskId}",
                $data
            ),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }

    public function deleteScheduleTask(string|int $serverId, ?string $identifier, int $scheduleId, int $taskId): void
    {
        $cid = $this->clientId($serverId, $identifier);
        match ($this->type) {
            'pterodactyl', 'pelican' => $this->request('DELETE', "/api/client/servers/{$cid}/schedules/{$scheduleId}/tasks/{$taskId}"),
            'featherpanel' => $this->request('DELETE', "/api/user/servers/{$serverId}/schedules/{$scheduleId}/tasks/{$taskId}"),
            'calagopus' => $this->request('DELETE', "/api/client/servers/{$cid}/schedules/{$scheduleId}/tasks/{$taskId}"),
            default => throw new \RuntimeException("Unknown source type: {$this->type}"),
        };
    }
}
