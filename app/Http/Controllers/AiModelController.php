<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use Inertia\Inertia;
use Illuminate\Http\Request;

class AiModelController extends Controller
{
    public function index()
    {
        return Inertia::render('Models/Index', [
            'aiModels' => AiModel::with([
                'categories',
                'providers' => function($q) {
                    $q->where('is_primary', true)->with(['schema', 'provider']);
                }
            ])->where('is_active', true)->get()
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $model = AiModel::where('slug', $slug)
            ->with(['categories', 'providers' => function($q) {
                $q->where('is_primary', true)->with(['schema', 'provider']);
            }])
            ->where('is_active', true)
            ->firstOrFail();

        $initialData = null;
        $repromptResult = null;
        if ($request->has('reprompt') && auth()->check()) {
            $generation = \App\Models\Generation::where('id', $request->reprompt)
                ->where('user_id', auth()->id())
                ->first();
            
            if ($generation) {
                $initialData = $generation->input_data;
                // Prepare signed URLs before passing to frontend
                $generation->prepareVideoUrl();
                // Pass the result to simulate a just-finished generation
                $repromptResult = $generation;
            }
        }

        return Inertia::render('Models/Show', [
            'aiModel' => $model,
            'initialData' => $initialData,
            'repromptResult' => $repromptResult
        ]);
    }

    public function docs(string $slug)
    {
        $model = AiModel::where('slug', $slug)
            ->with(['categories', 'providers' => function($q) {
                $q->where('is_primary', true)->with(['schema', 'provider']);
            }])
            ->where('is_active', true)
            ->firstOrFail();

        return Inertia::render('Models/Docs', [
            'aiModel' => $model,
            'appUrl' => config('app.url'),
        ]);
    }
}
