<?php
/*
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 8 2020
 */

namespace Modules\ModulePT1CCore\Lib\RestAPI\Controllers;
use MikoPBX\Core\System\BeanstalkClient;
use MikoPBX\Core\Workers\WorkerCdr;
use MikoPBX\PBXCoreREST\Controllers\BaseController;

class GetController extends BaseController
{
    /**
     * Последовательная загрузка данных из cdr таблицы.
     * /pbxcore/api/cdr/getData MIKO AJAM
     * curl 'http://127.0.0.1:80/pbxcore/api/cdr/get_data?offset=0&limit=1';
     * curl 'http://127.0.0.1:80/pbxcore/api/cdr/get_data?offset=0&limit=5&dst[0]=201&dst[1]=202';
     * curl 'http://127.0.0.1:80/pbxcore/api/cdr/get_data?offset=0&limit=5&src[0]=301';
     * curl 'http://127.0.0.1:80/pbxcore/api/cdr/get_data?offset=0&limit=5&dst[0]=201&src[0]=301';
     * curl 'http://127.0.0.1:80/pbxcore/api/cdr/get_data?offset=0&limit=5&format=json';
     * curl 'http://127.0.0.1:80/pbxcore/api/cdr/get_data?offset=0&limit=5&format=1c';
     */
    public function getDataAction(): void
    {
        $offset = $this->request->get('offset');
        $limit  = $this->request->get('limit');
        $limit  = ($limit > 2000) ? 2000 : $limit;
        $format = $this->request->get('format', 'string', 'xml');

        $conditions = 'id>:id:';
        $bind = ['id' => $offset];

        // Фильтр по dst_num
        $dst = $this->request->get('dst');
        if (is_array($dst) && !empty($dst)) {
            $conditions .= ' AND dst_num IN ({dst:array})';
            $bind['dst'] = array_values($dst);
        }

        // Фильтр по src_num
        $src = $this->request->get('src');
        if (is_array($src) && !empty($src)) {
            $conditions .= ' AND src_num IN ({src:array})';
            $bind['src'] = array_values($src);
        }

        $filter = [
            $conditions,
            'bind'                => $bind,
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
            if (is_string($result) && file_exists($result)) {
                $arr_data = json_decode(file_get_contents($result), true);
                @unlink($result);
            }
            if (!is_array($arr_data)) {
                $arr_data = [];
            }
            $output = $this->formatCdrOutput($arr_data, $format);
            $this->response->setContent($output);
        }
        $this->response->sendRaw();
    }

