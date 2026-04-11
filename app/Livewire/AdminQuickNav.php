<?php

namespace App\Livewire;

use Filament\Facades\Filament;
use Livewire\Component;

class AdminQuickNav extends Component
{
    public bool $open = false;

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function navigateTo(string $url): void
    {
        $this->open = false;
        $this->redirect($url, navigate: true);
    }

    public function getGroupsProperty(): array
    {
        $navigation = Filament::getNavigation();
        $groups = [];

        foreach ($navigation as $group) {
            $label = $group->getLabel() ?? __('nav.ungrouped');
            $items = [];

            foreach ($group->getItems() as $item) {
                $items[] = [
                    'label' => $item->getLabel(),
                    'icon' => $item->getIcon(),
                    'url' => $item->getUrl(),
                    'isActive' => $item->isActive(),
                ];
            }

            if (! empty($items)) {
                $groups[] = [
                    'label' => $label,
                    'items' => $items,
                ];
            }
        }

        return $groups;
    }

    public function render()
    {
        return view('livewire.admin-quick-nav');
    }
}
