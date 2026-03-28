<?php
$base = 'http://localhost:8000';

function apiGet(string $url, int $timeout = 15): array {
    $ctx = stream_context_create(['http' => [
        'header' => "Accept: application/json\r\n",
        'timeout' => $timeout,
        'ignore_errors' => true,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    $status = 'ERR';
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('/HTTP\/[\d.]+ (\d+)/', $h, $m)) $status = $m[1];
    }
    return [$status, json_decode($body ?: '{}', true)];
}

echo "Testing pricing content endpoints (15s timeout each)...\n\n";
foreach (['starter', 'professional', 'enterprise'] as $slug) {
    [$s, $d] = apiGet("$base/api/v2/pricing/$slug");
    $hero = $d['data']['hero_title'] ?? '(missing)';
    $trial = $d['data']['trial_label'] ?? '(none)';
    $features = count($d['data']['feature_bullet_list'] ?? []);
    $faqs = count($d['data']['faq'] ?? []);
    echo "/pricing/$slug → HTTP $s\n";
    echo "  hero: \"$hero\"\n";
    echo "  trial: \"$trial\"\n";
    echo "  features: $features bullets, $faqs FAQs\n\n";
}
