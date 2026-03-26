<?php

namespace App\Filament\Resources;

use App\Domain\ContentOnboarding\Models\ReceiptLayoutTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ReceiptLayoutTemplateResource extends Resource
{
    protected static ?string $model = ReceiptLayoutTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'UI Management';

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('ui.nav_receipt_templates');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['ui.manage']);
    }

    // ─── Form ────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('ui.basic_info'))
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('ui.name_en'))
                        ->required()
                        ->maxLength(100)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state))),
                    Forms\Components\TextInput::make('name_ar')
                        ->label(__('ui.name_ar'))
                        ->required()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('slug')
                        ->label(__('ui.slug'))
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true),
                    Forms\Components\Select::make('paper_width')
                        ->label(__('ui.paper_width'))
                        ->options([58 => '58mm', 80 => '80mm'])
                        ->required()
                        ->default(80),
                    Forms\Components\TextInput::make('sort_order')
                        ->label(__('ui.sort_order'))
                        ->numeric()
                        ->default(0),
                    Forms\Components\Toggle::make('show_bilingual')
                        ->label(__('ui.show_bilingual'))
                        ->default(true),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('ui.is_active'))
                        ->default(true),
                    Forms\Components\Select::make('zatca_qr_position')
                        ->label(__('ui.zatca_qr_position'))
                        ->options(['header' => __('ui.header'), 'footer' => __('ui.footer')])
                        ->default('footer'),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('ui.header_design'))
                ->schema([
                    Forms\Components\TextInput::make('header_config.logo_max_height_px')
                        ->label(__('ui.logo_max_height'))
                        ->numeric()
                        ->default(60)
                        ->suffix('px'),
                    Forms\Components\Select::make('header_config.store_name_font_size')
                        ->label(__('ui.store_name_font_size'))
                        ->options(['small' => __('ui.font_small'), 'medium' => __('ui.font_medium'), 'large' => __('ui.font_large')])
                        ->default('large'),
                    Forms\Components\Toggle::make('header_config.store_name_bold')
                        ->label(__('ui.store_name_bold'))
                        ->default(true),
                    Forms\Components\Select::make('header_config.address_font_size')
                        ->label(__('ui.address_font_size'))
                        ->options(['small' => __('ui.font_small'), 'medium' => __('ui.font_medium'), 'large' => __('ui.font_large')])
                        ->default('small'),
                    Forms\Components\Toggle::make('header_config.show_vat_number')
                        ->label(__('ui.show_vat_number'))
                        ->default(true),
                    Forms\Components\Select::make('header_config.separator')
                        ->label(__('ui.separator_style'))
                        ->options(['line' => __('ui.separator_line'), 'dashes' => __('ui.separator_dashes'), 'none' => __('ui.separator_none')])
                        ->default('dashes'),
                ])
                ->columns(3),

            Forms\Components\Section::make(__('ui.body_design'))
                ->schema([
                    Forms\Components\Select::make('body_config.item_font_size')
                        ->label(__('ui.item_font_size'))
                        ->options(['small' => __('ui.font_small'), 'medium' => __('ui.font_medium'), 'large' => __('ui.font_large')])
                        ->default('medium'),
                    Forms\Components\Select::make('body_config.price_alignment')
                        ->label(__('ui.price_alignment'))
                        ->options(['right' => __('ui.align_right'), 'left' => __('ui.align_left')])
                        ->default('right'),
                    Forms\Components\Toggle::make('body_config.show_sku')
                        ->label(__('ui.show_sku'))
                        ->default(false),
                    Forms\Components\Toggle::make('body_config.show_barcode')
                        ->label(__('ui.show_barcode'))
                        ->default(false),
                    Forms\Components\TextInput::make('body_config.column_widths.name')
                        ->label(__('ui.col_width_name'))
                        ->numeric()
                        ->default(50)
                        ->suffix('%'),
                    Forms\Components\TextInput::make('body_config.column_widths.qty')
                        ->label(__('ui.col_width_qty'))
                        ->numeric()
                        ->default(15)
                        ->suffix('%'),
                    Forms\Components\TextInput::make('body_config.column_widths.price')
                        ->label(__('ui.col_width_price'))
                        ->numeric()
                        ->default(35)
                        ->suffix('%'),
                    Forms\Components\Select::make('body_config.row_separator')
                        ->label(__('ui.row_separator'))
                        ->options(['line' => __('ui.separator_line'), 'none' => __('ui.separator_none')])
                        ->default('none'),
                    Forms\Components\Toggle::make('body_config.totals_bold')
                        ->label(__('ui.totals_bold'))
                        ->default(true),
                ])
                ->columns(3),

            Forms\Components\Section::make(__('ui.footer_design'))
                ->schema([
                    Forms\Components\TextInput::make('footer_config.zatca_qr_size_px')
                        ->label(__('ui.zatca_qr_size'))
                        ->numeric()
                        ->default(120)
                        ->suffix('px'),
                    Forms\Components\Toggle::make('footer_config.show_receipt_number')
                        ->label(__('ui.show_receipt_number'))
                        ->default(true),
                    Forms\Components\Toggle::make('footer_config.show_cashier_name')
                        ->label(__('ui.show_cashier_name'))
                        ->default(true),
                    Forms\Components\TextInput::make('footer_config.custom_footer_text')
                        ->label(__('ui.custom_footer_en'))
                        ->maxLength(200),
                    Forms\Components\TextInput::make('footer_config.custom_footer_text_ar')
                        ->label(__('ui.custom_footer_ar'))
                        ->maxLength(200),
                    Forms\Components\TextInput::make('footer_config.thank_you_en')
                        ->label(__('ui.thank_you_en'))
                        ->default('Thank you!')
                        ->maxLength(100),
                    Forms\Components\TextInput::make('footer_config.thank_you_ar')
                        ->label(__('ui.thank_you_ar'))
                        ->default('شكراً لزيارتكم')
                        ->maxLength(100),
                    Forms\Components\Toggle::make('footer_config.show_social_handles')
                        ->label(__('ui.show_social_handles'))
                        ->default(false),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('ui.package_visibility'))
                ->schema([
                    Forms\Components\Select::make('subscriptionPlans')
                        ->label(__('ui.visible_plans'))
                        ->relationship('subscriptionPlans', 'name')
                        ->multiple()
                        ->preload()
                        ->helperText(__('ui.visible_plans_help')),
                ]),
        ]);
    }

    // ─── Table ───────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('ui.name_en'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (ReceiptLayoutTemplate $r) => $r->name_ar),
                Tables\Columns\TextColumn::make('slug')
                    ->label(__('ui.slug'))
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('paper_width')
                    ->label(__('ui.paper_width'))
                    ->formatStateUsing(fn (int $state) => $state . 'mm')
                    ->badge()
                    ->color(fn (int $state) => $state === 80 ? 'success' : 'warning')
                    ->sortable(),
                Tables\Columns\IconColumn::make('show_bilingual')
                    ->label(__('ui.show_bilingual'))
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('ui.is_active'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('ui.sort_order'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscriptionPlans.name')
                    ->label(__('ui.visible_plans'))
                    ->badge()
                    ->color('info')
                    ->separator(', ')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('paper_width')
                    ->label(__('ui.paper_width'))
                    ->options([58 => '58mm', 80 => '80mm']),
                Tables\Filters\TernaryFilter::make('is_active')->label(__('ui.is_active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn (ReceiptLayoutTemplate $r) => $r->is_active ? __('ui.deactivate') : __('ui.activate'))
                    ->icon(fn (ReceiptLayoutTemplate $r) => $r->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (ReceiptLayoutTemplate $r) => $r->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (ReceiptLayoutTemplate $record) => $record->update(['is_active' => ! $record->is_active])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    // ─── Pages ───────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => ReceiptLayoutTemplateResource\Pages\ListReceiptLayoutTemplates::route('/'),
            'create' => ReceiptLayoutTemplateResource\Pages\CreateReceiptLayoutTemplate::route('/create'),
            'edit' => ReceiptLayoutTemplateResource\Pages\EditReceiptLayoutTemplate::route('/{record}/edit'),
        ];
    }
}
