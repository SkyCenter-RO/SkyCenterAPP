<?php

namespace App\Http\Controllers\Api\Automation;

use App\Actions\Automation\DispatchReviewRequests;
use App\Http\Controllers\Controller;
use App\Models\AutomationWebhookLog;
use Illuminate\Http\JsonResponse;

class DispatchReviewRequestsController extends Controller
{
    public function __invoke(DispatchReviewRequests $action): JsonResponse
    {
        $result = $action->handle();

        AutomationWebhookLog::create([
            'endpoint' => 'dispatch-review-requests',
            'event_type' => 'review_request_dispatch',
            'status' => 'processed',
            'http_status' => 200,
            'response_body' => $result,
            'received_at' => now(),
            'processed_at' => now(),
        ]);

        return response()->json($result);
    }
}
