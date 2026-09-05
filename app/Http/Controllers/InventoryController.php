<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventoryAdjustmentRequest;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Services\ActivityLogger;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(Request $request, InventoryService $inventory): View
    {
        $search = trim($request->string('q')->toString());
        $productQuery = Product::query()
            ->with('supplier')
            ->where('active', true)
            ->orderBy('name')
            ->when($search !== '', fn ($query) => $query->where(fn ($builder) => $builder
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")));
        $allProducts = Product::query()->with('supplier')->where('active', true)->orderBy('name')->get();
        $allSummary = $inventory->summaryForProducts($allProducts);
        if ($request->input('stock') === 'low') {
            $lowIds = $allProducts->filter(fn ($product) => (float) $product->minimum_stock > 0
                && $allSummary[$product->id]['on_hand'] < (float) $product->minimum_stock)->pluck('id');
            $productQuery->whereIn('id', $lowIds);
        }
        $products = $productQuery->paginate(20, ['*'], 'products_page')->withQueryString();

        $movementProduct = $request->integer('movement_product');
        $movementType = $request->string('movement_type')->toString();
        $movementFrom = $request->string('movement_from')->toString();
        $movementUntil = $request->string('movement_until')->toString();
        $movements = InventoryMovement::query()
            ->with(['product', 'recorder', 'purchaseOrderItem.purchaseOrder'])
            ->when($movementProduct, fn ($query) => $query->where('product_id', $movementProduct))
            ->when($movementType !== '', fn ($query) => $query->where('movement_type', $movementType))
            ->when($movementFrom !== '', fn ($query) => $query->whereDate('occurred_on', '>=', $movementFrom))
            ->when($movementUntil !== '', fn ($query) => $query->whereDate('occurred_on', '<=', $movementUntil))
            ->latest('occurred_on')
            ->latest('id')
            ->paginate(15, ['*'], 'movements_page')
            ->withQueryString();

        return view('inventory.index', [
            'products' => $products,
            'summary' => $inventory->summaryForProducts($products->getCollection()),
            'allProducts' => $allProducts,
            'allSummary' => $allSummary,
            'movements' => $movements,
            'search' => $search,
            'movementProduct' => $movementProduct,
            'movementType' => $movementType,
            'movementFrom' => $movementFrom,
            'movementUntil' => $movementUntil,
        ]);
    }

    public function adjust(
        InventoryAdjustmentRequest $request,
        InventoryService $inventory,
        ActivityLogger $logger,
    ): RedirectResponse {
        $validated = $request->validated();
        $product = Product::findOrFail($validated['product_id']);
        if (! $product->active) {
            abort(422, 'Barang tidak aktif tidak dapat disesuaikan melalui stok berjalan.');
        }
        if ($product->usesWholeUnits() && floor((float) $validated['counted_quantity']) !== (float) $validated['counted_quantity']) {
            return back()->withErrors([
                'counted_quantity' => "Stok fisik {$product->name} harus berupa bilangan bulat karena satuannya {$product->unit}.",
            ])->withInput();
        }
        $movement = $inventory->recordAdjustment(
            $product,
            (float) $validated['counted_quantity'],
            $validated['occurred_on'],
            $request->user(),
            $validated['notes'] ?? null,
        );

        if (! $movement) {
            return back()->with('success', 'Stok fisik sudah sama dengan stok sistem; tidak ada penyesuaian yang dibuat.');
        }

        $logger->log($request->user(), 'inventory.adjusted', "Melakukan stok opname {$product->name}.", [
            'product_id' => $product->id,
            'inventory_movement_id' => $movement->id,
            'quantity_change' => $movement->quantity_change,
            'counted_quantity' => $validated['counted_quantity'],
        ]);

        return back()->with('success', 'Penyesuaian stok berhasil dicatat pada stok berjalan.');
    }
}
