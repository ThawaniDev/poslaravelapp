<?php

namespace App\Filament\Resources;

use App\Domain\Notification\Enums\NotificationChannel;
use App\Domain\Notification\Models\NotificationTemplate;
use App\Domain\Notification\Services\NotificationTemplateService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NotificationTemplateResource extends Resource
{
    protected static ?string $model = NotificationTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_notifications');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('nav.templates');
    }

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['notifications.manage']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('notifications.template_details'))
                ->schema([
                    Forms\Components\Select::make('event_key')
                        ->label(__('notifications.event_key'))
                        ->options(NotificationTemplateService::eventSelectOptions())
                        ->searchable()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                            if ($state) {
                                $vars = NotificationTemplateService::getAvailableVariables($state);
                                $set('available_variables', $vars);
                            }
                        }),

                    Forms\Components\Select::make('channel')
                        ->label(__('notifications.channel'))
                        ->options(
                            collect(NotificationChannel::cases())
                                ->mapWithKeys(fn (NotificationChannel $c) => [$c->value => __("notifications.channel_{$c->value}")])
                                ->toArray()
                        )
                        ->required(),

                    Forms\Components\Toggle::make('is_active')
                        ->label(__('notifications.is_active'))
                        ->default(true)
                        ->columnSpanFull(),
                ])->columns(2),

            Forms\Components\Section::make(__('notifications.english_content'))
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label(__('notifications.title_en'))
                        ->required()
                        ->maxLength(255)
                        ->helperText(__('notifications.variable_hint')),
                    Forms\Components\Textarea::make('body')
                        ->label(__('notifications.body_en'))
                        ->required()
                        ->rows(4)
                        ->helperText(__('notifications.variable_hint')),
                ])->columns(1),

            Forms\Components\Section::make(__('notifications.arabic_content'))
                ->schema([
                    Forms\Components\TextInput::make('title_ar')
                        ->label(__('notifications.title_ar'))
                        ->maxLength(255)
                        ->helperText(__('notifications.variable_hint')),
                    Forms\Components\Textarea::make('body_ar')
                        ->label(__('notifications.body_ar'))
                        ->rows(4)
                        ->helperText(__('notifications.variable_hint')),
                ])->columns(1),

            Forms\Components\Section::make(__('notifications.available_variables'))
                ->schema([
                    Forms\Components\TagsInput::make('available_variables')
                        ->label(__('notifications.available_variables'))
                        ->helperText(__('notifications.available_variables_hint'))
                        ->placeholder(__('notifications.add_variable')),
                ])->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event_key')
                    ->label(__('notifications.event_key'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (NotificationTemplate $record) => self::getEventCategory($record->event_key)),

                Tables\Columns\TextColumn::make('channel')
                    ->label(__('notifications.channel'))
                    ->badge()
                    ->color(fn (NotificationChannel $state) => match ($state) {
                        NotificationChannel::InApp => 'info',
                        NotificationChannel::Push => 'success',
                        NotificationChannel::Sms => 'warning',
                        NotificationChannel::Email => 'primary',
                        NotificationChannel::Whatsapp => 'success',
                        NotificationChannel::Sound => 'gray',
                    })
                    ->formatStateUsing(fn (NotificationChannel $state) => __("notifications.channel_{$state->value}"))
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->label(__('notifications.title_en'))
                    ->limit(40)
                    ->searchable(),

                Tables\Columns\TextColumn::make('title_ar')
                    ->label(__('notifications.title_ar'))
                    ->limit(40),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('notifications.is_active'))
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('notifications.updated_at'))
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('channel')
                    ->options(
                        collect(NotificationChannel::cases())
                            ->mapWithKeys(fn (NotificationChannel $c) => [$c->value => __("notifications.channel_{$c->value}")])
                            ->toArray()
                    ),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('notifications.is_active')),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label(__('notifications.preview'))
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(__('notifications.preview_template'))
                    ->modalContent(function (NotificationTemplate $record) {
                        $service = app(NotificationTemplateService::class);
                        $previewEn = $service->renderPreview($record, 'en');
                        $previewAr = $service->renderPreview($record, 'ar');

                        return view('filament.pages.notification-template-preview', [
                            'previewEn' => $previewEn,
                            'previewAr' => $previewAr,
                            'template' => $record,
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('notifications.close')),
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn (NotificationTemplate $record) => $record->is_active ? __('notifications.deactivate') : __('notifications.activate'))
                    ->icon(fn (NotificationTemplate $record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (NotificationTemplate $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (NotificationTemplate $record) {
                        $record->update(['is_active' => !$record->is_active]);
                        app(NotificationTemplateService::class)->flushTemplateCache($record);
                        Notification::make()
                            ->title($record->is_active ? __('notifications.template_activated') : __('notifications.template_deactivated'))
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('event_key');
    }

    public static function getPages(): array
    {
        return [
            'index' => NotificationTemplateResource\Pages\ListNotificationTemplates::route('/'),
            'create' => NotificationTemplateResource\Pages\CreateNotificationTemplate::route('/create'),
            'edit' => NotificationTemplateResource\Pages\EditNotificationTemplate::route('/{record}/edit'),
        ];
    }

    private static function getEventCategory(string $eventKey): string
    {
        $events = NotificationTemplateService::allEvents();
        return $events[$eventKey]['category_label'] ?? '';
    }
}
