<?php

namespace App\Filament\Resources;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\DeliveryPlatformRegistry\Enums\DriverProtocol;
use App\Domain\SystemConfig\Enums\HardwareDeviceType;
use App\Domain\SystemConfig\Models\CertifiedHardware;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CertifiedHardwareResource extends Resource
{
    protected static ?string $model = CertifiedHardware::class;

    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_settings');
    }

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 6;

    public static function getNavigationLabel(): string
    {
        return __('settings.hardware_catalog');
    }

    public static function getModelLabel(): string
    {
        return __('settings.certified_hardware');
    }

    public static function getPluralModelLabel(): string
    {
        return __('settings.hardware_catalog');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['settings.hardware_catalog']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('settings.device_info'))
                ->schema([
                    Forms\Components\Select::make('device_type')
                        ->label(__('settings.device_type'))
                        ->options(collect(HardwareDeviceType::cases())->mapWithKeys(fn ($c) => [$c->value => __('settings.hw_type_' . $c->value)]))
                        ->required()
                        ->native(false),
                    Forms\Components\TextInput::make('brand')
                        ->label(__('settings.brand'))
                        ->required()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('model')
                        ->label(__('settings.model'))
                        ->required()
                        ->maxLength(100),
                    Forms\Components\Select::make('driver_protocol')
                        ->label(__('settings.driver_protocol'))
                        ->options(collect(DriverProtocol::cases())->mapWithKeys(fn ($c) => [$c->value => str_replace('_', ' ', ucfirst($c->value))]))
                        ->native(false),
                ])->columns(2),

            Forms\Components\Section::make(__('settings.specifications'))
                ->schema([
                    Forms\Components\TagsInput::make('connection_types')
                        ->label(__('settings.connection_types'))
                        ->placeholder('usb, bluetooth, wifi, ethernet'),
                    Forms\Components\TextInput::make('firmware_version_min')
                        ->label(__('settings.firmware_version_min'))
                        ->maxLength(30),
                    Forms\Components\TagsInput::make('paper_widths')
                        ->label(__('settings.paper_widths'))
                        ->placeholder('58mm, 80mm'),
                ])->columns(2),

            Forms\Components\Section::make(__('settings.setup_instructions'))
                ->schema([
                    Forms\Components\RichEditor::make('setup_instructions')
                        ->label(__('settings.setup_instructions_en'))
                        ->columnSpanFull(),
                    Forms\Components\RichEditor::make('setup_instructions_ar')
                        ->label(__('settings.setup_instructions_ar'))
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make(__('settings.status'))
                ->schema([
                    Forms\Components\Toggle::make('is_certified')
                        ->label(__('settings.is_certified'))
                        ->default(true),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('settings.is_active'))
                        ->default(true),
                    Forms\Components\Textarea::make('notes')
                        ->label(__('settings.notes'))
                        ->rows(3)
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('device_type')
                    ->label(__('settings.device_type'))
                    ->formatStateUsing(fn ($state) => __('settings.hw_type_' . $state->value))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        HardwareDeviceType::ReceiptPrinter => 'primary',
                        HardwareDeviceType::BarcodeScanner => 'info',
                        HardwareDeviceType::WeighingScale => 'warning',
                        HardwareDeviceType::CashDrawer => 'success',
                        HardwareDeviceType::CardTerminal => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('brand')
                    ->label(__('settings.brand'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('model')
                    ->label(__('settings.model'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('driver_protocol')
                    ->label(__('settings.driver_protocol'))
                    ->badge()
                    ->color('gray'),
                Tables\Columns\IconColumn::make('is_certified')
                    ->label(__('settings.is_certified'))
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('settings.is_active'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('settings.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('device_type')
                    ->label(__('settings.device_type'))
                    ->options(collect(HardwareDeviceType::cases())->mapWithKeys(fn ($c) => [$c->value => __('settings.hw_type_' . $c->value)])),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('settings.is_active')),
                Tables\Filters\TernaryFilter::make('is_certified')
                    ->label(__('settings.is_certified')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: 'delete_hardware',
                            entityType: 'certified_hardware',
                            entityId: $record->id,
                            details: ['brand' => $record->brand, 'model' => $record->model],
                        );
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => CertifiedHardwareResource\Pages\ListCertifiedHardware::route('/'),
            'create' => CertifiedHardwareResource\Pages\CreateCertifiedHardware::route('/create'),
            'edit' => CertifiedHardwareResource\Pages\EditCertifiedHardware::route('/{record}/edit'),
        ];
    }
}
