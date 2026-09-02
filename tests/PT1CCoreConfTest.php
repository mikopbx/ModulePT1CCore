<?php

declare(strict_types=1);

namespace MikoPBX\Modules\Config {
    class ConfigClass
    {
        protected $generalSettings = ['PBXRecordCalls' => '0'];
    }
}

namespace {
    require_once dirname(__DIR__) . '/Lib/PT1CCoreConf.php';

    $config = new \Modules\ModulePT1CCore\Lib\PT1CCoreConf();
    $dialplan = $config->extensionGenContexts();

    $expected = <<<'DIALPLAN'
exten => 10000105,1,ExecIf($["${PICKUP_CHAN_ID}x" = "x"]?Hangup())
	same => n,Set(pt1c_dnid=${EXTEN})
	same => n,PickupChan(${PICKUP_CHAN_ID})
	same => n,Hangup()
DIALPLAN;

    if (strpos($dialplan, $expected) === false) {
        fwrite(STDERR, "Generated dialplan does not contain the 10000105 full-channel pickup flow.\n");
        exit(1);
    }

    if (strpos($dialplan, 'PICKUP_EXTEN') !== false) {
        fwrite(STDERR, "Generated pickup flow still depends on PICKUP_EXTEN.\n");
        exit(1);
    }

    fwrite(STDOUT, "PT1CCoreConf pickup dialplan test passed.\n");
}
