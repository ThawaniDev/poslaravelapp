<x-filament-panels::page>
    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Chats</p>
                <p class="text-3xl font-bold text-primary-600">{{ number_format($totalChats) }}</p>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Today's Chats</p>
                <p class="text-3xl font-bold text-info-600">{{ number_format($todayChats) }}</p>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Messages</p>
                <p class="text-3xl font-bold text-warning-600">{{ number_format($totalMessages) }}</p>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">Avg Messages/Chat</p>
                <p class="text-3xl font-bold text-success-600">{{ $avgMessagesPerChat }}</p>
            </div>
        </x-filament::section>
    </div>

    <div class="grid grid-cols-1 gap-4 mt-4 lg:grid-cols-2">
        {{-- Chat List --}}
        <x-filament::section heading="Chats">
            {{-- Filters --}}
            <div class="flex gap-2 mb-3">
                <input type="date" wire:model.live="filterDateFrom" class="rounded-lg border-gray-300 text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200" placeholder="From" />
                <input type="date" wire:model.live="filterDateTo" class="rounded-lg border-gray-300 text-sm dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200" placeholder="To" />
            </div>

            <div class="divide-y divide-gray-100 dark:divide-gray-800 max-h-[32rem] overflow-y-auto">
                @forelse ($chats as $chat)
                    <button
                        wire:click="selectChat('{{ $chat->id }}')"
                        @class([
                            'w-full text-start px-3 py-2 transition hover:bg-gray-50 dark:hover:bg-white/5',
                            'bg-primary-50 dark:bg-primary-500/10' => $selectedChatId === $chat->id,
                        ])
                    >
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-sm">{{ $chat->store?->business_name ?? 'Unknown' }}</span>
                            <span class="text-xs text-gray-400">{{ $chat->created_at->diffForHumans() }}</span>
                        </div>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-xs text-gray-500">{{ $chat->user?->name ?? 'Anonymous' }}</span>
                            <span class="text-xs text-gray-400">·</span>
                            <span class="text-xs text-gray-400">{{ $chat->messages_count }} msg{{ $chat->messages_count !== 1 ? 's' : '' }}</span>
                        </div>
                        @if ($chat->title)
                            <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $chat->title }}</p>
                        @endif
                    </button>
                @empty
                    <p class="px-3 py-8 text-center text-sm text-gray-400">No chats found</p>
                @endforelse
            </div>
        </x-filament::section>

        {{-- Chat Detail --}}
        <x-filament::section heading="{{ $selectedChat ? ($selectedChat->title ?? 'Chat Detail') : 'Select a chat' }}">
            @if ($selectedChat)
                <div class="mb-3 flex items-center gap-3 text-xs text-gray-500">
                    <span>{{ $selectedChat->store?->business_name }}</span>
                    <span>·</span>
                    <span>{{ $selectedChat->user?->name }}</span>
                    <span>·</span>
                    <span>{{ $selectedChat->created_at->format('M d, Y H:i') }}</span>
                    <button wire:click="clearSelection" class="ms-auto text-danger-500 hover:text-danger-700">✕</button>
                </div>

                <div class="space-y-3 max-h-[28rem] overflow-y-auto">
                    @foreach ($chatMessages as $msg)
                        <div @class([
                            'rounded-lg px-3 py-2 text-sm',
                            'bg-gray-100 dark:bg-gray-800' => $msg->role === 'user',
                            'bg-primary-50 dark:bg-primary-500/10' => $msg->role === 'assistant',
                            'bg-warning-50 dark:bg-warning-500/10 text-xs italic' => $msg->role === 'system',
                        ])>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-medium text-gray-500">{{ ucfirst($msg->role) }}</span>
                                <span class="text-xs text-gray-400">{{ $msg->created_at->format('H:i:s') }}</span>
                            </div>
                            <div class="prose prose-sm dark:prose-invert max-w-none">{!! nl2br(e($msg->content)) !!}</div>
                            @if ($msg->tokens_used)
                                <p class="text-xs text-gray-400 mt-1">{{ number_format($msg->tokens_used) }} tokens</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-center text-sm text-gray-400 py-12">Select a chat from the list to view messages</p>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
