<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CalculationController;
use App\Http\Controllers\CriterionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatasetController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RestockActionController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::middleware(['auth', 'active.user'])->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/transaksi', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/penilaian', fn () => view('transactions.analysis'))->name('transactions.analysis');
    Route::post('/transaksi', [TransactionController::class, 'store'])->middleware('role:owner,staff')->name('transactions.store');
    Route::post('/penilaian', [TransactionController::class, 'calculate'])->middleware('role:owner,staff')->name('transactions.calculate');

    Route::get('/data-barang', [ProductController::class, 'index'])->name('products.index');
    Route::middleware('role:owner,staff')->group(function (): void {
        Route::post('/data-barang', [ProductController::class, 'store'])->name('products.store');
        Route::put('/data-barang/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/data-barang/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    });

    Route::get('/supplier', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::middleware('role:owner,staff')->group(function (): void {
        Route::post('/supplier', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::put('/supplier/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('/supplier/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
    });

    Route::get('/kriteria-bobot', [CriterionController::class, 'index'])->name('criteria.index');
    Route::put('/kriteria-bobot', [CriterionController::class, 'update'])->middleware('role:owner')->name('criteria.update');

    Route::get('/data-uji', [DatasetController::class, 'index'])->name('datasets.index');
    Route::get('/data-uji/template-impor', [DatasetController::class, 'downloadTemplate'])->name('datasets.template.download');
    Route::middleware('role:owner,staff')->group(function (): void {
        Route::post('/data-uji/periode', [DatasetController::class, 'storePeriod'])->name('datasets.periods.store');
        Route::post('/data-uji/impor', [DatasetController::class, 'import'])->name('datasets.import');
        Route::post('/data-uji/{period}/revisi', [DatasetController::class, 'revise'])->name('datasets.revise');
        Route::put('/data-uji', [DatasetController::class, 'update'])->name('datasets.update');
    });
    Route::delete('/data-uji/{period}/revisi', [DatasetController::class, 'discardRevision'])->middleware('role:owner')->name('datasets.revise.discard');
    Route::get('/data-uji/{period}/sumber', [DatasetController::class, 'downloadSource'])->name('datasets.source.download');

    Route::get('/proses-moora', [CalculationController::class, 'index'])->name('calculations.index');
    Route::post('/proses-moora', [CalculationController::class, 'store'])->middleware('role:owner,staff')->name('calculations.store');

    Route::get('/hasil-pengujian/{run?}', [CalculationController::class, 'results'])->name('calculations.results');
    Route::get('/hasil-pengujian/{run}/detail/{result}', [CalculationController::class, 'show'])->name('calculations.show');
    Route::get('/stok-berjalan', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/tindak-lanjut-restock', [RestockActionController::class, 'index'])->name('restock-actions.index');
    Route::put('/tindak-lanjut-restock/{run}/massal', [RestockActionController::class, 'bulkUpdate'])->middleware('role:owner,staff')->name('restock-actions.bulk-update');
    Route::put('/tindak-lanjut-restock/{run}/{result}', [RestockActionController::class, 'update'])->middleware('role:owner,staff')->name('restock-actions.update');
    Route::get('/pesanan-pembelian', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
    Route::get('/ekspor/hasil/{run}', [ExportController::class, 'run'])->name('exports.run');
    Route::get('/ekspor/periode/{period}', [ExportController::class, 'period'])->name('exports.period');

    Route::middleware('role:owner,staff')->group(function (): void {
        Route::post('/stok-berjalan/penyesuaian', [InventoryController::class, 'adjust'])->name('inventory.adjust');
        Route::post('/pesanan-pembelian/dari-rekomendasi/{run}', [PurchaseOrderController::class, 'createFromRun'])->name('purchase-orders.from-run');
        Route::put('/pesanan-pembelian/{order}/status', [PurchaseOrderController::class, 'updateStatus'])->name('purchase-orders.status');
        Route::put('/pesanan-pembelian/{order}/penerimaan', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
    });

    Route::get('/laporan', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/laporan/{run}', [ReportController::class, 'store'])->middleware('role:owner,staff')->name('reports.store');
    Route::get('/laporan/{run}/pdf', [ReportController::class, 'download'])->name('reports.download');
    Route::get('/aktivitas', [ActivityLogController::class, 'index'])->middleware('role:owner')->name('activity-logs.index');
    Route::get('/aktivitas/ekspor', [ExportController::class, 'activities'])->middleware('role:owner')->name('exports.activities');
    Route::get('/pengguna', [UserController::class, 'index'])->middleware('role:owner')->name('users.index');
    Route::post('/pengguna', [UserController::class, 'store'])->middleware('role:owner')->name('users.store');
    Route::put('/pengguna/{user}', [UserController::class, 'update'])->middleware('role:owner')->name('users.update');
    Route::delete('/pengguna/{user}', [UserController::class, 'destroy'])->middleware('role:owner')->name('users.destroy');
});
