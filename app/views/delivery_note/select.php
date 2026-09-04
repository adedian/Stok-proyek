<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Buat Surat Jalan</h4>
        <small class="text-muted"><?= count($rows) ?> baris Pengeluaran Barang terpilih</small>
    </div>
    <a href="<?= BASE_URL ?>/stock_out" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <form method="POST" action="<?= BASE_URL ?>/index.php?module=delivery_note&action=store" id="deliveryNoteForm">
            <?= csrfField() ?>
            <?php foreach ($ids as $id): ?>
                <input type="hidden" name="ids[]" value="<?= (int) $id ?>">
            <?php endforeach; ?>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Tanggal Surat Jalan</label>
                            <input type="date" name="delivery_date" class="form-control" value="<?= e(date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tujuan Pengiriman</label>
                            <select name="destination_type" id="destinationType" class="form-select">
                                <option value="project" selected>Site/Project</option>
                                <option value="client">Penjualan (Client)</option>
                                <option value="manual">Lainnya / Manual (isi bebas)</option>
                            </select>
                        </div>
                        <div class="col-12" id="projectDestWrap">
                            <label class="form-label">Project</label>
                            <select name="project_id" class="form-select">
                                <option value="">-- Pilih Project --</option>
                                <?php foreach ($projects as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>" data-location="<?= e($p['location'] ?? '') ?>"
                                        <?= (int) $rows[0]['project_id'] === (int) $p['id'] ? 'selected' : '' ?>>
                                        <?= e($p['project_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 d-none" id="clientDestWrap">
                            <label class="form-label">Client</label>
                            <div class="input-group">
                                <select name="client_id" id="client_id" class="form-select">
                                    <option value="">-- Pilih Client --</option>
                                    <?php foreach ($clients as $c): ?>
                                        <option value="<?= (int) $c['id'] ?>"><?= e($c['client_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (canQuickAdd('client')): ?>
                                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalQuickAddClient" title="Tambah Client Cepat">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nama Tujuan
                                <span class="text-danger d-none" id="destNameReq">*</span>
                                <span class="text-muted small">(bebas, mis. "Hotel Azana - Tulungagung")</span>
                            </label>
                            <input type="text" name="destination_name" id="destinationName" class="form-control" value="<?= e($rows[0]['destination'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Kota <span class="text-muted small">(untuk baris "Kota, Tanggal" di penutup Surat Jalan -- bukan nama project/client)</span></label>
                            <input type="text" name="city" id="cityInput" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">No. Kendaraan</label>
                            <input type="text" name="vehicle_number" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nama Driver</label>
                            <input type="text" name="driver_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Pengirim</label>
                            <input type="text" name="sender_name" class="form-control" value="<?= e($rows[0]['pic_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Penerima <span class="text-muted small">(diisi manual saat serah terima)</span></label>
                            <input type="text" name="recipient_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tanda Tangan HME</label>
                            <select name="signature_id" class="form-select">
                                <option value="">-- Tanpa Tanda Tangan Gambar --</option>
                                <?php foreach ($signatures as $s): ?>
                                    <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?> &middot; <?= e($s['position']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Catatan</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Buat &amp; Cetak Surat Jalan</button>
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
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= e($row['item_name'] ?? '') ?></td>
                                <td class="text-end"><?= number_format((float) $row['qty'], 2, ',', '.') ?> <?= e($row['unit'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if (canQuickAdd('client')): ?>
    <?php $quickAddClientTargetId = 'client_id'; ?>
    <?php require ROOT_PATH . '/app/views/partials/quick_add_client_modal.php'; ?>
<?php endif; ?>

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

        // Tujuan manual: Nama Tujuan jadi wajib (satu-satunya penanda tujuan).
        var manual = v === 'manual';
        destName.required = manual;
        destNameReq.classList.toggle('d-none', !manual);
        if (manual && !destName.value) { destName.focus(); }
    }

    destType.addEventListener('change', applyDestType);
    applyDestType();

    // Kota di-prefill dari lokasi project (kalau ada) sebagai kemudahan --
    // tetap bebas diedit/dikosongkan manual, TIDAK dipaksa/di-overwrite kalau
    // user sudah pernah mengetik sesuatu di field ini.
    var projectSelect = document.getElementById('projectSelect') || projectWrap.querySelector('select[name="project_id"]');
    var cityInput = document.getElementById('cityInput');
    var cityTouched = false;
    cityInput.addEventListener('input', function () { cityTouched = true; });

    function prefillCityFromProject() {
        if (cityTouched || !projectSelect) return;
        var opt = projectSelect.options[projectSelect.selectedIndex];
        var location = opt ? (opt.dataset.location || '') : '';
        if (location) {
            cityInput.value = location;
        }
    }

    if (projectSelect) {
        projectSelect.addEventListener('change', prefillCityFromProject);
        prefillCityFromProject();
    }
});
</script>
