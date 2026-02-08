<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use Illuminate\Http\Request;

class AiModelController extends Controller
{
    public function index(Request $request)
    {
        $models = AiModel::select([
            'id', 'name', 'slug', 'category', 'description', 
            'image_url', 'is_active', 'created_at', 'updated_at'
        ])->where('is_active', true)->get();

        return response()->json([
            'models' => $models,
        ]);
    }

    public function show(string $slug)
    {
        $model = AiModel::where('slug', $slug)
            ->with(['providers' => function($q) {
                $q->where('is_primary', true)->with(['schema', 'provider']);
            }])
            ->firstOrFail();

        return response()->json([
            'model' => $model,
        ]);
    }
}
