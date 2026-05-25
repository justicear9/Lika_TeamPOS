<?php

namespace Modules\InventoryReporting\Services;

use DB;
use Illuminate\Support\Collection;

class InventoryStockIntegrityService
{
    public function getOrphanSellMappings(int $businessId, ?int $locationId = null): Collection
    {
        $query = DB::table('transaction_sell_lines_purchase_lines as m')
            ->leftJoin('transaction_sell_lines as tsl', 'tsl.id', '=', 'm.sell_line_id')
            ->join('purchase_lines as pl', 'pl.id', '=', 'm.purchase_line_id')
            ->join('transactions as pt', 'pt.id', '=', 'pl.transaction_id')
            ->leftJoin('business_locations as bl', 'bl.id', '=', 'pt.location_id')
            ->join('products as p', 'p.id', '=', 'pl.product_id')
            ->join('variations as v', 'v.id', '=', 'pl.variation_id')
            ->whereNotNull('m.sell_line_id')
            ->where('m.sell_line_id', '!=', 0)
            ->whereNull('tsl.id')
            ->where('pt.business_id', $businessId);

        if (! empty($locationId)) {
            $query->where('pt.location_id', $locationId);
        }

        return $query->select([
            'm.id as mapping_id',
            'm.sell_line_id',
            'm.purchase_line_id',
            'm.quantity as mapping_quantity',
            'pt.location_id',
            'bl.name as location_name',
            'p.name as product_name',
            'v.sub_sku',
            'pl.quantity as purchase_quantity',
            'pl.quantity_sold',
        ])
            ->orderBy('m.id', 'desc')
            ->get();
    }

    public function repairOrphanSellMappings(int $businessId, ?int $locationId = null): array
    {
        return DB::transaction(function () use ($businessId, $locationId) {
            $orphans = $this->getOrphanSellMappings($businessId, $locationId);

            if ($orphans->isEmpty()) {
                return [
                    'success' => true,
                    'rows_fixed' => 0,
                    'purchase_lines_touched' => 0,
                ];
            }

            $mappingIds = $orphans->pluck('mapping_id')->all();
            $qtyByPurchaseLine = [];

            foreach ($orphans as $row) {
                $plId = (int) $row->purchase_line_id;
                $qtyByPurchaseLine[$plId] = ($qtyByPurchaseLine[$plId] ?? 0.0) + (float) $row->mapping_quantity;
            }

            foreach ($qtyByPurchaseLine as $purchaseLineId => $qty) {
                $safeQty = max(0, (float) $qty);
                DB::table('purchase_lines')
                    ->where('id', $purchaseLineId)
                    ->update([
                        'quantity_sold' => DB::raw('GREATEST(quantity_sold - '.$safeQty.', 0)'),
                    ]);
            }

            DB::table('transaction_sell_lines_purchase_lines')
                ->whereIn('id', $mappingIds)
                ->delete();

            return [
                'success' => true,
                'rows_fixed' => count($mappingIds),
                'purchase_lines_touched' => count($qtyByPurchaseLine),
            ];
        });
    }
}
