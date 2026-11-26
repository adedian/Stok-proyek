<?php
/** Detail Reimbursement. Variabel: $r, $items, $history, $actions, $banks, $jenisList, $tujuanList */
$id = (int) $r['id'];
$postUrl = fn(string $a) => BASE_URL . '/index.php?module=reimbursement&action=' . $a;
$t = Reimbursement::totals(array_column($items, 'amount'), $r['kasbon_amount']);
$kbMinus = Reimbursement::kasbonMinusExpense($t['total'], $t['kasbon']);
$decided = in_array($r['status'], [Reimbursement::DITERUSKAN, Reimbursement::DISETUJUI, Reimbursement::DITOLAK], true);
$actionForm = function (string $action, string $label, string $icon, string $btnClass, ?string $confirm) use ($postUrl, $id) {
    echo '<form method="POST" action="' . e($postUrl($action)) . '" class="d-inline rb-confirm"' . ($confirm ? ' data-message="' . e($confirm) . '"' : '') . '>'
        . csrfField() . '<input type="hidden" name="id" value="' . $id . '">'
        . '<button type="submit" class="btn ' . $btnClass . '"><i class="bi ' . e($icon) . '"></i> ' . e($label) . '</button></form> ';
};
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Reimbursement <?= e($r['reimb_number']) ?></h4>
        <small class="text-muted">Diajukan oleh <?= e($r['created_by_name']) ?> &middot; <?= e(formatTanggal($r['reimb_date'])) ?></small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <span class="badge bg-<?= e(Reimbursement::statusBadge($r['status'])) ?> fs-6 align-self-center"><?= e(Reimbursement::statusLabel($r['status'])) ?></span>
        <a href="<?= BASE_URL ?>/reimbursement/print/<?= $id ?>" target="_blank" class="btn btn-outline-dark"><i class="bi bi-printer"></i> Cetak</a>
        <a href="<?= BASE_URL ?>/reimbursement" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>
</div>

<?php if ($r['status'] === Reimbursement::DITERUSKAN): ?>
    <div class="alert alert-success py-2">
        <i class="bi bi-lock"></i> Disetujui oleh <strong><?= e($r['reviewed_by_name'] ?? '-') ?></strong> (<?= e(date('d/m/Y H:i', strtotime((string) $r['reviewed_at']))) ?>) dan diteruskan ke Pengajuan Budget
        <?php if (!empty($r['pengajuan_number'])): ?>
            <strong><?= can('pengajuan_budget', 'view') ? '<a href="' . BASE_URL . '/pengajuan_budget/detail/' . (int) $r['pengajuan_budget_id'] . '">' . e($r['pengajuan_number']) . '</a>' : e($r['pengajuan_number']) ?></strong>
            (status <?= e(PengajuanBudget::statusLabel($r['pb_status'])) ?>)
        <?php endif; ?>
        sebesar <strong><?= e(formatRupiah($r['net_amount'])) ?></strong>. Data terkunci agar Pengajuan Budget tidak berubah diam-diam.
    </div>
<?php elseif ($r['status'] === Reimbursement::DISETUJUI): ?>
    <div class="alert alert-success py-2"><i class="bi bi-lock"></i> Disetujui oleh <strong><?= e($r['reviewed_by_name'] ?? '-') ?></strong> (<?= e(date('d/m/Y H:i', strtotime((string) $r['reviewed_at']))) ?>). Kasbon &ge; total pengeluaran, jadi tidak ada kurang bayar &mdash; tidak diteruskan ke Pengajuan Budget. Data terkunci.</div>
<?php elseif ($r['status'] === Reimbursement::DITOLAK): ?>
    <div class="alert alert-danger py-2"><i class="bi bi-x-octagon"></i> Ditolak oleh <strong><?= e($r['reviewed_by_name'] ?? '-') ?></strong> (<?= e(date('d/m/Y H:i', strtotime((string) $r['reviewed_at']))) ?>). Alasan: <?= e($r['review_note']) ?>. Tidak diteruskan ke Pengajuan Budget.</div>
