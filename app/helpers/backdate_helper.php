<?php
/**
 * BackDate -- pembatasan tanggal mundur (back date) PER MENU TRANSAKSI.
 *
 * Super Admin memilih menu mana yang dikunci (Pengaturan Sistem > Back Date). Untuk menu yang
 * dikunci, tanggal transaksi TIDAK boleh sebelum tanggal aktual SERVER (Asia/Jakarta); hari ini
 * dan tanggal mendatang tetap mengikuti aturan lama. Menu yang tidak dikunci tidak tersentuh.
 *
 * Semua pengecekan di BACKEND (di titik simpan/ubah tiap modul) -- atribut min di form hanya UX.
 * Aturan edit: bila tanggal TIDAK diubah dari yang tersimpan, transaksi lama tetap bisa diedit
 * (tidak "dihukum" karena sudah bertanggal mundur); hanya tanggal yang DIUBAH ke masa lalu ditolak.
 *
 * Pemakaian di controller (di samping assertPeriodOpen):
 *   BackDate::enforce('purchase_order', $data['po_date'], null, 'purchase_order', 'create');            // baru
 *   BackDate::enforce('purchase_order', $data['po_date'], $existing['po_date'], 'purchase_order', 'edit', ['id' => $id]);
 */
class BackDate
{
    public const TITLE = 'Tanggal Transaksi Tidak Diizinkan';
    public const MESSAGE = 'Transaksi ini tidak dapat menggunakan tanggal sebelum tanggal aktual sistem karena pengaturan Back Date pada menu ini sedang aktif.';

    /** @var array<string,bool>|null cache per-request (bukan lintas-request: perubahan langsung berlaku). */
    private static ?array $cache = null;

    /**
     * Katalog menu transaksi yang BENAR-BENAR ada di aplikasi dan punya input tanggal transaksi.
     * Urutan & kelompok = urutan tampil di Pengaturan. Kode inilah yang dipakai di database.
     */
    public static function catalog(): array
    {
        return [
            'Pembelian & Pembayaran' => [
                'request_po'       => ['Request PO', 'Tanggal Request (Draft juga dicek)'],
                'purchase_order'   => ['Purchase Order (PO)', 'Tanggal PO'],
                'offline_purchase' => ['Pembelian Offline', 'Tanggal Pembelian'],
                'payment'          => ['Pembayaran PO', 'Tanggal Bayar'],
            ],
            'Persediaan & Gudang' => [
                'goods_receipt' => ['Penerimaan Barang', 'Tanggal Penerimaan'],
                'stock_out'     => ['Pengeluaran Barang', 'Tanggal Pengeluaran'],
                'stock_opname'  => ['Stok Opname', 'Tanggal Opname'],
                'delivery_note' => ['Surat Jalan', 'Tanggal Surat Jalan (termasuk Surat Jalan dari Order Penjualan)'],
            ],
            'Keuangan (Kas / Bank)' => [
                'bank_masuk'    => ['Kas/Bank - Bank Masuk', 'Tanggal transaksi Bank, mutasi Masuk'],
                'bank_keluar'   => ['Kas/Bank - Bank Keluar', 'Tanggal transaksi Bank, mutasi Keluar'],
                'cash_masuk'    => ['Kas/Bank - Kas Masuk', 'Tanggal transaksi Kas, mutasi Masuk'],
                'cash_keluar'   => ['Kas/Bank - Kas Keluar', 'Tanggal transaksi Kas, mutasi Keluar'],
                'cash_transfer' => ['Kas/Bank - Transfer Bank ke Kas', 'Transfer membuat Bank Keluar + Kas Masuk, sehingga juga terkunci bila Bank Keluar atau Kas Masuk dikunci'],
            ],
            'Budget' => [
                'request_budget'   => ['Request Budget', 'Tanggal Request (Draft juga dicek)'],
                'pengajuan_budget' => ['Pengajuan Budget (Permintaan Otorisasi)', 'Tanggal Pengajuan (Draft juga dicek)'],
            ],
            'Penjualan & Invoice' => [
                'sales_order'             => ['Order Penjualan', 'Tanggal Order'],
                'sales_order_fulfillment' => ['Order Penjualan - Pemenuhan (Pengeluaran Stok)', 'Tanggal Pemenuhan'],
                'sales_invoice'           => ['Invoice Keluar', 'Tanggal Invoice (termasuk Invoice yang dibuat dari Order Penjualan)'],
                'collection_receipt'      => ['Tanda Terima', 'Tanggal Tanda Terima'],
            ],
            'Dokumen' => [
                'form' => ['Form (Kwitansi, Order Form, Pengajuan Bon Sementara)', 'Tanggal dokumen pada Form'],
            ],
        ];
    }

