<?php

use App\App;
use App\Permissions;
use App\Helpers\ApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;
use App\Addons\apichan\controllers\SourcesController;

return function (RouteCollection $routes): void {
    // Test a source connection (must be before /{id} routes to avoid conflict)
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'apichan-sources-test',
        '/api/apichan/sources/test',
        function (Request $request) {
            return (new SourcesController())->test($request);
        },
        Permissions::ADMIN_SERVERS_CREATE,
        ['POST']
    );

    // List all sources
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'apichan-sources-index',
        '/api/apichan/sources',
        function (Request $request) {
            return (new SourcesController())->index($request);
        },
        Permissions::ADMIN_SERVERS_CREATE,
    );

    // Create a source
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'apichan-sources-create',
        '/api/apichan/sources',
        function (Request $request) {
            return (new SourcesController())->create($request);
        },
        Permissions::ADMIN_SERVERS_CREATE,
        ['POST']
    );

    // Show a source
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'apichan-sources-show',
        '/api/apichan/sources/{id}',
        function (Request $request, array $args) {
            $id = $args['id'] ?? null;
            if (!$id || !is_numeric($id)) {
                return ApiResponse::error('Invalid ID', 'INVALID_ID', 400);
            }
            return (new SourcesController())->show($request, (int) $id);
        },
        Permissions::ADMIN_SERVERS_VIEW,
    );

    // Update a source
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'apichan-sources-update',
        '/api/apichan/sources/{id}',
        function (Request $request, array $args) {
            $id = $args['id'] ?? null;
            if (!$id || !is_numeric($id)) {
                return ApiResponse::error('Invalid ID', 'INVALID_ID', 400);
            }
            return (new SourcesController())->update($request, (int) $id);
        },
        Permissions::ADMIN_SERVERS_CREATE,
        ['PATCH']
    );

    // Delete a source
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'apichan-sources-delete',
        '/api/apichan/sources/{id}',
        function (Request $request, array $args) {
            $id = $args['id'] ?? null;
            if (!$id || !is_numeric($id)) {
                return ApiResponse::error('Invalid ID', 'INVALID_ID', 400);
            }
            return (new SourcesController())->delete($request, (int) $id);
        },
        Permissions::ADMIN_SERVERS_CREATE,
        ['DELETE']
    );

    // List servers from a source
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'apichan-sources-servers',
        '/api/apichan/sources/{id}/servers',
        function (Request $request, array $args) {
            $id = $args['id'] ?? null;
            if (!$id || !is_numeric($id)) {
                return ApiResponse::error('Invalid ID', 'INVALID_ID', 400);
            }
            return (new SourcesController())->listServers($request, (int) $id);
        },
        Permissions::ADMIN_SERVERS_CREATE,
    );

    // OPTIONS preflight for sources
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'apichan-sources-options',
        '/api/apichan/sources',
        function (Request $request) {
            return ApiResponse::success(null, 'OK', 200);
        },
        Permissions::ADMIN_SERVERS_VIEW,
        ['OPTIONS']
    );

    App::getInstance(true)->registerAdminRoute(
        $routes,
        'apichan-sources-options-id',
        '/api/apichan/sources/{id}',
        function (Request $request, array $args) {
            return ApiResponse::success(null, 'OK', 200);
        },
        Permissions::ADMIN_SERVERS_VIEW,
        ['OPTIONS']
    );
};
