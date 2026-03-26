<?php

namespace App\Filament\Resources;

use App\Domain\ContentOnboarding\Models\OnboardingStep;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OnboardingStepResource extends Resource
{
    protected static ?string $model = OnboardingStep::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Onboarding Steps';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['content.manage']);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = OnboardingStep::where('is_required', true)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    // ─── Form ────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Step Content')
                ->description('Define the onboarding step content in both languages')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('Title (EN)')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('title_ar')
                        ->label('Title (AR)')
                        ->maxLength(255),
                    Forms\Components\RichEditor::make('description')
                        ->label('Description (EN)')
                        ->columnSpanFull()
                        ->toolbarButtons([
                            'bold', 'italic', 'underline', 'strike',
                            'bulletList', 'orderedList',
                            'link', 'redo', 'undo',
                        ]),
                    Forms\Components\RichEditor::make('description_ar')
                        ->label('Description (AR)')
                        ->columnSpanFull()
                        ->toolbarButtons([
                            'bold', 'italic', 'underline', 'strike',
                            'bulletList', 'orderedList',
                            'link', 'redo', 'undo',
                        ]),
                ])
                ->columns(2),

            Forms\Components\Section::make('Step Configuration')
                ->description('Control step ordering and behavior')
                ->schema([
                    Forms\Components\TextInput::make('step_number')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->helperText('Determines the order in which steps appear'),
                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Fine-tune display ordering within same step number'),
                    Forms\Components\Toggle::make('is_required')
                        ->label('Required')
                        ->default(true)
                        ->helperText('Required steps must be completed before finishing onboarding'),
                ])
                ->columns(3),
        ]);
    }

    // ─── Table ───────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('step_number')
                    ->label('#')
                    ->sortable()
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->description(fn (OnboardingStep $record) => $record->title_ar)
                    ->wrap(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(80)
                    ->html()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_required')
                    ->boolean()
                    ->label('Required')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sort')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_required')->label('Required'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => auth('admin')->user()?->hasPermission('content.manage')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth('admin')->user()?->hasPermission('content.manage')),
                ]),
            ])
            ->defaultSort('step_number', 'asc')
            ->reorderable('sort_order');
    }

    // ─── Infolist (View Page) ────────────────────────────────────

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Step Content')
                ->schema([
                    Infolists\Components\TextEntry::make('title')->label('Title (EN)')->weight('bold'),
                    Infolists\Components\TextEntry::make('title_ar')->label('Title (AR)'),
                    Infolists\Components\TextEntry::make('description')->label('Description (EN)')->html()->columnSpanFull(),
                    Infolists\Components\TextEntry::make('description_ar')->label('Description (AR)')->html()->columnSpanFull(),
                ])
                ->columns(2),

            Infolists\Components\Section::make('Configuration')
                ->schema([
                    Infolists\Components\TextEntry::make('step_number')->badge()->color('info'),
                    Infolists\Components\TextEntry::make('sort_order'),
                    Infolists\Components\IconEntry::make('is_required')->boolean()->label('Required'),
                    Infolists\Components\TextEntry::make('created_at')->dateTime(),
                    Infolists\Components\TextEntry::make('updated_at')->dateTime(),
                ])
                ->columns(5),
        ]);
    }

    // ─── Pages ───────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => OnboardingStepResource\Pages\ListOnboardingSteps::route('/'),
            'create' => OnboardingStepResource\Pages\CreateOnboardingStep::route('/create'),
            'view' => OnboardingStepResource\Pages\ViewOnboardingStep::route('/{record}'),
            'edit' => OnboardingStepResource\Pages\EditOnboardingStep::route('/{record}/edit'),
        ];
    }
}
