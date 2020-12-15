<?php
/*
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 9 2020
 */


namespace Modules\ModulePT1CCore\Lib;

use MikoPBX\Common\Models\PbxSettings;
use MikoPBX\Core\System\PBX;
use MikoPBX\Modules\Config\ConfigClass;
use MikoPBX\PBXCoreREST\Lib\PBXApiResult;
use Modules\ModulePT1CCore\Lib\RestAPI\Controllers\GetController;
use Modules\ModulePT1CCore\Lib\RestAPI\Controllers\PostController;

class PT1CCoreConf extends ConfigClass
{

    /**
     * Будет вызван после старта asterisk.
     * @throws \Exception
     */
    public function onAfterPbxStarted(): void
    {
        if (is_file('/var/etc/http_auth')) {
            return;
        }
        $user_name = md5(random_bytes(20));
        $pass      = md5(random_bytes(12));
        file_put_contents('/var/etc/http_auth', "{$user_name}:{$pass}");
    }


    /**
     * Генерация дополнительных контекстов.
     *
     * @return string
     */
    public function extensionGenContexts(): string
    {
        $PBXRecordCalls = $this->generalSettings['PBXRecordCalls'];
        $rec_options    = ($PBXRecordCalls === '1') ? 'r' : '';

        $conf = '';
        $conf .= "[miko_ajam]\n";
        $conf .= 'exten => 10000111,1,AGI(DialPlanAppsMikoPBX.php)' . "\n\t";
        $conf .= 'same => n,Answer()' . "\n\t";
        $conf .= 'same => n,Hangup()' . "\n\n";

        $conf .= 'exten => 10000104,1,Dial(LOCAL/${interception}@internal/n,${ChanTimeOut},tT)' . "\n\t";
        $conf .= 'same => n,ExecIf($["${DIALSTATUS}" = "ANSWER"]?Hangup())' . "\n\t";
        $conf .= 'same => n,Dial(LOCAL/${RedirectNumber}@internal/n,600,tT)' . "\n\t";
        $conf .= 'same => n,Hangup()' . "\n\n";

        $conf .= 'exten => 10000107,1,Answer()' . "\n\t";
        $conf .= 'same => n,Set(CHANNEL(hangup_handler_wipe)=hangup_handler_meetme,s,1)' . "\n\t";
        $conf .= 'same => n,AGI(cdr_connector.php,meetme_dial)' . "\n\t";
        $conf .= 'same => n,Set(CALLERID(num)=Conference_Room)' . "\n\t";
        $conf .= 'same => n,Set(CALLERID(name)=${mikoconfcid})' . "\n\t";
        $conf .= 'same => n,Meetme(${mikoidconf},' . $rec_options . '${mikoparamconf})' . "\n\t";
        $conf .= 'same => n,Hangup()' . "\n\n";

        $conf .= 'exten => 10000109,1,AGI(DialPlanAppsMikoPBX.php)' . "\n\t";
        $conf .= 'same => n,Answer()' . "\n\t";
        $conf .= 'same => n,Hangup()' . "\n\n";

        $conf .= 'exten => 10000222,1,AGI(DialPlanAppsMikoPBX.php)' . "\n\t";
        $conf .= 'same => n,Answer()' . "\n\t";
        $conf .= 'same => n,Hangup()' . "\n\n";

        $conf .= 'exten => 10000555,1,AGI(DialPlanAppsMikoPBX.php)' . "\n\t";
        $conf .= 'same => n,Answer()' . "\n\t";
        $conf .= 'same => n,Hangup()' . "\n\n";

        $conf .= 'exten => 10000666,1,AGI(DialPlanAppsMikoPBX.php)' . "\n\t";
        $conf .= 'same => n,Answer()' . "\n\t";
        $conf .= 'same => n,Hangup()' . "\n\n";

        $conf .= 'exten => 10000777,1,AGI(DialPlanAppsMikoPBX.php)' . "\n\t";
        $conf .= 'same => n,Answer()' . "\n\t";
        $conf .= 'same => n,Hangup()' . "\n\n";

        return $conf;
    }

