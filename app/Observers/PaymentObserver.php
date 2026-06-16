<?php

namespace App\Observers;

use App\Models\Payment;
use App\Models\PaymentChangeAudit;

class PaymentObserver
{
    public function created(Payment $payment): void
    {
        PaymentChangeAudit::create([
            'payment_id' => $payment->id,
            'user_id' => auth()->id() ?? $payment->updated_by_id ?? $payment->created_by_id,
            'action' => 'created',
            'old_values' => null,
            'new_values' => $payment->toArray(),
            'changed_fields' => null,
            'created_at' => now(),
        ]);
    }

    public function updated(Payment $payment): void
    {
        $changed = array_keys($payment->getDirty());

        if (empty($changed)) {
            return;
        }

        PaymentChangeAudit::create([
            'payment_id' => $payment->id,
            'user_id' => auth()->id() ?? $payment->updated_by_id,
            'action' => 'updated',
            'old_values' => array_intersect_key($payment->getOriginal(), array_flip($changed)),
            'new_values' => $payment->only($changed),
            'changed_fields' => $changed,
            'created_at' => now(),
        ]);
    }
}
