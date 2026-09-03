<?php

declare(strict_types=1);

namespace MikoPBX\Core\System {
    class Util {}
}

namespace MikoPBX\PBXCoreREST\Controllers {
    class BaseController {}
}

namespace MikoPBX\PBXCoreREST\Lib {
    class FilesManagementProcessor {}
    class PbxExtensionsProcessor {}
}

namespace {
    require_once dirname(__DIR__) . '/Lib/RestAPI/Controllers/PostController.php';

    $class = \Modules\ModulePT1CCore\Lib\RestAPI\Controllers\PostController::class;
    if (!method_exists($class, 'normalizeFileAction')) {
        fwrite(STDERR, "PostController has no file-action allowlist.\n");
        exit(1);
    }

    $method = new ReflectionMethod($class, 'normalizeFileAction');
    $method->setAccessible(true);

    $cases = [
        ['uploadFile', true, 'uploadFile'],
        ['anything', true, 'uploadFile'],
        ['status', false, 'statusUploadFile'],
        ['statusUploadFile', false, 'statusUploadFile'],
        ['getFileContent', false, null],
        ['delete', false, null],
        ['update', false, null],
        ['downloadFirmware', false, null],
    ];

    foreach ($cases as [$action, $hasFiles, $expected]) {
        $actual = $method->invoke(null, $action, $hasFiles);
        if ($actual !== $expected) {
            fwrite(STDERR, "Unexpected normalization for {$action}.\n");
            exit(1);
        }
    }

    fwrite(STDOUT, "PostController file-action allowlist test passed.\n");
}
