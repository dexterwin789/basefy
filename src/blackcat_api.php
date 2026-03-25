<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function blackcatRequest(string $method, string $path, ?array $payload = null): array
{
    if (BLACKCAT_API_KEY === '') {
        return [false, ['message' => 'BLACKCAT_API_KEY não configurada.']];
    }

    $url = rtrim(BLACKCAT_BASE_URL, '/') . '/' . ltrim($path, '/');

    $ch = curl_init($url);
    $headers = [
        'Content-Type: application/json',
        'X-API-Key: ' . BLACKCAT_API_KEY,
    ];

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ];

    if ($payload !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false || $err !== '') {
        return [false, ['message' => 'Falha de comunicação com BlackCat.', 'error' => $err]];
    }

    $json = json_decode($body, true);
    if (!is_array($json)) {
        return [false, ['message' => 'Resposta inválida da BlackCat.', 'raw' => $body, 'status' => $status]];
    }

    if ($status < 200 || $status >= 300 || ($json['success'] ?? false) !== true) {
        return [false, $json + ['statusCode' => $status]];
    }

    return [true, $json];
}

function blackcatCreatePixSale(array $payload): array
{
    return blackcatRequest('POST', '/sales/create-sale', $payload);
}

function blackcatGetSaleStatus(string $transactionId): array
{
    return blackcatRequest('GET', '/sales/' . rawurlencode($transactionId) . '/status');
}

function blackcatCreateWithdrawal(array $payload): array
{
    return blackcatRequest('POST', '/sales/create-withdrawal', $payload);
}
