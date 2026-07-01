<?php

use App\App;
use App\Helpers\ApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;
use App\Addons\apichan\controllers\RemoteServerController;

return function (RouteCollection $routes): void {
    $app = App::getInstance(true);

    // List my remote servers
    $app->registerAuthRoute($routes, 'apichan-remote-index', '/api/apichan/remote-servers', function (Request $request) {
        return (new RemoteServerController())->index($request);
    });

    // Add a remote server
    $app->registerAuthRoute($routes, 'apichan-remote-add', '/api/apichan/remote-servers', function (Request $request) {
        return (new RemoteServerController())->add($request);
    }, ['POST']);

    // Remove a remote server
    $app->registerAuthRoute($routes, 'apichan-remote-remove', '/api/apichan/remote-servers/{id}', function (Request $request, array $args) {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) return ApiResponse::error('Invalid ID', 'INVALID_ID', 400);
        return (new RemoteServerController())->remove($request, $id);
    }, ['DELETE']);

    // Get server status/resources
    $app->registerAuthRoute($routes, 'apichan-remote-status', '/api/apichan/remote-servers/{id}/status', function (Request $request, array $args) {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) return ApiResponse::error('Invalid ID', 'INVALID_ID', 400);
        return (new RemoteServerController())->status($request, $id);
    });

    // Power action
    $app->registerAuthRoute($routes, 'apichan-remote-power', '/api/apichan/remote-servers/{id}/power', function (Request $request, array $args) {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) return ApiResponse::error('Invalid ID', 'INVALID_ID', 400);
        return (new RemoteServerController())->power($request, $id);
    }, ['POST']);

    // Send console command
    $app->registerAuthRoute($routes, 'apichan-remote-command', '/api/apichan/remote-servers/{id}/command', function (Request $request, array $args) {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) return ApiResponse::error('Invalid ID', 'INVALID_ID', 400);
        return (new RemoteServerController())->command($request, $id);
    }, ['POST']);

    // List files
    $app->registerAuthRoute($routes, 'apichan-remote-files', '/api/apichan/remote-servers/{id}/files', function (Request $request, array $args) {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) return ApiResponse::error('Invalid ID', 'INVALID_ID', 400);
        return (new RemoteServerController())->files($request, $id);
    });

    // Get file content
    $app->registerAuthRoute($routes, 'apichan-remote-file-content', '/api/apichan/remote-servers/{id}/files/content', function (Request $request, array $args) {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) return ApiResponse::error('Invalid ID', 'INVALID_ID', 400);
        return (new RemoteServerController())->fileContent($request, $id);
    });

    // Write file
    $app->registerAuthRoute($routes, 'apichan-remote-file-write', '/api/apichan/remote-servers/{id}/files/write', function (Request $request, array $args) {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) return ApiResponse::error('Invalid ID', 'INVALID_ID', 400);
        return (new RemoteServerController())->writeFile($request, $id);
    }, ['POST']);

    // List allocations
    $app->registerAuthRoute($routes, 'apichan-remote-allocations', '/api/apichan/remote-servers/{id}/allocations', function (Request $request, array $args) {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) return ApiResponse::error('Invalid ID', 'INVALID_ID', 400);
        return (new RemoteServerController())->allocations($request, $id);
    });

    // List schedules
    $app->registerAuthRoute($routes, 'apichan-remote-schedules', '/api/apichan/remote-servers/{id}/schedules', function (Request $request, array $args) {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) return ApiResponse::error('Invalid ID', 'INVALID_ID', 400);
        return (new RemoteServerController())->schedules($request, $id);
    });

    // List backups
    $app->registerAuthRoute($routes, 'apichan-remote-backups', '/api/apichan/remote-servers/{id}/backups', function (Request $request, array $args) {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) return ApiResponse::error('Invalid ID', 'INVALID_ID', 400);
        return (new RemoteServerController())->listBackups($request, $id);
    });

    // Create backup
    $app->registerAuthRoute($routes, 'apichan-remote-backups-create', '/api/apichan/remote-servers/{id}/backups', function (Request $request, array $args) {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) return ApiResponse::error('Invalid ID', 'INVALID_ID', 400);
        return (new RemoteServerController())->createBackup($request, $id);
    }, ['POST']);

    // Delete backup
    $app->registerAuthRoute($routes, 'apichan-remote-backups-delete', '/api/apichan/remote-servers/{id}/backups/{backupId}', function (Request $request, array $args) {
        $id       = (int) ($args['id'] ?? 0);
        $backupId = (string) ($args['backupId'] ?? '');
        if ($id <= 0 || $backupId === '') return ApiResponse::error('Invalid ID', 'INVALID_ID', 400);
        return (new RemoteServerController())->deleteBackup($request, $id, $backupId);
    }, ['DELETE']);

    // List databases
    $app->registerAuthRoute($routes, 'apichan-remote-databases', '/api/apichan/remote-servers/{id}/databases', function (Request $request, array $args) {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) return ApiResponse::error('Invalid ID', 'INVALID_ID', 400);
        return (new RemoteServerController())->databases($request, $id);
    });

    // Get WebSocket credentials
    $app->registerAuthRoute($routes, 'apichan-remote-websocket', '/api/apichan/remote-servers/{id}/websocket', function (Request $request, array $args) {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) return ApiResponse::error('Invalid ID', 'INVALID_ID', 400);
        return (new RemoteServerController())->websocket($request, $id);
    });

    // Delete files
    $app->registerAuthRoute($routes, 'apichan-remote-files-delete', '/api/apichan/remote-servers/{id}/files/delete', function (Request $request, array $args) {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) return ApiResponse::error('Invalid ID', 'INVALID_ID', 400);
        return (new RemoteServerController())->deleteFiles($request, $id);
    }, ['POST']);

    // Rename file
    $app->registerAuthRoute($routes, 'apichan-remote-files-rename', '/api/apichan/remote-servers/{id}/files/rename', function (Request $request, array $args) {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) return ApiResponse::error('Invalid ID', 'INVALID_ID', 400);
        return (new RemoteServerController())->renameFile($request, $id);
    }, ['PUT']);

    // Create folder
    $app->registerAuthRoute($routes, 'apichan-remote-files-mkdir', '/api/apichan/remote-servers/{id}/files/mkdir', function (Request $request, array $args) {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) return ApiResponse::error('Invalid ID', 'INVALID_ID', 400);
        return (new RemoteServerController())->createFolder($request, $id);
    }, ['POST']);

    // Compress files
    $app->registerAuthRoute($routes, 'apichan-remote-files-compress', '/api/apichan/remote-servers/{id}/files/compress', function (Request $request, array $args) {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) return ApiResponse::error('Invalid ID', 'INVALID_ID', 400);
        return (new RemoteServerController())->compressFiles($request, $id);
    }, ['POST']);

    // Decompress file
    $app->registerAuthRoute($routes, 'apichan-remote-files-decompress', '/api/apichan/remote-servers/{id}/files/decompress', function (Request $request, array $args) {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) return ApiResponse::error('Invalid ID', 'INVALID_ID', 400);
        return (new RemoteServerController())->decompressFile($request, $id);
    }, ['POST']);

    // Get startup configuration
    $app->registerAuthRoute($routes, 'apichan-remote-startup', '/api/apichan/remote-servers/{id}/startup', function (Request $request, array $args) {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) return ApiResponse::error('Invalid ID', 'INVALID_ID', 400);
        return (new RemoteServerController())->getStartup($request, $id);
    });

    // Update startup variable
    $app->registerAuthRoute($routes, 'apichan-remote-startup-variable', '/api/apichan/remote-servers/{id}/startup/variable', function (Request $request, array $args) {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) return ApiResponse::error('Invalid ID', 'INVALID_ID', 400);
        return (new RemoteServerController())->updateStartupVariable($request, $id);
    }, ['PUT']);
};
