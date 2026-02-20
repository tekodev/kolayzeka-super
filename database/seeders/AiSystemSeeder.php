<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Provider;
use App\Models\AiModel;
use App\Models\AiModelProvider;
use App\Enums\AiModelInputType;

class AiSystemSeeder extends Seeder
{
    public function run()
    {
        // 1. Create or Update Provider
        $provider = Provider::updateOrCreate(
            ['slug' => 'google'],
            [
                'name' => 'Google',
                'api_key_env' => 'GEMINI_API_KEY',
                'type' => 'google',
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta/models',
            ]
        );

        $categories = \App\Models\Category::whereIn('name', ['Image to Image', 'Text to Image'])->get();

        // 2. Create Gemini 3 Pro Model
        $model = AiModel::firstOrCreate(
            ['slug' => 'nano-banana-pro'],
            [
                'name' => 'Nano Banana Pro',
                'description' => 'Google\'s most capable AI model for image generation and reasoning.',
                'is_active' => true,
            ]
        );
        
        $model->categories()->sync($categories);

        // 3. Create Cost Strategy
        $strategy = \App\Models\CostStrategy::firstOrCreate(
            ['name' => 'Gemini Standard'],
            [
                'calc_type' => 'per_unit',
                'provider_unit_price' => 0.040000,
                'markup_multiplier' => 1.50, // 50% markup
                'credit_conversion_rate' => 10, // 10 credits per unit
                'min_credit_limit' => 5,
            ]
        );

        // 4. Link them
        $aiModelProvider = AiModelProvider::updateOrCreate(
            [
                'ai_model_id' => $model->id,
                'provider_id' => $provider->id,
            ],
            [
                'provider_model_id' => 'gemini-3-pro-image-preview:generateContent', // Full Path Standard
                'is_primary' => true,
                'price_mode' => 'per_unit',
                'cost_strategy_id' => $strategy->id,
            ]
        );

        // 5. Create Schema with DYNAMIC TEMPLATE for IMAGE GENERATION
        // Force cleanup to avoid unique constraint issues if ID linkage is messy
        \App\Models\AiModelSchema::where('ai_model_provider_id', $aiModelProvider->id)->delete();
        
        \App\Models\AiModelSchema::create([
            'ai_model_provider_id' => $aiModelProvider->id,
                'input_schema' => [
                    [
                        'key' => 'prompt',
                        'type' => AiModelInputType::TEXTAREA->value,
                        'label' => 'Prompt',
                        'required' => true,
                        'default' => 'An office group photo of these people, they are making funny faces.',
                    ],
                    [
                        'key' => 'aspectRatio',
                        'type' => AiModelInputType::SELECT->value,
                        'label' => 'Aspect Ratio',
                        'required' => false,
                        'default' => '5:4',
                        'options' => [
                            ['value' => '1:1', 'label' => '1:1 (Square)'],
                            ['value' => '3:4', 'label' => '3:4 (Portrait)'],
                            ['value' => '4:3', 'label' => '4:3 (Landscape)'],
                            ['value' => '5:4', 'label' => '5:4'],
                            ['value' => '16:9', 'label' => '16:9 (Widescreen)'],
                            ['value' => '9:16', 'label' => '9:16 (Vertical)'],
                        ]
                    ],
                    [
                        'key' => 'imageSize',
                        'type' => AiModelInputType::SELECT->value,
                        'label' => 'Image Size',
                        'required' => false,
                        'default' => '2K',
                        'options' => [
                            ['value' => '1K', 'label' => '1K (1024x1024)'],
                            ['value' => '2K', 'label' => '2K (2048x2048)'],
                            ['value' => '4K', 'label' => '4K (4096x4096)'],
                        ]
                    ]
                ],
                'field_mapping' => [
                    'prompt' => 'prompt',
                    'aspectRatio' => 'aspectRatio',
                    'imageSize' => 'imageSize'
                ],
                // DYNAMIC TEMPLATE DEFINITION FOR IMAGE GENERATION
                'request_template' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => '{{prompt}}']
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'responseModalities' => ['TEXT', 'IMAGE'],
                        'imageConfig' => [
                            'aspectRatio' => '{{aspectRatio}}',
                            'imageSize' => '{{imageSize}}'
                        ]
                    ]
                ],
                // Path to Base64 image data in response (will be uploaded to S3 by provider)
                'response_path' => 'candidates.0.content.parts.0.inlineData.data',
            ]
        );

        $this->command->info('Gemini Model seeded successfully.');

        // ==========================================
        // 6. Create Google Veo 3.1 (Video Model)
        // ==========================================
        $veoModel = AiModel::firstOrCreate(
            ['slug' => 'veo-3.1-fast-generate-preview'],
            [
                'name' => 'Veo 3.1 (Preview)',
                'description' => 'Google\'s latest video generation model (Preview). Fast generation.',
                'is_active' => true,
            ]
        );

        // 7. Link Veo
        $veoModel->categories()->sync(\App\Models\Category::whereIn('name', ['Image to Video', 'Text to Video'])->pluck('id'));

        $veoProvider = AiModelProvider::updateOrCreate(
            [
                'ai_model_id' => $veoModel->id,
                'provider_id' => $provider->id,
            ],
            [
                // Clean ID based on STRICT LOGIC: base_url has /models, so ID is just name + action
                'provider_model_id' => 'veo-3.1-fast-generate-preview:predictLongRunning',
                'is_primary' => true,
                'price_mode' => 'per_unit',
                'cost_strategy_id' => $strategy->id, 
            ]
        );

        // 8. Create Schema for Veo
        \App\Models\AiModelSchema::where('ai_model_provider_id', $veoProvider->id)->delete();

        \App\Models\AiModelSchema::create([
            'ai_model_provider_id' => $veoProvider->id,
            'input_schema' => [
                [
                    'key' => 'prompt',
                    'type' => AiModelInputType::TEXTAREA->value,
                    'label' => 'Video Prompt',
                    'required' => true,
                    'default' => 'Describe the motion, camera style, and scene transition for this image.',
                    'placeholder' => 'Describe the video you want to generate...',
                ],
                [
                    'key' => 'image',
                    'type' => AiModelInputType::IMAGE->value,
                    'label' => 'Source Image (For Image-to-Video)',
                    'required' => false, // Optional for Text-to-Video
                    'accept' => 'image/png,image/jpeg',
                ],
                [
                    'key' => 'aspectRatio',
                    'type' => AiModelInputType::SELECT->value,
                    'label' => 'Aspect Ratio',
                    'required' => false,
                    'default' => '9:16',
                    'options' => [
                        ['value' => '9:16', 'label' => '9:16 (Vertical)'],
                        ['value' => '16:9', 'label' => '16:9 (Widescreen)'],
                        ['value' => '1:1', 'label' => '1:1 (Square)'],
                    ]
                ],
                [
                    'key' => 'durationSeconds',
                    'type' => AiModelInputType::SELECT->value,
                    'label' => 'Duration',
                    'required' => false,
                    'default' => 8, 
                    'options' => [
                        ['value' => 4, 'label' => '4 Seconds'],
                        ['value' => 6, 'label' => '6 Seconds'],
                        ['value' => 8, 'label' => '8 Seconds'],
                    ]
                ]
            ],
            'field_mapping' => [
                'prompt' => 'prompt',
                'image' => 'image', // Maps 'image' input to {{image}} placeholder
                'aspectRatio' => 'aspectRatio',
                'durationSeconds' => 'durationSeconds',
            ],
            // Request Template (JSON Structure for Veo)
            // Note: GoogleProvider replaces "{{image}}" with the entire image object.
            // For Veo, we need to handle the structure matching inside GoogleProvider if it differs from Gemini.
            // Assuming we will update GoogleProvider to output Veo-compatible structure for 'veo' models.
            'request_template' => [
                'instances' => [[
                    'prompt' => '{{prompt}}',
                    'image' => '{{image}}' // GoogleProvider will inject the image object here
                ]],
                'parameters' => [
                    'aspectRatio' => '{{aspectRatio}}',
                    'personGeneration' => 'allow_adult',
                    'durationSeconds' => '{{durationSeconds|int}}' // Strings are fine, API casts them
                ]
            ],
            'response_path' => null, 
            'interaction_method' => 'long_running',
        ]);

        $this->command->info('Google Provider setup complete (Gemini + Veo).');

        // ==========================================
        // 9. Create FAL AI Provider
        // ==========================================
        $falProvider = Provider::updateOrCreate(
            ['slug' => 'fal-ai'],
            [
                'name' => 'Fal.ai',
                'api_key_env' => 'FAL_AI_API_KEY',
                'type' => 'fal_ai',
                'base_url' => 'https://fal.run',
            ]
        );

        $this->command->info('FAL AI Provider created successfully.');

        // ==========================================
        // 10. Create Replicate Provider
        // ==========================================
        $replicateProvider = Provider::updateOrCreate(
            ['slug' => 'replicate'],
            [
                'name' => 'Replicate',
                'api_key_env' => 'REPLICATE_API_TOKEN',
                'type' => 'replicate',
                'base_url' => 'https://api.replicate.com',
            ]
        );

        $this->command->info('Replicate Provider created successfully.');
        $this->command->info('All providers seeded successfully (Google, FAL AI, Replicate).');
    }
}
