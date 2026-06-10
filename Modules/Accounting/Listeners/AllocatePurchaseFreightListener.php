<?php

namespace Modules\Accounting\Listeners;

use App\Events\PurchaseCreatedOrModified;
use Modules\Accounting\Services\PurchaseLandedCostService;

class AllocatePurchaseFreightListener
{
    public function __construct(protected PurchaseLandedCostService $landedCostService)
    {
    }

    public function handle(PurchaseCreatedOrModified $event): void
    {
        if (isset($event->isDeleted) && $event->isDeleted) {
            return;
        }

        try {
            $this->landedCostService->allocateShippingToPurchaseLines($event->transaction);
        } catch (\Throwable $e) {
            \Log::error('Purchase freight allocation failed', [
                'transaction_id' => $event->transaction->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
