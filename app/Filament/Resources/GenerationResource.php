<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GenerationResource\Pages;
use App\Filament\Resources\GenerationResource\RelationManagers;
use App\Models\Generation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class GenerationResource extends Resource
{
    protected static ?string $model = Generation::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('aiModel.name')
                    ->label('Model')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('provider.provider.name') // Access provider relationship chain
                    ->label('Provider')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'failed' => 'danger',
                        'processing' => 'warning',
                        'pending' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration')
                    ->suffix('s')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('user_credit_cost')
                    ->label('Credit Cost')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'processing' => 'Processing',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from'),
                        Forms\Components\DatePicker::make('created_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                // No edit or delete for logs typically, maybe delete if admin wants to clean up
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Section::make('Generation Details')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'completed' => 'success',
                                'failed' => 'danger',
                                'processing' => 'warning',
                                default => 'gray',
                            }),
                        \Filament\Infolists\Components\TextEntry::make('duration')
                            ->label('Duration (Seconds)'),
                        \Filament\Infolists\Components\TextEntry::make('provider_cost_usd')
                            ->money('USD')
                            ->label('Provider Cost'),
                        \Filament\Infolists\Components\TextEntry::make('user_credit_cost')
                            ->label('User Credits'),
                        \Filament\Infolists\Components\TextEntry::make('error_message')
                            ->color('danger')
                            ->visible(fn ($record) => $record->status === 'failed')
                            ->columnSpanFull(),
                    ])->columns(4),
                
                \Filament\Infolists\Components\Section::make('Input Data')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('input_data')
                            ->label('Parameters (JSON)')
                            ->state(fn ($record) => "```json\n" . json_encode($record->input_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n```")
                            ->markdown()
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                \Filament\Infolists\Components\Section::make('Provider Interaction')
                    ->schema([
                         \Filament\Infolists\Components\TextEntry::make('provider_request_body')
                            ->label('Request Payload (JSON)')
                            ->state(fn ($record) => $record->provider_request_body 
                                ? "```json\n" . json_encode($record->provider_request_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n```"
                                : 'No request body available (Old log or error)')
                            ->placeholder('No request body available')
                            ->markdown()
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),

                \Filament\Infolists\Components\Section::make('Output Data')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('output_data')
                             ->label('Result Data (JSON)')
                             ->state(fn ($record) => "```json\n" . json_encode($record->output_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n```")
                             ->visible(fn ($record) => is_array($record->output_data))
                             ->markdown()
                             ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageGenerations::route('/'),
        ];
    }
}
