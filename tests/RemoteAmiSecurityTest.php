<?php

declare(strict_types=1);

$secretFile = '/var/etc/pt1ccore_ami_secret';
$secret = is_file($secretFile) ? trim((string)file_get_contents($secretFile)) : '';
if (preg_match('/^[a-f0-9]{48}$/D', $secret) !== 1 || (fileperms($secretFile) & 0777) !== 0600) {
    fwrite(STDERR, "Dedicated AMI secret is missing, invalid, or has unsafe permissions.\n");
    exit(1);
}

$socket = fsockopen('127.0.0.1', 5038, $errorCode, $errorMessage, 2);
if ($socket === false) {
    fwrite(STDERR, "Cannot connect to AMI: {$errorCode} {$errorMessage}\n");
    exit(1);
}
stream_set_timeout($socket, 2);

function amiRequest($socket, string $request): string
{
    fwrite($socket, $request . "\r\n\r\n");
    $response = '';
    while (!feof($socket)) {
        $line = fgets($socket);
        if ($line === false || $line === "\r\n") {
            break;
        }
        $response .= $line;
    }
    return $response;
}

fgets($socket);
$login = amiRequest($socket, "Action: Login\r\nUsername: pt1ccore_getvar\r\nSecret: {$secret}\r\nEvents: off");
if (strpos($login, 'Response: Success') === false) {
    fwrite(STDERR, "Dedicated AMI login failed.\n");
    exit(1);
}

$getVar = amiRequest($socket, "Action: GetVar\r\nVariable: SYSTEMNAME");
if (strpos($getVar, 'Response: Success') === false) {
    fwrite(STDERR, "GetVar is not permitted for the dedicated AMI user.\n");
    exit(1);
}

$command = amiRequest($socket, "Action: Command\r\nCommand: core show version");
if (strpos($command, 'Permission denied') === false) {
    fwrite(STDERR, "Privileged AMI Command was not denied.\n");
    exit(1);
}

amiRequest($socket, 'Action: Logoff');
fclose($socket);
fwrite(STDOUT, "Remote AMI least-privilege test passed.\n");
