<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Services\GenerationService;
use App\Jobs\ProcessGenerationJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use Inertia\Inertia;

class GenerationController extends Controller
{
    public function __construct(protected GenerationService $generationService) {}

    public function index(Request $request)
    {
        // Select only necessary columns to reduce memory usage and JSON size
        $generations = \App\Models\Generation::where('user_id', $request->user()->id)
            ->select([
                'id', 
                'user_id', 
                'ai_model_id', 
                'status', 
                'created_at', 
                'output_data', 
                'user_credit_cost', 
                'error_message',
                'thumbnail_url'
            ])
            ->with(['aiModel' => function($q) {
                $q->select(['id', 'name', 'image_url'])->with('categories');
            }]) 
            ->orderBy('id', 'desc')
            ->paginate(12);

        // Transform collection to clean up data
        $generations->getCollection()->transform(function ($generation) {
            // Prepare the signed URL
            $generation->prepareVideoUrl();
            
            // Clean output_data to remove heavy raw responses
            if ($generation->output_data) {
                $cleanOutput = [
                    'result' => $generation->output_data['result'] ?? null,
                    'thumbnail' => $generation->output_data['thumbnail'] ?? null,
                    'is_s3_path' => $generation->output_data['is_s3_path'] ?? false,
                ];
                $generation->output_data = $cleanOutput;
            }

            // Remove input_data entirely as it's not used in the list view
            // (Note: We already didn't select it, but good to be explicit if model appended it)
            unset($generation->input_data);
            
            return $generation;
        });

        return Inertia::render('Generations/Index', [
            'generations' => $generations
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ai_model_id' => 'required|exists:ai_models,id',
            'input_data' => 'required|array',
        ]);
        
        // Handle file uploads in input_data explicitly (support nested arrays like 'images')
        $inputData = $validated['input_data'];
        $handleFiles = function (&$data) use (&$handleFiles) {
            foreach ($data as $key => &$value) {
                if ($value instanceof \Illuminate\Http\UploadedFile) {
                    try {
                         $path = $value->store('generations/inputs', 's3');
                         $value = Storage::disk('s3')->url($path);
                    } catch (\Exception $e) {
                         Log::error("[GenerationController] Failed to upload file for key {$key}: " . $e->getMessage());
                    }
                } elseif (is_array($value)) {
                    $handleFiles($value);
                }
            }
        };
        $handleFiles($inputData);
        $validated['input_data'] = $inputData;

        $model = AiModel::with('primaryProvider')->findOrFail($request->ai_model_id);
        
        if (!$model->primaryProvider) {
            return back()->withErrors(['error' => 'Model provider configuration not found']);
        }

        try {
            // Create generation record with pending status
            $generation = \App\Models\Generation::create([
                'user_id' => $request->user()->id,
                'ai_model_id' => $model->id,
                'ai_model_provider_id' => $model->primaryProvider->id,
                'status' => 'pending',
                'input_data' => $validated['input_data'], // Use validated data to include files
                'output_data' => null,
                'provider_request_body' => null,
                'provider_cost_usd' => 0,
                'user_credit_cost' => 0,
                'profit_usd' => 0,
                'duration' => 0,
            ]);

            // Dispatch job for async processing
            ProcessGenerationJob::dispatch(
                $generation->id,
                $request->user()->id,
                $model->id
            );

            // Return immediate response
            return redirect()->back()->with([
                'success' => 'Generation started! You will be notified when it completes.',
                'generation_id' => $generation->id,
                'generation_status' => 'pending',
            ]);
        } catch (\Exception $e) {
            Log::error('Generation dispatch failed: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to start generation: ' . $e->getMessage()]);
        }
    }
}
