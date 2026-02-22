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
                    ->label('Provider Model ID')
                    ->required()
                    ->suffixAction(
                        Forms\Components\Actions\Action::make('fetchSchema')
                            ->icon('heroicon-m-arrow-path')
                            ->tooltip('Fetch Schema from Provider')
                            ->action(function (Forms\Get $get, Forms\Set $set, $state) {
                                if (!$state) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Error')
                                        ->body('Please enter a Provider Model ID first.')
                                        ->danger()
                                        ->send();
                                    return;
                                }

                                $providerId = $get('provider_id');
                                if (!$providerId) {
                                     \Filament\Notifications\Notification::make()
                                        ->title('Error')
                                        ->body('Please select a Provider first.')
                                        ->danger()
                                        ->send();
                                    return;
                                }

                                try {
                                    $providerModel = \App\Models\Provider::find($providerId);
                                    if (!$providerModel) {
                                        throw new \Exception('Provider not found.');
                                    }

                                    // 1. Fetch Schema via Factory -> Provider Service
                                    /** @var \App\Services\AiProviders\AiProviderFactory $factory */
                                    $factory = app(\App\Services\AiProviders\AiProviderFactory::class);
                                    $service = $factory->make($providerModel->type);
                                    
                                    $rawSchema = $service->fetchSchema($state);

                                    if (!$rawSchema) {
                                        throw new \Exception("No schema returned from provider (or provider doesn't support schema fetching).");
                                    }

                                    // 2. Process Schema
                                    /** @var \App\Services\SchemaGeneratorService $generator */
                                    $generator = app(\App\Services\SchemaGeneratorService::class);
                                    $processed = $generator->generateFromProviderJson($rawSchema);
                                    
                                    $inputSchema = $processed['input_schema'] ?? [];
                                    $fieldMapping = $processed['field_mapping'] ?? [];
                                    $suggestedPath = $processed['suggested_response_path'] ?? null;

                                    if (empty($inputSchema)) {
                                         \Filament\Notifications\Notification::make()
                                            ->title('Warning')
                                            ->body('Schema fetched but no input fields could be extracted.')
                                            ->warning()
                                            ->send();
                                         return;
                                    }

                                    // 3. Generate Default Request Template
                                    // Default structure: {"prompt": "{{prompt}}", ...}
                                    $requestTemplate = [];
                                    foreach ($inputSchema as $field) {
                                        $key = $field['key'];
                                        $requestTemplate[$key] = "{{" . $key . "}}";
                                    }

                                    // 4. Update Form State
                                    // The fields are inside a relationship('schema') section, so we must target 'schema.field_name'
                                    
                                    // Use JSON values for Textareas to ensure compatibility
                                    $set('schema.input_schema', json_encode($inputSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                                    $set('schema.request_template', json_encode($requestTemplate, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); 
                                    $set('schema.field_mapping', $fieldMapping);
                                    
                                    // Set suggested response path
                                    if ($suggestedPath) {
                                        $set('schema.response_path', $suggestedPath);
                                    }
                                    
                                    if (!$get('schema.interaction_method')) {
                                        $set('schema.interaction_method', 'synchronous');
                                    }
                                    
                                    // price_mode is likely on the main model, not schema. Let's check.
                                    // Looking at the form definition:
                                    // provider_model_id (root)
                                    // is_primary (root)
                                    // price_mode (root)
                                    // cost_strategy_id (root)
                                    // Section(schema) -> request_template, etc.
                                    
                                    if (!$get('price_mode')) {
                                        $set('price_mode', 'strategy');
                                    }
                                    
                                    \Filament\Notifications\Notification::make()
                                        ->title('Schema Fetched')
                                        ->body('Successfully fetched schema and generated configuration.')
                                        ->success()
                                        ->send();

                                } catch (\Exception $e) {
                                    \Illuminate\Support\Facades\Log::error('[ProvidersRelationManager] Schema Fetch Error: ' . $e->getMessage());
                                    \Filament\Notifications\Notification::make()
                                        ->title('Fetch Failed')
                                        ->body($e->getMessage())
                                        ->danger()
                                        ->send();
                                }
                            })
                    ),
                Forms\Components\Toggle::make('is_primary'),
                Forms\Components\Select::make('price_mode')
                    ->options(['fixed' => 'Fixed', 'strategy' => 'Strategy'])
                    ->default('strategy'),
                Forms\Components\Select::make('cost_strategy_id')
                    ->relationship('costStrategy', 'name'),

                Forms\Components\Section::make('Integration Configuration')
                    ->description('Define how to communicate with the AI Provider.')
                    ->relationship('schema')
                    ->schema([
                        // 1. Request Template (The Source of Truth)
                        Forms\Components\Textarea::make('request_template')
                            ->label('API Request Body (JSON)')
                            ->helperText('Use {{variable}} placeholders to define dynamic inputs. Example: {"prompt": "{{prompt}}"}')
                            ->hintAction(
                                Forms\Components\Actions\Action::make('generateInputSchema')
                                    ->label('Auto-Generate Inputs')
                                    ->icon('heroicon-o-sparkles')
                                    ->action(function (Forms\Get $get, Forms\Set $set, $state) {
                                        if (!$state) {
                                            \Filament\Notifications\Notification::make()
                                                ->title('Error')
                                                ->body('Please enter a Request Template first.')
                                                ->danger()
                                                ->send();
                                            return;
                                        }

                                        // 1. Extract variables {{variable}}
                                        preg_match_all('/\{\{\s*([^}]+)\s*\}\}/', $state, $matches);
                                        $variables = array_unique($matches[1] ?? []);

                                        if (empty($variables)) {
                                            \Filament\Notifications\Notification::make()
                                                ->title('No Variables Found')
                                                ->body('Use {{variable}} syntax in your template.')
                                                ->warning()
                                                ->send();
                                            return;
                                        }

                                        // 2. Generate Input Schema
                                        $inputSchema = [];
                                        $fieldMapping = [];
                                        
                                        foreach ($variables as $var) {
                                            $inputSchema[] = [
                                                'key' => $var,
                                                'type' => 'textarea', // Default to textarea
                                                'label' => ucwords(str_replace('_', ' ', $var)),
                                                'required' => true,
                                                'default' => '',
                                                'placeholder' => 'Enter ' . $var,
                                            ];
                                            $fieldMapping[$var] = $var;
                                        }

                                        // 3. Set Values
                                        // Textarea components require string state. Since we're bypassing the DB loading cycle, 
                                        // formatStateUsing won't automatically convert arrays to strings here. We must encode them.
                                        $inputSchemaJson = json_encode($inputSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                                        $fieldMappingJson = json_encode($fieldMapping, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                                        // Try relative to relationship, and absolute root
                                        $set('../../input_schema', $inputSchemaJson);
                                        $set('input_schema', $inputSchemaJson);
                                        $set('../../field_mapping', $fieldMappingJson);
                                        $set('field_mapping', $fieldMappingJson);

                                        \Filament\Notifications\Notification::make()
                                            ->title('Schema Generated')
                                            ->body("Created inputs for: " . implode(', ', $variables))
                                            ->success()
                                            ->send();
                                    })
                            )
                            ->rows(10)
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $state)
                            ->dehydrateStateUsing(fn ($state) => is_string($state) ? json_decode($state, true) : $state),

                        // 2. Interaction Method
                        Forms\Components\Select::make('interaction_method')
                            ->label('Interaction Method')
                            ->options([
                                'synchronous' => 'Synchronous (Standard)',
                                'long_running' => 'Long Running (Async/Video)',
                            ])
                            ->default('synchronous')
                            ->required()
                            ->live(),

                        // 3. Response Path
                        Forms\Components\TextInput::make('response_path')
                            ->label('Response Path (Dot Notation)')
                            ->placeholder('candidates.0.content.parts.0.text')
                            ->helperText('Where to find the result in the API response JSON. Not required for Long Running operations.')
                            ->required(fn (Forms\Get $get) => $get('interaction_method') !== 'long_running')
                            ->disabled(fn (Forms\Get $get) => $get('interaction_method') === 'long_running'),

                        // 3. Input Schema (Generated Result)
                        Forms\Components\Textarea::make('input_schema')
                            ->rows(6) // Reduced size since it's auto-generated usually
                            ->label('Generated Input Definition (JSON)')
                            ->helperText('Automatically generated from variables in the Request Template.')
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $state)
                            ->dehydrateStateUsing(fn ($state) => is_string($state) ? json_decode($state, true) : $state)
                            ->required()
                            ->live(debounce: 500),

                        // 4. Preview
                        Forms\Components\Placeholder::make('input_schema_preview')
                            ->label('Frontend Form Preview')
                            ->content(fn (Forms\Get $get) => view('filament.forms.components.schema-preview', [
                                'schemaState' => $get('input_schema')
                            ]))
                            ->columnSpanFull(),

                        // Hidden/Implicit Fields (we still need them for data integrity but user doesn't need to tweak usually)
                        Forms\Components\Hidden::make('field_mapping')
                            ->dehydrateStateUsing(fn ($state) => $state),
                    ]),
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
