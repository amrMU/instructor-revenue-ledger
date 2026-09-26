<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Subscription;
use App\Models\SubscriptionPayment;

class SubscriptionRepository
{
    public function lockWithPayment(int $subscriptionId): Subscription
    {
        $subscription = Subscription::query()->lockForUpdate()->findOrFail($subscriptionId);
        $subscription->setRelation('payments', $subscription->payments()->lockForUpdate()->get());

        return $subscription;
    }

    public function lockPaymentWithSubscription(int $paymentId): SubscriptionPayment
    {
        return SubscriptionPayment::query()->with('subscription.plan')->lockForUpdate()->findOrFail($paymentId);
    }
}
