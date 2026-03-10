<?php

namespace App\Http\Controllers;

use App\Services\LLM\LLMProviderFactory;
use Illuminate\Http\JsonResponse;

class ModelController extends Controller
{
    public function __construct(
        protected LLMProviderFactory $factory,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->factory->availableModels(),
        ]);
    }
}
