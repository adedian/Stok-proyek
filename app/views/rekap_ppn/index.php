<?php
/**
 * Rekap PPN -- ringkasan setoran per masa + tab PPN Masukan | PPN Keluaran.
 * Variabel: $f (tahun, masa, q), $tab, $years, $summary, $masukan, $keluaran
 */
$canCreate = can('rekap_ppn', 'create');
$canEdit = can('rekap_ppn', 'edit');
$canDelete = can('rekap_ppn', 'delete');
$canExport = can('rekap_ppn', 'export');
$nf = fn($v) => number_format((float) $v, 2, '.', ',');
$rp = fn($v) => (float) $v < 0 ? '(' . number_format(abs((float) $v), 2, '.', ',') . ')' : number_format((float) $v, 2, '.', ',');
$qty = fn($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ','), '0'), '.');
$periodLabel = $f['masa'] > 0 ? RekapPpn::monthName($f['masa']) . ' ' . $f['tahun'] : 'Seluruh Tahun ' . $f['tahun'];
$qs = fn(array $extra = []) => http_build_query(array_filter(['tahun' => $f['tahun'], 'masa' => $f['masa'], 'q' => $f['q']] + $extra, fn($v) => $v !== '' && $v !== null));
$s = $summary;
$setor = (float) $s['setor'];
$confirmForm = function (string $action, int $id, string $msg) {
    return '<form method="POST" action="' . e(BASE_URL . '/index.php?module=rekap_ppn&action=' . $action) . '" class="d-inline" onsubmit="return confirm(' . e(json_encode($msg)) . ');">'
        . csrfField() . '<input type="hidden" name="id" value="' . $id . '">'
        . '<button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button></form>';
};
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Rekap PPN</h4>
        <small class="text-muted">PPN Masukan &amp; PPN Keluaran dalam satu menu &mdash; periode <strong><?= e($periodLabel) ?></strong>. Diisi manual, tidak terhubung ke PO / Invoice / Kas.</small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($canExport): ?>
            <a href="<?= BASE_URL ?>/index.php?module=rekap_ppn&action=exportExcel&<?= e($qs()) ?>" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
        <?php endif; ?>
        <?php if ($canCreate): ?>
            <a href="<?= BASE_URL ?>/rekap_ppn/<?= $tab === 'keluaran' ? 'createKeluaran' : 'createMasukan' ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Tambah PPN <?= $tab === 'keluaran' ? 'Keluaran' : 'Masukan' ?>
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/rekap_ppn" class="row g-2 align-items-end">
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Tahun</label>
                <select name="tahun" class="form-select form-select-sm">
                    <?php foreach ($years as $y): ?>
                        <option value="<?= (int) $y ?>" <?= (int) $y === $f['tahun'] ? 'selected' : '' ?>><?= (int) $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Masa</label>
                <select name="masa" class="form-select form-select-sm">
                    <option value="0" <?= $f['masa'] === 0 ? 'selected' : '' ?>>Semua masa</option>
                    <?php foreach (RekapPpn::MONTHS as $num => $name): ?>
                        <option value="<?= $num ?>" <?= $f['masa'] === $num ? 'selected' : '' ?>><?= e($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-5">
                <label class="form-label small text-muted mb-1">Cari (faktur / nama / NPWP / invoice / barang / project)</label>
                <input type="text" name="q" class="form-control form-control-sm" value="<?= e($f['q']) ?>" placeholder="mis. Honoris atau 0400260">
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100" title="Terapkan"><i class="bi bi-search"></i> Terapkan</button>
                <a href="<?= BASE_URL ?>/rekap_ppn?tab=<?= e($tab) ?>" class="btn btn-sm btn-outline-secondary w-100" title="Reset ke masa terbaru"><i class="bi bi-x-circle"></i> Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Ringkasan setoran (blok "HME" di bawah sheet PPN Masukan) -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="card-section-title mb-3">Ringkasan PPN &mdash; <?= e($periodLabel) ?></div>
        <div class="row g-3">
            <div class="col-lg-7">
                <table class="table table-sm mb-0 align-middle">
                    <tbody>
                        <tr>
                            <td>Pajak Keluaran <small class="text-muted">(<?= (int) $s['keluaran']['n'] ?> faktur)</small></td>
                            <td class="text-end"><?= e($nf($s['keluaran']['ppn'])) ?></td>
                        </tr>
                        <tr>
                            <td>Pajak Masukan <small class="text-muted">(<?= (int) $s['masukan']['n'] ?> faktur)</small></td>
                            <td class="text-end">&minus; <?= e($nf($s['masukan']['ppn'])) ?></td>
                        </tr>
                        <tr class="fw-semibold border-top">
                            <td>PPN <?= $s['selisih'] >= 0 ? 'Kurang Bayar' : 'Lebih Bayar' ?> <small class="text-muted fw-normal">(Keluaran &minus; Masukan)</small></td>
                            <td class="text-end"><?= e($rp($s['selisih'])) ?></td>
                        </tr>
                        <?php if ($f['masa'] > 0): ?>
                            <tr>
                                <td>Lebih Bayar masa sebelumnya <small class="text-muted">(kompensasi)</small></td>
                                <td class="text-end">&minus; <?= e($nf($s['carry'])) ?></td>
                            </tr>
                            <tr class="fw-bold border-top">
                                <td><?= $setor >= 0 ? 'PPN yang harus disetor' : 'Lebih bayar (dibawa ke masa berikutnya)' ?></td>
                                <td class="text-end fs-5 <?= $setor > 0 ? 'text-danger' : 'text-success' ?>"><?= e($rp($setor)) ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($f['masa'] === 0): ?>
                    <div class="form-text">Pilih satu masa untuk menghitung kompensasi dan jumlah yang harus disetor.</div>
                <?php endif; ?>
            </div>
            <div class="col-lg-5">
                <?php if ($f['masa'] > 0): ?>
                    <?php $period = $s['period']; ?>
                    <?php if ($canEdit): ?>
                        <form method="POST" action="<?= BASE_URL ?>/index.php?module=rekap_ppn&action=savePeriod" data-no-draft>
                            <?= csrfField() ?>
                            <input type="hidden" name="tahun" value="<?= (int) $f['tahun'] ?>">
                            <input type="hidden" name="masa" value="<?= (int) $f['masa'] ?>">
                            <input type="hidden" name="tab" value="<?= e($tab) ?>">
                            <label class="form-label small mb-1">Lebih Bayar masa sebelumnya <span class="text-muted">(mis. lebih bayar masa Mei)</span></label>
                            <input type="text" name="lebih_bayar" class="form-control form-control-sm text-end currency-input mb-2" inputmode="decimal"
                                   value="<?= (float) $period['lebih_bayar_sebelumnya'] > 0 ? e($nf($period['lebih_bayar_sebelumnya'])) : '' ?>" placeholder="0">
                            <label class="form-label small mb-1">Catatan masa ini</label>
                            <input type="text" name="period_notes" class="form-control form-control-sm mb-2" maxlength="300" value="<?= e($period['notes'] ?? '') ?>">
                            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-save"></i> Simpan Kompensasi</button>
                        </form>
                    <?php elseif (!empty($period['notes'])): ?>
                        <div class="small text-muted">Catatan masa ini</div>
                        <div><?= e($period['notes']) ?></div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'masukan' ? 'active' : '' ?>" href="<?= BASE_URL ?>/rekap_ppn?<?= e($qs(['tab' => 'masukan'])) ?>">
            <i class="bi bi-box-arrow-in-down"></i> PPN Masukan <span class="badge bg-secondary ms-1"><?= (int) $s['masukan']['n'] ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'keluaran' ? 'active' : '' ?>" href="<?= BASE_URL ?>/rekap_ppn?<?= e($qs(['tab' => 'keluaran'])) ?>">
            <i class="bi bi-box-arrow-up"></i> PPN Keluaran <span class="badge bg-secondary ms-1"><?= (int) $s['keluaran']['n'] ?></span>
        </a>
    </li>
</ul>

<?php if ($tab === 'masukan'): ?>
<?php $sum = ['dpp' => 0.0, 'dnl' => 0.0, 'ppn' => 0.0, 'total' => 0.0]; ?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:45px">No</th>
                        <th>NPWP</th>
                        <th>Nama</th>
                        <th>Faktur</th>
                        <th>Tgl</th>
                        <th class="text-center">Masa</th>
                        <th class="text-end">DPP</th>
                        <th class="text-end">DPP Nilai Lain</th>
                        <th class="text-end">PPN</th>
                        <th class="text-end">Jumlah</th>
                        <th>Project</th>
                        <th>Keterangan</th>
                        <th class="text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$masukan): ?>
                        <tr><td colspan="13" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-box-arrow-in-down empty-icon"></i>
                                <div class="empty-title"><?= $f['q'] !== '' ? 'Tidak ada faktur masukan yang sesuai pencarian.' : 'Belum ada faktur PPN Masukan pada periode ini.' ?></div>
                                <?php if ($canCreate): ?>
                                    <a href="<?= BASE_URL ?>/rekap_ppn/createMasukan" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle"></i> Tambah PPN Masukan</a>
                                <?php endif; ?>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($masukan as $n => $r):
                        $id = (int) $r['id'];
                        $sum['dpp'] += (float) $r['dpp']; $sum['dnl'] += (float) $r['dpp_nilai_lain']; $sum['ppn'] += (float) $r['ppn_amount']; $sum['total'] += (float) $r['total_amount']; ?>
                        <tr>
                            <td data-label="No" class="text-muted"><?= $n + 1 ?></td>
                            <td data-label="NPWP" class="text-nowrap"><?= e($r['npwp'] ?? '') ?></td>
                            <td data-label="Nama"><?= e($r['vendor_name']) ?></td>
                            <td data-label="Faktur" class="text-nowrap"><?= e($r['faktur_number']) ?></td>
                            <td data-label="Tgl" class="text-nowrap"><?= e(formatTanggal($r['faktur_date'])) ?></td>
                            <td data-label="Masa" class="text-center text-nowrap"><?= (int) $r['masa'] ?>/<?= (int) $r['tahun'] ?></td>
                            <td data-label="DPP" class="text-end text-nowrap"><?= e($nf($r['dpp'])) ?></td>
                            <td data-label="DPP Nilai Lain" class="text-end text-nowrap"><?= e($nf($r['dpp_nilai_lain'])) ?></td>
                            <td data-label="PPN" class="text-end text-nowrap"><?= e($nf($r['ppn_amount'])) ?></td>
                            <td data-label="Jumlah" class="text-end text-nowrap"><?= e($nf($r['total_amount'])) ?></td>
                            <td data-label="Project"><?= e($r['project_name'] ?? '') ?></td>
                            <td data-label="Keterangan"><?= e($r['notes'] ?? '') ?></td>
                            <td class="text-center no-print text-nowrap" data-label="Aksi">
                                <?php if ($canEdit): ?>
                                    <a href="<?= BASE_URL ?>/rekap_ppn/editMasukan/<?= $id ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                <?php endif; ?>
                                <?php if ($canDelete): echo $confirmForm('deleteMasukan', $id, 'Hapus faktur ' . $r['faktur_number'] . ' (' . $r['vendor_name'] . ')?'); endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if ($masukan): ?>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="6" class="text-end">TOTAL</td>
                        <td class="text-end text-nowrap"><?= e($nf($sum['dpp'])) ?></td>
                        <td class="text-end text-nowrap"><?= e($nf($sum['dnl'])) ?></td>
                        <td class="text-end text-nowrap"><?= e($nf($sum['ppn'])) ?></td>
                        <td class="text-end text-nowrap"><?= e($nf($sum['total'])) ?></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php else: ?>
<?php $sum = ['sub' => 0.0, 'dpp' => 0.0, 'dnl' => 0.0, 'ppn' => 0.0, 'total' => 0.0]; ?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:45px">No</th>
                        <th>Tanggal</th>
                        <th>Invoice</th>
                        <th>Faktur Pajak</th>
                        <th>Customer</th>
                        <th>NPWP</th>
                        <th>Nama Barang</th>
                        <th class="text-end">DPP</th>
                        <th class="text-end">DPP Nilai Lain</th>
                        <th class="text-end">PPN</th>
                        <th class="text-end">Total</th>
                        <th>Keterangan</th>
                        <th class="text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$keluaran): ?>
                        <tr><td colspan="13" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-box-arrow-up empty-icon"></i>
                                <div class="empty-title"><?= $f['q'] !== '' ? 'Tidak ada faktur keluaran yang sesuai pencarian.' : 'Belum ada faktur PPN Keluaran pada periode ini.' ?></div>
                                <?php if ($canCreate): ?>
                                    <a href="<?= BASE_URL ?>/rekap_ppn/createKeluaran" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle"></i> Tambah PPN Keluaran</a>
                                <?php endif; ?>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($keluaran as $n => $k):
                        $id = (int) $k['id'];
                        $sum['sub'] += (float) $k['total_subtotal']; $sum['dpp'] += (float) $k['total_dpp']; $sum['dnl'] += (float) $k['total_dpp_nilai_lain'];
                        $sum['ppn'] += (float) $k['total_ppn']; $sum['total'] += (float) $k['total_amount']; ?>
                        <tr>
                            <td data-label="No" class="text-muted"><?= $n + 1 ?></td>
                            <td data-label="Tanggal" class="text-nowrap"><?= e(formatTanggal($k['entry_date'])) ?></td>
                            <td data-label="Invoice" class="text-nowrap"><?= e($k['invoice_number'] ?? '') ?></td>
                            <td data-label="Faktur Pajak" class="text-nowrap"><?= e($k['faktur_number'] ?? '') ?></td>
                            <td data-label="Customer"><?= e($k['customer_name']) ?></td>
                            <td data-label="NPWP" class="text-nowrap"><?= e($k['npwp'] ?? '') ?></td>
                            <td data-label="Nama Barang">
                                <?php foreach ($k['items'] as $it): ?>
                                    <div class="small"><?= e($it['item_name']) ?> <span class="text-muted">&times; <?= e($qty($it['qty'])) ?> @ <?= e($nf($it['unit_price'])) ?><?php
                                        $disc = RekapPpn::discountPercent($it['pricelist'], $it['unit_price']);
                                        echo $disc !== null && abs($disc) >= 0.01 ? ' &middot; diskon ' . e(rtrim(rtrim(number_format($disc, 2, '.', ''), '0'), '.')) . '%' : ''; ?></span></div>
                                <?php endforeach; ?>
                            </td>
                            <td data-label="DPP" class="text-end text-nowrap"><?= e($nf($k['total_dpp'])) ?></td>
                            <td data-label="DPP Nilai Lain" class="text-end text-nowrap"><?= e($nf($k['total_dpp_nilai_lain'])) ?></td>
                            <td data-label="PPN" class="text-end text-nowrap"><?= e($nf($k['total_ppn'])) ?></td>
                            <td data-label="Total" class="text-end text-nowrap"><?= e($nf($k['total_amount'])) ?></td>
                            <td data-label="Keterangan"><?= e($k['notes'] ?? '') ?></td>
                            <td class="text-center no-print text-nowrap" data-label="Aksi">
                                <?php if ($canEdit): ?>
                                    <a href="<?= BASE_URL ?>/rekap_ppn/editKeluaran/<?= $id ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                <?php endif; ?>
                                <?php if ($canDelete): echo $confirmForm('deleteKeluaran', $id, 'Hapus faktur keluaran ' . ($k['invoice_number'] ?: $k['customer_name']) . '?'); endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if ($keluaran): ?>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="7" class="text-end">TOTAL</td>
                        <td class="text-end text-nowrap"><?= e($nf($sum['dpp'])) ?></td>
                        <td class="text-end text-nowrap"><?= e($nf($sum['dnl'])) ?></td>
                        <td class="text-end text-nowrap"><?= e($nf($sum['ppn'])) ?></td>
                        <td class="text-end text-nowrap"><?= e($nf($sum['total'])) ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
<div class="form-text mt-2">
    DPP Nilai Lain = DPP &times; 11/12 &middot; PPN = DPP Nilai Lain &times; 12% (setara 11% dari DPP). Pada PPN Keluaran harga satuan sudah termasuk PPN 11%.
</div>
