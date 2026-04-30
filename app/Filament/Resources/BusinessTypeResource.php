<?php

namespace App\Filament\Resources;

use App\Domain\ContentOnboarding\Models\BusinessType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class BusinessTypeResource extends Resource
{
    protected static ?string $model = BusinessType::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_content');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('nav.business_types');
    }

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['content.view', 'content.manage']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'name_ar', 'slug'];
    }

    // ─── Form ────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Business Type Info'))
                ->description(__('Define the business type for onboarding templates'))
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('Name (EN)'))
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state))),
                    Forms\Components\TextInput::make('name_ar')
                        ->label(__('Name (AR)'))
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('icon')
                        ->maxLength(255)
                        ->helperText(__('Heroicon name or emoji for display')),
                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->default(0),
                    Forms\Components\Toggle::make('is_active')
                        ->default(true)
                        ->helperText(__('Inactive business types will not appear in onboarding')),
                ])
                ->columns(2),
        ]);
    }

    // ─── Table ───────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (BusinessType $record) => $record->name_ar),
                Tables\Columns\TextColumn::make('slug')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('icon')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('business_type_category_templates_count')
                    ->counts('businessTypeCategoryTemplates')
                    ->label(__('Categories'))
                    ->badge()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('business_type_shift_templates_count')
                    ->counts('businessTypeShiftTemplates')
                    ->label(__('Shifts'))
                    ->badge()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label(__('Active'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('Sort'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label(__('Active')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('duplicate')
                    ->label(__('Duplicate'))
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->visible(fn () => auth('admin')->user()?->hasPermission('content.manage'))
                    ->requiresConfirmation()
                    ->action(function (BusinessType $record) {
                        $newType = $record->replicate();
                        $newType->name = $record->name . ' (Copy)';
                        $newType->slug = $record->slug . '-copy';
                        $newType->save();

                        // Duplicate all hasMany template relations
                        $hasManyRelations = [
                            'businessTypeCategoryTemplates',
                            'businessTypeShiftTemplates',
                            'businessTypePromotionTemplates',
                            'businessTypeCommissionTemplates',
                            'businessTypeCustomerGroupTemplates',
                            'businessTypeWasteReasonTemplates',
                            'businessTypeServiceCategoryTemplates',
                            'businessTypeGiftRegistryTypes',
                            'businessTypeGamificationBadges',
                            'businessTypeGamificationChallenges',
                            'businessTypeGamificationMilestones',
                        ];

                        foreach ($hasManyRelations as $relation) {
                            foreach ($record->$relation as $tpl) {
                                $new = $tpl->replicate();
                                $new->business_type_id = $newType->id;
                                $new->save();
                            }
                        }

                        // Duplicate hasOne relations
                        foreach (['businessTypeReceiptTemplate', 'businessTypeIndustryConfig', 'businessTypeLoyaltyConfig', 'businessTypeReturnPolicy', 'businessTypeAppointmentConfig'] as $hasOne) {
                            $related = $record->$hasOne;
                            if ($related) {
                                $new = $related->replicate();
                                $new->business_type_id = $newType->id;
                                $new->save();
                            }
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth('admin')->user()?->hasPermission('content.manage')),
                ]),
            ])
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order');
    }

    // ─── Infolist (View Page) ────────────────────────────────────

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('Business Type Info'))
                ->schema([
                    Infolists\Components\TextEntry::make('name')->weight('bold'),
                    Infolists\Components\TextEntry::make('name_ar')->label(__('Name (AR)')),
                    Infolists\Components\TextEntry::make('slug')->copyable(),
                    Infolists\Components\TextEntry::make('icon')->placeholder(__('N/A')),
                    Infolists\Components\TextEntry::make('sort_order'),
                    Infolists\Components\IconEntry::make('is_active')->boolean(),
                ])
                ->columns(3),

            Infolists\Components\Section::make(__('Template Counts'))
                ->schema([
                    Infolists\Components\TextEntry::make('categories_count')
                        ->label(__('Category Templates'))
                        ->state(fn (BusinessType $record) => $record->businessTypeCategoryTemplates()->count())
                        ->badge()->color('info'),
                    Infolists\Components\TextEntry::make('shifts_count')
                        ->label(__('Shift Templates'))
                        ->state(fn (BusinessType $record) => $record->businessTypeShiftTemplates()->count())
                        ->badge()->color('info'),
                    Infolists\Components\TextEntry::make('promotions_count')
                        ->label(__('Promotion Templates'))
                        ->state(fn (BusinessType $record) => $record->businessTypePromotionTemplates()->count())
                        ->badge()->color('info'),
                    Infolists\Components\TextEntry::make('commissions_count')
                        ->label(__('Commission Templates'))
                        ->state(fn (BusinessType $record) => $record->businessTypeCommissionTemplates()->count())
                        ->badge()->color('info'),
                    Infolists\Components\TextEntry::make('waste_reasons_count')
                        ->label(__('Waste Reason Templates'))
                        ->state(fn (BusinessType $record) => $record->businessTypeWasteReasonTemplates()->count())
                        ->badge()->color('info'),
                    Infolists\Components\TextEntry::make('customer_groups_count')
                        ->label(__('Customer Group Templates'))
                        ->state(fn (BusinessType $record) => $record->businessTypeCustomerGroupTemplates()->count())
                        ->badge()->color('info'),
                    Infolists\Components\TextEntry::make('service_categories_count')
                        ->label(__('Service Category Templates'))
                        ->state(fn (BusinessType $record) => $record->businessTypeServiceCategoryTemplates()->count())
                        ->badge()->color('info'),
                    Infolists\Components\TextEntry::make('gamification_badges_count')
                        ->label(__('Gamification Badges'))
                        ->state(fn (BusinessType $record) => $record->businessTypeGamificationBadges()->count())
                        ->badge()->color('info'),
                    Infolists\Components\TextEntry::make('gamification_challenges_count')
                        ->label(__('Gamification Challenges'))
                        ->state(fn (BusinessType $record) => $record->businessTypeGamificationChallenges()->count())
                        ->badge()->color('info'),
                    Infolists\Components\TextEntry::make('gamification_milestones_count')
                        ->label(__('Gamification Milestones'))
                        ->state(fn (BusinessType $record) => $record->businessTypeGamificationMilestones()->count())
                        ->badge()->color('info'),
                    Infolists\Components\IconEntry::make('has_receipt_template')
                        ->label(__('Receipt Template'))
                        ->state(fn (BusinessType $record) => $record->businessTypeReceiptTemplate()->exists())
                        ->boolean(),
                    Infolists\Components\IconEntry::make('has_industry_config')
                        ->label(__('Industry Config'))
                        ->state(fn (BusinessType $record) => $record->businessTypeIndustryConfig()->exists())
                        ->boolean(),
                    Infolists\Components\IconEntry::make('has_loyalty_config')
                        ->label(__('Loyalty Config'))
                        ->state(fn (BusinessType $record) => $record->businessTypeLoyaltyConfig()->exists())
                        ->boolean(),
                    Infolists\Components\IconEntry::make('has_return_policy')
                        ->label(__('Return Policy'))
                        ->state(fn (BusinessType $record) => $record->businessTypeReturnPolicy()->exists())
                        ->boolean(),
                ])
                ->columns(6),

            Infolists\Components\Section::make(__('Timestamps'))
                ->schema([
                    Infolists\Components\TextEntry::make('created_at')->dateTime(),
                    Infolists\Components\TextEntry::make('updated_at')->dateTime(),
                ])
                ->columns(2),
        ]);
    }

    // ─── Relations ───────────────────────────────────────────────

    public static function getRelations(): array
    {
        return [
            // ── Core Templates ───────────────────────────────────────────
            BusinessTypeResource\RelationManagers\CategoryTemplatesRelationManager::class,
            BusinessTypeResource\RelationManagers\ShiftTemplatesRelationManager::class,
            BusinessTypeResource\RelationManagers\ReceiptTemplateRelationManager::class,
            BusinessTypeResource\RelationManagers\IndustryConfigRelationManager::class,
            // ── Pricing & Commerce ───────────────────────────────────────
            BusinessTypeResource\RelationManagers\PromotionTemplatesRelationManager::class,
            BusinessTypeResource\RelationManagers\CommissionTemplatesRelationManager::class,
            // ── Customer & Loyalty ───────────────────────────────────────
            BusinessTypeResource\RelationManagers\LoyaltyConfigRelationManager::class,
            BusinessTypeResource\RelationManagers\CustomerGroupTemplatesRelationManager::class,
            // ── Operations & Compliance ──────────────────────────────────
            BusinessTypeResource\RelationManagers\ReturnPolicyRelationManager::class,
            BusinessTypeResource\RelationManagers\WasteReasonTemplatesRelationManager::class,
            // ── Service & Appointment ────────────────────────────────────
            BusinessTypeResource\RelationManagers\AppointmentConfigRelationManager::class,
            BusinessTypeResource\RelationManagers\ServiceCategoryTemplatesRelationManager::class,
            // ── Nice-to-Have ─────────────────────────────────────────────
            BusinessTypeResource\RelationManagers\GiftRegistryTypesRelationManager::class,
            BusinessTypeResource\RelationManagers\GamificationBadgesRelationManager::class,
            BusinessTypeResource\RelationManagers\GamificationChallengesRelationManager::class,
            BusinessTypeResource\RelationManagers\GamificationMilestonesRelationManager::class,
            // ── POS Layout ───────────────────────────────────────────────
            BusinessTypeResource\RelationManagers\PosLayoutTemplatesRelationManager::class,
        ];
    }

    // ─── Pages ───────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => BusinessTypeResource\Pages\ListBusinessTypes::route('/'),
            'create' => BusinessTypeResource\Pages\CreateBusinessType::route('/create'),
            'view' => BusinessTypeResource\Pages\ViewBusinessType::route('/{record}'),
            'edit' => BusinessTypeResource\Pages\EditBusinessType::route('/{record}/edit'),
        ];
    }
}
