<?php
/*
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 10 2020
 */

namespace Modules\ModulePT1CCore\Lib\RestAPI\Controllers;

use MikoPBX\Core\System\Util;
use MikoPBX\PBXCoreREST\Controllers\BaseController;

/**
 * /api/upload/{name}
 *   curl -F "file=@ModuleTemplate.zip" http://127.0.0.1/pbxcore/api/upload/module -H 'Cookie: XDEBUG_SESSION=PHPSTORM'
 *   curl -X POST -d '{"id": "1531474060"}' http://127.0.0.1/pbxcore/api/upload/status; -H 'Cookie:
 *   XDEBUG_SESSION=PHPSTORM'
 */
class PostController extends BaseController
{
    public function callAction($actionName): void
    {
        $data           = [];
        $data['result'] = 'ERROR';
        $data   = $this->request->getPost();

        if ($this->request->hasFiles() > 0) {
            $data = [
                'resumableFilename'    => $this->request->getPost('resumableFilename'),
                'resumableIdentifier'  => $this->request->getPost('resumableIdentifier'),
                'resumableChunkNumber' => $this->request->getPost('resumableChunkNumber'),
                'resumableTotalSize'   => $this->request->getPost('resumableTotalSize'),
                'resumableTotalChunks' => $this->request->getPost('resumableTotalChunks'),
            ];
            foreach ($this->request->getUploadedFiles() as $file) {
                $data['files'][]= [
                    'file_path' => $file->getTempName(),
                    'file_size' => $file->getSize(),
                    'file_error'=> $file->getError(),
                    'file_name' => $file->getName(),
                    'file_type' => $file->getType()
                ];
                if ($file->getError()) {
                    $data['data'] = 'error ' . $file->getError() . ' in file ' . $file->getTempName();
                    $this->sendError(400, $data['data']);
                    Util::sysLogMsg('UploadFile', 'error ' . $file->getError() . ' in file ' . $file->getTempName());
                    return;
                }
            }
            $actionName = 'uploadResumable';
        }
        $this->sendRequestToBackendWorker('upload', $actionName, $data);
    }

    public function sendRequestToBackendWorker($processor, $actionName, $payload = null, $modulename=''): void
    {
        $requestMessage = [
            'processor' => $processor,
            'data'      => $payload,
            'action'    => $actionName
        ];
        if ($processor==='modules'){
            $requestMessage['module'] = $modulename;
        }
        try {
            $message = json_encode($requestMessage, JSON_THROW_ON_ERROR);
            $response       = $this->di->getShared('beanstalkConnection')->request($message, 5, 0);
            if ($response !== false) {
                $response = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
                // $res_str = exec($gs_path.' -q -dNOPAUSE -dBATCH -sDEVICE=tiffg4 -sPAPERSIZE=a4 -g1680x2285 -sOutputFile=\''.escapeshellarg($tif_filename).'\' \''.escapeshellarg($pdf_filename).'\' > /dev/null 2>&1');
                $this->response->setPayloadSuccess($response);
            } else {
                $this->sendError(500);
            }
        } catch (\JsonException $e) {
            $this->sendError(400);
        }
    }

}