<?php elseif ($r['status'] === Reimbursement::MENUNGGU): ?>
    <div class="alert alert-warning py-2"><i class="bi bi-hourglass-split"></i> Menunggu pemeriksaan Nissa / Purwati (persetujuan salah satu sudah cukup).</div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="card-section-title mb-2">Laporan Biaya Reimburse</div>
        <div class="row g-2">
            <div class="col-md-4"><span class="text-muted small d-block">Nama</span><?= e($r['employee_name']) ?></div>
            <div class="col-md-4"><span class="text-muted small d-block">Tanggal</span><?= e(formatTanggal($r['reimb_date'])) ?></div>
            <div class="col-md-4"><span class="text-muted small d-block">B S (kasbon)</span><?= $t['kasbon'] > 0 ? e(formatRupiah($t['kasbon'])) : '-' ?></div>
            <div class="col-md-4"><span class="text-muted small d-block">Divisi</span><?= e($r['division'] ?: '-') ?></div>
            <div class="col-md-4"><span class="text-muted small d-block">Kota</span><?= e($r['city'] ?: '-') ?></div>
            <div class="col-md-4"><span class="text-muted small d-block">Tugas</span><?= e($r['task'] ?: '-') ?></div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="card-section-title mb-2">Pengeluaran</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" data-no-cards>
                <thead class="table-light">
                    <tr><th style="width:40px">No</th><th>Tanggal</th><th>Kategori</th><th>Keterangan</th><th class="text-end">Jumlah</th><th>Bukti</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $i => $it): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td class="text-nowrap"><?= e(formatTanggal($it['expense_date'])) ?></td>
                            <td><?= e($it['category_name'] ?? '-') ?></td>
                            <td><?= e($it['description']) ?></td>
                            <td class="text-end text-nowrap"><?= e(formatRupiah($it['amount'])) ?><?php if (!empty($it['amount_expr'])): ?><div class="small text-muted"><?= e($it['amount_expr']) ?></div><?php endif; ?></td>
                            <td><?= !empty($it['proof_file']) ? '<a href="' . e(fileUrl($it['proof_file'])) . '" target="_blank" rel="noopener"><i class="bi bi-paperclip"></i> Lihat</a>' : '<span class="text-muted">-</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr class="fw-bold"><td colspan="4" class="text-end">TOTAL PENGELUARAN</td><td class="text-end"><?= e(formatRupiah($t['total'])) ?></td><td></td></tr>
                    <tr><td colspan="4" class="text-end">TOTAL KASBON &minus; PENGELUARAN</td><td class="text-end"><?= e(formatRupiah($kbMinus)) ?></td><td></td></tr>
                    <?php if ($t['kasbon'] > 0): ?>
                        <tr><td colspan="4" class="text-end"><?= $t['net'] > 0 ? 'Kurang bayar (diteruskan ke Pengajuan Budget)' : 'Sisa kasbon (tidak diteruskan)' ?></td><td class="text-end"><?= e(formatRupiah(abs($t['net']))) ?></td><td></td></tr>
                    <?php endif; ?>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php if ($actions): ?>
