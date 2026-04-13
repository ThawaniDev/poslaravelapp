<?php

namespace App\Filament\Pages;

use App\Domain\WameedAI\Models\AIChat;
use App\Domain\WameedAI\Models\AIChatMessage;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class WameedAIChats extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.wameed-ai-chats';

    public ?string $selectedChatId = null;

    public ?string $filterStoreId = null;

    public string $filterDateFrom = '';

    public string $filterDateTo = '';

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_ai');
    }

    public static function getNavigationLabel(): string
    {
        return __('nav.ai_chats');
    }

    public function getTitle(): string
    {
        return 'AI Chat Analytics';
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['wameed_ai.view', 'wameed_ai.manage']);
    }

    public function selectChat(string $chatId): void
    {
        $this->selectedChatId = $chatId;
    }

    public function clearSelection(): void
    {
        $this->selectedChatId = null;
    }

    public function getViewData(): array
    {
        // Summary stats
        $totalChats = AIChat::count();
        $todayChats = AIChat::whereDate('created_at', today())->count();
        $totalMessages = AIChatMessage::count();
        $avgMessagesPerChat = $totalChats > 0 ? round($totalMessages / $totalChats, 1) : 0;

        // Chat list
        $chatsQuery = AIChat::with(['store:id,business_name', 'user:id,name'])
            ->withCount('messages')
            ->latest();

        if ($this->filterStoreId) {
            $chatsQuery->where('store_id', $this->filterStoreId);
        }
        if ($this->filterDateFrom) {
            $chatsQuery->whereDate('created_at', '>=', $this->filterDateFrom);
        }
        if ($this->filterDateTo) {
            $chatsQuery->whereDate('created_at', '<=', $this->filterDateTo);
        }

        $chats = $chatsQuery->limit(50)->get();

        // Selected chat detail
        $selectedChat = null;
        $chatMessages = collect();
        if ($this->selectedChatId) {
            $selectedChat = AIChat::with(['store:id,business_name', 'user:id,name'])
                ->find($this->selectedChatId);
            if ($selectedChat) {
                $chatMessages = AIChatMessage::where('chat_id', $this->selectedChatId)
                    ->orderBy('created_at')
                    ->get();
            }
        }

        return compact(
            'totalChats', 'todayChats', 'totalMessages', 'avgMessagesPerChat',
            'chats', 'selectedChat', 'chatMessages',
        );
    }
}
