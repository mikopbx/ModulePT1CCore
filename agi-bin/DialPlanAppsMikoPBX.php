#!/usr/bin/php -q
<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 5 2018
 *
 * Работа с историей звонков / записями разговоров.
 */

use MikoPBX\Common\Models\Extensions;
use MikoPBX\Common\Models\PbxSettings;
use MikoPBX\Common\Models\Sip;
use MikoPBX\Core\Asterisk\CdrDb;
use MikoPBX\Core\System\{BeanstalkClient, Processes, Util};
use MikoPBX\Core\Workers\WorkerCdr;
use MikoPBX\Core\Asterisk\AGI;

require_once 'Globals.php';

class DialPlanAppsMikoPBX
{
    private $callback;
    private array $vars;
    private $exten = null;
    /** @var AGI $agi */
    private $agi;

    /**
     * CDR_Data constructor.
     *
     * @param $argv
     *
     * @throws Exception
     */
    public function __construct($argv)
    {
        $this->callback = function ($message) {
            $this->q_done($message);
        };
        if (count($argv) === 1) {
            $this->agi = new AGI();
        } else {
            $this->exten = $argv[1];
            $AGI_class_name = Modules\ModulePT1CCore\Lib\AGIDebug::class;
            $this->agi = new $AGI_class_name($this->exten);
        }

        if (empty($this->exten)) {
            $this->exten = $this->agi->request['agi_extension'];
        }
        $this->get_vars();
        $this->exec_query();
        // После выполнения запроса q_done будет выполнен автоматически.
        $this->agi->answer();
    }

    /**
     * Метод вызывается по окончании выполнения запроса к NATS.
     * Обработка результата запроса.
     *
     * @param $q_res_data
     *
     * @throws Exception
     */
    private function q_done($q_res_data):void
    {
        if ('10000555' === $this->exten) {
            $res_data = json_decode($q_res_data, true, 512, JSON_THROW_ON_ERROR);
            $this->app_10000555($res_data);
        } elseif ('10000666' === $this->exten || '10000777' === $this->exten) {
            $res_data = json_decode($q_res_data, true, 512, JSON_THROW_ON_ERROR);
            $this->app_10000666_777($res_data);
        } elseif ('10000109' === $this->exten) {
            $res     = Extensions::findFirst("number='{$this->vars['number']}'");
            $context = ($res !== null) ? 'all_peers' : '';
            $this->UserEvent(
                "GetContest,chan1c:{$this->vars['tehnology']}/{$this->vars['number']},peercontext:{$context}"
            );
        } elseif ('10000111' === $this->exten) {
            $stat = file_get_contents('/var/etc/http_auth');
            $this->UserEvent(
                "AsteriskSettings,chan1c:{$this->vars['chan']},Statistic:{$stat},DefaultContext:all_peers,DialplanVer:1.0.0.8,usemp3player:1,autoanswernumber:*8"
            );
            $this->GetHints();
        } elseif ('10000222' === $this->exten) {
            $this->app_10000222();
        }
    }

    /**
     * Получение истории звонков.
     *
     * @param $res_data
     *
     * @throws Exception
     */
    private function app_10000555($res_data):void
    {
        $fields = [
            'start',
            'src_num',
            'dst_num',
            'src_chan',
            'dst_chan',
            'billsec',
            'disposition',
            'UNIQUEID',
            'recordingfile',
            'v1',
            'appname',
            'linkedid',
            'did',
        ];
        $result = "";
        $ch     = 0;
        foreach ($res_data as $_data) {
            $result .= ($result === "") ? "" : ".....";
            foreach ($fields as $key) {
                if ('start' === $key) {
                    $d      = new DateTime($_data[$key]);
                    $_field = $d->format("Y-m-d H:i:s");
                    $field  = trim(str_replace(" ", '\ ', $_field));
                } elseif (isset($_data[$key])) {
                    $field = trim(str_replace(" ", '\ ', $_data[$key]));
                } elseif ('appname' === $key) {
                    $field = 'Dial';
                } else {
                    $field = '';
                }
                $result .= $field . '@.@';
            }
            ++$ch;
            if ($ch >= 7) {
                $this->UserEvent("FromCDR,chan1c:{$this->vars['chan']},Date:{$this->vars['date1']},Lines:$result");
                $result = "";
                $ch     = 0;
            }
        }
        if ( $result !== "") {
            $this->UserEvent("FromCDR,chan1c:{$this->vars['chan']},Date:{$this->vars['date1']},Lines:$result");
        }
        $this->UserEvent("Refresh1CHistory,chan1c:{$this->vars['chan']},Date:{$this->vars['date1']}");
    }