<div class="card border-0 shadow-sm mb-3" id="aksi">
    <div class="card-body">
        <div class="card-section-title mb-2">Aksi</div>
        <?php
        if (!empty($actions['edit'])) { echo '<a href="' . BASE_URL . '/reimbursement/edit/' . $id . '" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a> '; }
        if (!empty($actions['submit'])) { $actionForm('submit', 'Ajukan', 'bi-send', 'btn-primary', 'Ajukan reimbursement ' . $r['reimb_number'] . ' untuk diperiksa?'); }
        if (!empty($actions['revise'])) { $actionForm('revise', 'Revisi', 'bi-arrow-counterclockwise', 'btn-outline-primary', 'Kembalikan ' . $r['reimb_number'] . ' ke Draft untuk direvisi? Catatan penolakan tetap tersimpan di riwayat.'); }
        if (!empty($actions['approve'])) { echo '<button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal"><i class="bi bi-check2-circle"></i> Setujui</button> '; }
        if (!empty($actions['reject'])) { echo '<button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal"><i class="bi bi-x-circle"></i> Tolak</button> '; }
        if (!empty($actions['delete'])) { $actionForm('delete', 'Hapus', 'bi-trash', 'btn-outline-danger', 'Hapus reimbursement ' . $r['reimb_number'] . '?'); }
        ?>
    </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="card-section-title mb-2">Riwayat</div>
        <?php if (empty($history)): ?><div class="text-muted small">Belum ada riwayat.</div><?php endif; ?>
        <ul class="list-unstyled mb-0">
            <?php foreach ($history as $hrow): ?>
                <li class="border-bottom py-2">
                    <span class="text-muted small"><?= e(date('d/m/Y H:i', strtotime($hrow['created_at']))) ?></span>
                    <strong><?= e($hrow['user_name'] ?? '-') ?></strong>
                    <span class="badge bg-light text-dark border"><?= e(activityLogActionLabel($hrow['action'])) ?></span>
                    <?php if ($hrow['old_status'] !== $hrow['new_status'] && $hrow['new_status']): ?>
                        <span class="small text-muted"><?= e(Reimbursement::statusLabel($hrow['old_status'])) ?> &rarr; <?= e(Reimbursement::statusLabel($hrow['new_status'])) ?></span>
                    <?php endif; ?>
                    <?php if ($hrow['notes']): ?><div class="small"><?= e($hrow['notes']) ?></div><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<?php if (!empty($actions['approve'])): ?>
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= e($postUrl('approve')) ?>" class="modal-content" id="approveForm">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="modal-header">
                <h5 class="modal-title">Setujui Reimbursement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <?php if ($t['net'] > 0): ?>
                    <p class="mb-3">Kurang bayar <strong><?= e(formatRupiah($t['net'])) ?></strong> (pengeluaran <?= e(formatRupiah($t['total'])) ?><?= $t['kasbon'] > 0 ? ' &minus; kasbon ' . e(formatRupiah($t['kasbon'])) : '' ?>) akan <strong>diteruskan otomatis ke Pengajuan Budget</strong> berstatus Draft. Lengkapi data pembayarannya:</p>
                    <div class="mb-3">
                        <label class="form-label">Rekening Sumber <span class="text-danger">*</span></label>
                        <select name="source_bank_id" class="form-select" required>
                            <option value="">-- Pilih --</option>
                            <?php foreach ($banks as $b): ?><option value="<?= (int) $b['id'] ?>"><?= e(MasterBank::reportLabel($b)) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jenis Transaksi <span class="text-danger">*</span></label>
                        <select name="jenis_transaksi" class="form-select" required>
                            <option value="">-- Pilih --</option>
                            <?php foreach ($jenisList as $j): ?><option value="<?= e($j) ?>"><?= e($j) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rekening Tujuan <span class="text-danger">*</span></label>
                        <select name="tujuan_id" id="rbTujuanSel" class="form-select">
                            <option value="">-- Pilih Rekening Tujuan --</option>
                            <?php foreach ($tujuanList as $tj): ?><option value="<?= (int) $tj['id'] ?>"><?= e(MasterRekeningTujuan::label($tj)) ?></option><?php endforeach; ?>
                            <option value="manual">&#9998; Ketik manual...</option>
                        </select>
                        <input type="text" name="tujuan_manual" id="rbTujuanManual" class="form-control mt-2 d-none" maxlength="255" placeholder="Nama - Bank No. Rek (mis. <?= e($r['employee_name']) ?> - BCA 1234567890)">
                    </div>
                <?php else: ?>
                    <p class="mb-3">Kasbon (<?= e(formatRupiah($t['kasbon'])) ?>) &ge; total pengeluaran (<?= e(formatRupiah($t['total'])) ?>): <strong>tidak ada kurang bayar</strong>, jadi reimbursement disetujui tanpa diteruskan ke Pengajuan Budget.</p>
                <?php endif; ?>
                <label class="form-label">Catatan pemeriksa <span class="text-muted small">(opsional)</span></label>
                <textarea name="review_note" class="form-control" rows="2" maxlength="500"></textarea>
                <div class="form-text mt-2">Persetujuan oleh salah satu pemeriksa sudah cukup dan <strong>tidak bisa diulang</strong>; setelah ini data reimbursement terkunci.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle"></i> Setujui<?= $t['net'] > 0 ? ' &amp; Teruskan' : '' ?></button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($actions['reject'])): ?>
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= e($postUrl('reject')) ?>" class="modal-content">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="modal-header">
                <h5 class="modal-title">Tolak Reimbursement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Reimbursement <strong><?= e($r['reimb_number']) ?></strong> ditolak dan <strong>tidak</strong> diteruskan ke Pengajuan Budget. Pengaju dapat merevisi lalu mengajukan ulang.</p>
                <label class="form-label">Alasan penolakan <span class="text-danger">*</span></label>
                <textarea name="review_note" class="form-control" rows="3" minlength="3" maxlength="500" required placeholder="Wajib diisi"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger"><i class="bi bi-x-circle"></i> Tolak</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.rb-confirm').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (form.dataset.confirmed) { return; }
            e.preventDefault();
            confirmAction(form.dataset.message, 'Ya, lanjutkan').then(function (ok) {
                if (ok) { form.dataset.confirmed = '1'; form.submit(); }
            });
        });
    });
    const sel = document.getElementById('rbTujuanSel'), man = document.getElementById('rbTujuanManual');
    if (sel && man) {
        sel.addEventListener('change', function () { man.classList.toggle('d-none', sel.value !== 'manual'); if (sel.value !== 'manual') { man.value = ''; } });
        document.getElementById('approveForm').addEventListener('submit', function (e) {
            if (!sel.value || (sel.value === 'manual' && !man.value.trim())) { e.preventDefault(); alert('Rekening Tujuan wajib dipilih / diisi.'); }
        });
    }
});
</script>
