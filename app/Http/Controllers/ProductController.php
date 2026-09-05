<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $query = Product::with(['category', 'supplier'])->orderBy('name');
        if ($request->filled('search')) {
            $term = trim((string) $request->string('search'));
            $query->where(fn ($builder) => $builder
                ->where('code', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%"));
        }

        if ($request->input('setup') === 'minimum') {
            $query->where('active', true)->where('minimum_stock', '<=', 0);
        }
        if ($request->input('setup') === 'supplier') {
            $query->whereNull('supplier_id');
        }

        $products = $query->paginate(10)->withQueryString();

        return view('products.index', compact('products') + [
            'categories' => Category::orderBy('name')->get(),
            'suppliers' => Supplier::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(ProductRequest $request, ActivityLogger $logger): RedirectResponse
    {
        $product = Product::create($request->validated());
        $logger->log($request->user(), 'product.created', "Menambahkan barang {$product->name}.", ['product_id' => $product->id]);

        return back()->with('success', 'Data barang berhasil ditambahkan.');
    }

    public function update(ProductRequest $request, Product $product, ActivityLogger $logger): RedirectResponse
    {
        $product->update($request->validated());
        $logger->log($request->user(), 'product.updated', "Memperbarui barang {$product->name}.", ['product_id' => $product->id]);

        return back()->with('success', 'Data barang berhasil diperbarui.');
    }

    public function destroy(Request $request, Product $product, ActivityLogger $logger): RedirectResponse
    {
        $product->update(['active' => false]);
        $logger->log($request->user(), 'product.deactivated', "Menonaktifkan barang {$product->name}.", ['product_id' => $product->id]);

        return back()->with('success', 'Barang dinonaktifkan tanpa menghapus riwayat perhitungan.');
    }
}
