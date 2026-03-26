<?php

namespace App\Filament\Resources;

use App\Domain\ContentOnboarding\Enums\MarketplaceListingStatus;
use App\Domain\ContentOnboarding\Enums\MarketplacePricingType;
use App\Domain\ContentOnboarding\Enums\SubscriptionInterval;
use App\Domain\ContentOnboarding\Models\MarketplaceCategory;
use App\Domain\ContentOnboarding\Models\PosLayoutTemplate;
use App\Domain\ContentOnboarding\Models\TemplateMarketplaceListing;
use App\Domain\ContentOnboarding\Models\Theme;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TemplateMarketplaceListingResource extends Resource
{
    protected static ?string $model = TemplateMarketplaceListing::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'UI Management';

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationLabel(): string
    {
        return __('ui.nav_marketplace_listings');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['ui.manage']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'title_ar', 'publisher_name'];
    }

    // ─── Form ────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('ui.listing_info'))
                ->schema([
                    Forms\Components\Select::make('pos_layout_template_id')
                        ->label(__('ui.layout_template'))
                        ->options(PosLayoutTemplate::where('is_active', true)->pluck('name', 'id'))
                        ->required()
                        ->searchable()
                        ->unique(ignoreRecord: true),
                    Forms\Components\Select::make('theme_id')
                        ->label(__('ui.bundled_theme'))
                        ->options(Theme::where('is_active', true)->pluck('name', 'id'))
                        ->searchable()
                        ->nullable()
                        ->helperText(__('ui.bundled_theme_help')),
                    Forms\Components\TextInput::make('title')
                        ->label(__('ui.title_en'))
                        ->required()
                        ->maxLength(150),
                    Forms\Components\TextInput::make('title_ar')
                        ->label(__('ui.title_ar'))
                        ->required()
                        ->maxLength(150),
                    Forms\Components\Textarea::make('description')
                        ->label(__('ui.description_en'))
                        ->required()
                        ->maxLength(2000),
                    Forms\Components\Textarea::make('description_ar')
                        ->label(__('ui.description_ar'))
                        ->required()
                        ->maxLength(2000),
                    Forms\Components\TextInput::make('short_description')
                        ->label(__('ui.short_description_en'))
                        ->maxLength(300),
                    Forms\Components\TextInput::make('short_description_ar')
                        ->label(__('ui.short_description_ar'))
                        ->maxLength(300),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('ui.publisher_info'))
                ->schema([
                    Forms\Components\TextInput::make('publisher_name')
                        ->label(__('ui.publisher_name'))
                        ->required()
                        ->maxLength(100),
                    Forms\Components\TextInput::make('publisher_avatar_url')
                        ->label(__('ui.publisher_avatar'))
                        ->url()
                        ->maxLength(500),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('ui.pricing'))
                ->schema([
                    Forms\Components\Select::make('pricing_type')
                        ->label(__('ui.pricing_type'))
                        ->options(collect(MarketplacePricingType::cases())->mapWithKeys(
                            fn ($c) => [$c->value => __('ui.pricing_type_' . $c->value)],
                        ))
                        ->required()
                        ->reactive(),
                    Forms\Components\TextInput::make('price_amount')
                        ->label(__('ui.price_amount'))
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->visible(fn (Forms\Get $get) => $get('pricing_type') !== 'free'),
                    Forms\Components\TextInput::make('price_currency')
                        ->label(__('ui.price_currency'))
                        ->default('SAR')
                        ->maxLength(3)
                        ->visible(fn (Forms\Get $get) => $get('pricing_type') !== 'free'),
                    Forms\Components\Select::make('subscription_interval')
                        ->label(__('ui.subscription_interval'))
                        ->options(collect(SubscriptionInterval::cases())->mapWithKeys(
                            fn ($c) => [$c->value => __('ui.interval_' . $c->value)],
                        ))
                        ->visible(fn (Forms\Get $get) => $get('pricing_type') === 'subscription'),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('ui.categorization'))
                ->schema([
                    Forms\Components\Select::make('category_id')
                        ->label(__('ui.marketplace_category'))
                        ->options(MarketplaceCategory::where('is_active', true)->pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),
                    Forms\Components\TagsInput::make('tags')
                        ->label(__('ui.tags')),
                    Forms\Components\TextInput::make('version')
                        ->label(__('ui.version'))
                        ->default('1.0.0')
                        ->maxLength(20),
                    Forms\Components\Textarea::make('changelog')
                        ->label(__('ui.changelog'))
                        ->maxLength(2000),
                ])
                ->columns(2),

            Forms\Components\Section::make(__('ui.media'))
                ->schema([
                    Forms\Components\TagsInput::make('preview_images')
                        ->label(__('ui.preview_images'))
                        ->helperText(__('ui.preview_images_help')),
                    Forms\Components\TextInput::make('demo_video_url')
                        ->label(__('ui.demo_video_url'))
                        ->url()
                        ->maxLength(500),
                ]),

            Forms\Components\Section::make(__('ui.listing_flags'))
                ->schema([
                    Forms\Components\Toggle::make('is_featured')
                        ->label(__('ui.is_featured')),
                    Forms\Components\Toggle::make('is_verified')
                        ->label(__('ui.is_verified')),
                    Forms\Components\Select::make('status')
                        ->label(__('ui.status'))
                        ->options(collect(MarketplaceListingStatus::cases())->mapWithKeys(
                            fn ($c) => [$c->value => __('ui.listing_status_' . $c->value)],
                        ))
                        ->default(MarketplaceListingStatus::Draft->value)
                        ->required(),
                    Forms\Components\Textarea::make('rejection_reason')
                        ->label(__('ui.rejection_reason'))
                        ->visible(fn (Forms\Get $get) => $get('status') === 'rejected'),
                ])
                ->columns(2),
        ]);
    }

    // ─── Table ───────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label(__('ui.title_en'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (TemplateMarketplaceListing $r) => $r->publisher_name),
                Tables\Columns\TextColumn::make('pricing_type')
                    ->label(__('ui.pricing_type'))
                    ->badge()
                    ->color(fn (TemplateMarketplaceListing $r) => match ($r->pricing_type) {
                        MarketplacePricingType::Free => 'success',
                        MarketplacePricingType::OneTime => 'info',
                        MarketplacePricingType::Subscription => 'warning',
                    })
                    ->formatStateUsing(fn (TemplateMarketplaceListing $r) => __('ui.pricing_type_' . $r->pricing_type->value)),
                Tables\Columns\TextColumn::make('price_amount')
                    ->label(__('ui.price'))
                    ->formatStateUsing(fn (TemplateMarketplaceListing $r) => $r->pricing_type === MarketplacePricingType::Free
                        ? __('ui.free')
                        : number_format((float) $r->price_amount, 2) . ' ' . $r->price_currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('ui.status'))
                    ->badge()
                    ->color(fn (TemplateMarketplaceListing $r) => match ($r->status) {
                        MarketplaceListingStatus::Draft => 'gray',
                        MarketplaceListingStatus::PendingReview => 'warning',
                        MarketplaceListingStatus::Approved => 'success',
                        MarketplaceListingStatus::Rejected => 'danger',
                        MarketplaceListingStatus::Suspended => 'danger',
                    })
                    ->formatStateUsing(fn (TemplateMarketplaceListing $r) => __('ui.listing_status_' . $r->status->value)),
                Tables\Columns\TextColumn::make('average_rating')
                    ->label(__('ui.rating'))
                    ->formatStateUsing(fn (TemplateMarketplaceListing $r) => "★ {$r->average_rating} ({$r->review_count})")
                    ->sortable(),
                Tables\Columns\TextColumn::make('download_count')
                    ->label(__('ui.downloads'))
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_featured')
                    ->label(__('ui.is_featured'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label(__('ui.category'))
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('ui.status'))
                    ->options(collect(MarketplaceListingStatus::cases())->mapWithKeys(
                        fn ($c) => [$c->value => __('ui.listing_status_' . $c->value)],
                    )),
                Tables\Filters\SelectFilter::make('pricing_type')
                    ->label(__('ui.pricing_type'))
                    ->options(collect(MarketplacePricingType::cases())->mapWithKeys(
                        fn ($c) => [$c->value => __('ui.pricing_type_' . $c->value)],
                    )),
                Tables\Filters\TernaryFilter::make('is_featured')->label(__('ui.is_featured')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label(__('ui.approve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (TemplateMarketplaceListing $r) => $r->status === MarketplaceListingStatus::PendingReview)
                    ->action(function (TemplateMarketplaceListing $record) {
                        $record->update([
                            'status' => MarketplaceListingStatus::Approved,
                            'approved_by' => auth('admin')->id(),
                            'approved_at' => now(),
                            'published_at' => now(),
                        ]);
                    }),
                Tables\Actions\Action::make('reject')
                    ->label(__('ui.reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (TemplateMarketplaceListing $r) => $r->status === MarketplaceListingStatus::PendingReview)
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label(__('ui.rejection_reason'))
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->action(function (TemplateMarketplaceListing $record, array $data) {
                        $record->update([
                            'status' => MarketplaceListingStatus::Rejected,
                            'rejection_reason' => $data['rejection_reason'],
                        ]);
                    }),
                Tables\Actions\Action::make('suspend')
                    ->label(__('ui.suspend'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (TemplateMarketplaceListing $r) => $r->status === MarketplaceListingStatus::Approved)
                    ->action(fn (TemplateMarketplaceListing $record) => $record->update(['status' => MarketplaceListingStatus::Suspended])),
                Tables\Actions\Action::make('toggle_featured')
                    ->label(fn (TemplateMarketplaceListing $r) => $r->is_featured ? __('ui.unfeature') : __('ui.feature'))
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->action(fn (TemplateMarketplaceListing $record) => $record->update(['is_featured' => ! $record->is_featured])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    // ─── Pages ───────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => TemplateMarketplaceListingResource\Pages\ListTemplateMarketplaceListings::route('/'),
            'create' => TemplateMarketplaceListingResource\Pages\CreateTemplateMarketplaceListing::route('/create'),
            'edit' => TemplateMarketplaceListingResource\Pages\EditTemplateMarketplaceListing::route('/{record}/edit'),
        ];
    }
}
