<?php

namespace App\Filament\Resources;

use App\Domain\Core\Models\Organization;
use App\Domain\LabelPrinting\Models\LabelTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Platform-admin read-only resource for reviewing label templates
 * across all organisations.
 *
 * Platform admins can view and delete templates; they cannot
 * create or update them (that is a store-owner action via the POS).
 */
class LabelTemplateResource extends Resource
{
    protected static ?string $model = LabelTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?int $navigationSort = 8;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_content');
    }

    public static function getNavigationLabel(): string
    {
        return __('nav.label_templates');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['labels.view', 'labels.manage', 'platform.superadmin']);
    }

    // ─── Form (view-only / edit-metadata) ────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('labels.admin_basic_info'))
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('labels.admin_name'))
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('organization_id')
                        ->label(__('labels.admin_organization'))
                        ->disabled(),

                    Forms\Components\TextInput::make('label_width_mm')
                        ->label(__('labels.admin_width'))
                        ->numeric()
                        ->suffix('mm')
                        ->disabled(),

                    Forms\Components\TextInput::make('label_height_mm')
                        ->label(__('labels.admin_height'))
                        ->numeric()
                        ->suffix('mm')
                        ->disabled(),

                    Forms\Components\Toggle::make('is_preset')
                        ->label(__('labels.admin_is_preset')),

                    Forms\Components\Toggle::make('is_default')
                        ->label(__('labels.admin_is_default'))
                        ->disabled(),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('labels.admin_layout_json'))
                ->schema([
                    Forms\Components\Textarea::make('layout_json')
                        ->label(__('labels.admin_layout_json'))
                        ->rows(12)
                        ->disabled()
                        ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : $state),
                ]),
        ]);
    }

    // ─── Infolist (read-only detail view) ────────────────────

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('labels.admin_basic_info'))
                ->schema([
                    Infolists\Components\TextEntry::make('id')
                        ->label(__('labels.admin_id'))
                        ->copyable(),

                    Infolists\Components\TextEntry::make('name')
                        ->label(__('labels.admin_name')),

                    Infolists\Components\TextEntry::make('organization.name')
                        ->label(__('labels.admin_organization')),

                    Infolists\Components\TextEntry::make('label_size')
                        ->label(__('labels.admin_label_size'))
                        ->state(fn (LabelTemplate $r) => $r->label_width_mm . ' × ' . $r->label_height_mm . ' mm'),

                    Infolists\Components\IconEntry::make('is_preset')
                        ->label(__('labels.admin_is_preset'))
                        ->boolean(),

                    Infolists\Components\IconEntry::make('is_default')
                        ->label(__('labels.admin_is_default'))
                        ->boolean(),

                    Infolists\Components\TextEntry::make('sync_version')
                        ->label(__('labels.admin_sync_version')),

                    Infolists\Components\TextEntry::make('createdBy.name')
                        ->label(__('labels.admin_created_by'))
                        ->default('—'),

                    Infolists\Components\TextEntry::make('created_at')
                        ->label(__('labels.admin_created_at'))
                        ->dateTime(),

                    Infolists\Components\TextEntry::make('updated_at')
                        ->label(__('labels.admin_updated_at'))
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
                Tables\Columns\TextColumn::make('name')
                    ->label(__('labels.admin_name'))
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('organization.name')
                    ->label(__('labels.admin_organization'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('label_size')
                    ->label(__('labels.admin_label_size'))
                    ->state(fn (LabelTemplate $r) => $r->label_width_mm . '×' . $r->label_height_mm . 'mm')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\IconColumn::make('is_preset')
                    ->label(__('labels.admin_is_preset'))
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_default')
                    ->label(__('labels.admin_is_default'))
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label(__('labels.admin_created_by'))
                    ->default('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('labels.admin_created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_preset')
                    ->label(__('labels.admin_is_preset')),

                Tables\Filters\TernaryFilter::make('is_default')
                    ->label(__('labels.admin_is_default')),

                Tables\Filters\SelectFilter::make('organization_id')
                    ->label(__('labels.admin_organization'))
                    ->options(fn () => Organization::orderBy('name')->pluck('name', 'id')->toArray())
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('toggle_preset')
                    ->label(fn (LabelTemplate $r) => $r->is_preset ? __('labels.admin_unmark_preset') : __('labels.admin_mark_preset'))
                    ->icon('heroicon-o-star')
                    ->color(fn (LabelTemplate $r) => $r->is_preset ? 'warning' : 'gray')
                    ->requiresConfirmation()
                    ->action(fn (LabelTemplate $record) => $record->update(['is_preset' => ! $record->is_preset])),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['organization', 'createdBy']);
    }

    // ─── Pages ───────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index'  => LabelTemplateResource\Pages\ListLabelTemplates::route('/'),
            'view'   => LabelTemplateResource\Pages\ViewLabelTemplate::route('/{record}'),
        ];
    }
}
