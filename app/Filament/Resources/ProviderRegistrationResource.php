<?php

namespace App\Filament\Resources;

use App\Domain\ProviderRegistration\Models\ProviderRegistration;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProviderRegistrationResource extends Resource
{
    protected static ?string $model = ProviderRegistration::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.group_core');
    }

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('nav.registrations');
    }

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user && $user->hasAnyPermission(['stores.view', 'stores.create']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Registration Details'))->schema([
                Forms\Components\TextInput::make('business_name')->disabled(),
                Forms\Components\TextInput::make('owner_name')->disabled(),
                Forms\Components\TextInput::make('email')->disabled(),
                Forms\Components\TextInput::make('phone')->disabled(),
                Forms\Components\TextInput::make('business_type')->disabled(),
                Forms\Components\TextInput::make('city')->disabled(),
                Forms\Components\Select::make('status')->options([
                    'pending' => __('Pending'),
                    'approved' => __('Approved'),
                    'rejected' => __('Rejected'),
                ])->required(),
                Forms\Components\Textarea::make('admin_notes')->rows(3),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('business_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('owner_name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('business_type')->badge(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending' => __('Pending'), 'approved' => 'Approved', 'rejected' => 'Rejected',
                ]),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ProviderRegistrationResource\Pages\ListProviderRegistrations::route('/'),
            'edit' => ProviderRegistrationResource\Pages\EditProviderRegistration::route('/{record}/edit'),
        ];
    }
}
