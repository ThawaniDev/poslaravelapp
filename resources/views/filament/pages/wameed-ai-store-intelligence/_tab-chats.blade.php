            {{-- Chat Stats --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-5">
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.total_chats') }}</p>
                        <p class="text-2xl font-bold text-primary-600">{{ number_format($chatData['chatStats']['totalChats']) }}</p>
                    </div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.total_messages') }}</p>
                        <p class="text-2xl font-bold text-info-600">{{ number_format($chatData['chatStats']['totalMessages']) }}</p>
                    </div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.avg_msgs_per_chat') }}</p>
                        <p class="text-2xl font-bold text-warning-600">{{ $chatData['chatStats']['avgMessagesPerChat'] }}</p>
                    </div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.chat_tokens') }}</p>
                        <p class="text-2xl font-bold text-gray-600 dark:text-gray-300">{{ number_format($chatData['chatStats']['totalTokens']) }}</p>
                    </div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai.chat_cost') }}</p>
                        <p class="text-2xl font-bold text-success-600">${{ number_format($chatData['chatStats']['totalCost'], 4) }}</p>
                    </div>
                </x-filament::section>
            </div>

            <div class="grid grid-cols-1 gap-4 mt-4 lg:grid-cols-2">
                {{-- Chat List --}}
                <x-filament::section heading="{{ __('ai.chats') }}">
                    <div class="divide-y divide-gray-100 dark:divide-gray-800 max-h-[32rem] overflow-y-auto">
                        @forelse ($chatData['chats'] as $chat)
                            <button
                                wire:click="selectChat('{{ $chat->id }}')"
                                @class([
                                    'w-full text-start px-3 py-2 transition hover:bg-gray-50 dark:hover:bg-white/5',
                                    'bg-primary-50 dark:bg-primary-500/10' => $selectedChatId === $chat->id,
                                ])
                            >
                                <div class="flex items-center justify-between">
                                    <span class="font-medium text-sm">{{ $chat->user?->name ?? __('ai.anonymous') }}</span>
                                    <span class="text-xs text-gray-400">{{ $chat->created_at->diffForHumans() }}</span>
                                </div>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-xs text-gray-400">{{ $chat->messages_count }} msg{{ $chat->messages_count !== 1 ? __('ai.msgs') : __('ai.msg') }}</span>
                                    @if ($chat->total_tokens)
                                        <span class="text-xs text-gray-400">· {{ number_format($chat->total_tokens) }} tokens</span>
                                    @endif
                                    @if ($chat->total_cost_usd)
                                        <span class="text-xs text-gray-400">· ${{ number_format($chat->total_cost_usd, 4) }}</span>
                                    @endif
                                </div>
                                @if ($chat->title)
                                    <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $chat->title }}</p>
                                @endif
                            </button>
                        @empty
                            <p class="px-3 py-8 text-center text-sm text-gray-400">{{ __('ai.no_chats_store') }}</p>
                        @endforelse
                    </div>
                </x-filament::section>

                {{-- Chat Messages --}}
                <x-filament::section :heading="$chatData['selectedChat'] ? ($chatData['selectedChat']->title ?? __('ai.chat_detail')) : __('ai.select_a_chat')">
                    @if ($chatData['selectedChat'])
                        <div class="mb-3 flex items-center gap-3 text-xs text-gray-500">
                            <span>{{ $chatData['selectedChat']->user?->name ?? __('ai.anonymous') }}</span>
                            <span>·</span>
                            <span>{{ $chatData['selectedChat']->created_at->format('M d, Y H:i') }}</span>
                            <button wire:click="clearChat" class="ms-auto text-danger-500 hover:text-danger-700">✕</button>
                        </div>
                        <div class="space-y-3 max-h-[28rem] overflow-y-auto">
                            @foreach ($chatData['chatMessages'] as $msg)
                                <div @class([
                                    'rounded-lg px-3 py-2 text-sm',
                                    'bg-gray-100 dark:bg-gray-800' => $msg->role === 'user',
                                    'bg-primary-50 dark:bg-primary-500/10' => $msg->role === 'assistant',
                                    'bg-warning-50 dark:bg-warning-500/10 text-xs italic' => $msg->role === 'system',
                                ])>
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-xs font-medium text-gray-500">{{ __('ai.' . $msg->role) }}</span>
                                        <span class="text-xs text-gray-400">{{ $msg->created_at->format('H:i:s') }}</span>
                                    </div>
                                    <div class="prose prose-sm dark:prose-invert max-w-none">{!! nl2br(e($msg->content)) !!}</div>
                                    @if ($msg->input_tokens || $msg->output_tokens)
                                        <p class="text-xs text-gray-400 mt-1">{{ number_format(($msg->input_tokens ?? 0) + ($msg->output_tokens ?? 0)) }} {{ __('ai.tokens') }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-center text-sm text-gray-400 py-12">{{ __('ai.select_chat_list') }}</p>
                    @endif
                </x-filament::section>
            </div>
