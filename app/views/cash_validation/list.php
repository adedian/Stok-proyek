<?php
/** @var array $rows @var string $status @var array $filters @var array $divisions @var bool $canValidate */
$statusTabs = ['menunggu' => 'Menunggu', 'tervalidasi' => 'Tervalidasi', 'ditolak' => 'Ditolak', 'semua' => 'Semua'];
$valBadge = [
    'menunggu'    => ['warning text-dark', 'Menunggu'],
    'tervalidasi' => ['success', 'Tervalidasi'],
    'ditolak'     => ['danger', 'Ditolak'],
];
$userRole = currentUserRole();
$modals = ''; // dikumpulkan lalu dirender DI LUAR <table> (form dalam <tbody> rusak oleh parser)
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Validasi Kas</h4>
        <small class="text-muted">
            Persetujuan transaksi Kas
            <?php if ($divisions): ?>
                &middot; divisi Anda:
                <?php foreach ($divisions as $d): ?><span class="badge bg-light text-dark border"><?= e(kasDivisionLabel($d)) ?></span> <?php endforeach; ?>
            <?php endif; ?>
        </small>
    </div>
</div>

<?php if (!$divisions): ?>
    <div class="alert alert-info">
        Role Anda tidak berwenang memvalidasi transaksi Kas mana pun.
    </div>
