<?php

declare(strict_types=1);

function request(string $url, string $method = 'GET', string $body = '', array $headers = []): array
{
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'content' => $body,
        'ignore_errors' => true,
        'timeout' => 5,
    ]]);
    $responseBody = file_get_contents($url, false, $context);
    $statusLine = $http_response_header[0] ?? '';
    preg_match('/\s(\d{3})\s/', $statusLine, $matches);
    return [(int)($matches[1] ?? 0), (string)$responseBody];
}

[$fileStatus, $fileBody] = request(
    'http://127.0.0.1/pbxcore/api/fax/upload/getFileContent',
    'POST',
    http_build_query(['filename' => '/etc/asterisk/manager.conf']),
    ['Content-Type: application/x-www-form-urlencoded']
);
if ($fileStatus !== 400 || strpos($fileBody, '[phpagi]') !== false) {
    fwrite(STDERR, "Unsafe file action was not rejected. HTTP {$fileStatus}.\n");
    exit(1);
}

$httpAuth = trim((string)file_get_contents('/var/etc/http_auth'));
$query = http_build_query([
    'channel' => "PJSIP/201-00000004\r\nAction: Command",
    'variables' => 'CHANNEL(linkedid),EXTEN',
]);
[$luaStatus, $luaBody] = request(
    'http://127.0.0.1/pbxcore/api/miko_ajam/getvar?' . $query,
    'GET',
    '',
    ['Authorization: Basic ' . base64_encode($httpAuth)]
);
if ($luaStatus !== 400) {
    $safeBody = preg_replace('/[^[:print:]\r\n\t]/', '?', $luaBody ?? '');
    fwrite(STDERR, "Lua AMI injection was not rejected. HTTP {$luaStatus}; body: " . substr($safeBody, 0, 300) . "\n");
    exit(1);
}

fwrite(STDOUT, "Remote HTTP security test passed.\n");
