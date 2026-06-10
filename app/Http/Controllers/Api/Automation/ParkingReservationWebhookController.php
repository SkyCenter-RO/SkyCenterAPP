<?php

namespace App\Http\Controllers\Api\Automation;

use App\Actions\Automation\UpsertParkingReservationFromWebhook;
use App\Http\Controllers\Controller;
use App\Models\AutomationWebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParkingReservationWebhookController extends Controller
{
    public function __invoke(Request $request, UpsertParkingReservationFromWebhook $action): JsonResponse
    {
        $payload = $request->all();

        $log = AutomationWebhookLog::create([
            'endpoint' => 'parking-reservations',
            'service' => 'parking',
            'event_type' => $payload['event_type'] ?? 'reservation',
            'external_id' => $payload['external_id'] ?? null,
            'payload' => $payload,
            'status' => 'received',
            'http_status' => 0,
            'received_at' => now(),
        ]);

        $result = $action->handle($payload, $log);

        $log->update([
            'status' => $result['status'],
            'http_status' => $result['http_status'],
            'error_message' => $result['error'] ?? null,
            'response_body' => $result['response'],
            'processed_at' => now(),
        ]);

        return response()->json($result['response'], $result['http_status']);
    }
}
