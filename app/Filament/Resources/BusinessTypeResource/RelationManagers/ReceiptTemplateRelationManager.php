<?php

namespace App\Filament\Resources\BusinessTypeResource\RelationManagers;

use App\Domain\ContentOnboarding\Models\BusinessTypeReceiptTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ReceiptTemplateRelationManager extends RelationManager
{
    protected static string $relationship = 'businessTypeReceiptTemplate';

    protected static ?string $title = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Default Receipt Template');
    }

    protected static ?string $icon = 'heroicon-o-printer';

    // ─── This is a hasOne relation — we override table to show single record ───

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Paper & Layout'))
                ->schema([
                    Forms\Components\Select::make('paper_width')
                        ->label(__('Paper Width (mm)'))
                        ->options([58 => '58 mm', 80 => '80 mm'])
                        ->default(80)
                        ->required(),
                    Forms\Components\Select::make('font_size')
                        ->label(__('Font Size'))
                        ->options([
                            'small'  => __('Small'),
                            'medium' => __('Medium'),
                            'large'  => __('Large'),
                        ])
                        ->default('medium'),
                    Forms\Components\Select::make('zatca_qr_position')
                        ->label(__('ZATCA QR Position'))
                        ->options([
                            'header' => __('Header'),
                            'footer' => __('Footer'),
                        ])
                        ->default('footer'),
                    Forms\Components\Toggle::make('show_bilingual')
                        ->label(__('Show Bilingual (EN + AR)'))
                        ->default(true),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('Section Order'))
                ->description(__('Comma-separated section keys. Drag to reorder by editing the JSON array.'))
                ->schema([
                    Forms\Components\Repeater::make('header_sections')
                        ->label(__('Header Sections'))
                        ->schema([
                            Forms\Components\Select::make('section')
                                ->label(__('Section'))
                                ->options([
                                    'store_logo'       => __('Store Logo'),
                                    'store_name'       => __('Store Name'),
                                    'store_address'    => __('Store Address'),
                                    'store_phone'      => __('Store Phone'),
                                    'store_vat_number' => __('VAT Number'),
                                ])
                                ->required(),
                        ])
                        ->reorderable()
                        ->addActionLabel(__('Add Header Section'))
                        ->columnSpanFull(),
                    Forms\Components\Repeater::make('body_sections')
                        ->label(__('Body Sections'))
                        ->schema([
                            Forms\Components\Select::make('section')
                                ->label(__('Section'))
                                ->options([
                                    'items_table'    => __('Items Table'),
                                    'subtotal'       => __('Subtotal'),
                                    'discount'       => __('Discount'),
                                    'vat'            => __('VAT'),
                                    'total'          => __('Total'),
                                    'payment_method' => __('Payment Method'),
                                ])
                                ->required(),
                        ])
                        ->reorderable()
                        ->addActionLabel(__('Add Body Section'))
                        ->columnSpanFull(),
                    Forms\Components\Repeater::make('footer_sections')
                        ->label(__('Footer Sections'))
                        ->schema([
                            Forms\Components\Select::make('section')
                                ->label(__('Section'))
                                ->options([
                                    'zatca_qr'           => __('ZATCA QR Code'),
                                    'receipt_number'     => __('Receipt Number'),
                                    'cashier_name'       => __('Cashier Name'),
                                    'thank_you_message'  => __('Thank You Message'),
                                    'custom_footer_text' => __('Custom Footer Text'),
                                ])
                                ->required(),
                        ])
                        ->reorderable()
                        ->addActionLabel(__('Add Footer Section'))
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make(__('Footer Text'))
                ->schema([
                    Forms\Components\TextInput::make('custom_footer_text')
                        ->label(__('Custom Footer Text (EN)'))
                        ->maxLength(200)
                        ->placeholder('e.g., Thank you for your visit!'),
                    Forms\Components\TextInput::make('custom_footer_text_ar')
                        ->label(__('Custom Footer Text (AR)'))
                        ->maxLength(200)
                        ->placeholder('شكراً لزيارتكم!'),
                ])
                ->columns(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('paper_width')
                    ->label(__('Paper Width'))
                    ->formatStateUsing(fn ($state) => $state . ' mm'),
                Tables\Columns\TextColumn::make('font_size')
                    ->label(__('Font Size'))
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('zatca_qr_position')
                    ->label(__('QR Position'))
                    ->badge()
                    ->color('info'),
                Tables\Columns\IconColumn::make('show_bilingual')
                    ->label(__('Bilingual'))
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('Set Receipt Template'))
                    ->mutateFormDataUsing(function (array $data): array {
                        // Convert repeater arrays to simple string arrays
                        $data['header_sections'] = collect($data['header_sections'] ?? [])->pluck('section')->toArray();
                        $data['body_sections']   = collect($data['body_sections']   ?? [])->pluck('section')->toArray();
                        $data['footer_sections'] = collect($data['footer_sections'] ?? [])->pluck('section')->toArray();
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['header_sections'] = collect($data['header_sections'] ?? [])->pluck('section')->filter()->values()->toArray();
                        $data['body_sections']   = collect($data['body_sections']   ?? [])->pluck('section')->filter()->values()->toArray();
                        $data['footer_sections'] = collect($data['footer_sections'] ?? [])->pluck('section')->filter()->values()->toArray();
                        return $data;
                    })
                    ->fillForm(function (BusinessTypeReceiptTemplate $record): array {
                        // Convert plain arrays back to repeater format
                        $toRepeater = fn (array $items) => collect($items)
                            ->map(fn ($s) => is_string($s) ? ['section' => $s] : $s)
                            ->toArray();
                        return array_merge($record->toArray(), [
                            'header_sections' => $toRepeater($record->header_sections ?? []),
                            'body_sections'   => $toRepeater($record->body_sections   ?? []),
                            'footer_sections' => $toRepeater($record->footer_sections ?? []),
                        ]);
                    }),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
