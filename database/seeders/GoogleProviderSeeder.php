<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Provider;
use App\Models\AiModel;
use App\Models\AiModelProvider;

class GoogleProviderSeeder extends Seeder
{
    public function run()
    {
        // 1. Create or Update Provider
        $provider = Provider::firstOrCreate(
            ['slug' => 'google'],
            [
                'name' => 'Google',
                'type' => 'google',
            ]
        );

        // 2. Create Gemini 3 Pro Model
        $model = AiModel::firstOrCreate(
            ['slug' => 'nano-banana-pro'],
            [
                'name' => 'Nano Banana Pro',
                'description' => 'Google\'s most capable AI model for image generation and reasoning.',
                'image_url' => 'ai_models/nano-banana-pro.png', 
                'category' => 'General',
                'is_active' => true,
            ]
        );

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
        AiModelProvider::firstOrCreate(
            [
                'ai_model_id' => $model->id,
                'provider_id' => $provider->id,
            ],
            [
                'provider_model_id' => 'gemini-3-pro-image-preview',
                'is_primary' => true,
                'price_mode' => 'per_unit',
                'cost_strategy_id' => $strategy->id,
            ]
        );

        $this->command->info('Google Provider and Gemini Model seeded successfully.');
    }
}
