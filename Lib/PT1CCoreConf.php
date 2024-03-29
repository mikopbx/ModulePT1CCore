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

        $conf .= 'exten => 10000112,1,AGI(DialPlanAppsMikoPBX.php)' . "\n\t";
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

        $conf .= '[miko-ajam-originate]' . "\n";
        $conf .= 'include => internal-originate' . "\n\n";

        $conf .= '[miko-ajam-goto]' . "\n";
        $conf .= 'exten => _[0-9*#+a-zA-Z][0-9*#+a-zA-Z]!,1,Wait(0.2)' . "\n\t";
        $conf .=   'same => n,ExecIf($["${mikoContext}x" = "x"]?Set(mikoContext=all_peers))' . "\n\t";
        $conf .=   'same => n,ExecIf($["${ORIGINATE_SRC_CHANNEL}x" != "x"]?ChannelRedirect(${ORIGINATE_SRC_CHANNEL},${mikoContext},${EXTEN},1))' . "\n\t";
        $conf .=   'same => n,Hangup' . "\n";
        $conf .= 'exten => failed,1,Hangup' . "\n\n";

        $conf .= '[miko-ajam-spy]' . "\n";
        $conf .= 'exten => _[0-9*#+a-zA-Z][0-9*#+a-zA-Z]!,1,Answer()' . "\n\t";
        $conf .=   'same => n,ExecIf($["${SPY_ARGS}x" != "x"]?ChanSpy(${DST_CHANNEL},${SPY_ARGS}))' . "\n\t";
        $conf .=   'same => n,Hangup' . "\n\n";

        $conf .= '[miko-ajam-playback-mp3]' . "\n";
        $conf .= 'exten => _[0-9*#+a-zA-Z][0-9*#+a-zA-Z]!,1,Answer()' . "\n\t";
        $conf .=   'same => n,ExecIf($["${FILENAME}x" != "x"]?MP3Player(${FILENAME}))' . "\n";
        $conf .=   'same => n,Hangup' . "\n\n";

        return $conf;
    }

    /**
     * Кастомизация входящего контекста для конкретного маршрута.
     *
     * @param $rout_number
     *
     * @return string
     */
    public function generateIncomingRoutBeforeDial($rout_number): string
    {
        // Перехват на ответственного.
        return "\t".'same => n,UserEvent(Interception,CALLERID: ${CALLERID(num)},chan1c: ${CHANNEL},FROM_DID: ${FROM_DID})';
    }

    /**
     *  Process CoreAPI requests under root rights
     *
     * @param array $request
     *
     * @return PBXApiResult An object containing the result of the API call.
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
     * @RoutePrefix("/pbxcore/api")
     * @Get("/cdr/get_data")
     * @Get("/cdr/records")
     * @Post("/fax/upload")
     *
     * @return array
     */
    public function getPBXCoreRESTAdditionalRoutes(): array
    {
        return [
            [GetController::class, 'getDataAction', '/pbxcore/api/cdr/get_data', 'get', '/', true],
            [GetController::class, 'recordsAction', '/pbxcore/api/cdr/records', 'get', '/', true],
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
