<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\Generation;
use App\Services\GenerationService;
use Illuminate\Http\Request;

class GenerationController extends Controller
{
    public function __construct(
        protected GenerationService $generationService
    ) {}

    public function generate(Request $request, string $slug)
    {
        $model = AiModel::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        // Pass all request data to the service. The service handles files, casting, etc.
        $inputData = $request->except(['_token', '_method']);

        try {
            $result = $this->generationService->generate(
                $request->user(),
                $model,
                $inputData
            );

            return response()->json([
                'success' => true,
                'generation' => $result,
            ]);
        } catch (\Exception $e) {
            \Log::error('API Generation Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function index(Request $request)
    {
        $generations = Generation::where('user_id', $request->user()->id)
            ->with('aiModel')
            ->latest()
            ->paginate(20);

        return response()->json($generations);
    }
}
