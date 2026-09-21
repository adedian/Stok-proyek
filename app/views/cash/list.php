<?php
/** @var array $rows @var array $filters @var array $categories @var array $picOptions @var bool $scoped @var array $summary
 *  @var array|null $balances @var float|null $bankBalance @var bool $kasExempt @var string|null $kasPicName
 *  @var bool $kasProjectGated @var string|null $kasProjectName
 *  @var array $projectOptions @var array $bankOptions @var array $rekeningOptions @var bool $canBank */
$balances   = $balances ?? null;
$balanceShowTotal = $balanceShowTotal ?? false;
$bankBalance = $bankBalance ?? null;
$kasExempt  = $kasExempt ?? true;
$kasPicName = $kasPicName ?? null;
$kasProjectGated = $kasProjectGated ?? false;
$kasProjectName  = $kasProjectName ?? null;
$projectOptions = $projectOptions ?? [];
$bankOptions = $bankOptions ?? [];
$rekeningOptions = $rekeningOptions ?? [];
$canBank = $canBank ?? false;
$selectedProjectIds = array_map('strval', $filters['project_ids'] ?? []);
$selectedBankIds = array_map('strval', $filters['bank_ids'] ?? []);
$selectedRekeningIds = array_map('strval', $filters['rekening_ids'] ?? []);
$canCetakVoucher = can('cash', 'print_voucher'); // Super Admin & Accounting saja
$canCetakBankVoucher = can('bank', 'view'); // Super Admin & Accounting saja (sama populasi dgn cash.print_voucher)
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Kas<?= $canBank ? '/Bank' : '' ?></h4>
        <small class="text-muted">
            Catatan kas masuk &amp; kas keluar
            <?php if ($scoped): ?><span class="badge bg-light text-dark border ms-1">PIC terkait Anda</span><?php endif; ?>
        </small>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <?php if ($kasProjectGated && $kasProjectName !== null): ?>
            <span class="text-muted small">
                <i class="bi bi-shield-check"></i> Sesi Kas &mdash; Project: <strong><?= e($kasProjectName) ?></strong>
            </span>
            <form method="POST" action="<?= BASE_URL ?>/index.php?module=cash&action=kasProjectLogout" class="d-inline">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-arrow-right"></i> Keluar Kas</button>
            </form>
        <?php elseif (!$kasExempt && $kasPicName !== null): ?>
            <span class="text-muted small">
                <i class="bi bi-shield-check"></i> Sesi Kas: <strong><?= e($kasPicName) ?></strong>
            </span>
            <form method="POST" action="<?= BASE_URL ?>/index.php?module=cash&action=kasLogout" class="d-inline">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-arrow-right"></i> Keluar Kas</button>
            </form>
        <?php endif; ?>
        <?php if ($canCetakVoucher): ?>
        <button type="button" id="kasCetakTerpilih" class="btn btn-outline-primary no-print" disabled>
            <i class="bi bi-printer"></i> Cetak Terpilih Kas <span class="badge text-bg-primary" id="kasCetakCount">0</span>
        </button>
        <?php endif; ?>
        <?php if ($canCetakBankVoucher): ?>
        <button type="button" id="bankCetakTerpilih" class="btn btn-outline-dark no-print" disabled>
            <i class="bi bi-printer"></i> Cetak Terpilih Bank <span class="badge text-bg-dark" id="bankCetakCount">0</span>
        </button>
        <?php endif; ?>
        <?php if (can('bank', 'create')): ?>
            <a href="<?= BASE_URL ?>/bank/create" class="btn btn-outline-dark">
                <i class="bi bi-bank"></i> Tambah Bank
            </a>
        <?php endif; ?>
        <?php if (can('cash', 'create')): ?>
            <a href="<?= BASE_URL ?>/cash/create" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Tambah Kas
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($balances !== null || $bankBalance !== null): ?>
    <?php /* Kartu saldo Kas -- SELALU TERPISAH dari Saldo Bank (Revisi lanjutan
             poin 9-14). Super Admin/Accounting: semua divisi + Total, atau satu
             angka "Sesuai Filter" bila Project/Rekening sedang dipilih. Role lain:
             hanya saldo divisi sendiri (cash.view_balance), tidak berubah oleh filter. */ ?>
    <?php
        // Kartu Saldo Bank dirender TEPAT di sebelah kanan kartu Saldo Kas
        // pertama (Total Saldo Kas / Saldo Kas Sesuai Filter) -- bukan di
        // akhir baris -- makanya dicetak lewat closure kecil ini supaya bisa
        // disisipkan persis setelah kartu Kas pertama di setiap kondisi
        // (filtered / total / fallback), tanpa duplikasi markup.
        $bankBalFiltered = !empty($filters['project_ids']) || !empty($filters['bank_ids']) || !empty($filters['rekening_ids']);
        $bankCardRendered = false;
        $renderBankCard = function () use ($bankBalance, $bankBalFiltered, &$bankCardRendered) {
            if ($bankBalance === null) {
                return;
            }
            $bankCardRendered = true;
            ?>
            <div class="col-12 col-md-3">
                <div class="card border-0 shadow-sm h-100 bg-dark text-white">
                    <div class="card-body py-2">
                        <div class="small opacity-75">Saldo Bank<?= $bankBalFiltered ? ' (Sesuai Filter)' : '' ?></div>
                        <div class="fs-5 fw-bold"><?= formatRupiah($bankBalance) ?></div>
                    </div>
                </div>
            </div>
            <?php
        };
    ?>
    <div class="row g-2 mb-3">
        <?php if ($balances !== null && !empty($balances['filtered'])): ?>
            <div class="col-12 col-md-3">
                <div class="card border-0 shadow-sm h-100 bg-primary text-white">
                    <div class="card-body py-2">
                        <div class="small opacity-75">Saldo Kas (Sesuai Filter)</div>
                        <div class="fs-5 fw-bold"><?= formatRupiah($balances['total']) ?></div>
                    </div>
                </div>
            </div>
            <?php $renderBankCard(); ?>
        <?php elseif ($balances !== null): ?>
            <?php if ($balanceShowTotal): ?>
            <div class="col-12 col-md-3">
                <div class="card border-0 shadow-sm h-100 bg-primary text-white">
                    <div class="card-body py-2">
                        <div class="small opacity-75">Total Saldo Kas</div>
                        <div class="fs-5 fw-bold"><?= formatRupiah($balances['total']) ?></div>
                    </div>
                </div>
            </div>
            <?php $renderBankCard(); ?>
            <?php endif; ?>
            <?php foreach ($balances['rows'] as $b): ?>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body py-2">
                            <div class="text-muted small"><?= e($b['label']) ?></div>
                            <div class="fs-6 fw-bold <?= $b['saldo'] < 0 ? 'text-danger' : '' ?>"><?= formatRupiah($b['saldo']) ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!$bankCardRendered): ?>
            <?php $renderBankCard(); ?>
        <?php endif; ?>
    </div>

    <?php if ($balances !== null && empty($balances['filtered']) && $balanceShowTotal): ?>
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="text-muted small">Total Kas Masuk (filter)</div>
                    <div class="fs-6 fw-bold text-success"><?= formatRupiah($summary['masuk']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="text-muted small">Total Kas Keluar (filter)</div>
                    <div class="fs-6 fw-bold text-danger"><?= formatRupiah($summary['keluar']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-2">
                    <div class="text-muted small">Selisih Filter (Masuk &minus; Keluar)</div>
                    <div class="fs-6 fw-bold"><?= formatRupiah($summary['masuk'] - $summary['keluar']) ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; /* $balanceShowTotal (ringkasan filter, hanya mode non-filtered) */ ?>
<?php endif; /* $balances || $bankBalance */ ?>

<?php if (hasRole([ROLE_SUPER_ADMIN])): ?>
    <div class="d-flex justify-content-end mb-2">
        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rangeDeleteModal">
            <i class="bi bi-calendar-x"></i> Hapus per Rentang Tanggal
        </button>
    </div>
    <?php $rangeDeleteAction = 'cash/rangeDelete'; $rangeDeleteLabel = 'Kas';
          require ROOT_PATH . '/app/views/partials/range_delete_modal.php'; ?>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/cash" class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label small text-muted mb-1">Cari</label>
                <input type="text" name="keyword" class="form-control form-control-sm"
                       value="<?= e($filters['keyword'] ?? '') ?>" placeholder="no bukti / PIC / uraian (mis. ad 01)">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Dari Tanggal</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from']) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Sampai Tanggal</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to']) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1">PIC</label>
                <?php if (!empty($picOptions)): ?>
                    <select name="pic" class="form-select form-select-sm">
                        <option value="">Semua PIC</option>
                        <?php foreach ($picOptions as $p): ?>
                            <option value="<?= e($p) ?>" <?= $filters['pic'] === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="text" name="pic" class="form-control form-control-sm" value="<?= e($filters['pic']) ?>" placeholder="Nama PIC">
                <?php endif; ?>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Kategori</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (string) $filters['category_id'] === (string) $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Mutasi</label>
                <select name="mutasi" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="masuk"  <?= $filters['mutasi'] === 'masuk' ? 'selected' : '' ?>>Masuk</option>
                    <option value="keluar" <?= $filters['mutasi'] === 'keluar' ? 'selected' : '' ?>>Keluar</option>
                </select>
            </div>
            <div class="col-12 col-md-1 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-search"></i></button>
                <a href="<?= BASE_URL ?>/cash" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
            </div>

            <?php if ($canBank): ?>
            <div class="col-12"><hr class="my-1"></div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1">Project (centang, kosong = semua)</label>
                <?php if ($kasProjectGated): ?>
                    <input type="text" class="form-control form-control-sm" value="<?= e($projectOptions[0]['project_name'] ?? '-') ?>" disabled>
                    <input type="hidden" name="project_ids[]" value="<?= (int) ($projectOptions[0]['id'] ?? 0) ?>">
                <?php else: ?>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle w-100 text-start" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                            <?= count($selectedProjectIds) ? count($selectedProjectIds) . ' Project dipilih' : 'Semua Project' ?>
                        </button>
                        <div class="dropdown-menu p-2" style="max-height:260px; overflow:auto; min-width:240px;">
                            <?php foreach ($projectOptions as $p): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="project_ids[]" value="<?= (int) $p['id'] ?>"
                                           id="listProj<?= (int) $p['id'] ?>" <?= in_array((string) $p['id'], $selectedProjectIds, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="listProj<?= (int) $p['id'] ?>"><?= e($p['project_name']) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1">Bank (centang, kosong = semua)</label>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle w-100 text-start" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                        <?= count($selectedBankIds) ? count($selectedBankIds) . ' Bank dipilih' : 'Semua Bank' ?>
                    </button>
                    <div class="dropdown-menu p-2" style="max-height:260px; overflow:auto; min-width:240px;">
                        <?php if (empty($bankOptions)): ?>
                            <div class="text-muted small px-2">Belum ada Master Bank.</div>
                        <?php endif; ?>
                        <?php foreach ($bankOptions as $b): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="bank_ids[]" value="<?= (int) $b['id'] ?>"
                                       id="listBank<?= (int) $b['id'] ?>" <?= in_array((string) $b['id'], $selectedBankIds, true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="listBank<?= (int) $b['id'] ?>"><?= e($b['bank_name']) ?> (<?= e(strtoupper($b['jenis'])) ?>)</label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1">Rekening (centang, kosong = semua)</label>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle w-100 text-start" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                        <?= count($selectedRekeningIds) ? count($selectedRekeningIds) . ' Rekening dipilih' : 'Semua Rekening' ?>
                    </button>
                    <div class="dropdown-menu p-2" style="max-height:260px; overflow:auto; min-width:240px;">
                        <?php if (empty($rekeningOptions)): ?>
                            <div class="text-muted small px-2">Belum ada Master Rekening.</div>
                        <?php endif; ?>
                        <?php foreach ($rekeningOptions as $r): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="rekening_ids[]" value="<?= (int) $r['id'] ?>"
                                       id="listRek<?= (int) $r['id'] ?>" <?= in_array((string) $r['id'], $selectedRekeningIds, true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="listRek<?= (int) $r['id'] ?>"><?= e($r['nama_rekening']) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <?php if ($canCetakVoucher || $canCetakBankVoucher): ?>
                        <th class="no-print text-center" style="width:38px;">
                            <input type="checkbox" id="kasSelectAll" class="form-check-input" title="Pilih semua">
                        </th>
                        <?php endif; ?>
                        <th style="width:44px;">No</th>
                        <th>Tanggal</th>
                        <th>PIC</th>
                        <th>No Bukti</th>
                        <th>Kategori</th>
                        <th>Mutasi</th>
                        <th class="text-end">Nominal (Rp)</th>
                        <th>Dibuat Oleh</th>
                        <th>Validasi</th>
                        <th class="text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        $valBadge = [
                            'menunggu'    => ['warning text-dark', 'Menunggu'],
                            'tervalidasi' => ['success', 'Tervalidasi'],
                            'ditolak'     => ['danger', 'Ditolak'],
                        ];
                    ?>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="<?= ($canCetakVoucher || $canCetakBankVoucher) ? 11 : 10 ?>" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-cash-coin empty-icon"></i>
                                <div class="empty-title">Belum ada transaksi Kas<?= $canBank ? '/Bank' : '' ?></div>
                                <div class="empty-desc">Catat kas masuk atau kas keluar untuk mulai.</div>
                                <?php if (can('cash', 'create')): ?>
                                    <a href="<?= BASE_URL ?>/cash/create" class="btn btn-sm btn-primary">
                                        <i class="bi bi-plus-circle"></i> Tambah Kas
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $i => $r): ?>
                        <?php $isBank = ($r['source'] ?? 'kas') === 'bank'; ?>
                        <tr>
                            <?php if ($canCetakVoucher || $canCetakBankVoucher): ?>
                            <td class="no-print text-center">
                                <?php if (!$isBank && $canCetakVoucher): ?>
                                    <input type="checkbox" class="form-check-input kas-row-check"
                                           value="<?= (int) $r['id'] ?>" data-label="<?= e($r['no_bukti']) ?>">
                                <?php elseif ($isBank && $canCetakBankVoucher): ?>
                                    <input type="checkbox" class="form-check-input bank-row-check"
                                           value="<?= (int) $r['id'] ?>" data-label="<?= e($r['no_bukti']) ?>">
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td><?= $i + 1 ?></td>
                            <td><?= formatTanggal($r['trx_date']) ?></td>
                            <td><?= e($r['pic'] ?: '-') ?></td>
                            <td><?= e($r['no_bukti']) ?></td>
                            <td><?= e($r['category_name']) ?></td>
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
                                <?php if ($isBank): ?>
                                    <span class="badge bg-light text-dark border">Bank</span>
                                <?php else: ?>
                                    <?php [$vc, $vl] = $valBadge[$r['validation_status'] ?? 'menunggu'] ?? ['secondary', $r['validation_status'] ?? '-']; ?>
                                    <span class="badge bg-<?= $vc ?>"><?= $vl ?></span>
                                    <?php if (($r['validation_status'] ?? '') === 'ditolak' && !empty($r['validation_note'])): ?>
                                        <div class="small fst-italic text-danger mt-1" style="max-width: 16rem;">
                                            <i class="bi bi-chat-left-quote"></i> <?= e($r['validation_note']) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-center no-print">
                                <?php if ($isBank): ?>
                                    <?php if ($canCetakBankVoucher || can('bank', 'edit') || can('bank', 'delete')): ?>
                                    <div class="dropdown row-actions">
                                        <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <?php if ($canCetakBankVoucher): ?>
                                            <li>
                                                <a class="dropdown-item" href="<?= BASE_URL ?>/index.php?module=bank&action=printVoucher&ids=<?= (int) $r['id'] ?>" target="_blank">
                                                    <i class="bi bi-printer"></i> Cetak
                                                </a>
                                            </li>
                                            <?php endif; ?>
                                            <?php if (can('bank', 'edit')): ?>
                                            <li>
                                                <a class="dropdown-item" href="<?= BASE_URL ?>/bank/edit/<?= (int) $r['id'] ?>">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                            </li>
                                            <?php endif; ?>
                                            <?php if (can('bank', 'delete')): ?>
                                            <li>
                                                <form method="POST" action="<?= BASE_URL ?>/index.php?module=bank&action=delete"
                                                      class="js-confirm-delete" data-message="Hapus transaksi Bank <?= e($r['no_bukti']) ?> ke Tempat Sampah?">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash"></i> Hapus</button>
                                                </form>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php $rowLocked = isPeriodClosed('cash', $r['trx_date']); ?>
                                    <?php if ($rowLocked): ?>
                                        <span class="badge bg-secondary" title="Periode ditutup -- transaksi terkunci">
                                            <i class="bi bi-lock-fill"></i> Terkunci
                                        </span>
                                    <?php elseif (can('cash', 'edit') || can('cash', 'delete')): ?>
                                    <div class="dropdown row-actions">
                                        <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <?php if (can('cash', 'edit')): ?>
                                            <li>
                                                <a class="dropdown-item" href="<?= BASE_URL ?>/cash/edit/<?= (int) $r['id'] ?>">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                            </li>
                                            <?php endif; ?>
                                            <?php if (can('cash', 'delete')): ?>
                                            <li>
                                                <form method="POST" action="<?= BASE_URL ?>/index.php?module=cash&action=delete"
                                                      class="js-confirm-delete" data-message="Hapus transaksi Kas <?= e($r['no_bukti']) ?> ke Tempat Sampah?">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash"></i> Hapus</button>
                                                </form>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-confirm-delete').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            confirmAction(form.dataset.message, 'Ya, hapus').then(function (ok) {
                if (ok) form.submit();
            });
        });
    });

    // ---- Cetak Terpilih Kas (BUKTI KAS KELUAR/MASUK) + Cetak Terpilih Bank
    // (BUKTI BANK KELUAR/MASUK) -- SATU kolom checkbox bersama (tiap baris
    // cuma render salah satu class sesuai sumbernya, lihat markup di atas),
    // tapi counter & tombol cetak TERPISAH karena beda template/endpoint.
    var kasBtn      = document.getElementById('kasCetakTerpilih');
    var kasCountEl  = document.getElementById('kasCetakCount');
    var bankBtn     = document.getElementById('bankCetakTerpilih');
    var bankCountEl = document.getElementById('bankCetakCount');

    var checkedKasIds = function () {
        return Array.prototype.filter.call(document.querySelectorAll('.kas-row-check'), function (b) { return b.checked; })
            .map(function (b) { return b.value; });
    };
    var checkedBankIds = function () {
        return Array.prototype.filter.call(document.querySelectorAll('.bank-row-check'), function (b) { return b.checked; })
            .map(function (b) { return b.value; });
    };
    var refresh = function () {
        if (kasBtn) {
            var kn = checkedKasIds().length;
            kasCountEl.textContent = String(kn);
            kasBtn.disabled = (kn === 0);
        }
        if (bankBtn) {
            var bn = checkedBankIds().length;
            bankCountEl.textContent = String(bn);
            bankBtn.disabled = (bn === 0);
        }
    };

    if (kasBtn || bankBtn) {
        if (window.wireSelectAllCheckbox) {
            wireSelectAllCheckbox('#kasSelectAll', '.kas-row-check, .bank-row-check', refresh);
        } else {
            var sa = document.getElementById('kasSelectAll');
            var allRows = function () { return document.querySelectorAll('.kas-row-check, .bank-row-check'); };
            if (sa) {
                sa.addEventListener('change', function () {
                    allRows().forEach(function (c) { c.checked = sa.checked; });
                    refresh();
                });
            }
            document.addEventListener('change', function (e) {
                if (e.target && e.target.matches && e.target.matches('.kas-row-check, .bank-row-check')) {
                    var all = allRows();
                    var n = checkedKasIds().length + checkedBankIds().length;
                    if (sa) { sa.checked = n === all.length && n > 0; sa.indeterminate = n > 0 && n < all.length; }
                    refresh();
                }
            });
        }
        if (kasBtn) {
            kasBtn.addEventListener('click', function () {
                var ids = checkedKasIds();
                if (ids.length === 0) {
                    if (window.notifyError) { notifyError('Silakan pilih minimal satu transaksi Kas untuk dicetak.'); }
                    else { alert('Silakan pilih minimal satu transaksi Kas untuk dicetak.'); }
                    return;
                }
                window.open('<?= BASE_URL ?>/index.php?module=cash&action=printVoucher&ids=' + encodeURIComponent(ids.join(',')), '_blank');
            });
        }
        if (bankBtn) {
            bankBtn.addEventListener('click', function () {
                var ids = checkedBankIds();
                if (ids.length === 0) {
                    if (window.notifyError) { notifyError('Silakan pilih minimal satu transaksi Bank untuk dicetak.'); }
                    else { alert('Silakan pilih minimal satu transaksi Bank untuk dicetak.'); }
                    return;
                }
                window.open('<?= BASE_URL ?>/index.php?module=bank&action=printVoucher&ids=' + encodeURIComponent(ids.join(',')), '_blank');
            });
        }
        refresh();
    }
});
</script>
