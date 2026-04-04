<?php

namespace App\Filament\Resources\OrganizationResource\Pages;

use App\Filament\Resources\OrganizationResource;
use App\Domain\Core\Enums\BusinessType;
use App\Domain\Core\Services\StoreService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;

class ListOrganizations extends ListRecords
{
    protected static string $resource = OrganizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('onboard_organization')
                ->label(__('Onboard New Organization'))
                ->icon('heroicon-o-rocket-launch')
                ->color('success')
                ->visible(fn () => auth('admin')->user()?->hasPermission('stores.create'))
                ->steps([
                    Forms\Components\Wizard\Step::make(__('Organization'))
                        ->icon('heroicon-o-building-office-2')
                        ->schema([
                            Forms\Components\TextInput::make('org_name')
                                ->label(__('Organization Name (EN)'))
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('org_name_ar')
                                ->label(__('Organization Name (AR)'))
                                ->maxLength(255),
                            Forms\Components\TextInput::make('cr_number')
                                ->label(__('CR Number'))
                                ->maxLength(50),
                            Forms\Components\TextInput::make('vat_number')
                                ->label(__('VAT Number'))
                                ->maxLength(50),
                            Forms\Components\TextInput::make('org_email')
                                ->label(__('Email'))
                                ->email(),
                            Forms\Components\TextInput::make('org_phone')
                                ->label(__('Phone'))
                                ->tel(),
                            Forms\Components\Select::make('country')
                                ->options([
                                    'SA' => __('Saudi Arabia'), 'OM' => 'Oman', 'AE' => 'UAE',
                                    'BH' => __('Bahrain'), 'KW' => 'Kuwait', 'QA' => 'Qatar',
                                ])
                                ->default('SA')
                                ->native(false),
                            Forms\Components\TextInput::make('city')->maxLength(100),
                        ])
                        ->columns(2),

                    Forms\Components\Wizard\Step::make(__('First Store'))
                        ->icon('heroicon-o-building-storefront')
                        ->schema([
                            Forms\Components\TextInput::make('store_name')
                                ->label(__('Store Name (EN)'))
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('store_name_ar')
                                ->label(__('Store Name (AR)'))
                                ->maxLength(255),
                            Forms\Components\Select::make('business_type')
                                ->options(BusinessType::class)
                                ->required()
                                ->native(false)
                                ->searchable(),
                            Forms\Components\TextInput::make('store_phone')
                                ->label(__('Store Phone'))
                                ->tel(),
                            Forms\Components\TextInput::make('store_email')
                                ->label(__('Store Email'))
                                ->email(),
                            Forms\Components\TextInput::make('store_city')
                                ->label(__('Store City'))
                                ->maxLength(100),
                            Forms\Components\Select::make('timezone')
                                ->options(collect(timezone_identifiers_list())
                                    ->filter(fn ($tz) => str_starts_with($tz, 'Asia/') || str_starts_with($tz, 'Europe/'))
                                    ->mapWithKeys(fn ($tz) => [$tz => $tz])
                                    ->toArray())
                                ->searchable()
                                ->default('Asia/Riyadh'),
                            Forms\Components\Select::make('currency')
                                ->options([
                                    'SAR' => __('SAR'), 'AED' => 'AED',
                                    'BHD' => __('BHD'), 'KWD' => 'KWD', 'QAR' => 'QAR',
                                ])
                                ->default('SAR')
                                ->native(false),
                        ])
                        ->columns(2),
                ])
                ->action(function (array $data) {
                    // Create Organization
                    $org = \App\Domain\Core\Models\Organization::create([
                        'name' => $data['org_name'],
                        'name_ar' => $data['org_name_ar'] ?? null,
                        'slug' => Str::slug($data['org_name']),
                        'cr_number' => $data['cr_number'] ?? null,
                        'vat_number' => $data['vat_number'] ?? null,
                        'email' => $data['org_email'] ?? null,
                        'phone' => $data['org_phone'] ?? null,
                        'country' => $data['country'] ?? 'SA',
                        'city' => $data['city'] ?? null,
                        'business_type' => $data['business_type'],
                        'is_active' => true,
                    ]);

                    // Create First Store via StoreService (auto-creates settings + working hours)
                    $storeService = app(StoreService::class);
                    $storeService->createStore([
                        'organization_id' => $org->id,
                        'name' => $data['store_name'],
                        'name_ar' => $data['store_name_ar'] ?? null,
                        'slug' => Str::slug($data['store_name']),
                        'business_type' => $data['business_type'],
                        'phone' => $data['store_phone'] ?? null,
                        'email' => $data['store_email'] ?? null,
                        'city' => $data['store_city'] ?? null,
                        'timezone' => $data['timezone'] ?? 'Asia/Riyadh',
                        'currency' => $data['currency'] ?? 'SAR',
                        'is_main_branch' => true,
                    ]);

                    Notification::make()
                        ->title("Organization '{$org->name}' onboarded with first store")
                        ->success()
                        ->send();
                }),
        ];
    }
}
