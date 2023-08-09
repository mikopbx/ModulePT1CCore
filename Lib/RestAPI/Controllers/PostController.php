<?php
/*
 * MikoPBX - free phone system for small business
 * Copyright © 2017-2023 Alexey Portnov and Nikolay Beketov
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <https://www.gnu.org/licenses/>.
 */

/*
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 10 2020
 */

namespace Modules\ModulePT1CCore\Lib\RestAPI\Controllers;

use MikoPBX\Common\Providers\BeanstalkConnectionWorkerApiProvider;
use MikoPBX\Core\System\Util;
use MikoPBX\PBXCoreREST\Controllers\BaseController;
use MikoPBX\PBXCoreREST\Lib\FilesManagementProcessor;
use MikoPBX\PBXCoreREST\Lib\PbxExtensionsProcessor;
use Pheanstalk\Pheanstalk;

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
            $actionName = 'uploadFile';
        }
        $this->sendRequestToBackendWorker(FilesManagementProcessor::class, $actionName, $data);
    }

    public function sendRequestToBackendWorker(  string $processor,
        string $actionName,
        $payload = null,
        string $modulename='',
        int $maxTimeout = 10,
        int $priority = Pheanstalk::DEFAULT_PRIORITY): void
    {
        $requestMessage = [
            'processor' => $processor,
            'data'      => $payload,
            'action'    => $actionName
        ];
        if ($processor === PbxExtensionsProcessor::class){
            $requestMessage['module'] = $modulename;
        }
        try {
            $message = json_encode($requestMessage, JSON_THROW_ON_ERROR);
            $response       = $this->di->getShared(BeanstalkConnectionWorkerApiProvider::SERVICE_NAME)->request($message, 5, 0);
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