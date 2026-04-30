<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace Preview – {{ $record->title }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen py-10 px-4">
@php
    $images = $record->preview_images ?? [];
    $tags = $record->tags ?? [];
    $pricing = match($record->pricing_type) {
        'free' => 'Free',
        'one_time' => number_format($record->price_amount, 2) . ' ' . ($record->price_currency ?? 'SAR'),
        'subscription' => number_format($record->price_amount, 2) . ' ' . ($record->price_currency ?? 'SAR') . ' / ' . ($record->subscription_interval ?? 'monthly'),
        default => '-',
    };
@endphp

<div class="max-w-4xl mx-auto space-y-6">
    {{-- Header --}}
    <div class="bg-white rounded-xl shadow p-6">
        <div class="flex items-start gap-6">
            @if(!empty($images))
                <img src="{{ $images[0] }}" alt="{{ $record->title }}" class="w-32 h-32 rounded-xl object-cover flex-shrink-0">
            @else
                <div class="w-32 h-32 rounded-xl bg-indigo-50 flex items-center justify-center flex-shrink-0">
                    <svg class="w-12 h-12 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                    </svg>
                </div>
            @endif
            <div class="flex-1">
                <h1 class="text-2xl font-bold text-gray-900">{{ $record->title }}</h1>
                @if($record->title_ar)
                    <p class="text-lg text-gray-500 mt-1" dir="rtl">{{ $record->title_ar }}</p>
                @endif
                <p class="text-sm text-gray-500 mt-2">{{ $record->short_description }}</p>
                <div class="flex items-center gap-3 mt-4 flex-wrap">
                    <span class="px-3 py-1 rounded-full text-sm font-semibold {{ $record->pricing_type === 'free' ? 'bg-green-100 text-green-800' : 'bg-indigo-100 text-indigo-800' }}">
                        {{ $pricing }}
                    </span>
                    @if($record->is_featured)
                        <span class="px-2 py-1 rounded text-xs font-medium bg-amber-100 text-amber-800">{{ __('ui.featured') }}</span>
                    @endif
                    @if($record->is_verified)
                        <span class="px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800">{{ __('ui.verified') }}</span>
                    @endif
                    <span class="px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-700">v{{ $record->version }}</span>
                    @if($record->category)
                        <span class="px-2 py-1 rounded text-xs font-medium bg-purple-100 text-purple-800">{{ $record->category->name }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Screenshot Gallery --}}
    @if(count($images) > 0)
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="font-semibold text-gray-900 mb-4">{{ __('ui.screenshots') }}</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                @foreach($images as $img)
                    <img src="{{ $img }}" alt="Preview" class="rounded-lg w-full aspect-video object-cover border border-gray-200">
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        {{-- Description --}}
        <div class="md:col-span-2 bg-white rounded-xl shadow p-6">
            <h2 class="font-semibold text-gray-900 mb-3">{{ __('ui.description') }}</h2>
            <div class="prose prose-sm text-gray-600 max-w-none">
                {!! nl2br(e($record->description ?? __('ui.no_description'))) !!}
            </div>
            @if($record->description_ar)
                <div class="mt-4 pt-4 border-t">
                    <h3 class="text-sm font-medium text-gray-500 mb-2">{{ __('ui.arabic_description') }}</h3>
                    <div class="prose prose-sm text-gray-600 max-w-none" dir="rtl">
                        {!! nl2br(e($record->description_ar)) !!}
                    </div>
                </div>
            @endif
        </div>

        {{-- Sidebar Info --}}
        <div class="space-y-4">
            {{-- Publisher --}}
            <div class="bg-white rounded-xl shadow p-4">
                <h3 class="text-sm font-medium text-gray-500 mb-2">{{ __('ui.publisher') }}</h3>
                <div class="flex items-center gap-3">
                    @if($record->publisher_avatar_url)
                        <img src="{{ $record->publisher_avatar_url }}" class="w-10 h-10 rounded-full object-cover">
                    @else
                        <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-gray-400">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>
                        </div>
                    @endif
                    <span class="text-sm font-medium text-gray-900">{{ $record->publisher_name ?? 'Unknown' }}</span>
                </div>
            </div>

            {{-- Stats --}}
            <div class="bg-white rounded-xl shadow p-4 space-y-3">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">{{ __('ui.rating') }}</span>
                    <span class="font-medium">
                        ⭐ {{ number_format($record->average_rating, 1) }}
                        <span class="text-gray-400">({{ $record->review_count }})</span>
                    </span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">{{ __('ui.downloads') }}</span>
                    <span class="font-medium">{{ number_format($record->download_count) }}</span>
                </div>
                @if($record->published_at)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">{{ __('ui.published') }}</span>
                        <span class="font-medium">{{ $record->published_at->format('M d, Y') }}</span>
                    </div>
                @endif
            </div>

            {{-- Tags --}}
            @if(!empty($tags))
                <div class="bg-white rounded-xl shadow p-4">
                    <h3 class="text-sm font-medium text-gray-500 mb-2">{{ __('ui.tags') }}</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach($tags as $tag)
                            <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{{ $tag }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($record->demo_video_url)
                <div class="bg-white rounded-xl shadow p-4">
                    <h3 class="text-sm font-medium text-gray-500 mb-2">{{ __('ui.demo_video') }}</h3>
                    <a href="{{ $record->demo_video_url }}" target="_blank" class="text-indigo-600 text-sm hover:underline flex items-center gap-1">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/></svg>
                        {{ __('ui.watch_demo') }}
                    </a>
                </div>
            @endif

            @if($record->changelog)
                <details class="bg-white rounded-xl shadow p-4">
                    <summary class="text-sm font-medium text-gray-500 cursor-pointer">{{ __('ui.changelog') }}</summary>
                    <div class="mt-2 prose prose-sm text-gray-600 max-w-none">
                        {!! nl2br(e($record->changelog)) !!}
                    </div>
                </details>
            @endif
        </div>
    </div>
</div>
</body>
</html>
