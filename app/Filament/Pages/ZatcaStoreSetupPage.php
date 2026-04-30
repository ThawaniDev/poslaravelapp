<?php

namespace App\Filament\Pages;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\Core\Models\Store;
use App\Domain\Core\Models\StoreSettings;
use App\Domain\ZatcaCompliance\Models\ZatcaCertificate;
use App\Domain\ZatcaCompliance\Models\ZatcaDevice;
use App\Domain\ZatcaCompliance\Services\DeviceService;
use App\Domain\ZatcaCompliance\Services\ZatcaComplianceService;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ZatcaStoreSetupPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.pages.zatca-store-setup';

    protected static ?int $navigationSort = 12;

    protected static ?string $slug = 'zatca/store-setup';

    public ?string $store_id = null;

    public array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_zatca');
    }

    public static function getNavigationLabel(): string
    {
        return __('zatca.store_setup');
    }

    public function getTitle(): string
    {
        return __('zatca.store_setup');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['settings.credentials']);
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('zatca.select_store'))
                    ->schema([
                        Select::make('store_id')
                            ->label(__('zatca.store'))
                            ->options(fn () => Store::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn ($state) => $this->loadStoreConfig($state))
                            ->required(),
                    ]),

                Section::make(__('zatca.tax_identity'))
                    ->visible(fn (callable $get) => filled($get('store_id')))
                    ->schema([
                        TextInput::make('vat_number')
                            ->label(__('zatca.vat_number'))
                            ->helperText(__('zatca.vat_number_help'))
                            ->maxLength(15),
                        TextInput::make('cr_number')
                            ->label(__('zatca.cr_number'))
                            ->maxLength(20),
                        TextInput::make('business_name_ar')->label(__('zatca.business_name_ar')),
                        TextInput::make('business_name_en')->label(__('zatca.business_name_en')),
                        TextInput::make('city')->label(__('zatca.city')),
                        TextInput::make('district')->label(__('zatca.district')),
                        TextInput::make('street')->label(__('zatca.street')),
                        TextInput::make('building_number')->label(__('zatca.building_number')),
                        TextInput::make('postal_code')->label(__('zatca.postal_code')),
                    ])
                    ->columns(2),

                Section::make(__('zatca.integration'))
                    ->visible(fn (callable $get) => filled($get('store_id')))
                    ->schema([
                        Select::make('environment')
                            ->label(__('zatca.environment'))
                            ->options([
                                'developer-portal' => 'Developer Portal (test only)',
                                'simulation'       => 'Simulation (pre-production — real CCSID/PCSID)',
                                'production'       => 'Production (live)',
                            ])
                            ->default('simulation')
                            ->required(),
                        TextInput::make('otp')
                            ->label(__('zatca.otp'))
                            ->helperText(__('zatca.otp_help'))
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                        Toggle::make('auto_submit')
                            ->label(__('zatca.auto_submit'))
                            ->helperText(__('zatca.auto_submit_help'))
                            ->default(true),
                        Toggle::make('b2b_required')
                            ->label(__('zatca.b2b_required'))
                            ->default(false),
                    ])
                    ->columns(2),

                Section::make(__('zatca.notes'))
                    ->visible(fn (callable $get) => filled($get('store_id')))
                    ->collapsible()
                    ->schema([
                        Textarea::make('notes')->label(__('zatca.internal_notes'))->rows(3),
                    ]),
            ])
            ->statePath('data');
    }

    public function loadStoreConfig(?string $storeId): void
    {
        if (! $storeId) {
            $this->data = [];
            return;
        }
        $settings = StoreSettings::firstOrNew(['store_id' => $storeId]);
        $extra = $settings->extra ?? [];
        $zatca = $extra['zatca'] ?? [];
        $store = Store::find($storeId);
        $this->data = array_merge([
            'store_id' => $storeId,
            'vat_number' => $zatca['vat_number'] ?? $store?->vat_number,
            'cr_number' => $zatca['cr_number'] ?? $store?->cr_number,
            'business_name_ar' => $zatca['business_name_ar'] ?? $store?->name_ar,
            'business_name_en' => $zatca['business_name_en'] ?? $store?->name,
            'city' => $zatca['city'] ?? $store?->city,
            'district' => $zatca['district'] ?? null,
            'street' => $zatca['street'] ?? null,
            'building_number' => $zatca['building_number'] ?? null,
            'postal_code' => $zatca['postal_code'] ?? $store?->postal_code,
            'environment' => $zatca['environment'] ?? 'sandbox',
            'otp' => null,
            'auto_submit' => $zatca['auto_submit'] ?? true,
            'b2b_required' => $zatca['b2b_required'] ?? false,
            'notes' => $zatca['notes'] ?? null,
        ], $this->data['store_id'] === $storeId ? [] : []);
    }

    private function composeAddress(array $state): string
    {
        return trim(implode(', ', array_filter([
            $state['building_number'] ?? null,
            $state['street'] ?? null,
            $state['district'] ?? null,
            $state['city'] ?? null,
            $state['postal_code'] ?? null,
        ])));
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $storeId = $state['store_id'] ?? null;
        if (! $storeId) {
            Notification::make()->title(__('zatca.select_store_first'))->danger()->send();
            return;
        }

        $settings = StoreSettings::firstOrNew(['store_id' => $storeId]);
        $extra = $settings->extra ?? [];
        $existingZatca = $extra['zatca'] ?? [];

        $newOtp = $state['otp'] ?? null;
        $zatca = [
            'vat_number' => $state['vat_number'] ?? null,
            'cr_number' => $state['cr_number'] ?? null,
            'business_name_ar' => $state['business_name_ar'] ?? null,
            'business_name_en' => $state['business_name_en'] ?? null,
            'city' => $state['city'] ?? null,
            'district' => $state['district'] ?? null,
            'street' => $state['street'] ?? null,
            'building_number' => $state['building_number'] ?? null,
            'postal_code' => $state['postal_code'] ?? null,
            'environment' => $state['environment'] ?? 'sandbox',
            'auto_submit' => (bool) ($state['auto_submit'] ?? true),
            'b2b_required' => (bool) ($state['b2b_required'] ?? false),
            'notes' => $state['notes'] ?? null,
            'otp_set' => $newOtp ? true : ($existingZatca['otp_set'] ?? false),
            'updated_by' => auth('admin')->id(),
            'updated_at' => now()->toIso8601String(),
        ];
        if ($newOtp) {
            $zatca['otp_encrypted'] = encrypt($newOtp);
        } elseif (isset($existingZatca['otp_encrypted'])) {
            $zatca['otp_encrypted'] = $existingZatca['otp_encrypted'];
        }

        $extra['zatca'] = $zatca;
        $settings->store_id = $storeId;
        $settings->extra = $extra;
        $settings->save();

        // Also mirror the tax-identity fields onto the Store row itself,
        // because CertificateService builds the ZATCA CSR from those
        // canonical columns (Store.vat_number / Store.cr_number / etc.).
        $store = Store::find($storeId);
        if ($store) {
            $store->fill(array_filter([
                'vat_number' => $state['vat_number'] ?? null,
                'cr_number' => $state['cr_number'] ?? null,
                'address' => $this->composeAddress($state) ?: null,
                'city' => $state['city'] ?? null,
                'postal_code' => $state['postal_code'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''))->save();
        }

        AdminActivityLog::record(
            adminUserId: auth('admin')->id(),
            action: 'update_zatca_store_config',
            entityType: 'store',
            entityId: $storeId,
            details: ['environment' => $zatca['environment'], 'otp_updated' => (bool) $newOtp],
        );

        Notification::make()->title(__('zatca.config_saved'))->success()->send();
    }

    public function getCurrentDevice(): ?ZatcaDevice
    {
        if (! $this->store_id && ! ($this->data['store_id'] ?? null)) {
            return null;
        }
        $sid = $this->data['store_id'] ?? $this->store_id;
        return ZatcaDevice::where('store_id', $sid)->latest('created_at')->first();
    }

    public function getCurrentCertificate(): ?ZatcaCertificate
    {
        $sid = $this->data['store_id'] ?? $this->store_id;
        if (! $sid) return null;
        return ZatcaCertificate::where('store_id', $sid)->latest('issued_at')->first();
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Forms\Components\Actions\Action::make('save')
                ->label(__('zatca.save_config'))
                ->submit('save'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('setup_guide')
                ->label(__('zatca.setup_guide'))
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(route('admin.documents.zatca-store-setup-guide'), shouldOpenInNewTab: true),

            Action::make('clone_from_store')
                ->label(__('zatca.clone_from_store'))
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->visible(fn () => filled($this->data['store_id'] ?? null))
                ->modalHeading(__('zatca.clone_from_store'))
                ->modalDescription(__('zatca.clone_from_store_help'))
                ->form(function () {
                    $currentId = $this->data['store_id'] ?? null;
                    $current = $currentId ? Store::find($currentId) : null;
                    $orgId = $current?->organization_id;

                    // Candidate sources: same organization, configured ZATCA settings,
                    // excluding the current store. We also include stores that share
                    // the same VAT number even across orgs as a convenience, because
                    // some businesses register branches under separate orgs.
                    $candidates = Store::query()
                        ->when($orgId, fn ($q) => $q->where(function ($q) use ($orgId, $current) {
                            $q->where('organization_id', $orgId);
                            if ($current?->vat_number) {
                                $q->orWhere('vat_number', $current->vat_number);
                            }
                        }))
                        ->where('id', '!=', $currentId)
                        ->whereHas('storeSettings', function ($q) {
                            // Postgres JSONB ?: extra->'zatca' must exist.
                            $q->whereNotNull('extra');
                        })
                        ->orderBy('name')
                        ->get()
                        ->filter(function (Store $s) {
                            $extra = $s->storeSettings?->extra ?? [];
                            return ! empty($extra['zatca']);
                        })
                        ->mapWithKeys(function (Store $s) {
                            $extra = $s->storeSettings?->extra ?? [];
                            $zatca = $extra['zatca'] ?? [];
                            $vat = $zatca['vat_number'] ?? $s->vat_number ?? '—';
                            return [$s->id => $s->name . ' (VAT: ' . $vat . ')'];
                        })
                        ->all();

                    return [
                        Select::make('source_store_id')
                            ->label(__('zatca.source_store'))
                            ->options($candidates)
                            ->required()
                            ->searchable()
                            ->helperText(__('zatca.source_store_help')),
                    ];
                })
                ->action(function (array $data) {
                    $targetId = $this->data['store_id'] ?? null;
                    $sourceId = $data['source_store_id'] ?? null;
                    if (! $targetId || ! $sourceId || $targetId === $sourceId) {
                        return;
                    }

                    $sourceSettings = StoreSettings::where('store_id', $sourceId)->first();
                    $sourceZatca = $sourceSettings?->extra['zatca'] ?? [];
                    $sourceStore = Store::find($sourceId);

                    if (empty($sourceZatca)) {
                        Notification::make()
                            ->title(__('zatca.clone_failed'))
                            ->body(__('zatca.clone_source_empty'))
                            ->danger()
                            ->send();
                        return;
                    }

                    // Strip everything that must remain per-EGS:
                    // OTP (single-use, store-specific), cert/device tracking flags,
                    // and audit metadata. Tax identity + integration prefs are safe.
                    $cloneable = collect($sourceZatca)->except([
                        'otp_encrypted',
                        'otp_set',
                        'updated_by',
                        'updated_at',
                    ])->all();

                    $targetSettings = StoreSettings::firstOrNew(['store_id' => $targetId]);
                    $extra = $targetSettings->extra ?? [];
                    $existingZatca = $extra['zatca'] ?? [];
                    // Preserve any existing OTP / audit fields on the target.
                    $extra['zatca'] = array_merge($cloneable, [
                        'otp_set' => $existingZatca['otp_set'] ?? false,
                        'otp_encrypted' => $existingZatca['otp_encrypted'] ?? null,
                        'updated_by' => auth('admin')->id(),
                        'updated_at' => now()->toIso8601String(),
                    ]);
                    $targetSettings->store_id = $targetId;
                    $targetSettings->extra = $extra;
                    $targetSettings->save();

                    // Mirror tax identity onto the Store row (same fields the
                    // Save action mirrors), so CertificateService picks them up
                    // when building the CSR for this store's own enrollment.
                    $targetStore = Store::find($targetId);
                    if ($targetStore && $sourceStore) {
                        $targetStore->fill(array_filter([
                            'vat_number' => $sourceStore->vat_number,
                            'cr_number' => $sourceStore->cr_number,
                            'address' => $sourceStore->address,
                            'city' => $sourceStore->city,
                            'postal_code' => $sourceStore->postal_code,
                        ], fn ($v) => $v !== null && $v !== ''))->save();
                    }

                    AdminActivityLog::record(
                        adminUserId: auth('admin')->id(),
                        action: 'zatca_clone_settings',
                        entityType: 'store',
                        entityId: $targetId,
                        details: ['source_store_id' => $sourceId],
                    );

                    // Reload form so user sees the cloned values immediately.
                    $this->loadStoreConfig($targetId);
                    $this->form->fill($this->data);

                    Notification::make()
                        ->title(__('zatca.clone_succeeded'))
                        ->body(__('zatca.clone_next_step'))
                        ->success()
                        ->send();
                }),

            Action::make('enroll')
                ->label(__('zatca.enroll_now'))
                ->icon('heroicon-o-bolt')
                ->color('success')
                ->visible(fn () => filled($this->data['store_id'] ?? null))
                ->form([
                    TextInput::make('otp')
                        ->label(__('zatca.otp'))
                        ->required()
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        ->helperText(__('zatca.otp_help_portal')),
                    Select::make('environment')
                        ->label(__('zatca.environment'))
                        ->options([
                            'developer-portal' => 'Developer Portal (test only — fake CCSID, cannot get PCSID)',
                            'simulation'       => 'Simulation (real CCSID, can get real PCSID — use this for pre-production)',
                            'production'       => 'Production (live invoices — use after simulation tests pass)',
                        ])
                        ->default(fn () => $this->data['environment'] ?? 'simulation')
                        ->required()
                        ->helperText(__('zatca.env_scope_help')),
                ])
                ->action(function (array $data) {
                    $sid = $this->data['store_id'] ?? null;
                    if (! $sid) return;
                    try {
                        $result = app(ZatcaComplianceService::class)->enroll($sid, $data['otp'], $data['environment']);
                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: 'zatca_enroll',
                            entityType: 'store',
                            entityId: $sid,
                            details: ['environment' => $data['environment'], 'certificate_id' => $result['certificate_id'] ?? null],
                        );
                        Notification::make()->title(__('zatca.enrolled'))->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title(__('zatca.enroll_failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),

            Action::make('renew_cert')
                ->label(__('zatca.renew_cert'))
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn () => filled($this->data['store_id'] ?? null))
                ->requiresConfirmation()
                ->action(function () {
                    $sid = $this->data['store_id'] ?? null;
                    if (! $sid) return;
                    try {
                        $result = app(ZatcaComplianceService::class)->renewCertificate($sid);
                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: 'zatca_renew_certificate',
                            entityType: 'store',
                            entityId: $sid,
                            details: ['certificate_id' => $result['certificate_id'] ?? null],
                        );
                        Notification::make()->title(__('zatca.renewed'))->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title(__('zatca.renewal_failed'))->body($e->getMessage())->danger()->send();
                    }
                }),

            Action::make('provision_device')
                ->label(__('zatca.provision_device'))
                ->icon('heroicon-o-cpu-chip')
                ->color('info')
                ->visible(fn () => filled($this->data['store_id'] ?? null))
                ->form([
                    Select::make('environment')
                        ->label(__('zatca.environment'))
                        ->options([
                            'sandbox' => __('zatca.env_sandbox'),
                            'simulation' => __('zatca.env_simulation'),
                            'production' => __('zatca.env_production'),
                        ])
                        ->default(fn () => $this->data['environment'] ?? 'sandbox')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $sid = $this->data['store_id'] ?? null;
                    if (! $sid) return;
                    try {
                        $device = app(DeviceService::class)->provision($sid, $data['environment']);
                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: 'zatca_provision_device',
                            entityType: 'zatca_device',
                            entityId: $device->id,
                            details: ['store_id' => $sid, 'environment' => $data['environment']],
                        );
                        Notification::make()
                            ->title(__('zatca.device_provisioned'))
                            ->body(__('zatca.activation_code') . ': ' . $device->activation_code)
                            ->success()
                            ->persistent()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title(__('zatca.action_failed'))->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
