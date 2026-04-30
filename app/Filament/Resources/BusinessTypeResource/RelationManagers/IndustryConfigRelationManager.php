<?php

namespace App\Filament\Resources\BusinessTypeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class IndustryConfigRelationManager extends RelationManager
{
    protected static string $relationship = 'businessTypeIndustryConfig';

    protected static ?string $title = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Industry Workflow Config');
    }

    protected static ?string $icon = 'heroicon-o-cog-6-tooth';

    private static array $ALL_MODULES = [
        // Restaurant
        'table_management'   => 'Table Management',
        'kds'                => 'Kitchen Display System (KDS)',
        'course_management'  => 'Course Management',
        'split_bill'         => 'Split Bill',
        'tab_management'     => 'Tab Management',
        'modifiers'          => 'Product Modifiers',
        // Pharmacy
        'prescription_mode'  => 'Prescription Mode',
        'drug_scheduling'    => 'Drug Scheduling',
        'fefo_tracking'      => 'FEFO Tracking (Expiry)',
        'insurance_claims'   => 'Insurance Claims',
        'batch_tracking'     => 'Batch Tracking',
        // General services
        'appointment_booking' => 'Appointment Booking',
        'repair_tracking'    => 'Repair Job Tracking',
        // Retail
        'gift_registry'      => 'Gift Registry',
        'layaway'            => 'Layaway / Installment',
        'trade_in'           => 'Trade-In / Buyback',
        // Bakery / Production
        'production_planning' => 'Production Planning',
        'recipe_costing'     => 'Recipe & Costing',
        // All
        'loyalty_gamification' => 'Loyalty Gamification',
        'cashier_gamification' => 'Cashier Gamification',
        'multi_currency'       => 'Multi-Currency',
        'age_restriction'      => 'Age Restriction Checks',
    ];

    private static array $ALL_PRODUCT_FIELDS = [
        'drug_schedule'   => 'Drug Schedule',
        'expiry_date'     => 'Expiry Date',
        'batch_number'    => 'Batch Number',
        'weight_grams'    => 'Weight (grams)',
        'karat'           => 'Karat / Gold Purity',
        'making_charge'   => 'Making Charge',
        'imei'            => 'IMEI / Serial Number',
        'repair_job_id'   => 'Repair Job ID',
        'shelf_life_days' => 'Shelf Life (days)',
        'country_of_origin' => 'Country of Origin',
    ];

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Active Modules'))
                ->description(__('Select which industry-specific POS modules activate for new stores of this business type.'))
                ->schema([
                    Forms\Components\CheckboxList::make('active_modules')
                        ->label(__('Active Modules'))
                        ->options(self::$ALL_MODULES)
                        ->columns(3)
                        ->columnSpanFull()
                        ->helperText(__('These modules will be enabled by default for new stores.')),
                ]),

            Forms\Components\Section::make(__('Required Product Fields'))
                ->description(__('Extra product fields providers must fill for this business type.'))
                ->schema([
                    Forms\Components\CheckboxList::make('required_product_fields')
                        ->label(__('Required Fields'))
                        ->options(self::$ALL_PRODUCT_FIELDS)
                        ->columns(3)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make(__('Default Module Settings (JSON)'))
                ->description(__('Per-module default config as JSON. e.g. {"table_management": {"default_floor_count": 1}}'))
                ->schema([
                    Forms\Components\Textarea::make('default_settings')
                        ->label(__('Default Settings (JSONB)'))
                        ->rows(6)
                        ->placeholder('{"table_management": {"default_floor_count": 1, "max_tables": 20}, "kds": {"ticket_timeout_minutes": 15}}')
                        ->columnSpanFull()
                        ->dehydrateStateUsing(fn ($state) => $state ? json_decode($state, true) : [])
                        ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : ''),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('active_modules')
                    ->label(__('Active Modules'))
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state)
                    ->wrap(),
                Tables\Columns\TextColumn::make('required_product_fields')
                    ->label(__('Required Fields'))
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : '—')
                    ->wrap(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('Set Industry Config')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
