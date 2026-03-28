<?php

namespace App\Filament\Resources;

use App\Domain\AdminPanel\Models\AdminActivityLog;
use App\Domain\Announcement\Enums\AnnouncementType;
use App\Domain\Announcement\Models\PlatformAnnouncement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlatformAnnouncementResource extends Resource
{
    protected static ?string $model = PlatformAnnouncement::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_business');
    }

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 6;

    public static function getNavigationLabel(): string
    {
        return __('announcements.announcements');
    }

    public static function getModelLabel(): string
    {
        return __('announcements.announcement');
    }

    public static function getPluralModelLabel(): string
    {
        return __('announcements.announcements');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();
        return $user && $user->hasAnyPermission(['announcements.view', 'announcements.manage']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('announcements.announcement_details'))
                ->schema([
                    Forms\Components\Select::make('type')
                        ->label(__('announcements.type'))
                        ->options(collect(AnnouncementType::cases())->mapWithKeys(fn ($c) => [$c->value => __('announcements.type_' . $c->value)]))
                        ->required()
                        ->native(false),
                    Forms\Components\TextInput::make('title')
                        ->label(__('announcements.title_en'))
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('title_ar')
                        ->label(__('announcements.title_ar'))
                        ->maxLength(255),
                ])->columns(2),

            Forms\Components\Section::make(__('announcements.content'))
                ->schema([
                    Forms\Components\RichEditor::make('body')
                        ->label(__('announcements.body_en'))
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\RichEditor::make('body_ar')
                        ->label(__('announcements.body_ar'))
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make(__('announcements.targeting'))
                ->schema([
                    Forms\Components\KeyValue::make('target_filter')
                        ->label(__('announcements.target_filter'))
                        ->helperText(__('announcements.target_filter_helper'))
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed(),

            Forms\Components\Section::make(__('announcements.scheduling'))
                ->schema([
                    Forms\Components\DateTimePicker::make('display_start_at')
                        ->label(__('announcements.display_start'))
                        ->native(false),
                    Forms\Components\DateTimePicker::make('display_end_at')
                        ->label(__('announcements.display_end'))
                        ->native(false)
                        ->after('display_start_at'),
                ])->columns(2),

            Forms\Components\Section::make(__('announcements.delivery'))
                ->schema([
                    Forms\Components\Toggle::make('is_banner')
                        ->label(__('announcements.is_banner'))
                        ->helperText(__('announcements.banner_helper')),
                    Forms\Components\Toggle::make('send_push')
                        ->label(__('announcements.send_push')),
                    Forms\Components\Toggle::make('send_email')
                        ->label(__('announcements.send_email')),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label(__('announcements.title'))
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('announcements.type'))
                    ->formatStateUsing(fn ($state) => __('announcements.type_' . $state->value))
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        AnnouncementType::Info => 'info',
                        AnnouncementType::Warning => 'warning',
                        AnnouncementType::Maintenance => 'danger',
                        AnnouncementType::Update => 'success',
                        AnnouncementType::Feature => 'primary',
                    })
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_banner')
                    ->label(__('announcements.is_banner'))
                    ->boolean(),
                Tables\Columns\IconColumn::make('send_push')
                    ->label(__('announcements.push'))
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('send_email')
                    ->label(__('announcements.email'))
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('display_start_at')
                    ->label(__('announcements.display_start'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('display_end_at')
                    ->label(__('announcements.display_end'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('platformAnnouncementDismissals_count')
                    ->counts('platformAnnouncementDismissals')
                    ->label(__('announcements.dismissals'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label(__('announcements.created_by'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('announcements.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label(__('announcements.type'))
                    ->options(collect(AnnouncementType::cases())->mapWithKeys(fn ($c) => [$c->value => __('announcements.type_' . $c->value)])),
                Tables\Filters\TernaryFilter::make('is_banner')
                    ->label(__('announcements.is_banner')),
                Tables\Filters\Filter::make('active')
                    ->label(__('announcements.currently_active'))
                    ->query(fn ($query) => $query
                        ->where(fn ($q) => $q->whereNull('display_start_at')->orWhere('display_start_at', '<=', now()))
                        ->where(fn ($q) => $q->whereNull('display_end_at')->orWhere('display_end_at', '>=', now()))
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        AdminActivityLog::record(
                            adminUserId: auth('admin')->id(),
                            action: 'delete_announcement',
                            entityType: 'platform_announcement',
                            entityId: $record->id,
                            details: ['title' => $record->title, 'type' => $record->type->value],
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
            'index' => PlatformAnnouncementResource\Pages\ListPlatformAnnouncements::route('/'),
            'create' => PlatformAnnouncementResource\Pages\CreatePlatformAnnouncement::route('/create'),
            'edit' => PlatformAnnouncementResource\Pages\EditPlatformAnnouncement::route('/{record}/edit'),
        ];
    }
}