    /** [kode => nama menu] datar. */
    public static function names(): array
    {
        $out = [];
        foreach (self::catalog() as $items) {
            foreach ($items as $code => $meta) {
                $out[$code] = $meta[0];
            }
        }
        return $out;
    }

    public static function isKnown(string $code): bool
    {
        return isset(self::names()[$code]);
    }

    public static function label(string $code): string
    {
        return self::names()[$code] ?? $code;
    }

    /** Peta aktif/nonaktif. Tabel belum ada (server belum migrasi) -> semua nonaktif, error dicatat. */
    private static function map(): array
    {
        if (self::$cache === null) {
            try {
                require_once ROOT_PATH . '/app/models/BackDateSetting.php';
                self::$cache = (new BackDateSetting())->activeMap();
            } catch (Throwable $e) {
                error_log('BackDate: gagal membaca pengaturan -- ' . $e->getMessage());
                self::$cache = [];
            }
        }
        return self::$cache;
    }

    /** Buang cache setelah pengaturan disimpan (request yang sama). */
    public static function reset(): void
    {
        self::$cache = null;
    }

    public static function isActive(string $code): bool
    {
        return self::map()[$code] ?? false;
    }

    /** Tanggal aktual SERVER, zona Asia/Jakarta (bukan tanggal browser). */
    public static function today(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
    }

    /**
     * Kode yang dilanggar, atau null bila tanggal boleh. $codes = satu kode atau beberapa (transaksi
     * gabungan, mis. Transfer): cukup salah satu aktif & dilanggar. $oldDate = tanggal tersimpan
     * (edit); sama dengan $newDate -> tidak dianggap melanggar. Tanggal tidak valid -> null
     * (ditolak oleh validasi tanggal modul masing-masing).
     */
    public static function violated($codes, ?string $newDate, ?string $oldDate = null): ?string
    {
        $newDate = is_string($newDate) ? substr(trim($newDate), 0, 10) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) {
            return null;
        }
        if ($oldDate !== null && substr(trim((string) $oldDate), 0, 10) === $newDate) {
            return null;
        }
        if ($newDate >= self::today()) {
            return null;
        }
        foreach ((array) $codes as $code) {
            if (self::isActive($code)) {
                return $code;
            }
        }
        return null;
    }

    /** Pesan error siap tampil (untuk modul yang mengumpulkan $errors[]), atau null bila boleh. */
    public static function error($codes, ?string $newDate, ?string $oldDate = null): ?string
    {
        return self::violated($codes, $newDate, $oldDate) !== null
            ? self::TITLE . '. ' . self::MESSAGE
            : null;
    }

    /**
     * Gerbang server-side: bila melanggar -> catat audit, set flash (judul + pesan), redirect, exit.
     * Panggil SETELAH validasi input & sebelum menulis ke DB (di samping assertPeriodOpen).
     */
    public static function enforce(
        $codes,
        ?string $newDate,
        ?string $oldDate,
        string $redirModule,
        string $redirAction = 'index',
        array $redirParams = []
    ): void {
        $hit = self::violated($codes, $newDate, $oldDate);
        if ($hit === null) {
            return;
        }
        self::rejectLog($hit, (string) $newDate);
        setFlash('error', self::MESSAGE, self::TITLE);
        header('Location: ' . route($redirModule, $redirAction, $redirParams));
        exit;
    }

    /** Varian untuk endpoint JSON/AJAX: balas 422 JSON lalu exit. */
    public static function enforceJson($codes, ?string $newDate, ?string $oldDate = null): void
    {
        $hit = self::violated($codes, $newDate, $oldDate);
        if ($hit === null) {
            return;
        }
        self::rejectLog($hit, (string) $newDate);
        http_response_code(422);
        header('Content-Type: application/json');
        echo json_encode(['error' => self::TITLE . '. ' . self::MESSAGE]);
        exit;
    }

    /** Catat penolakan ke Riwayat Aktivitas (best-effort). */
    public static function rejectLog(string $code, string $date): void
    {
        try {
            require_once ROOT_PATH . '/app/models/ActivityLog.php';
            (new ActivityLog())->log(
                function_exists('currentUserId') ? currentUserId() : null,
                'settings',
                'backdate_reject',
                'Ditolak (Back Date aktif): transaksi ' . self::label($code) . ' bertanggal ' . $date
                    . ' sebelum tanggal aktual ' . self::today() . '.'
            );
        } catch (Throwable $e) {
            error_log('BackDate::rejectLog gagal: ' . $e->getMessage());
        }
    }

    /** Atribut min=... untuk <input type="date"> (HANYA UX; backend tetap memvalidasi). '' bila tak dikunci. */
    public static function minAttr($code): string
    {
        foreach ((array) $code as $c) {
            if (self::isActive($c)) {
                return 'min="' . htmlspecialchars(self::today(), ENT_QUOTES) . '"';
            }
        }
        return '';
    }
}
