<?php

namespace App\Http\Controllers\Api\Automation;

use App\Http\Controllers\Controller;
use App\Models\OutboundMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OutboundMessagesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $status = $request->query('status', 'pending');

        $messages = OutboundMessage::query()
            ->where('status', $status)
            ->orderBy('scheduled_at')
            ->limit(50)
            ->get();

        $data = $messages->map(fn (OutboundMessage $message): array => [
            'id' => $message->id,
            'service' => $message->service,
            'reference_id' => $message->reference_id,
            'channel' => $message->channel,
            'template_key' => $message->template_key,
            'text' => $message->payload['text'] ?? null,
            'contact' => $message->payload['contact'] ?? null,
            'scheduled_at' => $message->scheduled_at?->toAtomString(),
        ]);

        return response()->json(['data' => $data]);
    }
}
