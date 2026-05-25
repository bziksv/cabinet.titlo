<?php

namespace App\Http\Controllers\Api\Demo;

use App\Http\Controllers\Controller;
use App\Services\Demo\UniqueWordsDemoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UniqueWordsDemoController extends Controller
{
    public function run(Request $request): JsonResponse
    {
        $body = $request->json()->all();
        if (!is_array($body)) {
            $body = $request->all();
        }

        $content = (string) ($body['content'] ?? '');
        $validated = UniqueWordsDemoService::validate($content);

        if (!($validated['ok'] ?? false)) {
            return response()->json([
                'error' => $validated['error'],
                'message' => $validated['message'],
            ], $validated['status']);
        }

        $analysis = UniqueWordsDemoService::analyze($content);

        return response()->json(UniqueWordsDemoService::buildResponse($analysis));
    }
}
