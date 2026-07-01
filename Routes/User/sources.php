<?php

use App\App;
use App\Helpers\ApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;
use App\Addons\apichan\controllers\UserSourcesController;

return function (RouteCollection $routes): void {
    $app = App::getInstance(true);

    $app->registerAuthRoute($routes, 'apichan-user-sources-index', '/api/apichan/user/sources', function (Request $request) {
        return (new UserSourcesController())->index($request);
    });

    $app->registerAuthRoute($routes, 'apichan-user-sources-create', '/api/apichan/user/sources', function (Request $request) {
        return (new UserSourcesController())->create($request);
    }, ['POST']);

    $app->registerAuthRoute($routes, 'apichan-user-sources-test', '/api/apichan/user/sources/test', function (Request $request) {
        return (new UserSourcesController())->test($request);
    }, ['POST']);

    $app->registerAuthRoute($routes, 'apichan-user-sources-update', '/api/apichan/user/sources/{id}', function (Request $request, array $args) {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) return ApiResponse::error('Invalid ID', 'INVALID_ID', 400);
        return (new UserSourcesController())->update($request, $id);
    }, ['PATCH']);

    $app->registerAuthRoute($routes, 'apichan-user-sources-delete', '/api/apichan/user/sources/{id}', function (Request $request, array $args) {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) return ApiResponse::error('Invalid ID', 'INVALID_ID', 400);
        return (new UserSourcesController())->delete($request, $id);
    }, ['DELETE']);

    $app->registerAuthRoute($routes, 'apichan-user-sources-servers', '/api/apichan/user/sources/{id}/servers', function (Request $request, array $args) {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) return ApiResponse::error('Invalid ID', 'INVALID_ID', 400);
        return (new UserSourcesController())->servers($request, $id);
    });
};