    /**
     *  Process CoreAPI requests under root rights
     *
     * @param array $request
     *
     * @return PBXApiResult
     */
    public function moduleRestAPICallback(array $request): PBXApiResult
    {
        $res = new PBXApiResult();
        $res->processor = __METHOD__;
        $action = strtoupper($request['action']);

        if($action === 'CHECK'){
            $templateMain       = new PT1CCoreMain();
            $res                = $templateMain->checkModuleWorkProperly();
        }else{
            $res->success = false;
            $res->messages[] = 'API action not found in moduleRestAPICallback ModulePT1CCore';
        }

        return $res;
    }

    /**
     * Create additional Nginx locations from modules
     *
     */
    public function createNginxLocations(): string
    {
        $luaScriptPath = $this->moduleDir.'/Lib/http_get_variables.lua';
        return "location /pbxcore/api/miko_ajam/getvar {
            default_type 'text/plain';
            content_by_lua_file {$luaScriptPath};
            keepalive_timeout 0;
		}";
    }

    /**
     * Returns array of additional routes for PBXCoreREST interface from module
     *
     * [ControllerClass, ActionMethod, RequestTemplate, HttpMethod, RootUrl, NoAuth ]
     *
     * @return array
     * @example
     *  [[GetController::class, 'callAction', '/pbxcore/api/backup/{actionName}', 'get', '/', false],
     *  [PostController::class, 'callAction', '/pbxcore/api/backup/{actionName}', 'post', '/', false]]
     */
    public function getPBXCoreRESTAdditionalRoutes(): array
    {
        return [
            [GetController::class, 'getDataAction', '/pbxcore/api/cdr/get_data', 'get', '/', false],
            [GetController::class, 'recordsAction', '/pbxcore/api/cdr/records', 'get', '/', false],
            [PostController::class,'callAction',    '/pbxcore/api/fax/upload/{actionName}',   'post','/', true],
        ];
    }

    /**
     * Generates additional fail2ban jail conf rules
     *
     * @return string
     */
    public function generateFail2BanJails():string
    {
        return "[INCLUDES]\n" .
            "before = common.conf\n" .
            "[Definition]\n" .
            "_daemon = (authpriv.warn |auth.warn )?miko_ajam\n" .
            'failregex = ^%(__prefix_line)sFrom\s+<HOST>.\s+UserAgent:\s+[a-zA-Z0-9 \s\.,/:;\+\-_\)\(\[\]]*.\s+Fail\s+auth\s+http.$' . "\n" .
            '            ^%(__prefix_line)sFrom\s+<HOST>.\s+UserAgent:\s+[a-zA-Z0-9 \s\.,/:;\+\-_\)\(\[\]]*.\s+File\s+not\s+found.$' . "\n" .
            "ignoreregex =\n";
    }

    /**
     * Returns array of additional firewall rules for module
     *
     * @return array
     */
    public function getDefaultFirewallRules(): array
    {
        $defaultWeb      = PbxSettings::getValueByKey('WEBPort');
        $defaultWebHttps = PbxSettings::getValueByKey('WEBHTTPSPort');
        $ajamPort        = PbxSettings::getValueByKey('AJAMPort');
        $ajamPortTLS     = PbxSettings::getValueByKey('AJAMPortTLS');

        return [
            'ModulePT1CCore' => [
                'rules'     => [
                    ['portfrom' => $ajamPort,       'portto' => $ajamPort,        'protocol' => 'tcp', 'name' => 'PT1CAjamPort'],
                    ['portfrom' => $ajamPortTLS,    'portto' => $ajamPortTLS,     'protocol' => 'tcp', 'name' => 'PT1CAjamTlsPort'],
                    ['portfrom' => $defaultWeb,     'portto' => $defaultWeb,      'protocol' => 'tcp', 'name' => 'PT1CHTTPPort'],
                    ['portfrom' => $defaultWebHttps,'portto' => $defaultWebHttps, 'protocol' => 'tcp', 'name' => 'PT1CHTTPSPort'],
                ],
                'action'    => 'allow',
                'shortName' => 'CTI client 1.0',
            ],
        ];
    }

    /**
     * Process after disable action in web interface
     *
     * @return void
     */
    public function onAfterModuleDisable(): void
    {
        PBX::dialplanReload();
    }

    /**
     * Process after enable action in web interface
     *
     * @return void
     * @throws \Exception
     */
    public function onAfterModuleEnable(): void
    {
        $this->onAfterPbxStarted();
        PBX::dialplanReload();
    }

}
