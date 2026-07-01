<?php

namespace App\Addons\apichan\controllers;

use App\Helpers\ApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Addons\apichan\chat\ApichanSourceChat;
use App\Addons\apichan\services\EncryptionHelper;
use App\Addons\apichan\services\ExternalApiClient;

class SourcesController
{
    private const VALID_TYPES = ['pterodactyl', 'featherpanel', 'pelican', 'calagopus'];

    public function index(Request $request): Response
    {
        $sources = ApichanSourceChat::getAll();

        return ApiResponse::success(['sources' => $sources], 'Sources fetched', 200);
    }

    public function show(Request $request, int $id): Response
    {
        $source = ApichanSourceChat::getById($id);
        if ($source === null) {
            return ApiResponse::error('Source not found', 'SOURCE_NOT_FOUND', 404);
        }

        unset($source['api_key']);

        return ApiResponse::success(['source' => $source], 'Source fetched', 200);
    }

    public function create(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ApiResponse::error('Invalid JSON', 'INVALID_JSON', 400);
        }

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

        $user = $request->attributes->get('user');
        $userId = (int) ($user['id'] ?? 0);
        if ($userId === 0) {
            return ApiResponse::error('Authentication required', 'AUTH_REQUIRED', 401);
        }

        $id = ApichanSourceChat::create([
            'name'       => $name,
            'type'       => $type,
            'url'        => $url,
            'api_key'    => EncryptionHelper::encrypt($apiKey),
            'timeout'    => $timeout,
            'created_by' => $userId,
        ]);

        if (!$id) {
            return ApiResponse::error('Failed to create source', 'CREATE_FAILED', 500);
        }

        $source = ApichanSourceChat::getById($id);
        unset($source['api_key']);

        return ApiResponse::success(['source' => $source], 'Source created', 201);
    }

    public function update(Request $request, int $id): Response
    {
        $source = ApichanSourceChat::getById($id);
        if ($source === null) {
            return ApiResponse::error('Source not found', 'SOURCE_NOT_FOUND', 404);
        }

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ApiResponse::error('Invalid JSON', 'INVALID_JSON', 400);
        }

        $update = [];

        if (isset($data['name'])) {
            $name = trim($data['name']);
            if ($name === '') {
                return ApiResponse::error('name cannot be empty', 'INVALID_FIELD', 400);
            }
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
            $update['url'] = $url;
        }

        if (isset($data['api_key']) && trim($data['api_key']) !== '') {
            $update['api_key'] = EncryptionHelper::encrypt(trim($data['api_key']));
        }

        if (isset($data['timeout'])) {
            $t = (int) $data['timeout'];
            $update['timeout'] = ($t >= 5 && $t <= 120) ? $t : 15;
        }

        if (empty($update)) {
            return ApiResponse::error('No fields to update', 'NO_DATA', 400);
        }

        if (!ApichanSourceChat::update($id, $update)) {
            return ApiResponse::error('Failed to update source', 'UPDATE_FAILED', 500);
        }

        $updated = ApichanSourceChat::getById($id);
        unset($updated['api_key']);

        return ApiResponse::success(['source' => $updated], 'Source updated', 200);
    }

    public function test(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ApiResponse::error('Invalid JSON', 'INVALID_JSON', 400);
        }

        $type    = $data['type'] ?? '';
        $url     = trim($data['url'] ?? '');
        $apiKey  = trim($data['api_key'] ?? '');
        $timeout = (int) ($data['timeout'] ?? 15);

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

        try {
            $encryptedKey = EncryptionHelper::encrypt($apiKey);
            $client = new ExternalApiClient($type, $url, $encryptedKey, $timeout);
            $result = $client->listServers(1);
            $total  = $result['total'] ?? count($result['servers'] ?? []);
            return ApiResponse::success(
                ['server_count' => $total],
                "Connected! Found {$total} server(s).",
                200
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'CONNECTION_FAILED', 502);
        }
    }

    public function delete(Request $request, int $id): Response
    {
        if (ApichanSourceChat::getById($id) === null) {
            return ApiResponse::error('Source not found', 'SOURCE_NOT_FOUND', 404);
        }

        if (!ApichanSourceChat::delete($id)) {
            return ApiResponse::error('Failed to delete source', 'DELETE_FAILED', 500);
        }

        return ApiResponse::success([], 'Source deleted', 200);
    }

    public function listServers(Request $request, int $id): Response
    {
        $source = ApichanSourceChat::getById($id);
        if ($source === null) {
            return ApiResponse::error('Source not found', 'SOURCE_NOT_FOUND', 404);
        }

        $page = max(1, (int) $request->query->get('page', 1));

        try {
            $client = new ExternalApiClient(
                $source['type'],
                $source['url'],
                $source['api_key'],
                (int) $source['timeout']
            );

            $result = $client->listServers($page);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 'EXTERNAL_API_ERROR', 502);
        }

        return ApiResponse::success($result, 'Servers fetched from source', 200);
    }
}
