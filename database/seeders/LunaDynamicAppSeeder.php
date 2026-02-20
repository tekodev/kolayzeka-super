<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LunaDynamicAppSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $model = \App\Models\AiModel::where('slug', 'nano-banana-pro')->first();
        if (!$model) return;

        $app = \App\Models\App::updateOrCreate(
            ['slug' => 'luna-v2'],
            [
                'name' => 'Luna Influencer v2 (Dynamic)',
                'description' => 'Fully dynamic version of the Luna Influencer app.',
                'icon' => 'heroicon-o-camera',
            ]
        );

        $promptTemplate = "Generate an ultra-high-resolution, hyper-realistic image.

{framing_instruction}.
{camera_distance}.
The subject occupies approximately {frame_coverage}% of the frame.
The framing is intentional, balanced, and editorial.
Background remains recognizable and realistic, not overly blurred.

Camera and lens:
Professionally shot using a high-end full-frame camera.
Using a {lens_type}.
Natural perspective with realistic optical compression.
Lens choice is optimized for the selected framing and camera distance.
Moderate depth of field suitable for professional fashion editorial photography.
No wide-angle distortion.
No perspective warping.
No optical deformation.

The subject is the exact same woman shown in {identity_reference_images}.
Her facial structure, bone anatomy, freckles, skin details, facial proportions,
and hair must be ABSOLUTELY IDENTICAL to the reference images.
The person must be unmistakably the same individual.
Perfect identity consistency and absolute reference fidelity are mandatory.

She is wearing the exact clothing from {image}.
The fabric texture, weave, stitching, seams, drape, folds, thickness,
and patterns must be a PIXEL-PERFECT MATCH to the reference images.
Flawless real-world material reproduction down to the smallest detail.

The scene takes place in {location_description}.
The environment is elegant, clean, realistic, and high-end.

She is {activity_style},
captured as a PROFESSIONAL lifestyle fashion photograph.

Her pose is {pose_style}.
Her facial expression is calm, confident, and natural.

The face must be EXTREMELY SHARP, TACK-SHARP, and CRYSTAL CLEAR.
Ultra-high-definition facial detail with realistic skin texture,
visible pores, fine skin details, and natural depth.
The face must remain perfectly recognizable and identical to the reference model.

She is looking {gaze_direction} with confident, natural eye contact.

Lighting is natural daylight with controlled contrast,
professionally balanced to enhance facial structure,
skin texture, fabric detail, and depth.
Soft, realistic shadows with editorial-level polish.

Masterpiece quality.
8K resolution.
Ultra-photorealistic.
Professional fashion photography.
Editorial-level sharpness.
High micro-contrast.
Perfect focus across the entire visible subject.

