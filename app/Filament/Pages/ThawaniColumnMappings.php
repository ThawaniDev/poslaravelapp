<?php

namespace App\Filament\Pages;

use App\Domain\ThawaniIntegration\Models\ThawaniColumnMapping;
use App\Domain\ThawaniIntegration\Services\ThawaniService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ThawaniColumnMappings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?int $navigationSort = 4;
    protected static string $view = 'filament.pages.thawani-column-mappings';

    // Form state
    public ?string $editingId = null;
    public string $entityType = 'product';
    public string $thawaniField = '';
    public string $wameedField = '';
    public string $transformType = 'direct';
    public string $transformConfig = '';

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_integrations');
    }

    public static function getNavigationLabel(): string
    {
        return __('thawani.column_mappings');
    }

    public function getTitle(): string
    {
        return __('thawani.column_mappings');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['thawani.manage_config']);
    }

    public function saveMapping(): void
    {
        $data = [
            'entity_type' => $this->entityType,
            'thawani_field' => $this->thawaniField,
            'wameed_field' => $this->wameedField,
            'transform_type' => $this->transformType,
            'transform_config' => !empty($this->transformConfig) ? json_decode($this->transformConfig, true) : null,
        ];

        if ($this->editingId) {
            ThawaniColumnMapping::find($this->editingId)?->update($data);
            Notification::make()->title(__('thawani.mapping_updated'))->success()->send();
        } else {
            ThawaniColumnMapping::create($data);
            Notification::make()->title(__('thawani.mapping_created'))->success()->send();
        }

        $this->resetForm();
    }

    public function editMapping(string $id): void
    {
        $mapping = ThawaniColumnMapping::find($id);
        if (!$mapping) return;

        $this->editingId = $mapping->id;
        $this->entityType = $mapping->entity_type;
        $this->thawaniField = $mapping->thawani_field;
        $this->wameedField = $mapping->wameed_field;
        $this->transformType = $mapping->transform_type;
        $this->transformConfig = $mapping->transform_config ? json_encode($mapping->transform_config, JSON_PRETTY_PRINT) : '';
    }

    public function deleteMapping(string $id): void
    {
        ThawaniColumnMapping::find($id)?->delete();
        Notification::make()->title(__('thawani.mapping_deleted'))->success()->send();
    }

    public function seedDefaults(): void
    {
        $service = app(ThawaniService::class);
        $service->seedDefaultColumnMappings();
        Notification::make()->title(__('thawani.defaults_seeded'))->success()->send();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->entityType = 'product';
        $this->thawaniField = '';
        $this->wameedField = '';
        $this->transformType = 'direct';
        $this->transformConfig = '';
    }

    public function getViewData(): array
    {
        $mappings = ThawaniColumnMapping::orderBy('entity_type')
            ->orderBy('thawani_field')
            ->get();

        return [
            'mappings' => $mappings,
        ];
    }
}
