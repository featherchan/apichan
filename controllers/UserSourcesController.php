<?php

namespace App\Addons\apichan\controllers;

use App\Helpers\ApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Addons\apichan\chat\ApichanSourceChat;
use App\Addons\apichan\services\EncryptionHelper;
use App\Addons\apichan\services\ExternalApiClient;

class UserSourcesController
{
    private const VALID_TYPES = ['pterodactyl', 'featherpanel', 'pelican', 'calagopus'];

    private function currentUserId(Request $request): int
    {
        return (int) ($request->attributes->get('user')['id'] ?? 0);
    }

    public function index(Request $request): Response
    {
        $userId  = $this->currentUserId($request);
        $sources = ApichanSourceChat::getAll($userId);
        return ApiResponse::success(['sources' => $sources], 'Sources fetched', 200);
    }

    public function create(Request $request): Response
    {
        $userId = $this->currentUserId($request);
        if ($userId === 0) {
            return ApiResponse::error('Authentication required', 'AUTH_REQUIRED', 401);
        }

        $data    = json_decode($request->getContent(), true);
        $name    = trim($data['name'] ?? '');
        $type    = $data['type'] ?? '';
        $url     = trim($data['url'] ?? '');
        $apiKey  = trim($data['api_key'] ?? '');
        $timeout = (int) ($data['timeout'] ?? 15);

        if ($name === '') {
            return ApiResponse::error('name is required', 'MISSING_FIELD', 400);
        }
        if (!in_array($type, self::VALID_TYPES, true)) {
            return ApiResponse::error('type must be one of: ' . implode(', ', self::VALID_TYPES), 'INVALID_TYPE', 400);
        }
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return ApiResponse::error('url must be a valid URL', 'INVALID_URL', 400);
        }
        if ($apiKey === '') {
            return ApiResponse::error('api_key is required', 'MISSING_FIELD', 400);
        }
        if ($timeout < 5 || $timeout > 120) {
            $timeout = 15;
        }

        $id = ApichanSourceChat::create([
            'name'       => $name,
            'type'       => $type,
            'url'        => rtrim($url, '/'),
            'api_key'    => EncryptionHelper::encrypt($apiKey),
            'timeout'    => $timeout,
            'created_by' => $userId,
        ]);

        if (!$id) {
            return ApiResponse::error('Failed to create source', 'CREATE_FAILED', 500);
        }

        $source = ApichanSourceChat::getById($id, $userId);
        unset($source['api_key']);

        return ApiResponse::success(['source' => $source], 'Source created', 201);
    }

    public function update(Request $request, int $id): Response
    {
        $userId = $this->currentUserId($request);
        $source = ApichanSourceChat::getById($id, $userId);
        if ($source === null) {
            return ApiResponse::error('Source not found', 'SOURCE_NOT_FOUND', 404);
        }

        $data   = json_decode($request->getContent(), true);
        $update = [];

        if (isset($data['name'])) {
            $name = trim($data['name']);
            if ($name === '') return ApiResponse::error('name cannot be empty', 'INVALID_FIELD', 400);
            $update['name'] = $name;
        }
        if (isset($data['type'])) {
            if (!in_array($data['type'], self::VALID_TYPES, true)) {
                return ApiResponse::error('Invalid type', 'INVALID_TYPE', 400);
            }
            $update['type'] = $data['type'];
        }
        if (isset($data['url'])) {
            $url = trim($data['url']);
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return ApiResponse::error('url must be a valid URL', 'INVALID_URL', 400);
            }
            $update['url'] = rtrim($url, '/');
        }
        if (isset($data['api_key']) && trim($data['api_key']) !== '') {
            $update['api_key'] = EncryptionHelper::encrypt(trim($data['api_key']));
        }
        if (isset($data['timeout'])) {
            $timeout = (int) $data['timeout'];
            $update['timeout'] = ($timeout >= 5 && $timeout <= 120) ? $timeout : 15;
        }

        if (empty($update)) {
            return ApiResponse::error('No fields to update', 'NO_UPDATE', 400);
        }

        ApichanSourceChat::update($id, $update, $userId);
        $updated = ApichanSourceChat::getById($id, $userId);
        unset($updated['api_key']);

        return ApiResponse::success(['source' => $updated], 'Source updated', 200);
    }

    public function delete(Request $request, int $id): Response
    {
        $userId = $this->currentUserId($request);
        $ok = ApichanSourceChat::delete($id, $userId);
        if (!$ok) {
            return ApiResponse::error('Source not found', 'SOURCE_NOT_FOUND', 404);
        }
        return ApiResponse::success(null, 'Source deleted', 200);
    }

    public function servers(Request $request, int $id): Response
    {
        $userId = $this->currentUserId($request);
        $source = ApichanSourceChat::getById($id, $userId);
        if ($source === null) {
            return ApiResponse::error('Source not found', 'SOURCE_NOT_FOUND', 404);
        }

        $page = max(1, (int) ($request->query->get('page') ?? 1));

        try {
            $client = new ExternalApiClient($source['type'], $source['url'], $source['api_key'], (int) $source['timeout']);
            $result = $client->listServers($page);
            return ApiResponse::success($result, 'Servers fetched', 200);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'EXTERNAL_API_ERROR', 502);
        }
    }

    public function test(Request $request): Response
    {
        $data    = json_decode($request->getContent(), true);
        $type    = $data['type'] ?? '';
        $url     = trim($data['url'] ?? '');
        $apiKey  = trim($data['api_key'] ?? '');
        $timeout = (int) ($data['timeout'] ?? 15);

        if (!in_array($type, self::VALID_TYPES, true) || $url === '' || $apiKey === '') {
            return ApiResponse::error('type, url, and api_key are required', 'MISSING_FIELD', 400);
        }

        try {
            $client = new ExternalApiClient($type, $url, EncryptionHelper::encrypt($apiKey), $timeout);
            $result = $client->listServers(1);
            return ApiResponse::success(['server_count' => $result['total'] ?? count($result['servers'] ?? [])], 'Connection successful', 200);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'CONNECTION_FAILED', 502);
        }
    }
}
