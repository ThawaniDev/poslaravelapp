<?php

namespace App\Filament\Resources;

use App\Domain\ProviderRegistration\Models\ProviderPermission;
use App\Domain\StaffManagement\Models\DefaultRoleTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DefaultRoleTemplateResource extends Resource
{
    protected static ?string $model = DefaultRoleTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_people');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('nav.role_templates');
    }

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['provider_roles.view', 'provider_roles.manage']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Template Details'))
                ->description(__('Role template applied to new provider stores'))
                ->icon('heroicon-o-document-duplicate')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('Name (EN)'))
                        ->required()
                        ->maxLength(100),

                    Forms\Components\TextInput::make('name_ar')
                        ->label(__('Name (AR)'))
                        ->maxLength(100),

                    Forms\Components\TextInput::make('slug')
                        ->label(__('Slug'))
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true)
                        ->disabled(fn (?DefaultRoleTemplate $record): bool => $record !== null)
                        ->dehydrated(),

                    Forms\Components\Textarea::make('description')
                        ->label(__('Description (EN)'))
                        ->rows(2)
                        ->maxLength(500),

                    Forms\Components\Textarea::make('description_ar')
                        ->label(__('Description (AR)'))
                        ->rows(2)
                        ->maxLength(500),
                ])->columns(2),

            Forms\Components\Section::make(__('Permissions'))
                ->description(__('Select which provider permissions this template includes. Changes affect new stores only.'))
                ->icon('heroicon-o-key')
                ->schema([
                    Forms\Components\CheckboxList::make('permissions')
                        ->label('')
                        ->relationship('permissions', 'name')
                        ->options(function () {
                            return \Illuminate\Support\Facades\Cache::store('array')
                                ->rememberForever('provider_permissions_all', fn () => ProviderPermission::query()
                                    ->where('is_active', true)->orderBy('group')->orderBy('name')->get())
                                ->mapWithKeys(fn ($p) => [$p->id => "[{$p->group}] {$p->name}"]);
                        })
                        ->descriptions(function () {
                            return \Illuminate\Support\Facades\Cache::store('array')
                                ->rememberForever('provider_permissions_all', fn () => ProviderPermission::query()
                                    ->where('is_active', true)->orderBy('group')->orderBy('name')->get())
                                ->mapWithKeys(fn ($p) => [$p->id => $p->description]);
                        })
                        ->searchable()
                        ->bulkToggleable()
                        ->columns(2)
                        ->gridDirection('row'),
                ])->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name_ar')
                    ->label(__('Name (AR)'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label(__('Slug'))
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('permissions_count')
                    ->label(__('Permissions'))
                    ->counts('permissions')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('description')
                    ->label(__('Description'))
                    ->limit(50)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Updated'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('name');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('Template Details'))
                ->icon('heroicon-o-document-duplicate')
                ->schema([
                    Infolists\Components\TextEntry::make('name')->label(__('Name (EN)')),
                    Infolists\Components\TextEntry::make('name_ar')->label(__('Name (AR)')),
                    Infolists\Components\TextEntry::make('slug')->label(__('Slug'))->badge()->color('gray'),
                    Infolists\Components\TextEntry::make('description')->label(__('Description (EN)'))->columnSpanFull(),
                    Infolists\Components\TextEntry::make('description_ar')->label(__('Description (AR)'))->columnSpanFull(),
                ])->columns(3),

            Infolists\Components\Section::make(__('Assigned Permissions'))
                ->icon('heroicon-o-key')
                ->schema([
                    Infolists\Components\TextEntry::make('permissions.name')
                        ->label('')
                        ->badge()
                        ->color('info')
                        ->separator(', '),
                ])->collapsible(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => DefaultRoleTemplateResource\Pages\ListDefaultRoleTemplates::route('/'),
            'create' => DefaultRoleTemplateResource\Pages\CreateDefaultRoleTemplate::route('/create'),
            'view' => DefaultRoleTemplateResource\Pages\ViewDefaultRoleTemplate::route('/{record}'),
            'edit' => DefaultRoleTemplateResource\Pages\EditDefaultRoleTemplate::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('permissions');
    }
}
