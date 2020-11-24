<?php
/*
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 8 2020
 */

namespace Modules\ModulePT1CCore\Lib\RestAPI\Controllers;
use MikoPBX\Core\System\BeanstalkClient;
use MikoPBX\Core\System\Util;
use MikoPBX\Core\Workers\WorkerCdr;
use MikoPBX\PBXCoreREST\Controllers\BaseController;

class GetController extends BaseController
{
    /**
     * Последовательная загрузка данных из cdr таблицы.
     * /pbxcore/api/cdr/getData MIKO AJAM
     * curl 'http://127.0.0.1:80/pbxcore/api/cdr/get_data?offset=0&limit=1';
     */
    public function getDataAction(): void
    {
        $offset = $this->request->get('offset');
        $limit  = $this->request->get('limit');
        $limit  = ($limit > 600) ? 600 : $limit;

        $filter = [
            'id>:id:',
            'bind'                => ['id' => $offset],
            'order'               => 'id',
            'limit'               => $limit,
            'miko_result_in_file' => true,
        ];

        $client  = new BeanstalkClient(WorkerCdr::SELECT_CDR_TUBE);
        $message = $client->request(json_encode($filter), 2);
        if ($message === false) {
            $this->response->setContent('');
        } else {
            $result   = json_decode($message, true);
            $arr_data = [];
            if (file_exists($result)) {
                $arr_data = json_decode(file_get_contents($result), true);
                @unlink($result);
            }
            $xml_output = "<?xml version=\"1.0\"?>\n";
            $xml_output .= "<cdr-table-askozia>\n";
            if (is_array($arr_data)) {
                foreach ($arr_data as $data) {
                    $attributes = '';
                    foreach ($data as $tmp_key => $tmp_val) {
                        $attributes .= sprintf('%s="%s" ', $tmp_key, rawurlencode($tmp_val));
                    }
                    $xml_output .= "<cdr-row $attributes />\n";
                }
            }
            $xml_output .= '</cdr-table-askozia>';
            $this->response->setContent($xml_output);
        }
        $this->response->sendRaw();
    }

    /**
     * Скачивание записи разговора.
     * /pbxcore/api/cdr/records MIKO AJAM
     * curl 'http://172.16.156.223/pbxcore/api/cdr/records?view=/storage/usbdisk1/mikoziapbx/voicemailarchive/monitor/2018/05/05/16/mikozia-1525527966.4_oWgzQFMPRA.mp3'
     */
    public function recordsAction(): void
    {
        $filename  = $this->request->get('view');
        $extension = strtolower(substr(strrchr($filename, '.'), 1));
        $type      = '';
        switch ($extension) {
            case 'mp3':
                $type = 'audio/mpeg';
                break;
            case 'wav':
                $type = 'audio/x-wav';
                break;
            case 'gsm':
                $type = 'audio/x-gsm';
                break;
        }
        $size = @filesize($filename);
        if ( ! $size || $type === '') {
            openlog('miko_ajam', LOG_PID | LOG_PERROR, LOG_AUTH);
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Undefined';
            syslog(LOG_WARNING, "From {$_SERVER['REMOTE_ADDR']}. UserAgent: ({$user_agent}). File not found.");
            closelog();
            $this->sendError(404);

            return;
        }

        $fp = fopen($filename, 'rb');
        if ($fp) {
            $this->response->setHeader('Content-Description', 'mp3 file');
            $this->response->setHeader('Content-Disposition', 'attachment; filename=' . basename($filename));
            $this->response->setHeader('Content-type', $type);
            $this->response->setHeader('Content-Transfer-Encoding', 'binary');
            $this->response->setContentLength($size);
            $this->response->sendHeaders();
            fpassthru($fp);
        } else {
            $this->sendError(404);
        }
    }
}