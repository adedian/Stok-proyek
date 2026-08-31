<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';

/**
 * FileController
 * Penyaji file upload SENSITIF (bukti pembayaran, invoice supplier, bukti
 * pembelian offline) lewat kontrol akses -- BUKAN link langsung ke
 * public/uploads/. Nama file di disk acak 32-hex, tapi tetap wajib login +
 * role yang berhak sebelum file di-stream.
 *
 * File non-sensitif (foto barang, logo, tanda tangan, foto profil, surat jalan)
 * tetap dilayani langsung oleh web server -- lihat fileUrl() di functions.php.
 */
class FileController extends Controller
{
    /** Folder sensitif -> daftar role_slug yang boleh melihat isinya. */
    private const GATED = [
        'payments'           => [ROLE_SUPER_ADMIN, ROLE_FINANCE],
        'bukti_pembelian'    => [ROLE_SUPER_ADMIN, ROLE_FINANCE, ROLE_GUDANG, ROLE_PROJECT_MANAGER],
        'invoice_penerimaan' => [ROLE_SUPER_ADMIN, ROLE_GUDANG, ROLE_FINANCE],
        'invoice'            => [ROLE_SUPER_ADMIN, ROLE_FINANCE],
    ];

    public function __construct()
    {
        Middleware::requireLogin();
    }

    public function show(): void
    {
        $rel = ltrim((string) ($_GET['path'] ?? ''), '/');

        // Pola ketat: uploads/<folder>/<32 hex>.<ext>. Menutup path traversal,
        // null byte, dan nama file di luar konvensi sistem.
        if (!preg_match('#^uploads/([a-z0-9_]+)/([a-f0-9]{32})\.(jpg|jpeg|png|webp|pdf)$#', $rel, $m)) {
            $this->deny(404);
        }
        $folder = $m[1];

        if (!isset(self::GATED[$folder])) {
            // Folder publik tidak seharusnya lewat sini -- arahkan balik ke URL langsung.
            header('Location: ' . BASE_URL . '/' . $rel);
            exit;
        }

        if (!in_array(currentUserRole(), self::GATED[$folder], true)) {
            $this->deny(403);
        }

        $base = realpath(UPLOAD_PATH);
        $full = realpath(UPLOAD_PATH . '/' . substr($rel, strlen('uploads/')));
        if ($base === false || $full === false || strncmp($full, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) !== 0 || !is_file($full)) {
            $this->deny(404);
        }

        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        $types = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png', 'webp' => 'image/webp',
            'pdf' => 'application/pdf',
        ];

        // Bersihkan buffer apa pun supaya biner tidak tercampur output lain.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
        header('Content-Length: ' . filesize($full));
        header('Content-Disposition: inline; filename="' . basename($full) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=300, no-transform');
        header('Referrer-Policy: no-referrer');
        readfile($full);
        exit;
    }

    private function deny(int $code): void
    {
        http_response_code($code);
        if ($code === 403 && is_file(ROOT_PATH . '/app/views/errors/403.php')) {
            require ROOT_PATH . '/app/views/errors/403.php';
        } else {
            echo $code === 403 ? 'Akses ditolak.' : 'File tidak ditemukan.';
        }
        exit;
    }
}
