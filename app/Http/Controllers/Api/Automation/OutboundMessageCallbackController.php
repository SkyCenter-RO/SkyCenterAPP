<?php

namespace App\Http\Controllers\Api\Automation;

use App\Http\Controllers\Controller;
use App\Models\AutomationEvent;
use App\Models\AutomationWebhookLog;
use App\Models\LodgingReservation;
use App\Models\OutboundMessage;
use App\Models\ParkingReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OutboundMessageCallbackController extends Controller
{
    public function __invoke(Request $request, OutboundMessage $outboundMessage): JsonResponse
    {
        if ($outboundMessage->status !== 'pending') {
            return response()->json(['status' => 'ok']);
        }

        $payload = $request->all();
        $status = $payload['status'] ?? null;

        if ($status === 'sent') {
            $outboundMessage->status = 'sent';
            $outboundMessage->sent_at = now();
        } else {
            $outboundMessage->status = 'failed';
            $outboundMessage->payload = array_merge($outboundMessage->payload ?? [], [
                'error_message' => $payload['error_message'] ?? null,
            ]);
        }
        $outboundMessage->save();

        $externalId = $this->resolveExternalId($outboundMessage);
        $eventType = $status === 'sent' ? 'message_sent' : 'message_failed';

        $log = AutomationWebhookLog::create([
            'endpoint' => 'outbound-messages-callback',
            'event_type' => $eventType,
            'service' => $outboundMessage->service,
            'external_id' => $externalId,
            'payload' => $payload,
            'status' => 'processed',
            'http_status' => 200,
            'received_at' => now(),
            'processed_at' => now(),
        ]);

        AutomationEvent::create([
            'webhook_log_id' => $log->id,
            'event_type' => $eventType,
            'service' => $outboundMessage->service,
            'external_id' => $externalId,
            'occurred_at' => now(),
            'status' => 'processed',
            'payload' => $payload,
        ]);

        return response()->json(['status' => 'ok']);
    }

    private function resolveExternalId(OutboundMessage $outboundMessage): ?string
    {
        return match ($outboundMessage->service) {
            'parking' => ParkingReservation::find($outboundMessage->reference_id)?->external_id,
            'lodging' => LodgingReservation::find($outboundMessage->reference_id)?->external_id,
            default => null,
        };
    }
}
