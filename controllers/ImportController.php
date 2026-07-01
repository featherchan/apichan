<?php

namespace App\Addons\apichan\controllers;

use App\Helpers\ApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Addons\apichan\chat\ApichanSourceChat;
use App\Addons\apichan\services\ExternalApiClient;
use App\Chat\Spell;
use App\Chat\SpellVariable;

class ImportController
{
    /**
     * Preview a server from an external source before importing.
     */
    public function preview(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ApiResponse::error('Invalid JSON', 'INVALID_JSON', 400);
        }

        $sourceId = (int) ($data['source_id'] ?? 0);
        $serverId = $data['server_id'] ?? null;

        if ($sourceId === 0 || $serverId === null) {
            return ApiResponse::error('source_id and server_id are required', 'MISSING_FIELD', 400);
        }

        $source = ApichanSourceChat::getById($sourceId);
        if ($source === null) {
            return ApiResponse::error('Source not found', 'SOURCE_NOT_FOUND', 404);
        }

        try {
            $client = new ExternalApiClient(
                $source['type'],
                $source['url'],
                $source['api_key'],
                (int) $source['timeout']
            );
            $server = $client->getServer($serverId);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'EXTERNAL_API_ERROR', 502);
        }

        return ApiResponse::success(['server' => $server], 'Server preview fetched', 200);
    }

    /**
     * Import a server: fetch from external source, create in this panel via admin API.
     *
     * Required body fields:
     *   source_id    - int
     *   server_id    - string|int  (ID on external panel)
     *   name         - string      (override name, optional)
     *   node_id      - int         (target Wings node in this panel)
     *   allocation_id- int         (target allocation in this panel)
     *   owner_id     - int         (user who will own the server)
     *   spell_id     - int         (spell/egg in this panel)
     */
    public function import(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ApiResponse::error('Invalid JSON', 'INVALID_JSON', 400);
        }

        // Owner is always the currently authenticated user
        $sessionUser = $request->attributes->get('user');
        $ownerId     = (int) ($sessionUser['id'] ?? 0);
        if ($ownerId === 0) {
            return ApiResponse::error('Authentication required', 'AUTH_REQUIRED', 401);
        }

        $sourceId    = (int) ($data['source_id'] ?? 0);
        $serverId    = $data['server_id'] ?? null;
        $nodeId      = (int) ($data['node_id'] ?? 0);
        $allocationId = (int) ($data['allocation_id'] ?? 0);
        $spellId     = (int) ($data['spell_id'] ?? 0);

        foreach (['source_id' => $sourceId, 'node_id' => $nodeId, 'allocation_id' => $allocationId, 'spell_id' => $spellId] as $field => $val) {
            if ($val === 0) {
                return ApiResponse::error("{$field} is required and must be a positive integer", 'MISSING_FIELD', 400);
            }
        }
        if ($serverId === null) {
            return ApiResponse::error('server_id is required', 'MISSING_FIELD', 400);
        }

        $source = ApichanSourceChat::getById($sourceId);
        if ($source === null) {
            return ApiResponse::error('Source not found', 'SOURCE_NOT_FOUND', 404);
        }

        // Fetch server details from the external panel
        try {
            $client = new ExternalApiClient(
                $source['type'],
                $source['url'],
                $source['api_key'],
                (int) $source['timeout']
            );
            $ext = $client->getServer($serverId);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'EXTERNAL_API_ERROR', 502);
        }

        // Look up realms_id from the spell
        $spell = Spell::getSpellById($spellId);
        if ($spell === null) {
            return ApiResponse::error("Spell ID {$spellId} not found in this panel", 'SPELL_NOT_FOUND', 404);
        }
        $realmsId = (int) $spell['realm_id'];

        // Build the server creation payload for this panel's admin API
        $serverName    = trim($data['name'] ?? '') ?: $ext['name'];
        $description   = $ext['description'] ?? '';
        $memory        = (int) ($data['memory'] ?? $ext['memory']);
        $disk          = (int) ($data['disk'] ?? $ext['disk']);
        $cpu           = (int) ($data['cpu'] ?? $ext['cpu']);
        $swap          = (int) ($data['swap'] ?? $ext['swap']);
        $io            = (int) ($data['io'] ?? $ext['io'] ?? 500);
        $dockerImage   = trim($data['docker_image'] ?? $ext['docker_image'] ?? '');
        $startup       = trim($data['startup'] ?? $ext['startup'] ?? '');

        // Build variables map: start from spell variable defaults, overlay matching source env values
        $spellVars  = SpellVariable::getVariablesBySpellId($spellId);
        $variables  = [];
        foreach ($spellVars as $v) {
            $variables[$v['env_variable']] = (string) ($v['default_value'] ?? '');
        }
        $sourceEnv = $data['environment'] ?? $ext['environment'] ?? [];
        if (is_array($sourceEnv)) {
            foreach ($sourceEnv as $k => $val) {
                if (isset($variables[$k])) {
                    $variables[$k] = (string) $val;
                }
            }
        }

        // Call this panel's internal server creation endpoint
        $payload = [
            'name'          => $serverName,
            'description'   => $description,
            'owner_id'      => $ownerId,
            'node_id'       => $nodeId,
            'allocation_id' => $allocationId,
            'realms_id'     => $realmsId,
            'spell_id'      => $spellId,
            'memory'        => $memory,
            'swap'          => $swap,
            'disk'          => $disk,
            'io'            => $io,
            'cpu'           => $cpu,
            'image'         => $dockerImage,
            'startup'       => $startup,
            'variables'     => $variables,
        ];

        // Proxy to the internal admin server creation route
        $internalResponse = $this->callInternalApi('PUT', '/api/admin/servers', $payload, $request);

        if (!isset($internalResponse['success']) || !$internalResponse['success']) {
            $msg = $internalResponse['message'] ?? $internalResponse['error_message'] ?? 'Failed to create server';
            return ApiResponse::error($msg, 'SERVER_CREATE_FAILED', 500);
        }

        return ApiResponse::success(
            [
                'server'   => $internalResponse['data']['server'] ?? $internalResponse['data'] ?? [],
                'source'   => ['id' => $sourceId, 'type' => $source['type'], 'name' => $source['name']],
                'imported_from_id' => $serverId,
            ],
            'Server imported successfully',
            201
        );
    }

    /**
     * Internal HTTP request to this panel's own API (bypasses network, hits same process).
     */
    private function callInternalApi(string $method, string $path, array $payload, Request $original): array
    {
        $cookies = [];
        foreach ($original->cookies->all() as $k => $v) {
            $cookies[] = urlencode($k) . '=' . urlencode($v);
        }

        $ch = curl_init('http://127.0.0.1/index.php' . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Cookie: ' . implode('; ', $cookies),
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $body = curl_exec($ch);
        curl_close($ch);

        if ($body === false) {
            return ['success' => false, 'message' => 'Internal API unreachable'];
        }

        return json_decode($body, true) ?? ['success' => false, 'message' => 'Invalid internal response'];
    }
}
