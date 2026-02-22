<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppResource\Pages;
use App\Filament\Resources\AppResource\RelationManagers;
use App\Models\App;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AppResource extends Resource
{
    protected static ?string $model = App::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('App Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('slug', \Illuminate\Support\Str::slug($state))),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true),
                        Forms\Components\Textarea::make('description')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('icon')
                            ->default('heroicon-o-cube'),
                        Forms\Components\FileUpload::make('image_url')
                            ->image()
                            ->directory('apps/images')
                            ->label('App Image')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('cost_multiplier')
                            ->numeric()
                            ->default(1.00),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                    ])->columns(2),

                Forms\Components\Section::make('Workflow Steps')
                    ->schema([
                        Forms\Components\Repeater::make('steps')
                            ->relationship()
                            ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                                $existingStep = null;

                                $existingId = $data['id'] ?? null;
                                if ($existingId) {
                                    $existingStep = \App\Models\AppStep::find($existingId);
                                }

                                // In some Filament relationship save flows, repeater item id may be absent.
                                // Fall back to current app record + step order (or name) to preserve config keys.
                                if (!$existingStep) {
                                    $appId = request()->route('record');
                                    if ($appId) {
                                        $query = \App\Models\AppStep::where('app_id', $appId);

                                        if (isset($data['order'])) {
                                            $existingStep = (clone $query)->where('order', $data['order'])->first();
                                        }

                                        if (!$existingStep && !empty($data['name'])) {
                                            $existingStep = (clone $query)->where('name', $data['name'])->first();
                                        }
                                    }
                                }

                                if (!$existingStep || !is_array($existingStep->config)) {
                                    return $data;
                                }

                                $incomingConfig = is_array($data['config'] ?? null) ? $data['config'] : [];
                                // Preserve non-rendered config keys (custom/template fields) while allowing edited keys to override.
                                $data['config'] = array_replace($existingStep->config, $incomingConfig);

                                return $data;
                            })
                            ->schema([
                                Forms\Components\Hidden::make('id')->dehydrated(),
                                Forms\Components\Select::make('ai_model_id')
                                    ->relationship('aiModel', 'name')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        // Optional: clear config on model change
                                    }),
                                 Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->label('Step Name')
                                    ->placeholder('e.g. Generate Base Image'),
                                Forms\Components\Toggle::make('requires_approval')
                                    ->label('Requires Manual Approval')
                                    ->helperText('If enabled, execution will pause after the previous step finishes and wait for user approval before starting this step.')
                                    ->default(false),
                                Forms\Components\Textarea::make('description')
                                    ->columnSpanFull(),
                                
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Textarea::make('prompt_template')
                                            ->label('Prompt Template')
                                            ->placeholder('Generate an image showing {location}...')
                                            ->rows(15),
                                        Forms\Components\Textarea::make('ui_schema')
                                            ->label('UI Schema (JSON)')
                                            ->placeholder('Paste your JSON UI schema here...')
                                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $state)
                                            ->dehydrateStateUsing(fn ($state) => is_string($state) ? json_decode($state, true) : $state)
                                            ->rows(15)
                                            ->columnSpan(1),
                                    ]),

                                // Dynamic Configuration
                                Forms\Components\Section::make('Input Configuration')
                                    ->description('Map inputs for this model')
                                    ->schema(function (Forms\Get $get, Forms\Set $set) {
                                        $modelId = $get('ai_model_id');
                                        if (!$modelId) return [];

                                        $model = \App\Models\AiModel::with('schema')->find($modelId);
                                        if (!$model || !$model->schema) return [];

                                        $schema = $model->schema->input_schema ?? [];
                                        
                                        // Merge image fields from UI Schema to allow independent configuration
                                        $uiSchemaState = $get('ui_schema');
                                        if (is_string($uiSchemaState)) {
                                            $uiSchemaDecoded = json_decode($uiSchemaState, true);
                                            if (json_last_error() === JSON_ERROR_NONE && is_array($uiSchemaDecoded)) {
                                                foreach ($uiSchemaDecoded as $uiField) {
                                                    $uiKey = $uiField['key'] ?? null;
                                                    $uiType = $uiField['type'] ?? 'text';
                                                    
                                                    if ($uiKey && in_array($uiType, ['image', 'images'])) {
                                                        // Only add if not already in the base schema
                                                        $exists = collect($schema)->contains('key', $uiKey);
                                                        if (!$exists) {
                                                            $schema[] = $uiField;
                                                        }
                                                    }
                                                }
                                            }
                                        }

                                        $components = [];

                                        foreach ($schema as $field) {
                                            $key = $field['key'];
                                            $label = $field['label'] ?? $key;
                                            $type = $field['type'] ?? 'text';
                                            $isImage = in_array($type, ['image', 'images', 'file', 'files']);

                                            $components[] = Forms\Components\Group::make()
                                                ->schema(function (Forms\Get $get) use ($key, $label, $isImage, $type, $field) {
                                                    $source = $get('source') ?? 'user';
                                                    
                                                    $fields = [
                                                        Forms\Components\Select::make("source")
                                                            ->label("{$label} Source")
                                                            ->options([
                                                                'user' => 'User Input',
                                                                'static' => 'Static Value',
                                                                'previous' => 'Previous Step Output',
                                                                'template' => 'Prompt Template (Merged)',
                                                                'merge_arrays' => 'Merge Inputs into Array',
                                                            ])
                                                            ->default('user')
                                                            ->live()
                                                            ->required(),
                                                        
                                                        Forms\Components\TextInput::make("label")
                                                            ->label("Display Label")
                                                            ->default($label)
                                                            ->visible(fn (Forms\Get $get) => $get("source") === 'user'),
                                                    ];

                                                    $options = $field['options'] ?? null;
                                                    
                                                    // Map options to label => value for Filament
                                                    $formattedOptions = [];
                                                    if ($options) {
                                                        foreach ($options as $opt) {
                                                            if (is_array($opt)) {
                                                                $formattedOptions[$opt['value']] = $opt['label'] ?? $opt['value'];
                                                            } else {
                                                                $formattedOptions[$opt] = $opt;
                                                            }
                                                        }
                                                    }

                                                    // Use FileUpload for static images OR user-input default images
                                                    if ($isImage && in_array($source, ['static', 'user'])) {
                                                        $fields[] = Forms\Components\FileUpload::make("value")
                                                            ->label($source === 'static' ? "Static Image/File" : "Default Image (Optional)")
                                                            ->disk('public')
                                                            ->directory('app_static_assets')
                                                            ->multiple(true) // Always allow multiple for robust defaults
                                                            ->image(in_array($type, ['image', 'images']))
                                                            ->maxSize(102400) // Increase max upload size to 100MB
                                                            ->required($source === 'static');
                                                    } elseif ($options && in_array($source, ['static', 'user'])) {
                                                        // Use Select for fields with options
                                                        $fields[] = Forms\Components\Select::make("value")
                                                            ->label($source === 'static' ? "Static Value" : "Default Value")
                                                            ->options($formattedOptions)
                                                            ->required($source === 'static');
                                                    } else {
                                                        // Fallback to Textarea for everything else
                                                        $fields[] = Forms\Components\Textarea::make("value")
                                                            ->label($source === 'static' ? "Static Value" : "Default Value")
                                                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $state)
                                                            ->dehydrateStateUsing(fn ($state) => (is_string($state) && (str_starts_with($state, '[') || str_starts_with($state, '{'))) ? json_decode($state, true) : $state)
                                                            ->visible(fn (Forms\Get $get) => in_array($get("source"), ['static', 'user']));
                                                    }

                                                    $fields[] = Forms\Components\TextInput::make("step_index")
                                                         ->label("Step Index (1-based)")
                                                         ->numeric()
                                                         ->visible(fn (Forms\Get $get) => $get("source") === 'previous');
                                                    
                                                    $fields[] = Forms\Components\TextInput::make("output_key")
                                                         ->label("Output Key")
                                                         ->placeholder("result_url")
                                                         ->visible(fn (Forms\Get $get) => $get("source") === 'previous');

                                                    $fields[] = Forms\Components\TagsInput::make("merge_keys")
                                                         ->label("Keys to Merge")
                                                         ->placeholder("Type key and press Enter or Comma")
                                                         ->helperText("Add your keys like: luna_identity, luna_clothing (press Enter or Comma after each)")
                                                         ->default([])
                                                         ->splitKeys(['Tab', ' ', ','])
                                                         ->visible(fn (Forms\Get $get) => $get("source") === 'merge_arrays');

                                                    return $fields;
                                                })
                                                ->statePath("config.{$key}")
                                                ->columns(3)
                                                ->columnSpanFull();
                                        }
                                        return $components;
                                    }),
                            ])
                            ->orderColumn('order')
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->collapsed()
                            ->deleteAction(fn ($action) => $action->requiresConfirmation())
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Image'),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('slug'),
                Tables\Columns\TextColumn::make('cost_multiplier')->numeric(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
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
            'index' => Pages\ListApps::route('/'),
            'create' => Pages\CreateApp::route('/create'),
            'edit' => Pages\EditApp::route('/{record}/edit'),
        ];
    }
}
