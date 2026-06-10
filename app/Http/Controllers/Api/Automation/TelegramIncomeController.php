<?php

namespace App\Http\Controllers\Api\Automation;

use App\Actions\Telegram\ProcessIncomeTelegramUpdate;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramIncomeController extends Controller
{
    public function __invoke(Request $request, ProcessIncomeTelegramUpdate $action): JsonResponse
    {
        $validated = $request->validate([
            'update_type'       => 'required|in:message,callback_query',
            'chat_id'           => 'required|string',
            'user_id'           => 'required|string',
            'username'          => 'nullable|string',
            'message_id'        => 'nullable|integer',
            'text'              => 'nullable|string|max:512',
            'callback_query_id' => 'nullable|string',
            'callback_data'     => 'nullable|string|max:64',
        ]);

        $result = $action->handle($validated);

        return response()->json($result);
    }
}
