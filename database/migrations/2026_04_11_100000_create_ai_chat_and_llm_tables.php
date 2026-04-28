<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── LLM Models Registry ─────────────────────────────
        Schema::create('ai_llm_models', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider', 20)->index();        // openai | anthropic | google
            $table->string('model_id', 80);                 // e.g. gpt-4o, claude-sonnet-4-20250514
            $table->string('display_name', 120);             // e.g. GPT-4o
            $table->string('description', 500)->nullable();
            $table->string('api_key_encrypted', 500)->nullable(); // per-model API key
            $table->boolean('supports_vision')->default(false);
            $table->boolean('supports_json_mode')->default(true);
            $table->integer('max_context_tokens')->default(128000);
            $table->integer('max_output_tokens')->default(4096);
            $table->decimal('input_price_per_1m', 10, 4)->default(0);   // $ per 1M input tokens
            $table->decimal('output_price_per_1m', 10, 4)->default(0);  // $ per 1M output tokens
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_default')->default(false);   // one default per provider
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['provider', 'model_id']);
        });

        // ─── Chat Conversations ──────────────────────────────
        Schema::create('ai_chats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->uuid('store_id')->index();
            $table->uuid('user_id')->index();
            $table->string('title', 200)->nullable();         // auto-generated from first msg
            $table->uuid('llm_model_id')->nullable();         // selected model
            $table->integer('message_count')->default(0);
            $table->integer('total_tokens')->default(0);
            $table->decimal('total_cost_usd', 10, 6)->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('llm_model_id')->references('id')->on('ai_llm_models')->nullOnDelete();
        });

        // ─── Chat Messages ───────────────────────────────────
        Schema::create('ai_chat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('chat_id')->index();
            $table->uuid('organization_id')->nullable()->index();
            $table->uuid('store_id')->nullable()->index();
            $table->string('role', 20);                      // user | assistant | system
            $table->text('content');                          // message text
            $table->string('feature_slug', 80)->nullable();  // which AI feature was invoked
            $table->jsonb('feature_data')->nullable();        // structured feature result data
            $table->jsonb('attachments')->nullable();         // [{type, url, name, mime}]
            $table->string('model_used', 80)->nullable();
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->integer('latency_ms')->default(0);
            $table->timestamps();

            $table->foreign('chat_id')->references('id')->on('ai_chats')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
        Schema::dropIfExists('ai_chats');
        Schema::dropIfExists('ai_llm_models');
    }
};
