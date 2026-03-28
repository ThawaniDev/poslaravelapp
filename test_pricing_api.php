<?php
$base = 'http://localhost:8000';

function apiGet(string $url): array {
    $ctx = stream_context_create(['http' => [
        'header' => "Accept: application/json\r\n",
        'timeout' => 5,
        'ignore_errors' => true,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    $status = 'ERR';
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('/HTTP\/[\d.]+ (\d+)/', $h, $m)) $status = $m[1];
    }
    return [$status, json_decode($body ?: '{}', true)];
}

// Plans
[$s, $d] = apiGet("$base/api/v2/subscription/plans");
echo "[plans] HTTP $s — " . count($d['data'] ?? []) . " plans\n";
foreach ($d['data'] ?? [] as $p) {
    echo "  * {$p['name']} ({$p['slug']}) — {$p['monthly_price']} SAR/mo | trial: {$p['trial_days']}d | ★: " . ($p['is_highlighted'] ? 'yes' : 'no') . "\n";
}

// Add-ons
[$s, $d] = apiGet("$base/api/v2/subscription/add-ons");
echo "\n[add-ons] HTTP $s — " . count($d['data'] ?? []) . " add-ons\n";
foreach ($d['data'] ?? [] as $a) {
    echo "  * {$a['name']} — {$a['monthly_price']} SAR/mo\n";
}

// Pricing page content
echo "\n[pricing content per slug]\n";
foreach (['starter', 'professional', 'enterprise'] as $slug) {
    [$s, $d] = apiGet("$base/api/v2/pricing/$slug");
    $hero = $d['data']['hero_title'] ?? '(missing)';
    $trial = $d['data']['trial_label'] ?? '(none)';
    $features = count($d['data']['feature_bullet_list'] ?? []);
    echo "  * /pricing/$slug → HTTP $s — hero: \"$hero\" | trial: \"$trial\" | features: $features\n";
}
