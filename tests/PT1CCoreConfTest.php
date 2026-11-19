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

    // [miko-ajam-goto]: when the originated call is answered via PickupChan, take the
    // answered channel from the Local;2 bridge (one retry after a short wait), clear
    // its stale self-reference, then redirect it.
    $gotoStart = strpos($dialplan, '[miko-ajam-goto]');
    if ($gotoStart === false) {
        fwrite(STDERR, "Generated dialplan has no [miko-ajam-goto] context.\n");
        exit(1);
    }
    $gotoEnd = strpos($dialplan, 'exten => failed', $gotoStart);
    if ($gotoEnd === false) {
        fwrite(STDERR, "[miko-ajam-goto] has no 'exten => failed' terminator.\n");
        exit(1);
    }
    $goto = substr($dialplan, $gotoStart, $gotoEnd - $gotoStart);

    $fallback = 'same => n,ExecIf($["${ORIGINATE_SRC_CHANNEL}x" = "x"]?Set(ORIGINATE_SRC_CHANNEL=${IMPORT(${CUT(CHANNEL,\;,1)}\;2,BRIDGEPEER)}))';
    $steps = [
        'fallback' => $fallback,
        'retry wait' => 'same => n,ExecIf($["${ORIGINATE_SRC_CHANNEL}x" = "x"]?Wait(0.3))',
        'fallback retry' => $fallback,
        'clear' => 'same => n,ExecIf($["${ORIGINATE_SRC_CHANNEL}x" != "x"]?Set(EXPORT(${ORIGINATE_SRC_CHANNEL},ORIGINATE_SRC_CHANNEL)=))',
        'redirect' => 'same => n,ExecIf($["${ORIGINATE_SRC_CHANNEL}x" != "x"]?ChannelRedirect(${ORIGINATE_SRC_CHANNEL},${mikoContext},${EXTEN},1))',
    ];
    // Each step must follow the previous one.
    $offset = 0;
    foreach ($steps as $name => $line) {
        $pos = strpos($goto, $line, $offset);
        if ($pos === false) {
            fwrite(STDERR, "[miko-ajam-goto] is missing the '{$name}' step (or it is out of order).\n");
            exit(1);
        }
        $offset = $pos + strlen($line);
    }

    fwrite(STDOUT, "PT1CCoreConf pickup dialplan test passed.\n");
}
