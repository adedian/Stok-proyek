<?php
/** @var array $rows @var string $status @var array $filters @var array $projects @var bool $canValidate */
$statusTabs = ['menunggu' => 'Menunggu', 'tervalidasi' => 'Tervalidasi', 'ditolak' => 'Ditolak', 'semua' => 'Semua'];
$valBadge = [
    'menunggu'    => ['warning text-dark', 'Menunggu'],
    'tervalidasi' => ['success', 'Tervalidasi'],
    'ditolak'     => ['danger', 'Ditolak'],
];
$modals = ''; // dikumpulkan lalu dirender DI LUAR <table> (form dalam <tbody> rusak oleh parser)
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Validasi Pengeluaran Barang</h4>
        <small class="text-muted">Persetujuan Pengeluaran Barang <strong>Project</strong>. Pengeluaran tanpa Project (mis. ke Client) tidak perlu divalidasi dan tidak tampil di sini.</small>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <ul class="nav nav-pills mb-3 gap-1">
            <?php foreach ($statusTabs as $key => $label): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $status === $key ? 'active' : '' ?>"
                       href="<?= BASE_URL ?>/stock_out_validation?status=<?= $key ?>"><?= $label ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
        <form method="GET" action="<?= BASE_URL ?>/stock_out_validation" class="row g-2 align-items-end">
            <input type="hidden" name="status" value="<?= e($status) ?>">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Cari (No / Barang / Tujuan / PIC)</label>
                <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword']) ?>" placeholder="ketik potongan kata...">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Project</label>
                <select name="project_id" class="form-select form-select-sm">
                    <option value="">-- Semua Project --</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= (string) $filters['project_id'] === (string) $p['id'] ? 'selected' : '' ?>><?= e($p['project_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Dari Tanggal</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from']) ?>">
            </div>
            <div class="col-md-2">
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
            <table class="table table-hover align-middle mb-0 entry-cards">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px;">No</th>
                        <th>No Pengeluaran</th>
                        <th>Tanggal</th>
                        <th>Tujuan</th>
                        <th>Barang</th>
                        <th class="text-end">Qty</th>
                        <th>Satuan</th>
                        <th>PIC</th>
                        <th>Dibuat Oleh</th>
                        <th>Status Validasi</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="11" class="text-center text-muted py-4">Tidak ada pengeluaran barang pada tampilan ini.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $i => $r): ?>
                        <?php
                            $canThis = $canValidate && $r['validation_status'] === 'menunggu';
                            [$vc, $vl] = $valBadge[$r['validation_status']] ?? ['secondary', $r['validation_status']];
                            $tujuan = $r['destination_type'] === 'client'
                                ? 'Client: ' . ($r['client_name'] ?? $r['destination'])
                                : ($r['project_name'] ?? $r['destination']);
                        ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td class="fw-semibold"><?= e($r['stock_out_number'] ?? '-') ?></td>
                            <td><?= formatTanggal($r['out_date']) ?></td>
                            <td><?= e($tujuan) ?><div class="text-muted small"><?= e($r['destination']) ?></div></td>
                            <td><?= e($r['item_name']) ?></td>
                            <td class="text-end"><?= number_format((float) $r['qty'], 2, ',', '.') ?></td>
                            <td><?= e($r['unit']) ?></td>
                            <td><?= e($r['pic_name']) ?></td>
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
                                <?php else: ?>
                                    <span class="text-muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($canThis): ?>
                            <?php ob_start(); ?>
                            <div class="modal fade" id="reviewModal<?= (int) $r['id'] ?>" tabindex="-1">
                              <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
                                <div class="modal-content">
                                  <form method="POST" action="<?= BASE_URL ?>/index.php?module=stock_out_validation&action=validate">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <div class="modal-header">
                                      <h6 class="modal-title">Tinjau Pengeluaran: <?= e($r['stock_out_number'] ?? '-') ?></h6>
                                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                      <div class="row g-2 small mb-3">
                                        <div class="col-sm-4"><span class="text-muted">Tanggal</span><br><?= formatTanggal($r['out_date']) ?></div>
                                        <div class="col-sm-4"><span class="text-muted">PIC</span><br><?= e($r['pic_name']) ?></div>
                                        <div class="col-sm-4"><span class="text-muted">Tujuan</span><br><?= e($tujuan) ?></div>
                                        <div class="col-sm-4"><span class="text-muted">Barang</span><br><?= e($r['item_name']) ?></div>
                                        <div class="col-sm-4"><span class="text-muted">Qty</span><br><?= number_format((float) $r['qty'], 2, ',', '.') ?> <?= e($r['unit']) ?></div>
                                        <div class="col-sm-4"><span class="text-muted">Dibuat oleh</span><br><?= e($r['created_by_name'] ?? '-') ?></div>
                                        <?php if (!empty($r['notes'])): ?>
                                        <div class="col-12"><span class="text-muted">Catatan</span><br><?= e($r['notes']) ?></div>
                                        <?php endif; ?>
                                      </div>
                                      <div class="mb-2">
                                        <label class="form-label">Keputusan</label>
                                        <div class="d-flex gap-3">
                                          <div class="form-check">
                                            <input class="form-check-input" type="radio" name="decision" value="tervalidasi"
                                                   id="dec-ok-<?= (int) $r['id'] ?>" required>
                                            <label class="form-check-label" for="dec-ok-<?= (int) $r['id'] ?>">Setujui (Valid)</label>
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
