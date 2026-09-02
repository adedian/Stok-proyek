<?php
/** @var array $existingNames */
?>
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body p-4">
                <div class="text-center mb-3">
                    <i class="bi bi-person-badge fs-1 text-warning"></i>
                    <h4 class="mt-2 mb-1">PIC Kas Belum Terdaftar</h4>
                    <p class="text-muted small mb-0">
                        Akun Anda belum memiliki PIC Kas dengan kredensial. Buat PIC Kas
                        terlebih dahulu untuk dapat menggunakan modul Kas.
                    </p>
                </div>

                <div class="text-center mb-3">
                    <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#formTambahPic">
                        <i class="bi bi-plus-circle"></i> Tambah PIC Kas
                    </button>
                </div>

                <div class="collapse show" id="formTambahPic">
                    <?php require ROOT_PATH . '/app/views/cash/_pic_form.php'; ?>
                </div>
            </div>
        </div>
    </div>
</div>
