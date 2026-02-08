<?php

namespace App\Filament\Resources\AiModelResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProvidersRelationManager extends RelationManager
{
    protected static string $relationship = 'providers';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('provider_id')
                    ->relationship('provider', 'name')
                    ->required()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')->required(),
                        Forms\Components\TextInput::make('slug')->required(),
                        Forms\Components\FileUpload::make('logo_url')->image(),
                    ]),
                Forms\Components\TextInput::make('provider_model_id')
                    ->label('Provider Model ID (e.g. black-forest/flux-1)')
                    ->required(),
                Forms\Components\Toggle::make('is_primary'),
                Forms\Components\Select::make('price_mode')
                    ->options(['fixed' => 'Fixed', 'strategy' => 'Strategy'])
                    ->default('strategy'),
                Forms\Components\Select::make('cost_strategy_id')
                    ->relationship('costStrategy', 'name'),

                Forms\Components\Section::make('Schema Configuration')
                    ->relationship('schema')
                    ->schema([
                         Forms\Components\TextInput::make('version')
                            ->hintAction(
                                Forms\Components\Actions\Action::make('fetchSchemaFromApi')
                                    ->label('Fetch from API')
                                    ->icon('heroicon-o-cloud-arrow-down')
                                    ->action(function (Forms\Get $get, Forms\Set $set) {
                                        // Access parent form values
                                        $providerId = $get('../provider_id');
                                        $modelId = $get('../provider_model_id');

                                        if (!$providerId || !$modelId) {
                                            \Filament\Notifications\Notification::make()
                                                ->title('Error')
                                                ->body('Please select a Provider and enter a Model ID first.')
                                                ->danger()
                                                ->send();
                                            return;
                                        }

                                        try {
                                            $provider = \App\Models\Provider::find($providerId);
                                            if (!$provider) throw new \Exception('Provider not found.');

                                            $apiService = new \App\Services\ProviderApiService();
                                            $rawSchema = $apiService->fetchSchema($provider, $modelId);

                                            if (!$rawSchema) {
                                                throw new \Exception('Failed to fetch schema from API.');
                                            }

                                            $generator = new \App\Services\SchemaGeneratorService();
                                            $result = $generator->generateFromProviderJson($rawSchema);

                                            // Set as JSON strings for Filament textarea display
                                            $set('input_schema', json_encode($result['input_schema'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                                            $set('output_schema', json_encode($result['output_schema'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                                            $set('field_mapping', $result['field_mapping']);

                                            $inputCount = count($result['input_schema']);
                                            $mappingCount = count($result['field_mapping']);

                                            \Filament\Notifications\Notification::make()
                                                ->title('Schema Auto-Filled')
                                                ->body("✓ {$inputCount} input fields\n✓ {$mappingCount} mappings\n✓ Output schema generated")
                                                ->success()
                                                ->send();

                                        } catch (\Exception $e) {
                                            \Filament\Notifications\Notification::make()
                                                ->title('Error')
                                                ->body($e->getMessage())
                                                ->danger()
                                                ->send();
                                        }
                                    })
                            ),

                         Forms\Components\Textarea::make('input_schema')
                            ->rows(10)
                            ->label('Input Schema (Standard JSON)')
                            ->afterStateHydrated(function (Forms\Components\Textarea $component, $state) {
                                // Convert array to JSON string for display in Filament
                                if (is_array($state)) {
                                    $component->state(json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                                }
                            })
                            ->dehydrateStateUsing(fn ($state) => $state) // Keep as string when saving
                            ->required(),
                         Forms\Components\Textarea::make('output_schema')
                            ->rows(10)
                            ->label('Output Schema (JSON)')
                            ->afterStateHydrated(function (Forms\Components\Textarea $component, $state) {
                                // Convert array to JSON string for display in Filament
                                if (is_array($state)) {
                                    $component->state(json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                                }
                            })
                            ->dehydrateStateUsing(fn ($state) => $state), // Keep as string when saving
                         Forms\Components\KeyValue::make('field_mapping')
                            ->label('Field Mapping (Standard -> Provider)')
                            ->keyLabel('Standard Field')
                            ->valueLabel('Provider Field'),
                         Forms\Components\KeyValue::make('default_values'),
                    ])
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('provider.name')
            ->columns([
                Tables\Columns\TextColumn::make('provider.name')->label('Provider'),
                Tables\Columns\TextColumn::make('provider_model_id'),
                Tables\Columns\IconColumn::make('is_primary')->boolean(),
                Tables\Columns\TextColumn::make('costStrategy.name')->label('Strategy'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                
                // The Playground Action
                Tables\Actions\Action::make('test_run')
                    ->label('Test Run')
                    ->icon('heroicon-o-play')
                    ->form([
                        Forms\Components\KeyValue::make('payload')
                            ->label('Test Payload')
                    ])
                    ->action(function ($record, array $data) {
                        // $record is AiModelProvider
                        // Call Generation logic simulation?
                        // For now just notification
                        \Filament\Notifications\Notification::make()
                            ->title('Test Run Initiated')
                            ->body('Payload: ' . json_encode($data))
                            ->success()
                            ->send();
                    })
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
