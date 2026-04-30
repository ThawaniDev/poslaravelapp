<?php

namespace App\Filament\Resources;

use App\Domain\ContentOnboarding\Models\PricingPageContent;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PricingPageContentResource extends Resource
{
    protected static ?string $model = PricingPageContent::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_content');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('nav.pricing_page');
    }

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['content.pricing']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FORM
    // ─────────────────────────────────────────────────────────────────────────
    public static function form(Form $form): Form
    {
        return $form->schema([

            // ── Linked Plan ───────────────────────────────────────────────
            Forms\Components\Section::make(__('pricing.section_plan'))
                ->icon('heroicon-o-link')
                ->schema([
                    Forms\Components\Select::make('subscription_plan_id')
                        ->label(__('pricing.subscription_plan'))
                        ->options(SubscriptionPlan::orderBy('sort_order')->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->columnSpanFull(),
                ]),

            // ── Publishing ────────────────────────────────────────────────
            Forms\Components\Section::make(__('pricing.section_publishing'))
                ->icon('heroicon-o-eye')
                ->columns(3)
                ->schema([
                    Forms\Components\Toggle::make('is_published')
                        ->label(__('pricing.is_published'))
                        ->default(true),
                    Forms\Components\Toggle::make('is_highlighted')
                        ->label(__('pricing.is_highlighted'))
                        ->helperText(__('pricing.is_highlighted_hint'))
                        ->default(false),
                    Forms\Components\TextInput::make('sort_order')
                        ->label(__('pricing.sort_order'))
                        ->numeric()
                        ->default(0),
                ]),

            // ── Hero / Display ────────────────────────────────────────────
            Forms\Components\Section::make(__('pricing.section_hero'))
                ->icon('heroicon-o-star')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('hero_title')
                        ->label(__('pricing.hero_title'))
                        ->maxLength(150),
                    Forms\Components\TextInput::make('hero_title_ar')
                        ->label(__('pricing.hero_title_ar'))
                        ->maxLength(150)
                        ->extraAttributes(['dir' => 'rtl']),
                    Forms\Components\Textarea::make('hero_subtitle')
                        ->label(__('pricing.hero_subtitle'))
                        ->rows(2)
                        ->maxLength(300),
                    Forms\Components\Textarea::make('hero_subtitle_ar')
                        ->label(__('pricing.hero_subtitle_ar'))
                        ->rows(2)
                        ->maxLength(300)
                        ->extraAttributes(['dir' => 'rtl']),
                ]),

            // ── Badge / Highlight ─────────────────────────────────────────
            Forms\Components\Section::make(__('pricing.section_badge'))
                ->icon('heroicon-o-tag')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('highlight_badge')
                        ->label(__('pricing.highlight_badge'))
                        ->placeholder(__('pricing.placeholder_most_popular'))
                        ->maxLength(60),
                    Forms\Components\TextInput::make('highlight_badge_ar')
                        ->label(__('pricing.highlight_badge_ar'))
                        ->placeholder('الأكثر شيوعاً')
                        ->maxLength(60)
                        ->extraAttributes(['dir' => 'rtl']),
                    Forms\Components\Select::make('highlight_color')
                        ->label(__('pricing.highlight_color'))
                        ->options([
                            'primary' => __('pricing.color_primary'),
                            'success' => __('pricing.color_success'),
                            'warning' => __('pricing.color_warning'),
                            'danger'  => __('pricing.color_danger'),
                            'info'    => __('pricing.color_info'),
                            'gray'    => __('pricing.color_gray'),
                        ])
                        ->default('primary'),
                    Forms\Components\Select::make('color_theme')
                        ->label(__('pricing.color_theme'))
                        ->options([
                            'primary' => __('pricing.color_primary'),
                            'success' => __('pricing.color_success'),
                            'warning' => __('pricing.color_warning'),
                            'danger'  => __('pricing.color_danger'),
                            'info'    => __('pricing.color_info'),
                            'gray'    => __('pricing.color_gray'),
                        ])
                        ->default('primary'),
                    Forms\Components\TextInput::make('card_icon')
                        ->label(__('pricing.card_icon'))
                        ->placeholder('heroicon-o-star')
                        ->maxLength(100),
                    Forms\Components\TextInput::make('card_image_url')
                        ->label(__('pricing.card_image_url'))
                        ->url()
                        ->maxLength(500),
                ]),

            // ── CTA Buttons ───────────────────────────────────────────────
            Forms\Components\Section::make(__('pricing.section_cta'))
                ->icon('heroicon-o-cursor-arrow-rays')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('cta_label')
                        ->label(__('pricing.cta_label'))
                        ->placeholder(__('pricing.placeholder_get_started'))
                        ->maxLength(80),
                    Forms\Components\TextInput::make('cta_label_ar')
                        ->label(__('pricing.cta_label_ar'))
                        ->placeholder('ابدأ الآن')
                        ->maxLength(80)
                        ->extraAttributes(['dir' => 'rtl']),
                    Forms\Components\TextInput::make('cta_secondary_label')
                        ->label(__('pricing.cta_secondary_label'))
                        ->placeholder(__('pricing.placeholder_learn_more'))
                        ->maxLength(80),
                    Forms\Components\TextInput::make('cta_secondary_label_ar')
                        ->label(__('pricing.cta_secondary_label_ar'))
                        ->placeholder('اعرف المزيد')
                        ->maxLength(80)
                        ->extraAttributes(['dir' => 'rtl']),
                    Forms\Components\TextInput::make('cta_url')
                        ->label(__('pricing.cta_url'))
                        ->url()
                        ->maxLength(500)
                        ->columnSpanFull(),
                ]),

            // ── Pricing Display Overrides ─────────────────────────────────
            Forms\Components\Section::make(__('pricing.section_pricing_display'))
                ->icon('heroicon-o-currency-dollar')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('price_prefix')
                        ->label(__('pricing.price_prefix'))
                        ->placeholder(__('pricing.placeholder_starting_at'))
                        ->maxLength(60),
                    Forms\Components\TextInput::make('price_prefix_ar')
                        ->label(__('pricing.price_prefix_ar'))
                        ->placeholder('يبدأ من')
                        ->maxLength(60)
                        ->extraAttributes(['dir' => 'rtl']),
                    Forms\Components\TextInput::make('price_suffix')
                        ->label(__('pricing.price_suffix'))
                        ->placeholder('/ month')
                        ->maxLength(60),
                    Forms\Components\TextInput::make('price_suffix_ar')
                        ->label(__('pricing.price_suffix_ar'))
                        ->placeholder('/ شهرياً')
                        ->maxLength(60)
                        ->extraAttributes(['dir' => 'rtl']),
                    Forms\Components\TextInput::make('annual_discount_label')
                        ->label(__('pricing.annual_discount_label'))
                        ->placeholder('Save 20% annually')
                        ->maxLength(100),
                    Forms\Components\TextInput::make('annual_discount_label_ar')
                        ->label(__('pricing.annual_discount_label_ar'))
                        ->placeholder('وفّر 20% سنوياً')
                        ->maxLength(100)
                        ->extraAttributes(['dir' => 'rtl']),
                    Forms\Components\TextInput::make('trial_label')
                        ->label(__('pricing.trial_label'))
                        ->placeholder('14-day free trial')
                        ->maxLength(100),
                    Forms\Components\TextInput::make('trial_label_ar')
                        ->label(__('pricing.trial_label_ar'))
                        ->placeholder('تجربة مجانية 14 يوماً')
                        ->maxLength(100)
                        ->extraAttributes(['dir' => 'rtl']),
                    Forms\Components\TextInput::make('money_back_days')
                        ->label(__('pricing.money_back_days'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(365)
                        ->suffix(__('pricing.days'))
                        ->columnSpanFull(),
                ]),

            // ── Feature Bullet List ───────────────────────────────────────
            Forms\Components\Section::make(__('pricing.section_features'))
                ->icon('heroicon-o-check-circle')
                ->schema([
                    Forms\Components\Repeater::make('feature_bullet_list')
                        ->label(__('pricing.feature_bullet_list'))
                        ->schema([
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\TextInput::make('text_en')
                                    ->label(__('pricing.feature_text_en'))
                                    ->required()
                                    ->maxLength(200),
                                Forms\Components\TextInput::make('text_ar')
                                    ->label(__('pricing.feature_text_ar'))
                                    ->required()
                                    ->maxLength(200)
                                    ->extraAttributes(['dir' => 'rtl']),
                            ]),
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\TextInput::make('icon')
                                    ->label(__('pricing.feature_icon'))
                                    ->placeholder('heroicon-o-check')
                                    ->maxLength(100),
                                Forms\Components\Toggle::make('is_included')
                                    ->label(__('pricing.feature_included'))
                                    ->default(true),
                                Forms\Components\Toggle::make('is_highlighted')
                                    ->label(__('pricing.feature_highlighted'))
                                    ->default(false),
                            ]),
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\TextInput::make('tooltip_en')
                                    ->label(__('pricing.feature_tooltip_en'))
                                    ->maxLength(300),
                                Forms\Components\TextInput::make('tooltip_ar')
                                    ->label(__('pricing.feature_tooltip_ar'))
                                    ->maxLength(300)
                                    ->extraAttributes(['dir' => 'rtl']),
                            ]),
                        ])
                        ->addActionLabel(__('pricing.add_feature'))
                        ->collapsible()
                        ->cloneable()
                        ->reorderable()
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ]),

            // ── Structured Feature Categories ─────────────────────────────
            Forms\Components\Section::make(__('pricing.section_feature_categories'))
                ->icon('heroicon-o-squares-2x2')
                ->schema([
                    Forms\Components\Repeater::make('feature_categories')
                        ->label(__('pricing.feature_categories'))
                        ->schema([
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\TextInput::make('category_en')
                                    ->label(__('pricing.category_name_en'))
                                    ->required()
                                    ->maxLength(100),
                                Forms\Components\TextInput::make('category_ar')
                                    ->label(__('pricing.category_name_ar'))
                                    ->required()
                                    ->maxLength(100)
                                    ->extraAttributes(['dir' => 'rtl']),
                            ]),
                            Forms\Components\Repeater::make('features')
                                ->label(__('pricing.category_features'))
                                ->schema([
                                    Forms\Components\Grid::make(2)->schema([
                                        Forms\Components\TextInput::make('text_en')
                                            ->label(__('pricing.feature_text_en'))
                                            ->required()
                                            ->maxLength(200),
                                        Forms\Components\TextInput::make('text_ar')
                                            ->label(__('pricing.feature_text_ar'))
                                            ->required()
                                            ->maxLength(200)
                                            ->extraAttributes(['dir' => 'rtl']),
                                    ]),
                                    Forms\Components\Grid::make(4)->schema([
                                        Forms\Components\TextInput::make('limit')
                                            ->label(__('pricing.feature_limit'))
                                            ->placeholder(__('pricing.placeholder_unlimited'))
                                            ->maxLength(50),
                                        Forms\Components\TextInput::make('icon')
                                            ->label(__('pricing.feature_icon'))
                                            ->placeholder('heroicon-o-check')
                                            ->maxLength(100),
                                        Forms\Components\Toggle::make('is_included')
                                            ->label(__('pricing.feature_included'))
                                            ->default(true),
                                        Forms\Components\Toggle::make('is_highlighted')
                                            ->label(__('pricing.feature_highlighted'))
                                            ->default(false),
                                    ]),
                                    Forms\Components\Grid::make(2)->schema([
                                        Forms\Components\TextInput::make('tooltip_en')
                                            ->label(__('pricing.feature_tooltip_en'))
                                            ->maxLength(300),
                                        Forms\Components\TextInput::make('tooltip_ar')
                                            ->label(__('pricing.feature_tooltip_ar'))
                                            ->maxLength(300)
                                            ->extraAttributes(['dir' => 'rtl']),
                                    ]),
                                ])
                                ->addActionLabel(__('pricing.add_feature'))
                                ->collapsible()
                                ->reorderable()
                                ->defaultItems(0),
                        ])
                        ->addActionLabel(__('pricing.add_category'))
                        ->collapsible()
                        ->reorderable()
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ]),

            // ── Comparison Highlights ─────────────────────────────────────
            Forms\Components\Section::make(__('pricing.section_comparison'))
                ->icon('heroicon-o-table-cells')
                ->schema([
                    Forms\Components\Repeater::make('comparison_highlights')
                        ->label(__('pricing.comparison_highlights'))
                        ->schema([
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\TextInput::make('feature_en')
                                    ->label(__('pricing.comparison_feature_en'))
                                    ->required()
                                    ->maxLength(150),
                                Forms\Components\TextInput::make('feature_ar')
                                    ->label(__('pricing.comparison_feature_ar'))
                                    ->required()
                                    ->maxLength(150)
                                    ->extraAttributes(['dir' => 'rtl']),
                            ]),
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\TextInput::make('value')
                                    ->label(__('pricing.comparison_value'))
                                    ->required()
                                    ->maxLength(80),
                                Forms\Components\TextInput::make('note_en')
                                    ->label(__('pricing.comparison_note_en'))
                                    ->maxLength(200),
                                Forms\Components\TextInput::make('note_ar')
                                    ->label(__('pricing.comparison_note_ar'))
                                    ->maxLength(200)
                                    ->extraAttributes(['dir' => 'rtl']),
                            ]),
                        ])
                        ->addActionLabel(__('pricing.add_comparison_row'))
                        ->collapsible()
                        ->reorderable()
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ]),

            // ── FAQ ───────────────────────────────────────────────────────
            Forms\Components\Section::make(__('pricing.section_faq'))
                ->icon('heroicon-o-question-mark-circle')
                ->schema([
                    Forms\Components\Repeater::make('faq')
                        ->label(__('pricing.faq'))
                        ->schema([
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\TextInput::make('question_en')
                                    ->label(__('pricing.faq_question_en'))
                                    ->required()
                                    ->maxLength(300),
                                Forms\Components\TextInput::make('question_ar')
                                    ->label(__('pricing.faq_question_ar'))
                                    ->required()
                                    ->maxLength(300)
                                    ->extraAttributes(['dir' => 'rtl']),
                            ]),
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\Textarea::make('answer_en')
                                    ->label(__('pricing.faq_answer_en'))
                                    ->required()
                                    ->rows(3)
                                    ->maxLength(1000),
                                Forms\Components\Textarea::make('answer_ar')
                                    ->label(__('pricing.faq_answer_ar'))
                                    ->required()
                                    ->rows(3)
                                    ->maxLength(1000)
                                    ->extraAttributes(['dir' => 'rtl']),
                            ]),
                        ])
                        ->addActionLabel(__('pricing.add_faq'))
                        ->collapsible()
                        ->reorderable()
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ]),

            // ── Testimonials ──────────────────────────────────────────────
            Forms\Components\Section::make(__('pricing.section_testimonials'))
                ->icon('heroicon-o-chat-bubble-left-right')
                ->schema([
                    Forms\Components\Repeater::make('testimonials')
                        ->label(__('pricing.testimonials'))
                        ->schema([
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('pricing.testimonial_name'))
                                    ->required()
                                    ->maxLength(100),
                                Forms\Components\TextInput::make('company')
                                    ->label(__('pricing.testimonial_company'))
                                    ->maxLength(100),
                                Forms\Components\Select::make('rating')
                                    ->label(__('pricing.testimonial_rating'))
                                    ->options([1 => '1 ★', 2 => '2 ★★', 3 => '3 ★★★', 4 => '4 ★★★★', 5 => '5 ★★★★★'])
                                    ->default(5),
                            ]),
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\TextInput::make('role_en')
                                    ->label(__('pricing.testimonial_role_en'))
                                    ->maxLength(100),
                                Forms\Components\TextInput::make('role_ar')
                                    ->label(__('pricing.testimonial_role_ar'))
                                    ->maxLength(100)
                                    ->extraAttributes(['dir' => 'rtl']),
                            ]),
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\Textarea::make('text_en')
                                    ->label(__('pricing.testimonial_text_en'))
                                    ->required()
                                    ->rows(3)
                                    ->maxLength(500),
                                Forms\Components\Textarea::make('text_ar')
                                    ->label(__('pricing.testimonial_text_ar'))
                                    ->required()
                                    ->rows(3)
                                    ->maxLength(500)
                                    ->extraAttributes(['dir' => 'rtl']),
                            ]),
                            Forms\Components\TextInput::make('avatar_url')
                                ->label(__('pricing.testimonial_avatar_url'))
                                ->url()
                                ->maxLength(500)
                                ->columnSpanFull(),
                        ])
                        ->addActionLabel(__('pricing.add_testimonial'))
                        ->collapsible()
                        ->cloneable()
                        ->reorderable()
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ]),

            // ── SEO Meta ──────────────────────────────────────────────────
            Forms\Components\Section::make(__('pricing.section_seo'))
                ->icon('heroicon-o-magnifying-glass')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Forms\Components\TextInput::make('meta_title')
                        ->label(__('pricing.meta_title'))
                        ->maxLength(160),
                    Forms\Components\TextInput::make('meta_title_ar')
                        ->label(__('pricing.meta_title_ar'))
                        ->maxLength(160)
                        ->extraAttributes(['dir' => 'rtl']),
                    Forms\Components\Textarea::make('meta_description')
                        ->label(__('pricing.meta_description'))
                        ->rows(2)
                        ->maxLength(320),
                    Forms\Components\Textarea::make('meta_description_ar')
                        ->label(__('pricing.meta_description_ar'))
                        ->rows(2)
                        ->maxLength(320)
                        ->extraAttributes(['dir' => 'rtl']),
                ]),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TABLE
    // ─────────────────────────────────────────────────────────────────────────
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('subscriptionPlan.name')
                    ->label(__('pricing.plan'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('hero_title')
                    ->label(__('pricing.hero_title'))
                    ->limit(40)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('highlight_badge')
                    ->label(__('pricing.highlight_badge'))
                    ->badge()
                    ->color(fn (PricingPageContent $record) => $record->highlight_color ?? 'gray')
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('is_highlighted')
                    ->label(__('pricing.is_highlighted'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_published')
                    ->label(__('pricing.is_published'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('pricing.sort_order'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('feature_bullet_list')
                    ->label(__('pricing.features_count'))
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state) . ' ' . __('pricing.features') : '0')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('faq')
                    ->label(__('pricing.faq_count'))
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state) . ' ' . __('pricing.faqs') : '0')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('testimonials')
                    ->label(__('pricing.testimonials_count'))
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state) . ' ' . __('pricing.testimonials') : '0')
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('pricing.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label(__('pricing.is_published')),
                Tables\Filters\TernaryFilter::make('is_highlighted')
                    ->label(__('pricing.is_highlighted')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order', 'asc');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INFOLIST (View page)
    // ─────────────────────────────────────────────────────────────────────────
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([

            Infolists\Components\Section::make(__('pricing.section_plan'))
                ->icon('heroicon-o-link')
                ->columns(4)
                ->schema([
                    Infolists\Components\TextEntry::make('subscriptionPlan.name')
                        ->label(__('pricing.plan'))
                        ->badge()
                        ->color('primary'),
                    Infolists\Components\IconEntry::make('is_published')
                        ->label(__('pricing.is_published'))
                        ->boolean(),
                    Infolists\Components\IconEntry::make('is_highlighted')
                        ->label(__('pricing.is_highlighted'))
                        ->boolean(),
                    Infolists\Components\TextEntry::make('sort_order')
                        ->label(__('pricing.sort_order')),
                ]),

            Infolists\Components\Section::make(__('pricing.section_hero'))
                ->icon('heroicon-o-star')
                ->columns(2)
                ->schema([
                    Infolists\Components\TextEntry::make('hero_title')
                        ->label(__('pricing.hero_title')),
                    Infolists\Components\TextEntry::make('hero_title_ar')
                        ->label(__('pricing.hero_title_ar')),
                    Infolists\Components\TextEntry::make('hero_subtitle')
                        ->label(__('pricing.hero_subtitle')),
                    Infolists\Components\TextEntry::make('hero_subtitle_ar')
                        ->label(__('pricing.hero_subtitle_ar')),
                ]),

            Infolists\Components\Section::make(__('pricing.section_badge'))
                ->icon('heroicon-o-tag')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('highlight_badge')
                        ->label(__('pricing.highlight_badge'))
                        ->badge()
                        ->color(fn (PricingPageContent $r) => $r->highlight_color ?? 'gray'),
                    Infolists\Components\TextEntry::make('highlight_color')
                        ->label(__('pricing.highlight_color'))
                        ->badge()
                        ->color(fn (?string $state) => $state ?? 'gray'),
                    Infolists\Components\TextEntry::make('color_theme')
                        ->label(__('pricing.color_theme'))
                        ->badge()
                        ->color(fn (?string $state) => $state ?? 'gray'),
                    Infolists\Components\TextEntry::make('card_icon')
                        ->label(__('pricing.card_icon')),
                    Infolists\Components\TextEntry::make('card_image_url')
                        ->label(__('pricing.card_image_url')),
                ]),

            Infolists\Components\Section::make(__('pricing.section_cta'))
                ->icon('heroicon-o-cursor-arrow-rays')
                ->columns(2)
                ->schema([
                    Infolists\Components\TextEntry::make('cta_label')
                        ->label(__('pricing.cta_label')),
                    Infolists\Components\TextEntry::make('cta_label_ar')
                        ->label(__('pricing.cta_label_ar')),
                    Infolists\Components\TextEntry::make('cta_secondary_label')
                        ->label(__('pricing.cta_secondary_label')),
                    Infolists\Components\TextEntry::make('cta_secondary_label_ar')
                        ->label(__('pricing.cta_secondary_label_ar')),
                    Infolists\Components\TextEntry::make('cta_url')
                        ->label(__('pricing.cta_url'))
                        ->columnSpanFull(),
                ]),

            Infolists\Components\Section::make(__('pricing.section_pricing_display'))
                ->icon('heroicon-o-currency-dollar')
                ->columns(2)
                ->schema([
                    Infolists\Components\TextEntry::make('price_prefix')->label(__('pricing.price_prefix')),
                    Infolists\Components\TextEntry::make('price_prefix_ar')->label(__('pricing.price_prefix_ar')),
                    Infolists\Components\TextEntry::make('price_suffix')->label(__('pricing.price_suffix')),
                    Infolists\Components\TextEntry::make('price_suffix_ar')->label(__('pricing.price_suffix_ar')),
                    Infolists\Components\TextEntry::make('annual_discount_label')->label(__('pricing.annual_discount_label')),
                    Infolists\Components\TextEntry::make('annual_discount_label_ar')->label(__('pricing.annual_discount_label_ar')),
                    Infolists\Components\TextEntry::make('trial_label')->label(__('pricing.trial_label')),
                    Infolists\Components\TextEntry::make('trial_label_ar')->label(__('pricing.trial_label_ar')),
                    Infolists\Components\TextEntry::make('money_back_days')
                        ->label(__('pricing.money_back_days'))
                        ->suffix(' ' . __('pricing.days'))
                        ->columnSpanFull(),
                ]),

            Infolists\Components\Section::make(__('pricing.section_features'))
                ->icon('heroicon-o-check-circle')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('feature_bullet_list')
                        ->label(__('pricing.feature_bullet_list'))
                        ->schema([
                            Infolists\Components\TextEntry::make('text_en')->label(__('pricing.feature_text_en')),
                            Infolists\Components\TextEntry::make('text_ar')->label(__('pricing.feature_text_ar')),
                            Infolists\Components\IconEntry::make('is_included')->label(__('pricing.feature_included'))->boolean(),
                        ])
                        ->columns(3)
                        ->columnSpanFull(),
                ]),

            Infolists\Components\Section::make(__('pricing.section_faq'))
                ->icon('heroicon-o-question-mark-circle')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('faq')
                        ->label(__('pricing.faq'))
                        ->schema([
                            Infolists\Components\TextEntry::make('question_en')->label(__('pricing.faq_question_en')),
                            Infolists\Components\TextEntry::make('question_ar')->label(__('pricing.faq_question_ar')),
                            Infolists\Components\TextEntry::make('answer_en')->label(__('pricing.faq_answer_en')),
                            Infolists\Components\TextEntry::make('answer_ar')->label(__('pricing.faq_answer_ar')),
                        ])
                        ->columns(2)
                        ->columnSpanFull(),
                ]),

            Infolists\Components\Section::make(__('pricing.section_testimonials'))
                ->icon('heroicon-o-chat-bubble-left-right')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('testimonials')
                        ->label(__('pricing.testimonials'))
                        ->schema([
                            Infolists\Components\TextEntry::make('name')->label(__('pricing.testimonial_name')),
                            Infolists\Components\TextEntry::make('company')->label(__('pricing.testimonial_company')),
                            Infolists\Components\TextEntry::make('rating')->label(__('pricing.testimonial_rating'))->suffix(' ★'),
                            Infolists\Components\TextEntry::make('text_en')->label(__('pricing.testimonial_text_en')),
                            Infolists\Components\TextEntry::make('text_ar')->label(__('pricing.testimonial_text_ar')),
                        ])
                        ->columns(3)
                        ->columnSpanFull(),
                ]),

            Infolists\Components\Section::make(__('pricing.section_seo'))
                ->icon('heroicon-o-magnifying-glass')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Infolists\Components\TextEntry::make('meta_title')->label(__('pricing.meta_title')),
                    Infolists\Components\TextEntry::make('meta_title_ar')->label(__('pricing.meta_title_ar')),
                    Infolists\Components\TextEntry::make('meta_description')->label(__('pricing.meta_description')),
                    Infolists\Components\TextEntry::make('meta_description_ar')->label(__('pricing.meta_description_ar')),
                ]),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PAGES
    // ─────────────────────────────────────────────────────────────────────────
    public static function getPages(): array
    {
        return [
            'index'  => PricingPageContentResource\Pages\ListPricingPageContents::route('/'),
            'create' => PricingPageContentResource\Pages\CreatePricingPageContent::route('/create'),
            'view'   => PricingPageContentResource\Pages\ViewPricingPageContent::route('/{record}'),
            'edit'   => PricingPageContentResource\Pages\EditPricingPageContent::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('subscriptionPlan');
    }
}

