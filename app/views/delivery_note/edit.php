<?php
$destType = $note['destination_type'] ?? 'project';
$sigIds = array_map(fn($s) => (int) $s['id'], $signatures);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Edit Surat Jalan</h4>
        <small class="text-muted"><?= e($note['delivery_number']) ?> &middot; <?= count($items) ?> baris Pengeluaran Barang</small>
    </div>
    <a href="<?= BASE_URL ?>/stock_out" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <form method="POST" action="<?= BASE_URL ?>/index.php?module=delivery_note&action=update" id="deliveryNoteForm">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= (int) $note['id'] ?>">

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">No. Surat Jalan <span class="text-danger">*</span></label>
                            <input type="text" name="delivery_number" class="form-control" maxlength="50" required
                                   autocomplete="off" value="<?= e($note['delivery_number']) ?>">
                            <div class="form-text">Harus unik (maks. 50 karakter).</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tanggal Surat Jalan</label>
                            <input type="date" name="delivery_date" class="form-control" value="<?= e($note['delivery_date']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tujuan Pengiriman</label>
                            <select name="destination_type" id="destinationType" class="form-select">
                                <option value="project" <?= $destType === 'project' ? 'selected' : '' ?>>Site/Project</option>
                                <option value="client" <?= $destType === 'client' ? 'selected' : '' ?>>Penjualan (Client)</option>
                                <option value="manual" <?= $destType === 'manual' ? 'selected' : '' ?>>Lainnya / Manual (isi bebas)</option>
                            </select>
                        </div>
                        <div class="col-12" id="projectDestWrap">
                            <label class="form-label">Project</label>
                            <select name="project_id" class="form-select">
                                <option value="">-- Pilih Project --</option>
                                <?php foreach ($projects as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>" <?= (int) ($note['project_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
                                        <?= e($p['project_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 d-none" id="clientDestWrap">
                            <label class="form-label">Client</label>
                            <select name="client_id" id="client_id" class="form-select">
                                <option value="">-- Pilih Client --</option>
                                <?php foreach ($clients as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>" <?= (int) ($note['client_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                                        <?= e($c['client_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nama Tujuan
                                <span class="text-danger d-none" id="destNameReq">*</span>
                                <span class="text-muted small">(bebas, mis. "Hotel Azana - Tulungagung")</span>
                            </label>
                            <input type="text" name="destination_name" id="destinationName" class="form-control" value="<?= e($note['destination_name'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Kota <span class="text-muted small">(untuk baris "Kota, Tanggal" di penutup Surat Jalan -- bukan nama project/client)</span></label>
                            <input type="text" name="city" class="form-control" value="<?= e($note['city'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">No. Kendaraan</label>
                            <input type="text" name="vehicle_number" class="form-control" value="<?= e($note['vehicle_number'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nama Driver</label>
                            <input type="text" name="driver_name" class="form-control" value="<?= e($note['driver_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Pengirim</label>
                            <input type="text" name="sender_name" class="form-control" value="<?= e($note['sender_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Penerima</label>
                            <input type="text" name="recipient_name" class="form-control" value="<?= e($note['recipient_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tanda Tangan HME</label>
                            <select name="signature_id" class="form-select">
                                <option value="">-- Tanpa Tanda Tangan Gambar --</option>
                                <?php foreach ($signatures as $s): ?>
                                    <option value="<?= (int) $s['id'] ?>" <?= (int) ($note['signature_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>>
                                        <?= e($s['name']) ?> &middot; <?= e($s['position']) ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if (!empty($note['signature_id']) && !in_array((int) $note['signature_id'], $sigIds, true)): ?>
                                    <option value="<?= (int) $note['signature_id'] ?>" selected>
                                        <?= e($note['signature_name'] ?? '') ?> &middot; <?= e($note['signature_position'] ?? '') ?> (nonaktif)
                                    </option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Catatan</label>
                            <textarea name="notes" class="form-control" rows="2"><?= e($note['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan Perubahan</button>
                <a href="<?= BASE_URL ?>/stock_out" class="btn btn-light border">Batal</a>
            </div>
        </form>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Barang</th><th class="text-end">Qty</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $row): ?>
                            <tr>
                                <td><?= e($row['item_name'] ?? '') ?></td>
                                <td class="text-end"><?= number_format((float) $row['qty'], 2, ',', '.') ?> <?= e($row['unit'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer small text-muted">Daftar barang mengikuti Pengeluaran Barang dan tidak diubah dari sini.</div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var destType = document.getElementById('destinationType');
    var projectWrap = document.getElementById('projectDestWrap');
    var clientWrap = document.getElementById('clientDestWrap');
    var destName = document.getElementById('destinationName');
    var destNameReq = document.getElementById('destNameReq');

    function applyDestType() {
        var v = destType.value; // 'project' | 'client' | 'manual'
        projectWrap.classList.toggle('d-none', v !== 'project');
        clientWrap.classList.toggle('d-none', v !== 'client');
        var manual = v === 'manual';
        destName.required = manual;
        destNameReq.classList.toggle('d-none', !manual);
    }

    destType.addEventListener('change', applyDestType);
    applyDestType();
});
</script>
