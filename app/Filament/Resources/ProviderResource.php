<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProviderResource\Pages;
use App\Filament\Resources\ProviderResource\RelationManagers;
use App\Models\Provider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProviderResource extends Resource
{
    protected static ?string $model = Provider::class;

    protected static ?string $navigationIcon = 'heroicon-o-cloud';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('slug', \Illuminate\Support\Str::slug($state))),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('type')
                    ->options([
                        'fal_ai' => 'Fal.ai',
                        'replicate' => 'Replicate',
                        'google' => 'Google (Gemini/Veo)',
                    ])
                    ->default('fal_ai')
                    ->required(),
                Forms\Components\TextInput::make('base_url')
                    ->label('API Base URL')
                    ->placeholder('https://api.example.com/v1')
                    ->helperText('The base URL for API requests (e.g. https://generativelanguage.googleapis.com/v1beta/models)')
                    ->url()
                    ->maxLength(255),
                Forms\Components\TextInput::make('api_key_env')
                    ->label('API Key Environment Variable')
                    ->placeholder('e.g. GEMINI_API_KEY')
                    ->helperText('The name of the .env variable that holds the API key.')
                    ->maxLength(255),
                Forms\Components\FileUpload::make('logo_url')
                    ->image()
                    ->directory('providers'),
                Forms\Components\Textarea::make('description'),
                Forms\Components\Toggle::make('active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo_url'),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'fal_ai' => 'info',
                        'replicate' => 'warning',
                        'google' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('api_key_env')
                    ->label('Env Var')
                    ->copyable()
                    ->searchable(),
                Tables\Columns\IconColumn::make('active')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                //
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
            'index' => Pages\ListProviders::route('/'),
            'edit' => Pages\EditProvider::route('/{record}/edit'),
        ];
    }
    
    public static function canCreate(): bool
    {
       return false;
    }
    
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
       return false;
    }

    public static function canDeleteAny(): bool
    {
       return false;
    }
}