    /**
     * Отправка ивента.
     *
     * @param $options
     */
    private function UserEvent($options):void
    {
        $this->agi->exec("UserEvent", $options);
    }

    /**
     * Обработка запроса скачивания / прослушивания.
     *
     * @param $res_data
     */
    private function app_10000666_777($res_data):void
    {
        $search_file = '';
        foreach ($res_data as $ar_str) {
            if (Util::recFileExists($ar_str['recordingfile'])) {
                $search_file .= ($search_file === "") ? '' : "@.@";
                $search_file .= $ar_str['recordingfile'];
            }
        }
        if ($search_file === '') {
            $search_file = $this->old_10000666_777($this->vars['id']);
        }
        if ($search_file !== '') {
            if ('10000777' === $this->exten) {
                $event = 'CallRecord';
            } else {
                $event = 'StartDownloadRecord';
            }
            $WEBPort = PbxSettings::getValueByKey('WEBPort');
            $path     = "{$WEBPort}/pbxcore/api/cdr/records?view=";
            $response = "{$event},chan1c:{$this->vars['chan']},fPath:{$path},FileName:{$search_file},uniqueid1c:{$this->vars['id']}";
        } else {
            $event = ('10000777' === $this->exten) ? 'CallRecordFail' : 'FailDownloadRecord';

            $response = "{$event},chan1c:{$this->vars['chan']},uniqueid1c:{$this->vars['id']}";
        }
        $this->UserEvent($response);
    }

    /**
     * Для совместимости со старой Askozia. Обращение к базе данных старого формата. //TODO::Удалить?
     * Попытка скачать запись со старой АТС.
     *
     * @param $id
     *
     * @return string
     */
    private function old_10000666_777($id):string
    {
        $filename = '/storage/usbdisk1/mikopbx/astlogs/asterisk/askozia_http_settings.php';
        if ( ! file_exists($filename)) {
            return '';
        }
        try {
            $settings = json_decode(file_get_contents($filename), true, 512, JSON_THROW_ON_ERROR);
        }catch (\Throwable $e){
            Util::sysLogMsg(__CLASS__, "File data '$filename' is not JSON",LOG_ERR);
            return '';
        }
        $host     = $settings['host']??'';
        $res      = $settings['res']??'';
        $auth     = $settings['auth']??'';

        $zapros = "SELECT" . " recordingfile FROM cdr WHERE linkedid = \"{$id}\" GROUP BY recordingfile";
        $output = [];
        $cdr_db = dirname(CdrDb::getPathToDB()) . '/master.db';
        if ( ! file_exists($cdr_db)) {
            return '';
        }
        exec("sqlite3 '$cdr_db' '{$zapros}'", $output);
        $arr_files = [];
        foreach ($output as $_data) {
            if (empty($_data)) {
                continue;
            }
            $fname = "{$_data}.wav";
            if (in_array($fname, $arr_files, true)) {
                // Файл уже обработали ранее успешно.
                continue;
            }
            if (file_exists("{$fname}.empty")) {
                // Уже пытались скачать файл. Файл не найден на другой АТС.
                continue;
            }
            if ( ! file_exists($fname)) {
                Util::mwMkdir(dirname($_data));
                exec("curl  -s -f 'http://{$host}{$res}{$_data}' -u {$auth} -I", $curl_output);
                if (stripos(implode('', $curl_output), 'attachment;') === false) {
                    file_put_contents("{$fname}.empty", '');
                    continue;
                }
                exec("curl -s -f 'http://{$host}{$res}{$_data}' -u {$auth} --output '{$fname}'");
                exec("/sbin/wav2mp3.sh '{$_data}'");
            }
            $arr_files[] = $fname;
        }

        return implode("@.@", $arr_files);
    }

