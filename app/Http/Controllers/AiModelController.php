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
            'aiModels' => AiModel::with(['providers' => function($q) {
                $q->where('is_primary', true)->with(['schema', 'provider']);
            }])->where('is_active', true)->get()
        ]);
    }

    public function show(string $slug)
    {
        $model = AiModel::where('slug', $slug)
            ->with(['providers' => function($q) {
                $q->where('is_primary', true)->with(['schema', 'provider']);
            }])
            ->where('is_active', true)
            ->firstOrFail();

        return Inertia::render('Models/Show', [
            'aiModel' => $model
        ]);
    }
}
