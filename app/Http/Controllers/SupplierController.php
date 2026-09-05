<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View
    {
        return view('suppliers.index', [
            'suppliers' => Supplier::withCount(['products', 'purchaseOrders'])->orderBy('name')->get(),
        ]);
    }

    public function store(SupplierRequest $request, ActivityLogger $logger): RedirectResponse
    {
        $supplier = Supplier::create($request->validated());
        $logger->log($request->user(), 'supplier.created', "Menambahkan supplier {$supplier->name}.", ['supplier_id' => $supplier->id]);

        return back()->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function update(SupplierRequest $request, Supplier $supplier, ActivityLogger $logger): RedirectResponse
    {
        $supplier->update($request->validated());
        $logger->log($request->user(), 'supplier.updated', "Memperbarui supplier {$supplier->name}.", ['supplier_id' => $supplier->id]);

        return back()->with('success', 'Supplier berhasil diperbarui. Lead time baru akan dipakai pada rekomendasi berikutnya.');
    }

    public function destroy(Request $request, Supplier $supplier, ActivityLogger $logger): RedirectResponse
    {
        $supplier->update(['active' => false]);
        $logger->log($request->user(), 'supplier.deactivated', "Menonaktifkan supplier {$supplier->name}.", ['supplier_id' => $supplier->id]);

        return back()->with('success', 'Supplier dinonaktifkan. Riwayat pesanan tetap tersimpan.');
    }
}
