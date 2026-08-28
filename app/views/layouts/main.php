<?php
require ROOT_PATH . '/app/views/layouts/header.php';
require ROOT_PATH . '/app/views/layouts/sidebar.php';
?>
<div class="main-content flex-grow-1 p-3 p-lg-4" style="background-color: #f4f6f9; min-height: 100vh;">
    <?php $flash = getFlash(); ?>
    <?php if ($flash): ?>
        <noscript>
            <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : e($flash['type']) ?> alert-dismissible fade show">
                <?= e($flash['message']) ?>
            </div>
        </noscript>
        <script>
            (function () {
                var type = <?= json_encode($flash['type']) ?>;
                var message = <?= json_encode($flash['message']) ?>;
                var icon = type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info');
                if (window.Swal) {
                    Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 4000,
                        timerProgressBar: true,
                        didOpen: function (t) {
                            t.addEventListener('mouseenter', Swal.stopTimer);
                            t.addEventListener('mouseleave', Swal.resumeTimer);
                        }
                    }).fire({ icon: icon, title: message });
                } else {
                    var wrap = document.createElement('div');
                    wrap.className = 'alert alert-' + (type === 'error' ? 'danger' : type) + ' alert-dismissible fade show';
                    wrap.textContent = message;
                    var mainContent = document.querySelector('.main-content');
                    if (mainContent) { mainContent.insertBefore(wrap, mainContent.firstChild); }
                }
            })();
        </script>
    <?php endif; ?>

    <?php require $viewFile; ?>
</div>
<?php require ROOT_PATH . '/app/views/layouts/footer.php'; ?>
