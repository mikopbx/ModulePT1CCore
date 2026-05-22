<?php

namespace Modules\ModulePT1CCore\Lib;

use MikoPBX\Modules\PbxExtensionBase;
use MikoPBX\PBXCoreREST\Lib\PBXApiResult;

class PT1CCoreMain extends PbxExtensionBase
{
    /**
     * Check something and answer over RestAPI
     *
     * @return PBXApiResult An object containing the result of the API call.
     */
    public function checkModuleWorkProperly(): PBXApiResult
    {
        $res = new PBXApiResult();
        $res->processor = __METHOD__;
        $res->success = true;
        return $res;
    }

}
