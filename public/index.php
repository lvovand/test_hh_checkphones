<?php
declare(strict_types=1);

require __DIR__ . '/../src/Sync.php';
$cfg = require __DIR__ . '/../src/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;

function fetchOrders(array $cfg): array
{
    $ch = curl_init($cfg['source_url']);
    $headers = ['Accept: application/json'];
    if ($cfg['source_token'] !== '') {
        $headers[] = 'Authorization: Bearer ' . $cfg['source_token'];
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => $cfg['timeout'],
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($body === false || $code !== 200) {
        throw new RuntimeException("upstream HTTP $code");
    }
    $data = json_decode($body, true);
    if (!is_array($data) || !array_is_list($data)) {
        throw new RuntimeException('upstream: not a JSON array');
    }
    return $data;
}

try {
    // Каждый запуск — полная пересборка из источника; снимок перезаписывается целиком,
    // поэтому повторная синхронизация не плодит дубли.
    $result = Sync::process(fetchOrders($cfg));
    $json = json_encode($result, $flags);
    $tmp = $cfg['snapshot'] . '.tmp';
    if (@file_put_contents($tmp, $json, LOCK_EX) !== false) {
        @rename($tmp, $cfg['snapshot']);
    }
    echo $json;
} catch (Throwable $e) {
    // Источник недоступен — отдаём последний успешный результат.
    if (is_file($cfg['snapshot'])) {
        header('X-Data-Source: snapshot');
        readfile($cfg['snapshot']);
    } else {
        http_response_code(502);
        echo json_encode(['error' => $e->getMessage()], $flags);
    }
}
