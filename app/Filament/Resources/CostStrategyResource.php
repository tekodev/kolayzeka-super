<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CostStrategyResource\Pages;
use App\Filament\Resources\CostStrategyResource\RelationManagers;
use App\Models\CostStrategy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CostStrategyResource extends Resource
{
    protected static ?string $model = CostStrategy::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('calc_type')
                    ->options([
                        'fixed' => 'Fixed',
                        'per_unit' => 'Per Unit',
                        'per_second' => 'Per Second',
                        'per_token' => 'Per Token',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('provider_unit_price')
                    ->numeric()
                    ->prefix('$')
                    ->required(),
                Forms\Components\TextInput::make('markup_multiplier')
                    ->numeric()
                    ->default(1.0)
                    ->required(),
                Forms\Components\TextInput::make('credit_conversion_rate')
                    ->numeric()
                    ->default(100)
                    ->required(),
                Forms\Components\TextInput::make('min_credit_limit')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('calc_type'),
                Tables\Columns\TextColumn::make('provider_unit_price')->money('USD'),
                Tables\Columns\TextColumn::make('markup_multiplier'),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCostStrategies::route('/'),
            'create' => Pages\CreateCostStrategy::route('/create'),
            'edit' => Pages\EditCostStrategy::route('/{record}/edit'),
        ];
    }
}
