<?php

declare(strict_types=1);

namespace MikoPBX\Modules\Config {
    class ConfigClass
    {
        protected $generalSettings = ['PBXRecordCalls' => '0'];
        protected $moduleDir = '/module';
    }
}

namespace {
    require_once dirname(__DIR__) . '/Lib/PT1CCoreConf.php';

    $class = \Modules\ModulePT1CCore\Lib\PT1CCoreConf::class;
    if (!method_exists($class, 'generateManagerConf')) {
        fwrite(STDERR, "Module does not generate a dedicated AMI user.\n");
        exit(1);
    }

    $config = new class extends \Modules\ModulePT1CCore\Lib\PT1CCoreConf {
        private $testSecretFile;
        private $testHttpAuthFile;

        public function __construct()
        {
            $this->testSecretFile = tempnam(sys_get_temp_dir(), 'pt1c-ami-');
            unlink($this->testSecretFile);
            $this->testHttpAuthFile = tempnam(sys_get_temp_dir(), 'pt1c-http-');
            file_put_contents($this->testHttpAuthFile, 'user:password');
            chmod($this->testHttpAuthFile, 0600);
        }

        protected function getAmiSecretFile(): string
        {
            return $this->testSecretFile;
        }

        protected function getHttpAuthFile(): string
        {
            return $this->testHttpAuthFile;
        }

        protected function getNginxGroup()
        {
            return filegroup($this->testHttpAuthFile);
        }

        public function secretFile(): string
        {
            return $this->testSecretFile;
        }

        public function httpAuthFile(): string
        {
            return $this->testHttpAuthFile;
        }
    };

    $first = $config->generateManagerConf();
    $nginxLocation = $config->createNginxLocations();
    $httpAuthPermissions = fileperms($config->httpAuthFile()) & 0777;
    $secret = trim(file_get_contents($config->secretFile()));
    $permissions = fileperms($config->secretFile()) & 0777;
    $second = $config->generateManagerConf();
    unlink($config->secretFile());
    unlink($config->httpAuthFile());

    $expectations = [
        '[pt1ccore_getvar]',
        "secret={$secret}",
        'deny=0.0.0.0/0.0.0.0',
        'permit=127.0.0.1/255.255.255.255',
        'read=call',
        'write=call',
    ];
    foreach ($expectations as $expected) {
        if (strpos($first, $expected) === false) {
            fwrite(STDERR, "AMI config is missing: {$expected}\n");
            exit(1);
        }
    }
    if ($httpAuthPermissions !== 0640
        || strpos($nginxLocation, 'set $pt1c_authorization $http_authorization;') === false) {
        fwrite(STDERR, "Nginx location does not preserve Authorization for Lua.\n");
        exit(1);
    }
    if (!preg_match('/^[a-f0-9]{48}$/D', $secret) || $permissions !== 0600 || $first !== $second || strpos($first, 'phpagi') !== false) {
        fwrite(STDERR, "AMI secret is invalid, unstable, or still uses phpagi.\n");
        exit(1);
    }

    fwrite(STDOUT, "Dedicated AMI user test passed.\n");
}
