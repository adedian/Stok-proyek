<?php
$notifItems = [
    'notify_selisih_barang'     => ['label' => 'Selisih Barang', 'desc' => 'Banner peringatan di dashboard saat ada penerimaan barang dengan selisih yang belum divalidasi.'],
    'notify_invoice_pending'    => ['label' => 'Invoice Belum Terbayar', 'desc' => 'Kartu jumlah Invoice Keluar yang belum ada Pembayaran (belum terbayar) di dashboard.'],
    'notify_stok_minimum'       => ['label' => 'Stok Minimum', 'desc' => 'Banner peringatan saat ada barang dengan stok di bawah batas minimum.'],
    'notify_po_belum_diproses'  => ['label' => 'PO Belum Diproses', 'desc' => 'Banner peringatan saat ada Purchase Order yang masih menunggu approval.'],
];
?>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/index.php?module=settings&action=saveNotifications">
            <?= csrfField() ?>
            <?php foreach ($notifItems as $key => $item): ?>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" name="<?= e($key) ?>" id="<?= e($key) ?>"
                           <?= ($notification[$key] ?? '1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="<?= e($key) ?>">
                        <strong><?= e($item['label']) ?></strong><br>
                        <span class="text-muted small"><?= e($item['desc']) ?></span>
                    </label>
                </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-primary mt-2"><i class="bi bi-save"></i> Simpan Notifikasi</button>
        </form>
    </div>
</div>
