<?php
// Quick debug script — delete after use
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $prompt = App\Domain\WameedAI\Models\AIPrompt::where('feature_slug', 'smart_search')
        ->where('is_active', true)->first();
    echo "Prompt found: " . ($prompt ? 'YES' : 'NO') . PHP_EOL;
    echo "response_format: " . $prompt->response_format->value . PHP_EOL;
    echo "max_tokens: " . $prompt->max_tokens . PHP_EOL;
    echo "temperature: " . $prompt->temperature . PHP_EOL;

    $key = config('openai.api_key');
    $hasKey = strlen(trim($key)) > 0;
    echo "API Key set: " . ($hasKey ? 'YES (' . strlen($key) . ' chars)' : 'NO — EMPTY') . PHP_EOL;

    if (!$hasKey) {
        echo "STOP: No API key configured. Set OPENAI_API_KEY in .env" . PHP_EOL;
        exit(1);
    }

    echo "Testing OpenAI API call..." . PHP_EOL;
    $result = OpenAI\Laravel\Facades\OpenAI::chat()->create([
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => 'Reply with exactly: OK'],
            ['role' => 'user', 'content' => 'Test'],
        ],
        'max_tokens' => 5,
    ]);
    echo "OpenAI response: " . $result->choices[0]->message->content . PHP_EOL;
    echo "Tokens used: " . $result->usage->totalTokens . PHP_EOL;
    echo "SUCCESS — OpenAI is working!" . PHP_EOL;
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
    echo "CLASS: " . get_class($e) . PHP_EOL;
    echo "FILE: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo "TRACE: " . $e->getTraceAsString() . PHP_EOL;
}
