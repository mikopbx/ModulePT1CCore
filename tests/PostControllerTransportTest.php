<?php

declare(strict_types=1);

namespace MikoPBX\Core\System {
    class Util {}
}

namespace MikoPBX\PBXCoreREST\Controllers {
    class BaseController
    {
        public static $request;

        public function sendRequestToBackendWorker(
            string $processor,
            string $actionName,
            $payload = null,
            string $moduleName = '',
            int $maxTimeout = 30,
            int $priority = 0
        ): void {
            self::$request = [$processor, $actionName, $payload, $moduleName, $maxTimeout, $priority];
        }
    }
}

namespace MikoPBX\PBXCoreREST\Lib {
    class FilesManagementProcessor {}
    class PbxExtensionsProcessor {}
}

namespace {
    require_once dirname(__DIR__) . '/Lib/RestAPI/Controllers/PostController.php';

    $controller = new \Modules\ModulePT1CCore\Lib\RestAPI\Controllers\PostController();
    try {
        $controller->sendRequestToBackendWorker('processor', 'statusUploadFile', ['id' => 'safe']);
    } catch (Throwable $e) {
        fwrite(STDERR, "Controller did not delegate to the current BaseController transport.\n");
        exit(1);
    }

    $request = \MikoPBX\PBXCoreREST\Controllers\BaseController::$request;
    if ($request === null || $request[1] !== 'statusUploadFile' || $request[2] !== ['id' => 'safe']) {
        fwrite(STDERR, "Current BaseController transport was not called correctly.\n");
        exit(1);
    }

    fwrite(STDOUT, "PostController current transport delegation test passed.\n");
}