    private function normalize_hint(&$str):void{
        $hint_val = '';
        $arr_val = explode('&', $str);
        foreach ($arr_val as $val){
            if( strrpos($val, 'SIP/') === FALSE &&
                strrpos($val, 'PJSIP/') === FALSE &&
                strrpos($val, 'IAX2/') === FALSE &&
                strrpos($val, 'DAHDI/') === FALSE){
                continue;
            }

            if($hint_val !== ''){
                continue;
            }
            $hint_val.=$val;
        }
        $str = $hint_val;
    }


    /**
     * Собирает инвормацию по хинтам и оповещает о них в UserEvent.
     */
    private function GetHints(): void
    {
        $arr_hints = [];
        $context   = 'internal-hints';
        $sipNumbers = $this->getSipPeers();
        exec(
            "asterisk -rx\"core show hints\" | grep -v egistered | grep State | awk -F'([ ]*[:]?[ ]+)|@' ' {print $1\"@{$context}\" \"@.@\" $3 \"@.@\" $4 \"@.@\" $1 } '",
            $arr_hints
        );
        $result = '';
        $count  = 1;
        foreach ($arr_hints as $hint_row) {
            if ($count >= 10) {
                $this->UserEvent("RowsHint,chan1c:{$this->vars['chan']},Lines:{$result}");
                $result = '';
                $count  = 1;
            }
            $rowData = explode('@.@', $hint_row);
            $contact = "";

            if( isset($sipNumbers[$rowData[3]]) ){
                $contact    = $this->agi->get_variable("PJSIP_AOR({$rowData[3]},contact)", true);
                $rowData[4] = $sipNumbers[$rowData[3]];
            }
            $this->normalize_hint($rowData[1]);
            if(!empty($contact)){
                $rowData[3] = rawurlencode($this->agi->get_variable("PJSIP_CONTACT($contact,user_agent)", true));
            }else{
                $rowData[3] = '';
            }

            $hint_row   = implode('@.@', $rowData);
            $result .= trim($hint_row).'.....';
            $count++;
        }
        if ($result !== '') {
            $this->UserEvent("RowsHint,chan1c:{$this->vars['chan']},Lines:{$result}");
        }
        $this->UserEvent("HintsEnd,chan1c:{$this->vars['chan']}");
    }

    /**
     * Возвращает массив внутренних номеров.
     * В качестве значения - разрешена ли запись.
     * Ключ - номер телефона.
     * @return array
     */
    private function getSipPeers(): array{
        $numbers = [];
        $filter = [
            'conditions' => 'type="peer"',
            'columns'    => 'extension,enableRecording',
        ];
        $peers = Sip::find($filter);
        foreach ($peers as $peer) {
            $numbers[$peer->extension] = ($peer->enableRecording=== '0')?0:1;
        }
        return $numbers;
    }

