<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = ['user_id', 'action', 'description', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'login' => 'Masuk ke sistem',
            'logout' => 'Keluar dari sistem',
            'product.created' => 'Barang ditambahkan',
            'product.updated' => 'Barang diperbarui',
            'product.deactivated' => 'Barang dinonaktifkan',
            'supplier.created' => 'Supplier ditambahkan',
            'supplier.updated' => 'Supplier diperbarui',
            'supplier.deactivated' => 'Supplier dinonaktifkan',
            'criteria.updated' => 'Kriteria diperbarui',
            'period.created' => 'Data operasional dibuat',
            'period.revised' => 'Pembaruan data dibuat',
            'period.revision_discarded' => 'Pembaruan data dibatalkan',
            'dataset.imported' => 'Data penjualan diimpor',
            'dataset.updated' => 'Data operasional disimpan',
            'moora.executed' => 'Rekomendasi dibuat',
            'restock.action_updated' => 'Tindak lanjut diperbarui',
            'restock.bulk_updated' => 'Tindak lanjut massal diperbarui',
            'purchase_order.created' => 'Pesanan pembelian dibuat',
            'purchase_order.status_updated' => 'Status pesanan diperbarui',
            'purchase_order.received' => 'Penerimaan barang dicatat',
            'inventory.adjusted' => 'Stok opname dicatat',
            'report.saved' => 'Laporan diarsipkan',
            'report.downloaded' => 'Laporan diunduh',
            'report.archive_failed', 'report.download_failed' => 'Pembuatan laporan gagal',
            'user.created' => 'Pengguna ditambahkan',
            'user.updated' => 'Pengguna diperbarui',
            'user.deactivated' => 'Pengguna dinonaktifkan',
            default => 'Aktivitas sistem',
        };
    }
}
