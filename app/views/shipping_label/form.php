<?php
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? 'update' : 'store';

// Dua kotak isian, urutan & isinya mengikuti hasil cetak: Kepada lalu Pengirim.
$parties = [
    'to'   => ['title' => 'Kepada',   'icon' => 'bi-box-arrow-in-down'],
    'from' => ['title' => 'Pengirim', 'icon' => 'bi-box-arrow-up'],
];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> Label Pengiriman</h4>
        <small class="text-muted">Isi alamat penerima dan pengirim, hasil cetak berupa dua kotak: Kepada &amp; Pengirim</small>
    </div>
    <a href="<?= BASE_URL ?>/shipping_label" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=shipping_label&action=<?= $actionUrl ?>" id="shippingLabelForm">
    <?= csrfField() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $label['id'] ?>">
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <?php foreach ($parties as $p => $cfg): ?>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold">
                        <i class="bi <?= $cfg['icon'] ?>"></i> <?= e($cfg['title']) ?>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="<?= $p ?>_name">Nama <span class="text-danger">*</span></label>
                                <input type="text" id="<?= $p ?>_name" name="<?= $p ?>_name" class="form-control" maxlength="150"
                                       value="<?= e($label[$p . '_name'] ?? '') ?>" required
                                       data-preview="<?= $p ?>-name" <?= $p === 'to' ? 'autofocus' : '' ?>>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="<?= $p ?>_address">Alamat <span class="text-danger">*</span></label>
                                <input type="text" id="<?= $p ?>_address" name="<?= $p ?>_address" class="form-control" maxlength="255"
                                       value="<?= e($label[$p . '_address'] ?? '') ?>" required
                                       data-preview="<?= $p ?>-address">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="<?= $p ?>_city">Kelurahan / Kota</label>
                                <input type="text" id="<?= $p ?>_city" name="<?= $p ?>_city" class="form-control" maxlength="150"
                                       value="<?= e($label[$p . '_city'] ?? '') ?>"
                                       data-preview="<?= $p ?>-city">
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="<?= $p ?>_postal_code">Kode Pos</label>
                                <input type="text" id="<?= $p ?>_postal_code" name="<?= $p ?>_postal_code" class="form-control" maxlength="20"
                                       inputmode="numeric" value="<?= e($label[$p . '_postal_code'] ?? '') ?>"
                                       data-preview="<?= $p ?>-postal">
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="<?= $p ?>_phone">No. Telp</label>
                                <input type="text" id="<?= $p ?>_phone" name="<?= $p ?>_phone" class="form-control" maxlength="50"
                                       inputmode="tel" value="<?= e($label[$p . '_phone'] ?? '') ?>"
                                       data-preview="<?= $p ?>-phone">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-eye"></i> Pratinjau Cetak</div>
        <div class="card-body">
            <div class="sl-form-preview">
                <?php foreach ($parties as $p => $cfg): ?>
                    <div class="sl-box">
                        <div class="sl-title"><?= e($cfg['title']) ?> :</div>
                        <div class="sl-name" data-out="<?= $p ?>-name"></div>
                        <div data-out="<?= $p ?>-address"></div>
                        <div data-out="<?= $p ?>-city"></div>
                        <div data-out="<?= $p ?>-postal" data-prefix="Kode Pos "></div>
                        <div data-out="<?= $p ?>-phone" data-prefix="No. Telp "></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan &amp; Cetak</button>
        <a href="<?= BASE_URL ?>/shipping_label" class="btn btn-outline-secondary">Batal</a>
    </div>
</form>

<style>
    .sl-form-preview { display: flex; flex-wrap: wrap; gap: 14px; }
    .sl-form-preview .sl-box {
        flex: 1 1 260px; max-width: 340px; border: 1px solid #212529; border-radius: 0;
        padding: 14px 20px; font-size: 14px; line-height: 1.55; background: #fff; color: #212529;
    }
    .sl-form-preview .sl-title, .sl-form-preview .sl-name { font-weight: 700; }
    .sl-form-preview .sl-box > div:empty { display: none; }
</style>

<script>
// Pratinjau langsung: isi kotak mengikuti ketikan, baris kosong disembunyikan (sama seperti hasil cetak).
document.addEventListener('DOMContentLoaded', function () {
    var inputs = document.querySelectorAll('#shippingLabelForm [data-preview]');

    function render(input) {
        var out = document.querySelector('[data-out="' + input.dataset.preview + '"]');
        if (!out) return;
        var v = input.value.trim();
        out.textContent = v === '' ? '' : (out.dataset.prefix || '') + v;
    }

    inputs.forEach(function (input) {
        render(input);
        input.addEventListener('input', function () { render(input); });
    });
});
</script>