    /**
     * Форматирование CDR-данных в указанный формат.
     *
     * @param array  $arr_data массив CDR-записей
     * @param string $format   формат вывода: xml, json, 1c
     * @return string
     */
    private function formatCdrOutput(array $arr_data, string $format): string
    {
        switch ($format) {
            case 'json':
                return json_encode($arr_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            case '1c':
                $lines = [];
                foreach ($arr_data as $data) {
                    $keys   = implode(',', array_keys($data));
                    $values = [];
                    foreach ($data as $val) {
                        $values[] = '"' . str_replace('"', '""', $val) . '"';
                    }
                    $lines[] = 'New Structure("' . $keys . '", ' . implode(', ', $values) . ')';
                }
                return implode("\n", $lines);
            default:
                $xml = "<?xml version=\"1.0\"?>\n";
                $xml .= "<cdr-table-askozia>\n";
                foreach ($arr_data as $data) {
                    $attributes = '';
                    foreach ($data as $tmp_key => $tmp_val) {
                        $attributes .= sprintf('%s="%s" ', $tmp_key, rawurlencode($tmp_val));
                    }
                    $xml .= "<cdr-row $attributes />\n";
                }
                $xml .= '</cdr-table-askozia>';
                return $xml;
        }
    }

    /**
     * Получаем список файлов записей по идентификаторам.
     * curl 'http://127.0.0.1/pbxcore/api/cdr/records-path?id[]=mikopbx-1751624009.210&id[]=mikopbx-1751622380.183&id[]=mikopbx-1751377388.208'
     * @return void
     */
    public function getRecordsPathByIdAction(): void
    {
        $id = $this->request->get('id');
        if(is_string($id)){
            $id = [$id];
        }
        $result = array_fill_keys($id, []);
        $filter = [
            'columns' => 'linkedid,recordingfile,start,answer,src_num,dst_num',
            'linkedid IN ({linkedid:array}) AND recordingfile <> ""',
            'bind'                => [
                'linkedid' => $id
            ],
            'miko_result_in_file' => true,
        ];

        $client  = new BeanstalkClient(WorkerCdr::SELECT_CDR_TUBE);
        $message = $client->request(json_encode($filter), 2);
        if ($message === false) {
            $this->response->setContent('');
        } else {
            $filename   = json_decode($message, true);
            $arr_data = [];
            if (is_string($filename) && file_exists($filename)) {
                $arr_data = json_decode(file_get_contents($filename), true);
                @unlink($filename);
            }
            foreach ($arr_data as $cdrData){
                if(!file_exists($cdrData['recordingfile'])){
                    continue;
                }
                // Создаём объект DateTime
                $date = \DateTime::createFromFormat('Y-m-d H:i:s.u', $cdrData['start']);
                $formatted = $date->format('Y-m-d_H-i-s');
                $result[$cdrData['linkedid']][] = [
                    'file' => $cdrData['recordingfile'],
                    'start' => $formatted,
                    'src' => $cdrData['src_num'],
                    'dst' => $cdrData['dst_num']
                ];
            }

        }
        print_r(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->response->sendRaw();
    }

    /**
     * Скачивание записи разговора.
     * /pbxcore/api/cdr/records MIKO AJAM
     * curl 'http://172.16.156.223/pbxcore/api/cdr/records?view=/storage/usbdisk1/mikoziapbx/voicemailarchive/monitor/2018/05/05/16/mikozia-1525527966.4_oWgzQFMPRA.mp3'
     */
    /**
     * Allowed directories for serving recording files.
     */
    private const ALLOWED_RECORD_DIRS = [
        '/storage/usbdisk1/mikopbx/astspool/monitor/',
    ];

    public function recordsAction(): void
    {
        $filename  = $this->request->get('view');

        // Validate path: resolve symlinks and ensure it's within allowed directories
        $realPath = realpath($filename);
        if ($realPath === false || !$this->isPathAllowed($realPath)) {
            openlog('miko_ajam', LOG_PID | LOG_PERROR, LOG_AUTH);
            $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            syslog(LOG_WARNING, "From {$remoteAddr}. Denied access to: {$filename}");
            closelog();
            $this->sendError(403);
            return;
        }

        $extension = strtolower(substr(strrchr($realPath, '.'), 1));
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
            case 'webm':
                $type = 'audio/webm';
                break;
        }
        $size = filesize($realPath);
        if ( ! $size || $type === '') {
            openlog('miko_ajam', LOG_PID | LOG_PERROR, LOG_AUTH);
            $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            syslog(LOG_WARNING, "From {$remoteAddr}. File not found or invalid type.");
            closelog();
            $this->sendError(404);

            return;
        }

        $fp = fopen($realPath, 'rb');
        if ($fp) {
            $this->response->setHeader('Content-Description', 'audio file');
            $this->response->setHeader('Content-Disposition', 'attachment; filename=' . basename($realPath));
            $this->response->setHeader('Content-type', $type);
            $this->response->setHeader('Content-Transfer-Encoding', 'binary');
            $this->response->setHeader('X-Content-Type-Options', 'nosniff');
            $this->response->setContentLength($size);
            $this->response->sendHeaders();
            fpassthru($fp);
            fclose($fp);
        } else {
            $this->sendError(404);
        }
    }

    /**
     * Check if the resolved path is within allowed directories.
     */
    private function isPathAllowed(string $realPath): bool
    {
        foreach (self::ALLOWED_RECORD_DIRS as $allowedDir) {
            if (strpos($realPath, $allowedDir) === 0) {
                return true;
            }
        }
        return false;
    }
}