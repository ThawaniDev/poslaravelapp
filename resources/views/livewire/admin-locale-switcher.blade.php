<div class="flex items-center">
    @if ($locale === 'ar')
        <button
            wire:click="switchLocale('en')"
            class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold transition hover:bg-gray-100 dark:hover:bg-white/5"
            title="{{ __('ui.switch_to_english') }}"
        >
            <span class="text-base leading-none">🇬🇧</span>
            <span class="hidden sm:inline text-gray-700 dark:text-gray-300">EN</span>
        </button>
    @else
        <button
            wire:click="switchLocale('ar')"
            class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold transition hover:bg-gray-100 dark:hover:bg-white/5"
            title="{{ __('ui.switch_to_arabic') }}"
        >
            <span class="text-base leading-none">🇸🇦</span>
            <span class="hidden sm:inline text-gray-700 dark:text-gray-300">AR</span>
        </button>
    @endif
</div>
