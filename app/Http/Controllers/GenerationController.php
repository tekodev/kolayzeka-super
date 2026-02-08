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
        $generations = \App\Models\Generation::where('user_id', $request->user()->id)
            ->with(['aiModel'])
            ->orderBy('id', 'desc')
            ->paginate(12);

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
