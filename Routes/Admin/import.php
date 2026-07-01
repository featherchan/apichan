<?php

use App\App;
use App\Permissions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;
use App\Addons\apichan\controllers\ImportController;

return function (RouteCollection $routes): void {
    // Preview a server from an external source
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'apichan-import-preview',
        '/api/apichan/import/preview',
        function (Request $request) {
            return (new ImportController())->preview($request);
        },
        Permissions::ADMIN_SERVERS_CREATE,
        ['POST']
    );

    // Execute the import
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'apichan-import',
        '/api/apichan/import',
        function (Request $request) {
            return (new ImportController())->import($request);
        },
        Permissions::ADMIN_SERVERS_CREATE,
        ['POST']
    );
};
