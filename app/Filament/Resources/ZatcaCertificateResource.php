<?php

namespace App\Filament\Resources;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\ZatcaCompliance\Enums\ZatcaCertificateStatus;
use App\Domain\ZatcaCompliance\Enums\ZatcaCertificateType;
use App\Domain\ZatcaCompliance\Models\ZatcaCertificate;
use App\Domain\ZatcaCompliance\Services\ZatcaComplianceService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Crypt;

class ZatcaCertificateResource extends Resource
{
    protected static ?string $model = ZatcaCertificate::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 13;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_zatca');
    }

    public static function getNavigationLabel(): string
    {
        return __('zatca.certificates');
    }

    public static function getModelLabel(): string
    {
        return __('zatca.certificate');
    }

    public static function getPluralModelLabel(): string
    {
        return __('zatca.certificates');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['settings.credentials']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('zatca.certificate_info'))->schema([
                Forms\Components\Select::make('store_id')
                    ->label(__('zatca.store'))
                    ->relationship('store', 'name')
                    ->searchable()
                    ->required()
                    ->disabledOn('edit'),
                Forms\Components\Select::make('certificate_type')
                    ->label(__('zatca.certificate_type'))
                    ->options(collect(ZatcaCertificateType::cases())->mapWithKeys(fn ($c) => [$c->value => __('zatca.cert_type_' . $c->value)]))
                    ->required(),
                Forms\Components\TextInput::make('environment')
                    ->label('Environment')
                    ->disabled()
                    ->helperText('developer-portal = stub QA · simulation = ZATCA simulation server · production = live ZATCA'),
                Forms\Components\TextInput::make('api_url')
                    ->label('ZATCA API URL')
                    ->disabled()
                    ->columnSpanFull(),
                Forms\Components\Select::make('status')
                    ->label(__('zatca.status'))
                    ->options(collect(ZatcaCertificateStatus::cases())->mapWithKeys(fn ($c) => [$c->value => __('zatca.cert_status_' . $c->value)]))
                    ->required(),
                Forms\Components\TextInput::make('ccsid')->label('CCSID')->disabled(),
                Forms\Components\TextInput::make('pcsid')->label('PCSID')->disabled(),
                Forms\Components\DateTimePicker::make('issued_at')->label(__('zatca.issued_at'))->disabled(),
                Forms\Components\DateTimePicker::make('expires_at')->label(__('zatca.expires_at')),
            ])->columns(2),

            Forms\Components\Section::make(__('zatca.cert_material'))
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('certificate_pem')->label('Certificate PEM')->rows(8)->disabled(),
                    Forms\Components\Textarea::make('csr_pem')->label('CSR PEM')->rows(8)->disabled(),
                    Forms\Components\Textarea::make('public_key_pem')->label('Public Key PEM')->rows(6)->disabled(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('store.name')->label(__('zatca.store'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('certificate_type')
                    ->label(__('zatca.certificate_type'))
                    ->formatStateUsing(fn ($state) => __('zatca.cert_type_' . ($state?->value ?? $state))),
                Tables\Columns\TextColumn::make('ccsid')->label('CCSID')->copyable()->limit(20),
                Tables\Columns\TextColumn::make('pcsid')->label('PCSID')->copyable()->limit(20)->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('zatca.status'))
                    ->badge()
                    ->color(fn ($state) => match ($state?->value ?? $state) {
                        'active' => 'success',
                        'expired' => 'gray',
                        'revoked' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn ($state) => __('zatca.cert_status_' . ($state?->value ?? $state))),
                Tables\Columns\TextColumn::make('issued_at')->label(__('zatca.issued_at'))->dateTime('Y-m-d')->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('zatca.expires_at'))
                    ->dateTime('Y-m-d')
                    ->sortable()
                    ->color(fn ($record) => $record->expires_at && $record->expires_at->lt(now()->addDays(30)) ? 'danger' : null),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('zatca.status'))
                    ->options(collect(ZatcaCertificateStatus::cases())->mapWithKeys(fn ($c) => [$c->value => __('zatca.cert_status_' . $c->value)])),
                Tables\Filters\SelectFilter::make('certificate_type')
                    ->label(__('zatca.certificate_type'))
                    ->options(collect(ZatcaCertificateType::cases())->mapWithKeys(fn ($c) => [$c->value => __('zatca.cert_type_' . $c->value)])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('revoke')
                    ->label(__('zatca.revoke'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (ZatcaCertificate $record) => $record->status === ZatcaCertificateStatus::Active)
                    ->action(function (ZatcaCertificate $record) {
                        $record->update(['status' => ZatcaCertificateStatus::Revoked]);
                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: 'revoke_zatca_certificate',
                            entityType: 'zatca_certificate',
                            entityId: $record->id,
                            details: ['store_id' => $record->store_id],
                        );
                        Notification::make()->title(__('zatca.certificate_revoked'))->success()->send();
                    }),
                Tables\Actions\Action::make('renew')
                    ->label(__('zatca.renew'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (ZatcaCertificate $record) {
                        try {
                            $svc = app(ZatcaComplianceService::class);
                            $result = $svc->renewCertificate($record->store_id);
                            AdminActivityLog::record(
                                adminUserId: auth('admin')->id(),
                                action: 'renew_zatca_certificate',
                                entityType: 'zatca_certificate',
                                entityId: $record->id,
                                details: ['new_certificate_id' => $result['certificate_id']],
                            );
                            Notification::make()->title(__('zatca.renewed'))->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title(__('zatca.renewal_failed'))->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('download_pem')
                    ->label(__('zatca.download_pem'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (ZatcaCertificate $record) {
                        return response()->streamDownload(
                            fn () => print($record->certificate_pem ?? ''),
                            "zatca-cert-{$record->id}.pem",
                            ['Content-Type' => 'application/x-pem-file']
                        );
                    }),
            ])
            ->defaultSort('issued_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ZatcaCertificateResource\Pages\ListZatcaCertificates::route('/'),
            'edit' => ZatcaCertificateResource\Pages\EditZatcaCertificate::route('/{record}/edit'),
        ];
    }
}
