<?php

namespace Modules\ApprovalWorkflow\Services;

use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SellController;
use App\Http\Controllers\SellReturnController;
use App\Http\Controllers\StockAdjustmentController;
use App\Transaction;

/**
 * Resolve the same “view document” targets the rest of the app uses (modal vs full page).
 */
class ApprovalTransactionLink
{
    /**
     * @return array{url: string, modal: bool, container: ?string}
     */
    public static function viewTarget(Transaction $transaction): array
    {
        $id = $transaction->id;

        return match ($transaction->type) {
            'purchase' => [
                'url' => action([PurchaseController::class, 'show'], [$id]),
                'modal' => true,
                'container' => '.view_modal',
            ],
            'stock_adjustment' => [
                'url' => action([StockAdjustmentController::class, 'show'], [$id]),
                'modal' => true,
                'container' => '.view_modal',
            ],
            'sell_return' => [
                'url' => action([SellReturnController::class, 'show'], [$id]),
                'modal' => false,
                'container' => null,
            ],
            'sell', 'sales_order' => [
                'url' => action([SellController::class, 'show'], [$id]),
                'modal' => false,
                'container' => null,
            ],
            default => [
                'url' => action([SellController::class, 'show'], [$id]),
                'modal' => false,
                'container' => null,
            ],
        };
    }
}
