<?php

namespace App\Domain\WameedAI\Services;

use App\Domain\WameedAI\Models\AIChat;
use App\Domain\WameedAI\Models\AIChatMessage;
use App\Domain\WameedAI\Models\AIFeatureDefinition;
use App\Domain\WameedAI\Models\AILlmModel;
use App\Domain\WameedAI\Services\AIGatewayService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AIChatService
{
    private const MAX_CONTEXT_MESSAGES = 50;

    public function __construct(
        private readonly AIGatewayService $gateway,
    ) {}

    /**
     * Create a new chat.
     */
    public function createChat(
        string $organizationId,
        string $storeId,
        string $userId,
        ?string $llmModelId = null,
        ?string $title = null,
    ): AIChat {
        $model = $llmModelId
            ? AILlmModel::enabled()->where('id', $llmModelId)->first()
            : null;

        // Fall back to default model if none found or none provided
        if (!$model) {
            $model = AILlmModel::enabled()->where('is_default', true)->first();
        }

        return AIChat::create([
            'organization_id' => $organizationId,
            'store_id' => $storeId,
            'user_id' => $userId,
            'title' => $title ?? 'New Chat',
            'llm_model_id' => $model?->id,
            'message_count' => 0,
            'total_tokens' => 0,
            'total_cost_usd' => 0,
            'last_message_at' => now(),
        ]);
    }

    /**
     * List chats for a user/store sorted by last message.
     */
    public function listChats(string $storeId, string $userId, int $perPage = 20): mixed
    {
        return AIChat::where('store_id', $storeId)
            ->where('user_id', $userId)
            ->with('llmModel:id,provider,model_id,display_name')
            ->orderByDesc('last_message_at')
            ->paginate($perPage);
    }

    /**
     * Get chat with messages.
     */
    public function getChat(string $chatId, string $userId): ?AIChat
    {
        return AIChat::where('id', $chatId)
            ->where('user_id', $userId)
            ->with(['messages' => fn ($q) => $q->orderBy('created_at'), 'llmModel'])
            ->first();
    }

    /**
     * Send a message in a chat and get AI response.
     */
    public function sendMessage(
        AIChat $chat,
        string $userMessage,
        ?string $featureSlug = null,
        ?array $featureData = null,
        ?string $imageBase64 = null,
        ?array $attachments = null,
    ): ?AIChatMessage {
        // 1. Save user message
        $userMsg = $chat->messages()->create([
            'role' => 'user',
            'content' => $userMessage,
            'feature_slug' => $featureSlug,
            'feature_data' => $featureData,
            'attachments' => $attachments,
            'model_used' => null,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cost_usd' => 0,
            'latency_ms' => 0,
        ]);

        // 2. Build conversation context
        $systemPrompt = $this->buildSystemPrompt($chat, $featureSlug);
        $conversationMessages = $this->buildConversationHistory($chat);

        // Add image if provided
        if ($imageBase64) {
            $lastIdx = count($conversationMessages) - 1;
            $conversationMessages[$lastIdx]['content'] = [
                ['type' => 'text', 'text' => $conversationMessages[$lastIdx]['content']],
                ['type' => 'image_url', 'image_url' => [
                    'url' => "data:image/jpeg;base64,{$imageBase64}",
                    'detail' => 'high',
                ]],
            ];
        }

        // 3. Call AI gateway with chat context
        $response = $this->gateway->chatCall(
            chat: $chat,
            conversationMessages: $conversationMessages,
            systemPrompt: $systemPrompt,
            imageBase64: null, // Already embedded in messages
            featureSlug: $featureSlug,
        );

        if (!$response) {
            // Save error message
            return $chat->messages()->create([
                'role' => 'assistant',
                'content' => 'I apologize, but I encountered an error processing your request. Please try again.',
                'model_used' => $chat->llmModel?->model_id ?? 'unknown',
                'input_tokens' => 0,
                'output_tokens' => 0,
                'cost_usd' => 0,
                'latency_ms' => 0,
            ]);
        }

        // 4. Save assistant message
        $assistantMsg = $chat->messages()->create([
            'role' => 'assistant',
            'content' => $response['content'],
            'feature_slug' => $featureSlug,
            'model_used' => $response['model'],
            'input_tokens' => $response['input_tokens'],
            'output_tokens' => $response['output_tokens'],
            'cost_usd' => $response['cost'],
            'latency_ms' => $response['latency_ms'],
        ]);

        // 5. Update chat stats (use query builder to avoid Eloquent cast issues with DB::raw)
        DB::table('ai_chats')->where('id', $chat->id)->update([
            'message_count' => DB::raw('message_count + 2'),
            'total_tokens' => DB::raw("total_tokens + {$response['input_tokens']} + {$response['output_tokens']}"),
            'total_cost_usd' => DB::raw("total_cost_usd + {$response['cost']}"),
            'last_message_at' => now(),
        ]);
        $chat->refresh();

        // 6. Auto-title on first message
        if ($chat->message_count <= 2 && $chat->title === 'New Chat') {
            $this->autoTitleChat($chat, $userMessage);
        }

        return $assistantMsg;
    }

    /**
     * Invoke a feature within chat context.
     */
    public function invokeFeatureInChat(
        AIChat $chat,
        string $featureSlug,
        array $featureParams,
    ): ?AIChatMessage {
        $feature = AIFeatureDefinition::where('slug', $featureSlug)->where('is_enabled', true)->first();
        if (!$feature) {
            return null;
        }

        $featureDescription = $feature->name ?? $featureSlug;
        $userMessage = "Use the {$featureDescription} feature with these parameters: " . json_encode($featureParams);

        return $this->sendMessage(
            chat: $chat,
            userMessage: $userMessage,
            featureSlug: $featureSlug,
            featureData: $featureParams,
        );
    }

    /**
     * Change the LLM model for a chat.
     */
    public function changeModel(AIChat $chat, string $llmModelId): bool
    {
        $model = AILlmModel::enabled()->where('id', $llmModelId)->first();
        if (!$model) return false;

        $chat->update(['llm_model_id' => $model->id]);
        return true;
    }

    /**
     * Delete (soft-delete) a chat.
     */
    public function deleteChat(AIChat $chat): bool
    {
        return $chat->delete();
    }

    /**
     * Get available LLM models.
     */
    public function getAvailableModels(): mixed
    {
        return AILlmModel::enabled()
            ->orderBy('sort_order')
            ->get(['id', 'provider', 'model_id', 'display_name', 'description', 'supports_vision', 'supports_json_mode', 'max_context_tokens', 'max_output_tokens', 'is_default']);
    }

    // ─── Private Helpers ─────────────────────────────────────

    private function buildSystemPrompt(AIChat $chat, ?string $featureSlug = null): string
    {
        try {
            return $this->buildEnrichedSystemPrompt($chat, $featureSlug);
        } catch (\Throwable $e) {
            Log::warning("buildSystemPrompt enrichment failed, using fallback: {$e->getMessage()}");
            return $this->buildFallbackSystemPrompt($chat, $featureSlug);
        }
    }

    private function buildFallbackSystemPrompt(AIChat $chat, ?string $featureSlug): string
    {
        $storeName = 'your store';
        $currency = 'OMR';
        try {
            $store = DB::selectOne("SELECT name, currency FROM stores WHERE id = ?", [$chat->store_id]);
            $storeName = $store->name ?? $storeName;
            $currency = $store->currency ?? $currency;
        } catch (\Throwable) {}

        $prompt = "You are Wameed AI, an intelligent POS assistant for \"{$storeName}\". Currency: {$currency}.\n"
            . "Respond in the same language the user writes in. Be concise and provide actionable recommendations.";

        if ($featureSlug) {
            $feature = AIFeatureDefinition::where('slug', $featureSlug)->first();
            if ($feature) {
                $prompt .= "\n\nThe user is using the '{$feature->name}' feature.";
            }
        }

        return $prompt;
    }

    private function buildEnrichedSystemPrompt(AIChat $chat, ?string $featureSlug): string
    {
        $storeId = $chat->store_id;
        $orgId = $chat->organization_id;

        // Precompute dates for database-agnostic queries
        $thirtyDaysAgo = now()->subDays(30)->toDateTimeString();
        $today = now()->toDateString();
        $thirtyDaysFromNow = now()->addDays(30)->toDateString();

        // ─── Store Info ─────────────────────────────────
        $store = DB::selectOne("SELECT name, name_ar, currency, city, timezone, is_main_branch FROM stores WHERE id = ?", [$storeId]);
        $storeName = $store->name ?? 'your store';
        $currency = $store->currency ?? 'OMR';
        $city = $store->city ?? '';
        $timezone = $store->timezone ?? 'Asia/Muscat';

        // Total branches in org
        $branchCount = DB::selectOne("SELECT COUNT(*) as cnt FROM stores WHERE organization_id = ?", [$orgId])->cnt ?? 1;

        // ─── Sales Snapshot (last 30 days) ──────────────
        $salesStats = DB::selectOne("
            SELECT
                COUNT(*) as total_transactions,
                COALESCE(SUM(total_amount), 0) as total_revenue,
                COALESCE(AVG(total_amount), 0) as avg_transaction,
                COALESCE(MAX(total_amount), 0) as max_transaction,
                COALESCE(SUM(discount_amount), 0) as total_discounts,
                COALESCE(SUM(tax_amount), 0) as total_tax
            FROM transactions
            WHERE store_id = ? AND status = 'completed' AND created_at >= ?
        ", [$storeId, $thirtyDaysAgo]);

        // Today's sales
        $todaySales = DB::selectOne("
            SELECT COUNT(*) as cnt, COALESCE(SUM(total_amount), 0) as total
            FROM transactions
            WHERE store_id = ? AND status = 'completed' AND created_at >= ?
        ", [$storeId, $today]);

        // ─── Top 10 Products (last 30 days by revenue) ──
        $topProducts = DB::select("
            SELECT ti.product_name, SUM(ti.quantity) as qty_sold, SUM(ti.line_total) as revenue
            FROM transaction_items ti
            JOIN transactions t ON t.id = ti.transaction_id
            WHERE t.store_id = ? AND t.status = 'completed' AND t.created_at >= ?
            GROUP BY ti.product_name
            ORDER BY revenue DESC
            LIMIT 10
        ", [$storeId, $thirtyDaysAgo]);

        $topProductsText = '';
        foreach ($topProducts as $i => $p) {
            $n = $i + 1;
            $qty = round($p->qty_sold, 1);
            $rev = number_format($p->revenue, 2);
            $topProductsText .= "  {$n}. {$p->product_name}: {$qty} units, {$currency} {$rev}\n";
        }

        // ─── Inventory Snapshot ─────────────────────────
        $inventory = DB::selectOne("
            SELECT
                COUNT(*) as total_skus,
                COALESCE(SUM(quantity), 0) as total_units,
                COUNT(CASE WHEN quantity <= reorder_point AND reorder_point > 0 THEN 1 END) as low_stock_count,
                COUNT(CASE WHEN quantity = 0 THEN 1 END) as out_of_stock_count
            FROM stock_levels
            WHERE store_id = ?
        ", [$storeId]);

        // Expiring within 30 days
        $expiringCount = DB::selectOne("
            SELECT COUNT(*) as cnt FROM stock_batches
            WHERE store_id = ? AND expiry_date IS NOT NULL AND expiry_date <= ? AND expiry_date >= ? AND quantity > 0
        ", [$storeId, $thirtyDaysFromNow, $today])->cnt ?? 0;

        // ─── Categories ─────────────────────────────────
        $categories = DB::select("
            SELECT name FROM categories WHERE organization_id = ? AND is_active = 1 AND parent_id IS NULL ORDER BY sort_order LIMIT 20
        ", [$orgId]);
        $categoryNames = implode(', ', array_map(fn($c) => $c->name, $categories));

        // ─── Customers ──────────────────────────────────
        $customers = DB::selectOne("
            SELECT
                COUNT(*) as total_customers,
                COALESCE(SUM(total_spend), 0) as lifetime_spend,
                COALESCE(AVG(visit_count), 0) as avg_visits,
                COUNT(CASE WHEN last_visit_at >= ? THEN 1 END) as active_30d
            FROM customers
            WHERE organization_id = ?
        ", [$thirtyDaysAgo, $orgId]);

        // ─── Staff ──────────────────────────────────────
        $staffCount = DB::selectOne("
            SELECT COUNT(*) as cnt FROM staff_users WHERE store_id = ? AND status = 'active'
        ", [$storeId])->cnt ?? 0;

        // ─── Products ───────────────────────────────────
        $productStats = DB::selectOne("
            SELECT
                COUNT(*) as total_products,
                COALESCE(AVG(sell_price), 0) as avg_price,
                COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_count
            FROM products
            WHERE organization_id = ?
        ", [$orgId]);

        // ─── Payment Methods (last 30 days) ─────────────
        $paymentMethods = DB::select("
            SELECT p.method, COUNT(*) as cnt, SUM(p.amount) as total
            FROM payments p
            JOIN transactions t ON t.id = p.transaction_id
            WHERE t.store_id = ? AND t.status = 'completed' AND t.created_at >= ?
            GROUP BY p.method ORDER BY total DESC
        ", [$storeId, $thirtyDaysAgo]);
        $paymentText = '';
        foreach ($paymentMethods as $pm) {
            $paymentText .= "  - {$pm->method}: {$pm->cnt} transactions, {$currency} " . number_format($pm->total, 2) . "\n";
        }

        // ─── Recent Expenses (last 30 days) ─────────────
        $expenses = DB::selectOne("
            SELECT COALESCE(SUM(amount), 0) as total_expenses, COUNT(*) as cnt
            FROM expenses WHERE store_id = ? AND expense_date >= ?
        ", [$storeId, $thirtyDaysAgo]);

        // ─── Active Promotions ──────────────────────────
        $activePromos = DB::selectOne("
            SELECT COUNT(*) as cnt FROM promotions
            WHERE organization_id = ? AND is_active = 1 AND (valid_to IS NULL OR valid_to >= ?)
        ", [$orgId, $today])->cnt ?? 0;

        // ─── Build Prompt ───────────────────────────────
        $prompt = <<<PROMPT
You are Wameed AI, an intelligent POS assistant for "{$storeName}" in {$city}. Timezone: {$timezone}. Currency: {$currency}.
The business has {$branchCount} branch(es).

═══ TODAY'S SNAPSHOT ═══
- Transactions today: {$todaySales->cnt}, Revenue: {$currency} {$this->fmt($todaySales->total)}

═══ LAST 30 DAYS SALES ═══
- Total transactions: {$salesStats->total_transactions}
- Total revenue: {$currency} {$this->fmt($salesStats->total_revenue)}
- Average transaction: {$currency} {$this->fmt($salesStats->avg_transaction)}
- Largest transaction: {$currency} {$this->fmt($salesStats->max_transaction)}
- Total discounts given: {$currency} {$this->fmt($salesStats->total_discounts)}
- Total tax collected: {$currency} {$this->fmt($salesStats->total_tax)}

═══ TOP SELLING PRODUCTS (30 days) ═══
{$topProductsText}
═══ INVENTORY STATUS ═══
- Total SKUs tracked: {$inventory->total_skus}
- Total units in stock: {$this->fmt($inventory->total_units, 0)}
- Products at/below reorder point: {$inventory->low_stock_count}
- Out of stock: {$inventory->out_of_stock_count}
- Expiring within 30 days: {$expiringCount}

═══ PRODUCT CATALOG ═══
- Total products: {$productStats->total_products} (inactive: {$productStats->inactive_count})
- Average sell price: {$currency} {$this->fmt($productStats->avg_price)}
- Categories: {$categoryNames}

═══ CUSTOMERS ═══
- Total customers: {$customers->total_customers}
- Active in last 30 days: {$customers->active_30d}
- Lifetime spend: {$currency} {$this->fmt($customers->lifetime_spend)}
- Average visits per customer: {$this->fmt($customers->avg_visits, 1)}

═══ STAFF & OPERATIONS ═══
- Active staff: {$staffCount}
- Active promotions: {$activePromos}

═══ PAYMENT METHODS (30 days) ═══
{$paymentText}
═══ EXPENSES (30 days) ═══
- Total expenses: {$currency} {$this->fmt($expenses->total_expenses)} ({$expenses->cnt} entries)

═══ GUIDELINES ═══
- Always respond in the SAME LANGUAGE the user writes in (Arabic or English).
- Use {$currency} for all monetary values.
- Be concise but thorough. Use tables, bullet points, and structured formatting.
- Provide actionable recommendations backed by the data above.
- If a question requires data you don't have, say so clearly.
- When comparing periods, note that your data covers the last 30 days.
PROMPT;

        if ($featureSlug) {
            $feature = AIFeatureDefinition::where('slug', $featureSlug)->first();
            if ($feature) {
                $prompt .= "\n\nThe user is using the '{$feature->name}' feature. Prioritize analysis related to this area.";
            }
        }

        return $prompt;
    }

    private function fmt(mixed $value, int $decimals = 2): string
    {
        return number_format((float) $value, $decimals);
    }

    private function buildConversationHistory(AIChat $chat): array
    {
        $messages = $chat->messages()
            ->orderBy('created_at')
            ->select(['role', 'content'])
            ->limit(self::MAX_CONTEXT_MESSAGES)
            ->get();

        return $messages->map(fn ($m) => [
            'role' => $m->role,
            'content' => $m->content,
        ])->toArray();
    }

    private function autoTitleChat(AIChat $chat, string $firstMessage): void
    {
        try {
            // Generate a short title from the first message
            $title = Str::limit($firstMessage, 50);
            $chat->update(['title' => $title]);
        } catch (\Throwable $e) {
            Log::warning("Failed to auto-title chat: {$e->getMessage()}");
        }
    }
}
