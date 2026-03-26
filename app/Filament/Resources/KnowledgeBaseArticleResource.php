<?php

namespace App\Filament\Resources;

use App\Domain\ContentOnboarding\Models\KnowledgeBaseArticle;
use App\Domain\SystemConfig\Enums\KnowledgeBaseCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class KnowledgeBaseArticleResource extends Resource
{
    protected static ?string $model = KnowledgeBaseArticle::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Support';

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationLabel(): string
    {
        return __('support.nav_knowledge_base');
    }

    public static function getModelLabel(): string
    {
        return __('support.kb_article');
    }

    public static function getPluralModelLabel(): string
    {
        return __('support.kb_articles');
    }

    public static function getNavigationBadge(): ?string
    {
        $draft = static::getModel()::where('is_published', false)->count();

        return $draft > 0 ? (string) $draft : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('support.draft_articles');
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['kb.manage']);
    }

    // ═══════════════════════════════════════════════════════════
    //  FORM
    // ═══════════════════════════════════════════════════════════

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('support.article_details'))
                ->icon('heroicon-o-book-open')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label(__('support.title_en'))
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, ?string $state) {
                            if (!$get('slug') || $get('slug') === '') {
                                $set('slug', Str::slug($state));
                            }
                        }),

                    Forms\Components\TextInput::make('title_ar')
                        ->label(__('support.title_ar'))
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('slug')
                        ->label(__('support.slug'))
                        ->required()
                        ->maxLength(100)
                        ->unique(ignoreRecord: true)
                        ->helperText(__('support.slug_help')),

                    Forms\Components\Select::make('category')
                        ->label(__('support.category'))
                        ->options(KnowledgeBaseCategory::class)
                        ->required()
                        ->searchable(),

                    Forms\Components\Toggle::make('is_published')
                        ->label(__('support.is_published'))
                        ->default(false),

                    Forms\Components\TextInput::make('sort_order')
                        ->label(__('support.sort_order'))
                        ->numeric()
                        ->default(0)
                        ->minValue(0),
                ])->columns(2),

            Forms\Components\Section::make(__('support.article_body'))
                ->icon('heroicon-o-language')
                ->schema([
                    Forms\Components\RichEditor::make('body')
                        ->label(__('support.body_en'))
                        ->required()
                        ->columnSpanFull(),

                    Forms\Components\RichEditor::make('body_ar')
                        ->label(__('support.body_ar'))
                        ->required()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  TABLE
    // ═══════════════════════════════════════════════════════════

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label(__('support.title'))
                    ->searchable(['title', 'title_ar'])
                    ->sortable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('slug')
                    ->label(__('support.slug'))
                    ->searchable()
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('category')
                    ->label(__('support.category'))
                    ->badge()
                    ->formatStateUsing(fn (?KnowledgeBaseCategory $state) => $state?->label() ?? '—')
                    ->color(fn (?KnowledgeBaseCategory $state) => $state?->color() ?? 'gray')
                    ->icon(fn (?KnowledgeBaseCategory $state) => $state?->icon()),

                Tables\Columns\IconColumn::make('is_published')
                    ->label(__('support.is_published'))
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('support.sort_order'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('support.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label(__('support.category'))
                    ->options(KnowledgeBaseCategory::class),

                Tables\Filters\TernaryFilter::make('is_published')
                    ->label(__('support.is_published')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_publish')
                    ->label(fn (KnowledgeBaseArticle $record) => $record->is_published
                        ? __('support.unpublish')
                        : __('support.publish'))
                    ->icon(fn (KnowledgeBaseArticle $record) => $record->is_published
                        ? 'heroicon-o-eye-slash'
                        : 'heroicon-o-eye')
                    ->color(fn (KnowledgeBaseArticle $record) => $record->is_published ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->action(function (KnowledgeBaseArticle $record) {
                        $record->update(['is_published' => !$record->is_published]);
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }

    // ═══════════════════════════════════════════════════════════
    //  INFOLIST
    // ═══════════════════════════════════════════════════════════

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(__('support.article_details'))
                ->schema([
                    Infolists\Components\TextEntry::make('title')
                        ->label(__('support.title_en')),

                    Infolists\Components\TextEntry::make('title_ar')
                        ->label(__('support.title_ar')),

                    Infolists\Components\TextEntry::make('slug')
                        ->label(__('support.slug'))
                        ->color('gray'),

                    Infolists\Components\TextEntry::make('category')
                        ->label(__('support.category'))
                        ->badge()
                        ->formatStateUsing(fn (?KnowledgeBaseCategory $state) => $state?->label() ?? '—')
                        ->color(fn (?KnowledgeBaseCategory $state) => $state?->color() ?? 'gray'),

                    Infolists\Components\IconEntry::make('is_published')
                        ->label(__('support.is_published'))
                        ->boolean(),

                    Infolists\Components\TextEntry::make('sort_order')
                        ->label(__('support.sort_order')),
                ])->columns(3),

            Infolists\Components\Section::make(__('support.article_body'))
                ->schema([
                    Infolists\Components\TextEntry::make('body')
                        ->label(__('support.body_en'))
                        ->html()
                        ->columnSpanFull(),

                    Infolists\Components\TextEntry::make('body_ar')
                        ->label(__('support.body_ar'))
                        ->html()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  PAGES
    // ═══════════════════════════════════════════════════════════

    public static function getPages(): array
    {
        return [
            'index'  => KnowledgeBaseArticleResource\Pages\ListKnowledgeBaseArticles::route('/'),
            'create' => KnowledgeBaseArticleResource\Pages\CreateKnowledgeBaseArticle::route('/create'),
            'view'   => KnowledgeBaseArticleResource\Pages\ViewKnowledgeBaseArticle::route('/{record}'),
            'edit'   => KnowledgeBaseArticleResource\Pages\EditKnowledgeBaseArticle::route('/{record}/edit'),
        ];
    }
}
