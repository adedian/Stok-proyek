/**
 * Komponen generik Quick-Add.
 * Submit form di dalam modal Bootstrap lewat fetch(), lalu suntik <option>
 * baru ke <select> target dan langsung pilih otomatis -- tanpa reload halaman.
 *
 * Dipakai oleh: quick_add_supplier_modal.php, quick_add_project_modal.php,
 * quick_add_item_modal.php (dan modal quick-add lain yang mengikuti pola sama).
 *
 * config:
 *   modalId, formId, endpoint  (wajib)
 *   selectEl          string (id) | Element | function(): Element -- select yang di-auto-select
 *                      (function berguna untuk kasus banyak baris dinamis, mis. item PO,
 *                      supaya target-nya di-resolve saat submit, bukan saat halaman dimuat)
 *   broadcastSelector  CSS selector opsional -- kalau diisi, <option> baru ditambahkan ke
 *                      SEMUA <select> yang cocok (bukan cuma target), supaya baris lain yang
 *                      sudah ada di halaman juga langsung punya pilihan baru ini
 *   extraFill          function(optionEl, responseData) -- isi data-attribute tambahan di <option>
 */
function initQuickAdd(config) {
    const modalEl = document.getElementById(config.modalId);
    const formEl = document.getElementById(config.formId);
    if (!modalEl || !formEl) {
        return;
    }

    const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const errorBox = formEl.querySelector('.quick-add-error');

    function resolveSelect() {
        if (typeof config.selectEl === 'function') {
            return config.selectEl();
        }
        if (typeof config.selectEl === 'string') {
            return document.getElementById(config.selectEl);
        }
        return config.selectEl || null;
    }

    formEl.addEventListener('submit', function (e) {
        e.preventDefault();

        const targetSelect = resolveSelect();
        if (!targetSelect) {
            showError('Tidak tahu baris/field mana yang harus diisi. Coba tutup modal dan ulangi.');
            return;
        }

        if (errorBox) {
            errorBox.classList.add('d-none');
            errorBox.textContent = '';
        }

        const submitBtn = formEl.querySelector('button[type="submit"]');
        const originalHtml = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Menyimpan...';
        }

        fetch(config.endpoint, {
            method: 'POST',
            body: new FormData(formEl),
            credentials: 'same-origin',
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, data: data };
                });
            })
            .then(function (result) {
                if (!result.ok || result.data.errors) {
                    const msg = (result.data.errors || ['Gagal menyimpan data.']).join(' ');
                    showError(msg);
                    return;
                }

                const targets = config.broadcastSelector
                    ? Array.prototype.slice.call(document.querySelectorAll(config.broadcastSelector))
                    : [targetSelect];

                targets.forEach(function (sel) {
                    const opt = document.createElement('option');
                    opt.value = result.data.id;
                    opt.textContent = result.data.label;
                    if (typeof config.extraFill === 'function') {
                        config.extraFill(opt, result.data);
                    }
                    sel.appendChild(opt);
                });

                targetSelect.value = String(result.data.id);
                targetSelect.dispatchEvent(new Event('change', { bubbles: true }));

                formEl.reset();
                bsModal.hide();
                notifySuccess('Berhasil ditambahkan & dipilih.');
            })
            .catch(function () {
                showError('Gagal menghubungi server. Silakan coba lagi.');
            })
            .finally(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalHtml;
                }
            });

        function showError(msg) {
            if (errorBox) {
                errorBox.textContent = msg;
                errorBox.classList.remove('d-none');
            } else if (window.Swal) {
                window.Swal.fire({ icon: 'error', title: 'Gagal', text: msg });
            } else {
                alert(msg);
            }
        }
    });
}

function notifySuccess(message) {
    if (window.Swal) {
        window.Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: message,
            showConfirmButton: false,
            timer: 2200,
            timerProgressBar: true,
        });
    }
}

function notifyError(message) {
    if (window.Swal) {
        window.Swal.fire({ icon: 'error', title: 'Gagal', text: message });
    } else {
        alert(message);
    }
}

/**
 * Ganti native confirm() dengan SweetAlert kalau tersedia -- dipakai modul baru
 * untuk konfirmasi hapus/nonaktifkan. Mengembalikan Promise<boolean>.
 */
function confirmAction(message, confirmText) {
    if (window.Swal) {
        return window.Swal.fire({
            icon: 'warning',
            text: message,
            showCancelButton: true,
            confirmButtonText: confirmText || 'Ya, lanjutkan',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#dc3545',
        }).then(function (result) {
            return result.isConfirmed;
        });
    }
    return Promise.resolve(window.confirm(message));
}
