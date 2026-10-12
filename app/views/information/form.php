<?php
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? 'update' : 'store';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> Informasi</h4>
    <a href="<?= BASE_URL ?>/information" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=information&action=<?= $actionUrl ?>" enctype="multipart/form-data" id="informationForm">
    <?= csrfField() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $info['id'] ?>">
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Judul Informasi <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" maxlength="200"
                           value="<?= e($info['title'] ?? '') ?>" placeholder="mis. Perubahan Jadwal Penerimaan Barang" required autofocus>
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label">Kategori <span class="text-danger">*</span></label>
                    <select name="category" class="form-select" required>
                        <?php foreach ($categories as $slug => $label): ?>
                            <option value="<?= e($slug) ?>" <?= ($info['category'] ?? 'umum') === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Maintenance &amp; Pengumuman otomatis tampil sebagai warning di Dashboard.</div>
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        <?php foreach ($statuses as $slug => $label): ?>
                            <option value="<?= e($slug) ?>" <?= ($info['status'] ?? 'aktif') === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Hanya informasi berstatus Aktif yang tampil ke pengguna biasa.</div>
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label">Tanggal Publikasi <span class="text-danger">*</span></label>
                    <input type="date" name="publish_date" class="form-control"
                           value="<?= e($info['publish_date'] ?? date('Y-m-d')) ?>" required>
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label">Tanggal Berakhir</label>
                    <input type="date" name="end_date" class="form-control"
                           value="<?= e($info['end_date'] ?? '') ?>">
                    <div class="form-text">Opsional. Kosongkan jika tidak ada batas akhir.</div>
                </div>

                <div class="col-12">
                    <label class="form-label" for="infoImage">Gambar <span class="text-muted fw-normal">(opsional)</span></label>
                    <?php $hasImage = $isEdit && !empty($info['image_path']); ?>
                    <?php if ($hasImage): ?>
                        <div class="mb-2" id="infoImageCurrent">
                            <img src="<?= e(fileUrl($info['image_path'])) ?>" alt="Gambar saat ini" class="img-fluid rounded border"
                                 style="max-height: 180px; object-fit: contain;">
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="removeImage">
                                <label class="form-check-label small" for="removeImage">Hapus gambar ini</label>
                            </div>
                            <div class="form-text">Pilih file baru di bawah untuk mengganti gambar.</div>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="image" id="infoImage" class="form-control"
                           accept="image/jpeg,image/png,image/webp">
                    <div class="form-text">
                        JPG, PNG, atau WebP. Gambar dikompres otomatis oleh server hingga di bawah
                        <?= (int) (Information::IMAGE_MAX_BYTES / 1000) + 1 ?> KB (rasio &amp; orientasi dipertahankan).
                    </div>
                    <div id="infoImagePreviewBox" class="mt-2 d-none">
                        <img id="infoImagePreview" alt="Pratinjau gambar" class="img-fluid rounded border"
                             style="max-height: 220px; object-fit: contain;">
                        <div class="small text-muted mt-1" id="infoImageMeta"></div>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">Isi Informasi <span class="text-danger">*</span></label>
                    <textarea name="content" class="form-control" rows="8" placeholder="Tulis keterangan lengkap di sini..." required><?= e($info['content'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary" id="informationSubmit"><i class="bi bi-save"></i> Simpan</button>
        <a href="<?= BASE_URL ?>/information" class="btn btn-light border">Batal</a>
    </div>
</form>

<script>
// Pusat Informasi: pratinjau gambar + info ukuran. Foto besar (> 1,5 MB) diperkecil dulu di browser
// (maks. 1920 px, tipe & transparansi dipertahankan) supaya upload cepat dan lolos batas upload server.
// Ini HANYA penghemat upload: kompresi final < 300 KB selalu dikerjakan & divalidasi ulang oleh server.
(function () {
    const input = document.getElementById('infoImage');
    const form = document.getElementById('informationForm');
    if (!input || !form) return;
    const box = document.getElementById('infoImagePreviewBox');
    const img = document.getElementById('infoImagePreview');
    const meta = document.getElementById('infoImageMeta');
    const submit = document.getElementById('informationSubmit');
    const BROWSER_SHRINK_OVER = 1.5 * 1024 * 1024;
    const MAX_SIDE = 1920;
    let objectUrl = null;
    let busy = false;

    function kb(n) { return (n / 1000).toLocaleString('id-ID', {maximumFractionDigits: 1}) + ' KB'; }
    function mb(n) { return (n / 1048576).toLocaleString('id-ID', {maximumFractionDigits: 1}) + ' MB'; }
    function size(n) { return n >= 1048576 ? mb(n) : kb(n); }

    function show(file, note) {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = URL.createObjectURL(file);
        img.src = objectUrl;
        box.classList.remove('d-none');
        meta.textContent = file.name + ' \u2014 ' + size(file.size) + (note ? ' \u2014 ' + note : '');
    }

    async function shrink(file) {
        if (!window.createImageBitmap || !window.DataTransfer) return file;
        const bmp = await createImageBitmap(file, {imageOrientation: 'from-image'});
        const scale = Math.min(1, MAX_SIDE / Math.max(bmp.width, bmp.height));
        const w = Math.max(1, Math.round(bmp.width * scale)), h = Math.max(1, Math.round(bmp.height * scale));
        const canvas = document.createElement('canvas');
        canvas.width = w; canvas.height = h;
        canvas.getContext('2d').drawImage(bmp, 0, 0, w, h);
        if (bmp.close) bmp.close();
        const type = file.type === 'image/png' ? 'image/png' : (file.type === 'image/webp' ? 'image/webp' : 'image/jpeg');
        const blob = await new Promise(function (res) { canvas.toBlob(res, type, 0.85); });
        if (!blob || blob.size >= file.size || blob.type !== type) return file;
        return new File([blob], file.name, {type: type, lastModified: Date.now()});
    }

    input.addEventListener('change', async function () {
        let file = input.files && input.files[0];
        if (!file) { box.classList.add('d-none'); meta.textContent = ''; return; }
        if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
            show(file, 'tipe file tidak didukung \u2014 server akan menolaknya');
            return;
        }
        let note = file.size <= 299000 ? 'sudah di bawah 300 KB' : 'akan dikompres otomatis oleh server menjadi < 300 KB';
        if (file.size > BROWSER_SHRINK_OVER) {
            busy = true; submit.disabled = true;
            meta.textContent = 'Memperkecil gambar\u2026';
            box.classList.remove('d-none');
            try {
                const small = await shrink(file);
                if (small !== file) {
                    const dt = new DataTransfer();
                    dt.items.add(small);
                    input.files = dt.files;
                    note = 'diperkecil di browser dari ' + size(file.size) + '; server menyelesaikan kompresi < 300 KB';
                    file = small;
                }
            } catch (e) { /* gagal -> pakai file asli, server tetap mengompres */ }
            busy = false; submit.disabled = false;
        }
        show(file, note);
    });

    form.addEventListener('submit', function (e) {
        if (busy) { e.preventDefault(); return; }
        if (input.files && input.files[0]) {
            submit.disabled = true;
            submit.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengompres &amp; menyimpan\u2026';
        }
    });
})();
</script>
