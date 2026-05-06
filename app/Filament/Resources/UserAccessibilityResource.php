<?php

namespace App\Filament\Resources;

use App\Domain\Auth\Models\User;
use App\Domain\Core\Models\Store;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Form;

/**
 * Admin read-only view of all users' accessibility preferences.
 * Accessible to admins with security.view or provider_roles.manage.
 *
 * Allows filtering by store, searching by user name/email,
 * and inspecting each user's saved accessibility_json.
 */
class UserAccessibilityResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-eye';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'user-accessibility';

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_people');
    }

    public static function getNavigationLabel(): string
    {
        return __('Accessibility Settings');
    }

    public static function getModelLabel(): string
    {
        return __('User Accessibility');
    }

    public static function getPluralModelLabel(): string
    {
        return __('User Accessibility Settings');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['security.view', 'provider_roles.manage']);
    }

    public static function canCreate(): bool   { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('User'))
                ->columns(2)
                ->schema([
                    Infolists\Components\TextEntry::make('name')
                        ->label(__('Name')),
                    Infolists\Components\TextEntry::make('email')
                        ->label(__('Email'))
                        ->copyable(),
                    Infolists\Components\TextEntry::make('store.name')
                        ->label(__('Store')),
                ]),

            Infolists\Components\Section::make(__('Accessibility Preferences'))
                ->description(__('Values from accessibility_json. Null means user has not saved any preferences.'))
                ->columns(2)
                ->schema([
                    Infolists\Components\TextEntry::make('preference.accessibility_json.font_scale')
                        ->label(__('Font Scale'))
                        ->default('1.0 (default)')
                        ->placeholder('—'),
                    Infolists\Components\IconEntry::make('preference.accessibility_json.high_contrast')
                        ->label(__('High Contrast'))
                        ->boolean(),
                    Infolists\Components\TextEntry::make('preference.accessibility_json.color_blind_mode')
                        ->label(__('Color Blind Mode'))
                        ->badge()
                        ->color(fn ($state) => match($state) {
                            'protanopia'   => 'warning',
                            'deuteranopia' => 'info',
                            'tritanopia'   => 'success',
                            default        => 'gray',
                        })
                        ->default('none'),
                    Infolists\Components\IconEntry::make('preference.accessibility_json.reduced_motion')
                        ->label(__('Reduced Motion'))
                        ->boolean(),
                    Infolists\Components\IconEntry::make('preference.accessibility_json.audio_feedback')
                        ->label(__('Audio Feedback'))
                        ->boolean(),
                    Infolists\Components\TextEntry::make('preference.accessibility_json.audio_volume')
                        ->label(__('Audio Volume'))
                        ->default('0.7 (default)'),
                    Infolists\Components\IconEntry::make('preference.accessibility_json.large_touch_targets')
                        ->label(__('Large Touch Targets'))
                        ->boolean(),
                    Infolists\Components\IconEntry::make('preference.accessibility_json.visible_focus')
                        ->label(__('Visible Focus'))
                        ->boolean(),
                    Infolists\Components\IconEntry::make('preference.accessibility_json.screen_reader_hints')
                        ->label(__('Screen Reader Hints'))
                        ->boolean(),
                ]),

            Infolists\Components\Section::make(__('Custom Shortcuts'))
                ->description(__('Overridden keyboard shortcuts. Empty if user uses defaults.'))
                ->schema([
                    Infolists\Components\KeyValueEntry::make('preference.accessibility_json.custom_shortcuts')
                        ->label(__('Shortcuts'))
                        ->columnSpanFull(),
                ])
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['preference', 'store'])
                ->whereHas('preference', fn ($q) => $q->whereNotNull('accessibility_json'))
                ->orWhereDoesntHave('preference'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('User'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('store.name')
                    ->label(__('Store'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('preference.accessibility_json.font_scale')
                    ->label(__('Font Scale'))
                    ->default('—'),
                Tables\Columns\IconColumn::make('preference.accessibility_json.high_contrast')
                    ->label(__('High Contrast'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('preference.accessibility_json.color_blind_mode')
                    ->label(__('Color Blind'))
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'protanopia'   => 'warning',
                        'deuteranopia' => 'info',
                        'tritanopia'   => 'success',
                        default        => 'gray',
                    })
                    ->default('none'),
                Tables\Columns\IconColumn::make('preference.accessibility_json.reduced_motion')
                    ->label(__('Reduced Motion'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label(__('Store'))
                    ->relationship('store', 'name')
                    ->preload()
                    ->searchable(),
                Tables\Filters\Filter::make('has_custom_prefs')
                    ->label(__('Has Custom Prefs'))
                    ->query(fn ($query) => $query->whereHas('preference',
                        fn ($q) => $q->whereNotNull('accessibility_json'))),
                Tables\Filters\SelectFilter::make('color_blind_mode')
                    ->label(__('Color Blind Mode'))
                    ->options([
                        'none'         => 'None',
                        'protanopia'   => 'Protanopia',
                        'deuteranopia' => 'Deuteranopia',
                        'tritanopia'   => 'Tritanopia',
                    ])
                    ->query(fn ($query, array $data) => $data['value']
                        ? $query->whereHas('preference',
                            fn ($q) => $q->where("accessibility_json->color_blind_mode", $data['value']))
                        : $query),
            ])
            ->defaultSort('name')
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\UserAccessibilityResource\Pages\ListUserAccessibility::route('/'),
            'view'  => \App\Filament\Resources\UserAccessibilityResource\Pages\ViewUserAccessibility::route('/{record}'),
        ];
    }
}
