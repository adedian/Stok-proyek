<?php
/** @var array $banks @var array $rekeningOptions @var array $picOptions @var string $myPicName */
$banks = $banks ?? [];
$rekeningOptions = $rekeningOptions ?? [];
$picOptions = $picOptions ?? [];
$myPicName = $myPicName ?? '';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Transfer Bank ke Kas</h4>
        <small class="text-muted">Kirim dana dari Bank ke Kas milik PIC lain -- otomatis tercatat di kedua sisi (Bank Keluar &amp; Kas Masuk).</small>
    </div>
    <a href="<?= BASE_URL ?>/cash" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=cash&action=transferStore" id="transferForm">
    <?= csrfField() ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="trx_date" class="form-control" value="<?= e(date('Y-m-d')) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Bank Sumber <span class="text-danger">*</span></label>
                    <select name="bank_id" class="form-select" required>
                        <option value="">-- Pilih Bank --</option>
                        <?php foreach ($banks as $b): ?>
                            <option value="<?= (int) $b['id'] ?>"><?= e($b['bank_name']) ?> (<?= e(strtoupper($b['jenis'])) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Rekening</label>
                    <select name="rekening_id" class="form-select">
                        <option value="">-- Tanpa Rekening --</option>
                        <?php foreach ($rekeningOptions as $r): ?>
                            <option value="<?= (int) $r['id'] ?>"><?= e($r['nama_rekening']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Nominal <span class="text-danger">*</span></label>
                    <input type="text" name="amount" class="form-control currency-input" inputmode="numeric" placeholder="0" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Dari PIC (pengirim)</label>
                    <select name="from_pic" class="form-select">
                        <option value="">-- Tanpa PIC --</option>
                        <?php foreach ($picOptions as $p): ?>
                            <option value="<?= e($p) ?>" <?= $myPicName === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Tampil sebagai keterangan di sisi Bank Keluar. Opsional.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Ke Kas (PIC tujuan) <span class="text-danger">*</span></label>
                    <select name="to_pic" class="form-select" required>
                        <option value="">-- Pilih PIC Tujuan --</option>
                        <?php foreach ($picOptions as $p): ?>
                            <option value="<?= e($p) ?>"><?= e($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">PIC tujuan wajib sudah punya Prefix Kas (Master Data &rarr; PIC Kas).</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Catatan</label>
                    <input type="text" name="notes" class="form-control" placeholder="Opsional">
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info small">
        <i class="bi bi-info-circle"></i>
        Simpan akan membuat 2 transaksi sekaligus: <strong>Bank Keluar</strong> (sisi pengirim) dan
        <strong>Kas Masuk</strong> (sisi PIC tujuan), dengan nominal &amp; tanggal yang sama, saling tertaut.
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-left-right"></i> Simpan Transfer</button>
        <a href="<?= BASE_URL ?>/cash" class="btn btn-light border">Batal</a>
    </div>
</form>