<?php else: ?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <ul class="nav nav-pills mb-3 gap-1">
            <?php foreach ($statusTabs as $key => $label): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $status === $key ? 'active' : '' ?>"
                       href="<?= BASE_URL ?>/cash_validation?status=<?= $key ?>"><?= $label ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
        <form method="GET" action="<?= BASE_URL ?>/cash_validation" class="row g-2 align-items-end">
            <input type="hidden" name="status" value="<?= e($status) ?>">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Cari (No Bukti / PIC)</label>
                <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Dari Tanggal</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Sampai Tanggal</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to']) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-search"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px;">No</th>
                        <th>Tanggal</th>
                        <th>No Bukti</th>
                        <th>PIC</th>
                        <th>Divisi</th>
                        <th>Mutasi</th>
                        <th class="text-end">Nominal (Rp)</th>
                        <th>Dibuat Oleh</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">Tidak ada transaksi Kas pada tampilan ini.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $i => $r): ?>
                        <?php
                            $canThis = $canValidate && kasCanValidateDivision($userRole, $r['division']) && $r['validation_status'] === 'menunggu';
                            [$vc, $vl] = $valBadge[$r['validation_status']] ?? ['secondary', $r['validation_status']];
                        ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= formatTanggal($r['trx_date']) ?></td>
                            <td class="fw-semibold"><?= e($r['no_bukti']) ?></td>
                            <td><?= e($r['pic']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= e(kasDivisionLabel($r['division'])) ?></span></td>
                            <td>
                                <?php if ($r['mutasi'] === 'masuk'): ?>
                                    <span class="badge text-bg-success">Masuk</span>
                                <?php else: ?>
                                    <span class="badge text-bg-danger">Keluar</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-semibold"><?= formatRupiah($r['total_amount']) ?></td>
                            <td><?= e($r['created_by_name'] ?? '-') ?></td>
                            <td>
                                <span class="badge bg-<?= $vc ?>"><?= $vl ?></span>
                                <?php if ($r['validation_status'] !== 'menunggu'): ?>
                                    <div class="text-muted small mt-1">
                                        <?= e($r['validated_by_name'] ?? '-') ?>
                                        <?php if (!empty($r['validated_at'])): ?> &middot; <?= formatTanggal($r['validated_at']) ?><?php endif; ?>
                                    </div>
                                    <?php if (!empty($r['validation_note'])): ?>
                                        <div class="small fst-italic text-<?= $r['validation_status'] === 'ditolak' ? 'danger' : 'muted' ?>">
                                            &ldquo;<?= e($r['validation_note']) ?>&rdquo;
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($canThis): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal" data-bs-target="#reviewModal<?= (int) $r['id'] ?>">
                                        <i class="bi bi-clipboard-check"></i> Tinjau
                                    </button>
                                <?php elseif ($r['validation_status'] === 'menunggu'): ?>
                                    <span class="text-muted small" title="Bukan divisi Anda">&mdash;</span>
                                <?php else: ?>
                                    <span class="text-muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($canThis): ?>
                            <?php ob_start(); ?>
                            <div class="modal fade" id="reviewModal<?= (int) $r['id'] ?>" tabindex="-1">
                              <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                  <form method="POST" action="<?= BASE_URL ?>/index.php?module=cash_validation&action=validate">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <div class="modal-header">
                                      <h6 class="modal-title">Tinjau Kas: <?= e($r['no_bukti']) ?></h6>
                                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                      <div class="row g-2 small mb-3">
                                        <div class="col-sm-4"><span class="text-muted">Tanggal</span><br><?= formatTanggal($r['trx_date']) ?></div>
                                        <div class="col-sm-4"><span class="text-muted">PIC</span><br><?= e($r['pic']) ?></div>
                                        <div class="col-sm-4"><span class="text-muted">Divisi</span><br><?= e(kasDivisionLabel($r['division'])) ?></div>
                                        <div class="col-sm-4"><span class="text-muted">Mutasi</span><br><?= ucfirst($r['mutasi']) ?></div>
                                        <div class="col-sm-4"><span class="text-muted">Dibuat oleh</span><br><?= e($r['created_by_name'] ?? '-') ?></div>
                                        <div class="col-sm-4"><span class="text-muted">Total</span><br><strong><?= formatRupiah($r['total_amount']) ?></strong></div>
                                      </div>
                                      <div class="table-responsive mb-3">
                                        <table class="table table-sm mb-0">
                                          <thead class="table-light"><tr>
                                            <th>Uraian</th><th>Kategori</th><th class="text-end">Qty</th>
                                            <th class="text-end">Harga Satuan</th><th class="text-end">Jumlah</th>
                                          </tr></thead>
                                          <tbody>
                                          <?php foreach (($r['items'] ?? []) as $it): ?>
                                            <tr>
                                              <td><?= e($it['uraian']) ?></td>
                                              <td><?= e($it['category_name'] ?? '-') ?></td>
                                              <td class="text-end"><?= $it['qty'] !== null ? number_format((float) $it['qty'], 2, ',', '.') : '-' ?></td>
                                              <td class="text-end"><?= formatRupiah($it['satuan'] ?? 0) ?></td>
                                              <td class="text-end"><?= formatRupiah($it['jumlah'] ?? 0) ?></td>
                                            </tr>
                                          <?php endforeach; ?>
                                          </tbody>
                                        </table>
                                      </div>
                                      <div class="mb-2">
                                        <label class="form-label">Keputusan</label>
                                        <div class="d-flex gap-3">
                                          <div class="form-check">
                                            <input class="form-check-input" type="radio" name="decision" value="tervalidasi"
                                                   id="dec-ok-<?= (int) $r['id'] ?>" required>
                                            <label class="form-check-label" for="dec-ok-<?= (int) $r['id'] ?>">Setujui</label>
                                          </div>
                                          <div class="form-check">
                                            <input class="form-check-input" type="radio" name="decision" value="ditolak"
                                                   id="dec-no-<?= (int) $r['id'] ?>">
                                            <label class="form-check-label" for="dec-no-<?= (int) $r['id'] ?>">Tolak</label>
                                          </div>
                                        </div>
                                      </div>
                                      <div class="mb-0">
                                        <label class="form-label">Catatan <span class="text-muted small">(wajib jika menolak)</span></label>
                                        <textarea name="note" class="form-control" rows="2" maxlength="255"></textarea>
                                      </div>
                                    </div>
                                    <div class="modal-footer">
                                      <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                                      <button type="submit" class="btn btn-primary">Simpan Keputusan</button>
                                    </div>
                                  </form>
                                </div>
                              </div>
                            </div>
                            <?php $modals .= ob_get_clean(); ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $modals ?>

<?php endif; /* $divisions */ ?>
