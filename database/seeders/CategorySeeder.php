<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => '3D to 3D', 'input' => '3d', 'output' => '3d'],
            ['name' => 'Audio to Audio', 'input' => 'audio', 'output' => 'audio'],
            ['name' => 'Audio to Text', 'input' => 'audio', 'output' => 'text'],
            ['name' => 'Audio to Video', 'input' => 'audio', 'output' => 'video'],
            ['name' => 'Image to 3D', 'input' => 'image', 'output' => '3d'],
            ['name' => 'Image to Image', 'input' => 'image', 'output' => 'image'],
            ['name' => 'Image to JSON', 'input' => 'image', 'output' => 'json'],
            ['name' => 'Image to Video', 'input' => 'image', 'output' => 'video'],
            ['name' => 'JSON', 'input' => 'json', 'output' => 'json'],
            ['name' => 'Large Language Models', 'input' => 'text', 'output' => 'text'],
            ['name' => 'Speech to Speech', 'input' => 'audio', 'output' => 'audio'],
            ['name' => 'Speech to Text', 'input' => 'audio', 'output' => 'text'],
            ['name' => 'Text to 3D', 'input' => 'text', 'output' => '3d'],
            ['name' => 'Text to Audio', 'input' => 'text', 'output' => 'audio'],
            ['name' => 'Text to Image', 'input' => 'text', 'output' => 'image'],
            ['name' => 'Text to JSON', 'input' => 'text', 'output' => 'json'],
            ['name' => 'Text to Speech', 'input' => 'text', 'output' => 'audio'],
            ['name' => 'Text to Text', 'input' => 'text', 'output' => 'text'],
            ['name' => 'Text to Video', 'input' => 'text', 'output' => 'video'],
            ['name' => 'Training', 'input' => 'text', 'output' => 'text'],
            ['name' => 'Unknown', 'input' => 'text', 'output' => 'text'],
            ['name' => 'Video to Audio', 'input' => 'video', 'output' => 'audio'],
            ['name' => 'Video to Text', 'input' => 'video', 'output' => 'text'],
            ['name' => 'Video to Video', 'input' => 'video', 'output' => 'video'],
            ['name' => 'Vision', 'input' => 'image', 'output' => 'text'],
        ];

        foreach ($categories as $cat) {
            DB::table('categories')->updateOrInsert(
                ['name' => $cat['name']],
                [
                    'input_type' => $cat['input'],
                    'output_type' => $cat['output'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
