<?php
/**
 * Modal "Hapus per Rentang Tanggal" -- KHUSUS Super Admin (pemanggil sudah
 * membungkus require ini dengan hasRole([ROLE_SUPER_ADMIN])).
 *
 * Wajib dari pemanggil:
 *   $rangeDeleteAction  mis. 'stock_out/rangeDelete'
 *   $rangeDeleteLabel   mis. 'Pengeluaran Barang'
 */
$rangeDeleteAction = $rangeDeleteAction ?? '';
$rangeDeleteLabel  = $rangeDeleteLabel ?? 'Data';
?>
<div class="modal fade" id="rangeDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= BASE_URL ?>/<?= e($rangeDeleteAction) ?>" id="rangeDeleteForm">
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="bi bi-calendar-x"></i> Hapus <?= e($rangeDeleteLabel) ?> per Rentang Tanggal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <?= csrfField() ?>
                    <div class="alert alert-warning py-2 small mb-3">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Semua</strong> <strong><?= e($rangeDeleteLabel) ?></strong> yang tanggal dokumennya berada di rentang ini
                        akan <strong>dipindahkan ke Tempat Sampah</strong> (bisa dipulihkan) &mdash;
                        termasuk yang di periode <strong>Tutup Bulan</strong> maupun yang <strong>terkait dokumen lain</strong>.
                        Untuk hilang permanen, lanjut ke <strong>Tempat Sampah &rarr; Kosongkan</strong>.
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small mb-1">Dari Tanggal <span class="text-danger">*</span></label>
                            <input type="date" name="range_from" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">Sampai Tanggal <span class="text-danger">*</span></label>
                            <input type="date" name="range_to" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash3"></i> Hapus per Tanggal</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var f = document.getElementById('rangeDeleteForm');
    if (!f || f.dataset.wired) return;
    f.dataset.wired = '1';
    f.addEventListener('submit', function (e) {
        var from = f.range_from.value, to = f.range_to.value;
        if (!from || !to) return;
        e.preventDefault();
        var msg = 'Pindahkan SEMUA <?= e($rangeDeleteLabel) ?> dari ' + from + ' s/d ' + to
            + ' ke Tempat Sampah? (masih bisa dipulihkan)';
        if (typeof confirmAction === 'function') {
            confirmAction(msg, 'Ya, hapus per tanggal').then(function (ok) { if (ok) f.submit(); });
        } else if (window.confirm(msg)) {
            f.submit();
        }
    });
});
</script>
