<?php

namespace App\Livewire;

use Illuminate\Support\Facades\App;
use Livewire\Component;

class AdminLocaleSwitcher extends Component
{
    public string $locale;

    public function mount(): void
    {
        $this->locale = session('admin_locale', config('app.locale', 'en'));
    }

    public function switchLocale(string $locale): void
    {
        if (! in_array($locale, ['en', 'ar'])) {
            return;
        }

        session(['admin_locale' => $locale]);
        App::setLocale($locale);
        $this->locale = $locale;

        $this->redirect(request()->header('Referer') ?: '/admin', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin-locale-switcher');
    }
}
