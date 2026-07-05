<?php

namespace Platform\Dev\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Generic dev ingest endpoint. Dispatches by the payload's `type` field to the
 * matching handler. Defaults to `error` for backwards compatibility with
 * callers that predate the feature-request channel.
 */
class IngestController extends Controller
{
    public function ingest(
        Request $request,
        string $token,
        ErrorIngestController $errors,
        FeatureIngestController $features
    ): JsonResponse {
        $type = $request->input('type', 'error');

        return match ($type) {
            'feature' => $features->ingest($request, $token),
            'error' => $errors->ingest($request, $token),
            default => response()->json([
                'error' => "Unknown ingest type '{$type}'. Expected 'error' or 'feature'.",
            ], 422),
        };
    }
}
