<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Report that the API is reachable, without exposing any internal detail.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'version' => 'v1',
            'timestamp' => now()->utc()->toIso8601ZuluString(),
        ]);
    }
}