    /**
     * Работа со статусами в astdb.
     */
    private function app_10000222():void
    {
        $commands = ['CF', 'UserBuddyStatus', 'DND'];
        if ( ! in_array($this->vars['dbFamily'], $commands, true)) {
            $this->UserEvent("DB_ERR,user:{$this->vars['key']},status:{$this->vars['val']}");
        } elseif ($this->vars['command'] === 'get') {
            // Получение статуса конкретного пользователя
            $ret = $this->agi->evaluate("DATABASE GET {$this->vars['dbFamily']} {$this->vars['key']}");
            if ($ret['result'] === '1' && $ret['code'] === '200') {
                // Успех выполнения операции
                $this->UserEvent(
                    "DB_{$this->vars['dbFamily']},chan1c:{$this->vars['chan']},key:{$this->vars['key']},val:{$ret['data']}"
                );
            } else {
                // Не установлена!
                $this->UserEvent(
                    "DB_{$this->vars['dbFamily']},chan1c:{$this->vars['chan']},key:{$this->vars['key']},val:"
                );
            }
        } elseif ($this->vars['command'] === 'put') {
            if ($this->vars['val'] === '') {
                $ret = $this->agi->evaluate("DATABASE DEL {$this->vars['dbFamily']} {$this->vars['key']}");
            } else {
                // Установка статуса
                $ret = $this->agi->evaluate(
                    "DATABASE PUT {$this->vars['dbFamily']} {$this->vars['key']} {$this->vars['val']}"
                );
            }
            if ($ret['result'] === '1' && $ret['code'] === '200') {
                // Успех выполнения операции
                $this->UserEvent(
                    "DB_{$this->vars['dbFamily']},chan1c:{$this->vars['chan']},key:{$this->vars['key']},val:{$this->vars['val']}"
                );
            } else {
                // Были ошибки
                $this->UserEvent(
                    "Error_data_put_{$this->vars['dbFamily']},chan1c:{$this->vars['chan']},key:{$this->vars['key']},val:{$this->vars['val']}"
                );
            }
        } elseif ($this->vars['command'] === 'show') {
            $output = [];
            $result = '';

            // Получение статустов всех пользователей
            $dbFamily = escapeshellcmd($this->vars['dbFamily']);
            $asteriskPath = Util::which('asterisk');
            $grepPath = Util::which('grep');
            Processes::mwExec($asteriskPath.' -rx "database show ' . $dbFamily . '" | '.$grepPath.' /', $output);
            $ch = 0;
            // Обходим файл построчно
            foreach ($output as $_data) {
                // Набор символов - разделитель строк
                if ( $result !== "") {
                    $result .= ".....";
                }

                $_data = str_replace('/UserBuddyStatus/', '', $_data);

                $arr_data = explode(':', $_data);
                if (count($arr_data) !== 2) {
                    continue;
                }
                $key = trim($arr_data[0]);
                $key = str_replace('/', '.', $key);
                $key = urlencode($key);
                $key = str_replace('.', '/', $key);

                $val = urlencode(trim($arr_data[1]));

                $result .= "$key@.@$val";

                // Если необходимо отправляем данные порциями
                if ($ch === 20) {
                    // Отправляем данные в 1С, обнуляем буфер
                    $this->UserEvent("From{$this->vars['dbFamily']},chan1c:{$this->vars['chan']},Lines:$result");
                    $result = "";
                    $ch     = 1;
                }
                ++$ch;
            }
            // Проверяем, есть ли остаток данных для отправки
            if ( $result !== "") {
                $this->UserEvent("From{$this->vars['dbFamily']},chan1c:{$this->vars['chan']},Lines:$result");
            }
        }
    }

    /**
     * Получение переменных канала. Параметры запроса.
     */
    private function get_vars():void
    {
        if ('10000555' === $this->exten) {
            $this->vars['chan']  = $this->agi->get_variable("v1", true);
            $this->vars['date1'] = $this->agi->get_variable("v2", true);
            $this->vars['date2'] = $this->agi->get_variable("v3", true);

            $numbers               = explode("-", $this->agi->get_variable("v4", true));
            $this->vars['numbers'] = $numbers;
        } elseif ('10000666' === $this->exten) {
            $this->vars['chan']    = $this->agi->get_variable("v1", true);
            $this->vars['id']      = $this->agi->get_variable("v2", true);
            $this->vars['recfile'] = $this->agi->get_variable("v6", true);
        } elseif ('10000777' === $this->exten) {
            $this->vars['chan'] = $this->agi->get_variable("chan", true);
            $this->vars['id']   = $this->agi->get_variable("uniqueid1c", true);
        } elseif ('10000111' === $this->exten) {
            $this->vars['chan'] = $this->agi->get_variable("v1", true);
        } elseif ('10000109' === $this->exten) {
            $this->vars['tehnology'] = $this->agi->get_variable("tehnology", true);
            $this->vars['number']    = $this->agi->get_variable("number", true);
        } elseif ('10000112' === $this->exten) {
            // Обновление информации по имени файла записи
            $this->vars['filename']  = Util::trimExtensionForFile($this->agi->get_variable("v1", true)).'.mp3';
            $this->vars['chan']      = $this->agi->get_variable("v2", true);
            $this->vars['UNIQUEID']  = $this->agi->get_variable("IMPORT({$this->vars['chan']},pt1c_UNIQUEID)", true);
        } elseif ('10000222' === $this->exten) {
            $this->vars['command']  = $this->agi->get_variable("command", true);
            $this->vars['dbFamily'] = $this->agi->get_variable("dbFamily", true);
            $this->vars['key']      = $this->agi->get_variable("key", true);
            $this->vars['val']      = $this->agi->get_variable("val", true);
            $this->vars['chan']     = $this->agi->get_variable("chan", true);
        }
    }