No noise artifacts.
No distortions.
No cropping errors.
No facial changes.
No anatomical errors.
No identity drift.
Absolute realism and maximum fidelity to all reference images.";

        $uiSchema = [
            // 1. Composition
            [
                'key' => 'framing_instruction',
                'type' => 'select',
                'label' => 'Çerçeveleme Tipi',
                'section' => '1. Kompozisyon & Çerçeveleme',
                'options' => [
                    ['label' => 'Başdan Ayağa', 'value' => 'Head to toe shot'],
                    ['label' => 'Tam Boy', 'value' => 'Full body shot'],
                    ['label' => 'Diz üstü', 'value' => 'Three quarter shot'],
                    ['label' => 'Bel Üstü', 'value' => 'Waist-up shot'],
                    ['label' => 'Portre', 'value' => 'Portrait focus']
                ],
                'default' => 'Head to toe shot'
            ],
            [
                'key' => 'camera_distance',
                'type' => 'select',
                'label' => 'Kamera Mesafe',
                'section' => '1. Kompozisyon & Çerçeveleme',
                'options' => [
                    ['label' => 'Yakın Çekim', 'value' => 'close-up'],
                    ['label' => 'Orta Mesafe', 'value' => 'medium distance'],
                    ['label' => 'Tam Boy', 'value' => 'full-body distance'],
                ],
                'default' => 'medium distance'
            ],
            [
                'key' => 'frame_coverage',
                'type' => 'range',
                'label' => 'Kadraj Doluluğu',
                'section' => '1. Kompozisyon & Çerçeveleme',
                'min' => 10,
                'max' => 100,
                'default' => 70
            ],
            // 2. Camera & Lens
            [
                'key' => 'lens_type',
                'type' => 'select',
                'label' => 'Lens Tipi',
                'section' => '2. Kamera & Lens',
                'options' => ['24mm', '35mm', '50mm prime lens', '85mm portrait lens', '105mm', '135mm'],
                'default' => '50mm prime lens',
                'description' => 'Ideal for portraits'
            ],
            [
                'key' => 'image',
                'type' => 'images',
                'label' => 'Kıyafet Referansları',
                'section' => '3. Kimlik & Referanslar',
                'description' => 'Kıyafet referans görselleri (Opsiyonel)'
            ],
            // 4. Scene & Pose
            [
                'key' => 'location_description',
                'type' => 'text',
                'label' => 'Mekan Açıklaması (Prompt)',
                'section' => '4. Sahne & Poz',
                'default' => 'Her own bedroom, styled like a realistic vlog setup; clean, modern, lived-in, and natural. The environment feels personal, authentic, and high-quality, suitable for a lifestyle vlog.',
                'placeholder' => 'Örn: luxury apartment living room'
            ],
            [
                'key' => 'activity_style',
                'type' => 'text',
                'label' => 'Aktivite / Eylem (Prompt)',
                'section' => '4. Sahne & Poz',
                'default' => 'Standing naturally as if recording a vlog in her bedroom, but not holding a phone or camera',
                'placeholder' => 'Örn: walking casually'
            ],
            [
                'key' => 'pose_style',
                'type' => 'text',
                'label' => 'Poz Stili (Prompt)',
                'section' => '4. Sahne & Poz',
                'default' => 'Model-like, confident, balanced, and intentional, with natural posture and clean lines',
                'placeholder' => 'Örn: relaxed editorial pose'
            ],
            [
                'key' => 'gaze_direction',
                'type' => 'text',
                'label' => 'Bakış Yönü (Prompt)',
                'section' => '4. Sahne & Poz',
                'default' => 'Directly at the camera (the viewer), with confident, natural eye contact',
                'placeholder' => 'Örn: directly into the camera'
            ]
        ];

        \App\Models\AppStep::updateOrCreate(
            ['app_id' => $app->id, 'order' => 1],
            [
                'ai_model_id' => $model->id,
                'prompt_template' => $promptTemplate,
                'ui_schema' => $uiSchema,
                'requires_approval' => false,
                'config' => [
                    'prompt' => ['source' => 'template', 'label' => 'Prompt'],
                    'identity_reference_images' => [
                        'source' => 'static',
                        'label' => 'Identity Reference Images',
                        'value' => ['app_static_assets/luna_identity.jpg', 'app_static_assets/luna_face.png']
                    ],
                    'image' => ['source' => 'user', 'label' => 'Reference Image (Optional)'], // Reference Image (Optional) field in model
                    'aspectRatio' => ['source' => 'user', 'label' => 'Aspect Ratio', 'value' => '9:16'],
                    'imageSize' => ['source' => 'user', 'label' => 'Image Size', 'value' => '1K'],
                    'framing_instruction' => ['source' => 'user', 'label' => 'Çerçeveleme Tipi', 'value' => 'Head to toe shot'],
                    'camera_distance' => ['source' => 'user', 'label' => 'Kamera Mesafe', 'value' => 'medium distance'],
                    'frame_coverage' => ['source' => 'user', 'label' => 'Kadraj Doluluğu', 'value' => 70],
                    'lens_type' => ['source' => 'user', 'label' => 'Lens Tipi', 'value' => '50mm prime lens'],
                    'location_description' => ['source' => 'user', 'label' => 'Mekan Açıklaması (Prompt)', 'value' => 'Her own bedroom, styled like a realistic vlog setup; clean, modern, lived-in, and natural. The environment feels personal, authentic, and high-quality, suitable for a lifestyle vlog.'],
                    'activity_style' => ['source' => 'user', 'label' => 'Aktivite / Eylem (Prompt)', 'value' => 'Standing naturally as if recording a vlog in her bedroom, but not holding a phone or camera'],
                    'pose_style' => ['source' => 'user', 'label' => 'Poz Stili (Prompt)', 'value' => 'Model-like, confident, balanced, and intentional, with natural posture and clean lines'],
                    'gaze_direction' => ['source' => 'user', 'label' => 'Bakış Yönü (Prompt)', 'value' => 'Directly at the camera (the viewer), with confident, natural eye contact'],
                ]
            ]
        );

        // Step 2: Veo (Video Generation from Step 1 Image)
        $veoModel = \App\Models\AiModel::where('slug', 'veo-3.1-fast-generate-preview')->first();
        if ($veoModel) {
            \App\Models\AppStep::updateOrCreate(
                ['app_id' => $app->id, 'order' => 2],
                [
                    'ai_model_id' => $veoModel->id,
                    'name' => 'Video Generation',
                    'requires_approval' => true,
                    'ui_schema' => [
                        [
                            'key' => 'video_prompt',
                            'type' => 'text',
                            'label' => 'Video Hareket / Stil (Prompt)',
                            'section' => 'Video Ayarları'
                        ],
                        [
                            'key' => 'aspect',
                            'type' => 'select',
                            'label' => 'Aspect Ratio',
                            'section' => 'Video Ayarları',
                            'options' => [
                                ['label' => '9:16 (Vertical)', 'value' => '9:16'],
                                ['label' => '16:9 (Horizontal)', 'value' => '16:9'],
                                ['label' => '1:1 (Square)', 'value' => '1:1']
                            ],
                            'default' => '9:16'
                        ],
                        [
                            'key' => 'duration',
                            'type' => 'select',
                            'label' => 'Duration',
                            'section' => 'Video Ayarları',
                            'options' => [
                                ['label' => '4 Seconds', 'value' => '4'],
                                ['label' => '6 Seconds', 'value' => '6'],
                                ['label' => '8 Seconds', 'value' => '8']
                            ],
                            'default' => '6'
                        ]
                    ],
                    'config' => [
                        'prompt' => ['source' => 'user', 'input_key' => 'video_prompt', 'label' => 'Video Prompt'],
                        'image' => ['source' => 'previous', 'step_index' => 1, 'output_key' => 'result', 'label' => 'Source Image'],
                        'aspectRatio' => ['source' => 'user', 'input_key' => 'aspect', 'label' => 'Aspect Ratio'],
                        'durationSeconds' => ['source' => 'user', 'input_key' => 'duration', 'label' => 'Duration'],
                    ]
                ]
            );
        }
    }
}
