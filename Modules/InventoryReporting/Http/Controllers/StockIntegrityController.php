<?php

namespace Modules\InventoryReporting\Http\Controllers;

use App\BusinessLocation;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\InventoryReporting\Services\InventoryStockIntegrityService;

class StockIntegrityController extends Controller
{
    public function index(Request $request, InventoryStockIntegrityService $integrity)
    {
        if (! auth()->user()->can('inventoryreporting.stock_integrity')) {
            abort(403, 'Unauthorized action.');
        }

        $moduleUtil = app(ModuleUtil::class);
        if (! $moduleUtil->isModuleInstalled('InventoryReporting')) {
            abort(404);
        }

        $businessId = (int) session()->get('user.business_id');
        if (! $moduleUtil->isSubscribed($businessId)) {
            return $moduleUtil->expiredResponse(action([self::class, 'index']));
        }

        $locationId = $request->filled('location_id') ? (int) $request->input('location_id') : null;
        $business_locations = BusinessLocation::forDropdown($businessId, false, false);
        $rows = $integrity->getOrphanSellMappings($businessId, $locationId);

        return view('inventoryreporting::integrity.orphans', compact('rows', 'business_locations', 'locationId'));
    }

    public function repair(Request $request, InventoryStockIntegrityService $integrity)
    {
        if (! auth()->user()->can('inventoryreporting.stock_integrity')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'location_id' => 'nullable|integer',
        ]);

        $moduleUtil = app(ModuleUtil::class);
        $businessId = (int) session()->get('user.business_id');
        if (! $moduleUtil->isSubscribed($businessId)) {
            return $moduleUtil->expiredResponse(action([self::class, 'index']));
        }

        $locationId = $request->filled('location_id') ? (int) $request->input('location_id') : null;
        $result = $integrity->repairOrphanSellMappings($businessId, $locationId);

        $message = __('inventoryreporting::lang.orphan_mapping_repair_success', [
            'rows' => $result['rows_fixed'],
            'lines' => $result['purchase_lines_touched'],
        ]);

        return redirect()
            ->route('inventoryreporting.integrity.orphans', array_filter(['location_id' => $locationId]))
            ->with('status', ['success' => 1, 'msg' => $message]);
    }
}