    /**
     * Инициация запроса к NATS.
     *
     * @throws Exception
     */
    private function exec_query():void
    {
        $miko_result_in_file = false;
        if ('10000555' === $this->exten) {
            $miko_result_in_file           = true;
            $filter                        = [
                'start BETWEEN :date1: AND :date2: AND (src_num IN ({numbers:array}) OR dst_num IN ({numbers:array}) )',
                'bind'    => [
                    'numbers' => $this->vars['numbers'],
                    'date1'   => $this->vars['date1'],
                    'date2'   => $this->vars['date2'],
                ],
                'group'   => 'linkedid',
                'columns' => 'linkedid',
            ];
            $add_query                     = [
                'columns' => 'start,src_num,dst_num,src_chan,dst_chan,billsec,disposition,UNIQUEID,recordingfile,linkedid,did',
                'linkedid IN ({linkedid:array})',
                'bind'    => [
                    'linkedid' => null,
                ],
                'limit'   => 300,
            ];
            $filter['add_pack_query']      = $add_query;
            $filter['miko_result_in_file'] = $miko_result_in_file;
        } elseif ('10000666' === $this->exten || '10000777' === $this->exten) {
            $filter = [
                'linkedid=:linkedid: AND recordingfile<>""',
                'bind'    => ['linkedid' => $this->vars['id']],
                'limit'   => 10,
                'columns' => 'recordingfile',
            ];
        } elseif ('10000112' === $this->exten) {
            $data = [
                'UNIQUEID' => $this->vars['UNIQUEID'],
                'recordingfile' => $this->vars['filename']
            ];
            $clientQueue = new BeanstalkClient(WorkerCdr::SELECT_CDR_TUBE);
            $clientQueue->publish(json_encode($data), WorkerCdr::UPDATE_CDR_TUBE);
            $this->q_done(null);
            return;
        } elseif ('10000111' === $this->exten || '10000109' === $this->exten || '10000222' === $this->exten) {
            $this->q_done(null);
            return;
        } else {
            return;
        }

        $client  = new BeanstalkClient(WorkerCdr::SELECT_CDR_TUBE);
        $message = $client->request(json_encode($filter, JSON_THROW_ON_ERROR), 2);
        if (!$message) {
            Util::sysLogMsg('miko_ajam', "Error get data from queue 'WorkerCdr::SELECT_CDR_TUBE'. ", LOG_ERR);
            $this->q_done("[]");
        } else {
            $result_data = "[]";
            $result      = $client->getBody();
            if ($miko_result_in_file) {
                $filename = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
                if (file_exists($filename)) {
                    $result_data = file_get_contents($filename);
                    unlink($filename);
                }
            } else{
                $result_data = $result;
            }
            $this->q_done($result_data);
        }
    }
}

try {
    $d = new DialPlanAppsMikoPBX($argv);
} catch (\Throwable $e) {
    Util::sysLogMsg('miko_ajam', $e->getMessage(),LOG_ERR);
}