<?php

namespace App\Filament\Resources;

use App\Domain\Core\Models\Organization;
use App\Domain\Core\Models\Store;
use App\Domain\LabelPrinting\Models\LabelPrintHistory;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Platform-admin read-only resource for reviewing label print history
 * across all stores.
 */
class LabelPrintHistoryResource extends Resource
{
    protected static ?string $model = LabelPrintHistory::class;

    protected static ?string $navigationIcon = 'heroicon-o-printer';

    protected static ?int $navigationSort = 9;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_content');
    }

    public static function getNavigationLabel(): string
    {
        return __('nav.label_print_history');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['labels.view', 'labels.manage', 'platform.superadmin']);
    }

    // ─── Infolist ────────────────────────────────────────────

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('labels.admin_print_detail'))
                ->schema([
                    Infolists\Components\TextEntry::make('id')
                        ->label(__('labels.admin_id'))
                        ->copyable(),

                    Infolists\Components\TextEntry::make('store.name')
                        ->label(__('labels.admin_store'))
                        ->default('—'),

                    Infolists\Components\TextEntry::make('template.name')
                        ->label(__('labels.admin_template'))
                        ->default('—'),

                    Infolists\Components\TextEntry::make('printedBy.name')
                        ->label(__('labels.admin_printed_by'))
                        ->default('—'),

                    Infolists\Components\TextEntry::make('product_count')
                        ->label(__('labels.admin_product_count')),

                    Infolists\Components\TextEntry::make('total_labels')
                        ->label(__('labels.admin_total_labels')),

                    Infolists\Components\TextEntry::make('printer_name')
                        ->label(__('labels.admin_printer_name'))
                        ->default('—'),

                    Infolists\Components\TextEntry::make('printed_at')
                        ->label(__('labels.admin_printed_at'))
                        ->dateTime(),
                ])
                ->columns(2),
        ]);
    }

    // ─── Table ───────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('printed_at')
                    ->label(__('labels.admin_printed_at'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('store.name')
                    ->label(__('labels.admin_store'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('template.name')
                    ->label(__('labels.admin_template'))
                    ->default('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('printedBy.name')
                    ->label(__('labels.admin_printed_by'))
                    ->default('—'),

                Tables\Columns\TextColumn::make('product_count')
                    ->label(__('labels.admin_product_count'))
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_labels')
                    ->label(__('labels.admin_total_labels'))
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('printer_name')
                    ->label(__('labels.admin_printer_name'))
                    ->default('—')
                    ->toggleable(),
            ])
            ->defaultSort('printed_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label(__('labels.admin_store'))
                    ->options(fn () => Store::orderBy('name')->pluck('name', 'id')->toArray())
                    ->searchable(),

                Tables\Filters\Filter::make('printed_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label(__('labels.admin_from')),
                        \Filament\Forms\Components\DatePicker::make('to')
                            ->label(__('labels.admin_to')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q) => $q->where('printed_at', '>=', $data['from']))
                            ->when($data['to'], fn (Builder $q) => $q->where('printed_at', '<=', $data['to'] . ' 23:59:59'));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->recordUrl(null);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['store', 'template', 'printedBy']);
    }

    // ─── Pages ───────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => LabelPrintHistoryResource\Pages\ListLabelPrintHistories::route('/'),
            'view'  => LabelPrintHistoryResource\Pages\ViewLabelPrintHistory::route('/{record}'),
        ];
    }
}
