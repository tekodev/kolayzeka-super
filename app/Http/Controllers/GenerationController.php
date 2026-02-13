<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Services\GenerationService;
use Illuminate\Http\Request;

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
                'error_message'
            ])
            ->with(['aiModel:id,name,image_url']) // Optimize eager loading
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
        $request->validate([
            'ai_model_id' => 'required|exists:ai_models,id',
            'input_data' => 'required|array',
        ]);

        $model = AiModel::findOrFail($request->ai_model_id);
        
        try {
            $generation = $this->generationService->generate(
                $request->user(),
                $model,
                $request->input_data
            );
            
            // Return result, maybe flash message or Inertia back with data
            return redirect()->back()->with([
                'success' => 'Generation successful!',
                'generation_result' => $generation,
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Generation failed: ' . $e->getMessage());
        }
    }
}
