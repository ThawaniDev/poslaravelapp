<?php

function testEndpoint(string $url, string $label): void {
    $context = stream_context_create(['http' => [
        'header'  => "Accept: application/json\r\n",
        'timeout' => 5,
        'ignore_errors' => true,
    ]]);

    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        echo "[$label] UNREACHABLE — server not running at $url\n";
        return;
    }

    $json = json_decode($body, true);

    // Get HTTP status from response headers stored in $http_response_header
    $statusLine = $http_response_header[0] ?? 'HTTP/1.1 ???';
    preg_match('/HTTP\/\S+ (\d+)/', $statusLine, $m);
    $statusCode = $m[1] ?? '???';

    if ($statusCode !== '200') {
        $msg = $json['message'] ?? substr($body, 0, 150);
        echo "[$label] HTTP $statusCode — $msg\n";
        return;
    }

    $data = $json['data'] ?? [];
    if (is_array($data) && isset($data[0])) {
        echo "[$label] HTTP 200 — " . count($data) . " item(s) returned:\n";
        foreach ($data as $item) {
            $name  = $item['name'] ?? ($item['question'] ?? '?');
            $slug  = isset($item['slug']) ? " ({$item['slug']})" : '';
            $price = isset($item['monthly_price'])
                ? (' — ' . ($item['monthly_price'] > 0 ? $item['monthly_price'] . ' SAR' : 'Custom'))
                : '';
            $star  = !empty($item['is_highlighted']) ? ' ★' : '';
            echo "    $name$slug$price$star\n";
        }
    } else {
        echo "[$label] HTTP 200 — response: " . substr($body, 0, 200) . "\n";
    }
}

$base = 'http://localhost:8000';

testEndpoint("$base/api/v2/subscription/plans",       'GET /api/v2/subscription/plans');
testEndpoint("$base/api/v2/pricing",                  'GET /api/v2/pricing');
testEndpoint("$base/api/v2/pricing/starter",          'GET /api/v2/pricing/starter');
testEndpoint("$base/api/v2/pricing/professional",     'GET /api/v2/pricing/professional');
testEndpoint("$base/api/v2/pricing/enterprise",       'GET /api/v2/pricing/enterprise');
