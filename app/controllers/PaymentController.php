<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/Payment.php';
require_once ROOT_PATH . '/app/models/PurchaseOrder.php';
require_once ROOT_PATH . '/app/models/PurchaseOrderHistory.php';
require_once ROOT_PATH . '/app/models/PaymentMethod.php';

class PaymentController extends Controller
{
    private Payment $paymentModel;
    private PurchaseOrder $poModel;
    private PurchaseOrderHistory $historyModel;
    private PaymentMethod $methodModel;

    public function __construct()
    {
        Middleware::requirePermission('payment', 'view');

        $this->paymentModel = new Payment();
        $this->poModel      = new PurchaseOrder();
        $this->historyModel = new PurchaseOrderHistory();
        $this->methodModel  = new PaymentMethod();
    }

    /**
     * List pembayaran + kartu ringkasan status (belum/sebagian/lunas)
     */
    public function index()
    {
        $filters = [
            'status'  => $_GET['status'] ?? '',
            'keyword' => $_GET['keyword'] ?? '',
        ];

        $payments = $this->paymentModel->listWithRelations($filters);
        $summary  = $this->paymentModel->countStatusSummary();

        $this->view('payment/list', [
            'pageTitle'        => 'Pembayaran',
            'payments'         => $payments,
            'summary'          => $summary,
            'filters'          => $filters,
            'statusLabels'     => $this->paymentModel->statusLabels,
            'statusBadgeClass' => $this->paymentModel->statusBadgeClass,
            'fundingSourceLabels' => $this->paymentModel->fundingSourceLabels,
        ]);
    }

    /**
     * Rekap semua PO berdasarkan status pembayaran (belum dibayar / sebagian / lunas)
     */
    public function summary()
    {
        $poSummary = $this->paymentModel->poPaymentSummary();

        $this->view('payment/summary', [
            'pageTitle'        => 'Rekap Status Pembayaran PO',
            'poSummary'        => $poSummary,
            'statusLabels'     => $this->paymentModel->statusLabels,
            'statusBadgeClass' => $this->paymentModel->statusBadgeClass,
        ]);
    }

    /**
     * Form tambah pembayaran. Bisa diakses dengan po_id sudah ditentukan (dari halaman detail PO)
     * atau kosong (lalu user pilih PO dari dropdown).
     */
    public function create()
    {
        Middleware::requirePermission('payment', 'create');
        $poId = (int) ($_GET['po_id'] ?? 0);
        $selectedPo = $poId ? $this->poModel->findWithRelations($poId) : null;

        $this->view('payment/form', [
            'pageTitle'     => 'Tambah Pembayaran',
            'mode'          => 'create',
            'payment'       => null,
            // Nomor pembayaran BELUM digenerate di sini -- DocumentNumber::next()
            // memakai counter persisten yang increment tiap dipanggil (bukan cuma
            // MAX(number)+1 seperti dulu), jadi kalau dipanggil di sini untuk
            // sekadar preview, nomor akan "terbakar"/terlewat tiap kali form ini
            // dibuka tanpa disimpan. Nomor asli baru dibuat sekali di store().
            'paymentNumber' => null,
            'poList'        => $this->getPayablePoList(),
            'selectedPo'    => $selectedPo,
            'remaining'     => $selectedPo ? $this->getRemaining($selectedPo) : null,
            'progress'      => $selectedPo ? $this->paymentModel->poPaymentInfo((int) $selectedPo['id'], (float) $selectedPo['total_amount']) : null,
            'paymentMethods' => $this->methodModel->activeList(),
            'fundingSourceLabels' => $this->paymentModel->fundingSourceLabels,
        ]);
    }

    public function store()
    {
        Middleware::requirePermission('payment', 'create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('payment', 'create');
        }
        verifyCsrf();

        $data = $this->collectInput();
        $errors = $this->validateInput($data);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('payment', 'create', $data['purchase_order_id'] ? ['po_id' => $data['purchase_order_id']] : []);
        }

        assertPeriodOpen('payment', $data['payment_date'], 'payment', 'create');

        try {
            $proofFile = handleFileUpload('proof_file', 'payments', ['jpg', 'jpeg', 'png', 'webp', 'pdf'], 5);
        } catch (RuntimeException $e) {
            setFlash('error', $e->getMessage());
            $this->redirect('payment', 'create', ['po_id' => $data['purchase_order_id']]);
        }

        $po = $this->poModel->find($data['purchase_order_id']);
        $totalPaidBefore = $this->paymentModel->totalPaidByPo($data['purchase_order_id']);
        $totalPaidAfter = $totalPaidBefore + $data['amount'];
        $status = $this->paymentModel->resolveStatus((float) $po['total_amount'], $totalPaidAfter);

        $this->paymentModel->create([
            'purchase_order_id' => $data['purchase_order_id'],
            'payment_number'    => $this->paymentModel->generatePaymentNumber($data['funding_source'], $data['payment_date']),
            'termin'            => $data['termin'],
            'payment_method_id' => $data['payment_method_id'],
            'funding_source'    => $data['funding_source'],
            'amount'            => $data['amount'],
            'currency'          => $data['currency'],
            'kurs'              => $data['kurs'],
            'amount_idr'        => $data['amount_idr'],
            'payment_date'      => $data['payment_date'],
            'proof_file'        => $proofFile,
            'status'            => $status,
            'notes'             => $data['notes'],
            'created_by'        => currentUserId(),
        ]);

        $this->historyModel->log(
            $data['purchase_order_id'],
            'payment_added',
            "Pembayaran termin {$data['termin']} sebesar " . formatMoney($data['amount'], $data['currency'])
                . ($data['currency'] !== 'IDR' ? ' (kurs ' . formatKurs($data['kurs']) . ' = ' . formatRupiah($data['amount_idr']) . ')' : '') . " ditambahkan",
            currentUserId()
        );

        setFlash('success', 'Pembayaran berhasil disimpan.');
        $this->redirect('payment', 'index');
    }

    public function edit()
    {
        Middleware::requirePermission('payment', 'edit');
        $id = (int) ($_GET['id'] ?? 0);
        $payment = $this->paymentModel->findWithRelations($id);

        if (!$payment) {
            setFlash('error', 'Data pembayaran tidak ditemukan.');
            $this->redirect('payment', 'index');
        }

        $po = $this->poModel->find($payment['purchase_order_id']);

        $this->view('payment/form', [
            'pageTitle'     => 'Edit Pembayaran',
            'mode'          => 'edit',
            'payment'       => $payment,
            'paymentNumber' => $payment['payment_number'],
            'poList'        => $this->getPayablePoList(),
            'selectedPo'    => $po,
            'remaining'     => $this->getRemaining($po, (float) $payment['amount']),
            'progress'      => $po ? $this->paymentModel->poPaymentInfo((int) $po['id'], (float) $po['total_amount']) : null,
            'paymentMethods' => $this->methodModel->activeList(),
            'fundingSourceLabels' => $this->paymentModel->fundingSourceLabels,
        ]);
    }

    public function update()
    {
        Middleware::requirePermission('payment', 'edit');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('payment', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->paymentModel->find($id);

        if (!$existing) {
            setFlash('error', 'Data pembayaran tidak ditemukan.');
            $this->redirect('payment', 'index');
        }

        $data = $this->collectInput();
        $errors = $this->validateInput($data, $id);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('payment', 'edit', ['id' => $id]);
        }

        assertPeriodOpen('payment', $existing['payment_date'], 'payment', 'edit', ['id' => $id]);
        assertPeriodOpen('payment', $data['payment_date'], 'payment', 'edit', ['id' => $id]);

        try {
            $proofFile = handleFileUpload('proof_file', 'payments', ['jpg', 'jpeg', 'png', 'webp', 'pdf'], 5);
        } catch (RuntimeException $e) {
            setFlash('error', $e->getMessage());
            $this->redirect('payment', 'edit', ['id' => $id]);
        }

        $po = $this->poModel->find($data['purchase_order_id']);
        // Hitung ulang total dibayar TANPA pembayaran ini, lalu tambahkan nominal baru
        $totalPaidOthers = $this->paymentModel->totalPaidByPo($data['purchase_order_id']) - (float) $existing['amount'];
        $totalPaidAfter = $totalPaidOthers + $data['amount'];
        $status = $this->paymentModel->resolveStatus((float) $po['total_amount'], $totalPaidAfter);

        $updateData = [
            'purchase_order_id' => $data['purchase_order_id'],
            'termin'            => $data['termin'],
            'payment_method_id' => $data['payment_method_id'],
            'funding_source'    => $data['funding_source'],
            'amount'            => $data['amount'],
            'currency'          => $data['currency'],
            'kurs'              => $data['kurs'],
            'amount_idr'        => $data['amount_idr'],
            'payment_date'      => $data['payment_date'],
            'status'            => $status,
            'notes'             => $data['notes'],
        ];
        if ($proofFile !== null) {
            $updateData['proof_file'] = $proofFile; // hanya update kalau upload file baru
        }

        $this->paymentModel->updateById($id, $updateData);

        $this->historyModel->log(
            $data['purchase_order_id'],
            'payment_updated',
            "Pembayaran {$existing['payment_number']} diperbarui menjadi " . formatMoney($data['amount'], $data['currency'])
                . ($data['currency'] !== 'IDR' ? ' (kurs ' . formatKurs($data['kurs']) . ' = ' . formatRupiah($data['amount_idr']) . ')' : ''),
            currentUserId()
        );

        setFlash('success', 'Pembayaran berhasil diperbarui.');
        $this->redirect('payment', 'index');
    }

    public function delete()
    {
        Middleware::requirePermission('payment', 'delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('payment', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $payment = $this->paymentModel->find($id);

        if (!$payment) {
            setFlash('error', 'Data pembayaran tidak ditemukan.');
            $this->redirect('payment', 'index');
        }

        assertPeriodOpen('payment', $payment['payment_date'], 'payment', 'index');
        $res = $this->deleteOneRecord($id);
        setFlash($res === true ? 'success' : 'error',
            $res === true ? 'Pembayaran berhasil dihapus.' : 'Gagal menghapus pembayaran.');

        $this->redirect('payment', 'index');
    }

    /** Hapus 1 pembayaran ke Tempat Sampah. true = sukses, string = alasan skip. */
    private function deleteOneRecord(int $id)
    {
        $payment = $this->paymentModel->find($id);
        if (!$payment) {
            return 'gagal';
        }
        // Soft-delete ke Tempat Sampah aman walau periode terkunci -- gerbang
        // Tutup Bulan tetap berlaku untuk hapus per-baris lewat delete().
        try {
            $this->paymentModel->deleteById($id);
            $this->historyModel->log(
                $payment['purchase_order_id'],
                'payment_deleted',
                "Pembayaran {$payment['payment_number']} dihapus",
                currentUserId()
            );
            return true;
        } catch (Throwable $e) {
            error_log('Payment deleteOneRecord error: ' . $e->getMessage());
            return 'gagal';
        }
    }

    /** Hapus semua pembayaran dalam rentang tanggal ke Tempat Sampah -- KHUSUS Super Admin. */
    public function rangeDelete()
    {
        rangeDeleteGuardSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('payment', 'index');
        }
        verifyCsrf();

        [$from, $to] = rangeDeleteReadDates();
        if ($err = rangeDeleteValidate($from, $to)) {
            setFlash('error', $err);
            $this->redirect('payment', 'index');
        }

        $deleted = 0;
        $skipped = [];
        foreach ($this->paymentModel->idsByDateRange('payment_date', $from, $to) as $id) {
            $r = $this->deleteOneRecord($id);
            if ($r === true) {
                $deleted++;
            } else {
                $skipped[$r] = ($skipped[$r] ?? 0) + 1;
            }
        }

        rangeDeleteLog('payment', $from, $to, $deleted, array_sum($skipped));
        rangeDeleteFlash($deleted, $skipped);
        $this->redirect('payment', 'index');
    }

    /**
     * AJAX: kembalikan sisa tagihan (remaining) untuk PO tertentu -- dipanggil saat
     * user memilih PO di dropdown form pembayaran, supaya nominal bisa divalidasi di client.
     */
    public function ajaxRemaining()
    {
        $poId = (int) ($_GET['po_id'] ?? 0);
        $excludePaymentId = (int) ($_GET['exclude_payment_id'] ?? 0);

        $po = $this->poModel->find($poId);
        if (!$po) {
            $this->json(['error' => 'PO tidak ditemukan'], 404);
        }

        $totalPaid = $this->paymentModel->totalPaidByPo($poId);
        if ($excludePaymentId) {
            $existing = $this->paymentModel->find($excludePaymentId);
            if ($existing && (int) $existing['purchase_order_id'] === $poId) {
                $totalPaid -= (float) $existing['amount'];
            }
        }

        $totalAmount = (float) $po['total_amount'];
        $remaining = max(0, $totalAmount - $totalPaid);
        $percentage = $totalAmount > 0 ? min(100, round($totalPaid / $totalAmount * 100, 1)) : 0.0;

        $this->json([
            'total_amount'        => $totalAmount,
            'total_paid'          => $totalPaid,
            'remaining'           => $remaining,
            'remaining_formatted' => formatMoney($remaining, $po['currency'] ?? 'IDR'),
            'currency'            => normalizeCurrency($po['currency'] ?? 'IDR'),
            'currency_prefix'     => currencyPrefix($po['currency'] ?? 'IDR'),
            'kurs_required'       => normalizeCurrency($po['currency'] ?? 'IDR') !== 'IDR',
            'percentage'          => $percentage,
        ]);
    }

    // ================= Helper privat =================

    /**
     * PO yang boleh dibuatkan pembayaran: bukan draft/cancelled
     */
    private function getPayablePoList(): array
    {
        return $this->poModel->payablePoList();
    }

    private function getRemaining(?array $po, float $excludeAmount = 0): ?float
    {
        if (!$po) {
            return null;
        }
        $totalPaid = $this->paymentModel->totalPaidByPo((int) $po['id']) - $excludeAmount;
        return max(0, (float) $po['total_amount'] - $totalPaid);
    }

    private function collectInput(): array
    {
        $fundingSource = $_POST['funding_source'] ?? 'bank';
        if (!array_key_exists($fundingSource, $this->paymentModel->fundingSourceLabels)) {
            $fundingSource = 'bank';
        }

        return [
            'purchase_order_id' => (int) ($_POST['purchase_order_id'] ?? 0),
            'termin'            => max(1, (int) ($_POST['termin'] ?? 1)),
            'funding_source'    => $fundingSource,
            // Jenis Bank (payment_method_id, master Cek/Giro/Transfer Bank/Tunai) HANYA
            // relevan kalau sumber dana = Bank -- kalau Kas Kecil/Kas Project, dipaksa
            // null di sini (backend, bukan cuma disembunyikan di UI) supaya data tidak
            // menyesatkan (misal Jenis "Tunai" nempel di pembayaran Kas Project).
            'payment_method_id' => $fundingSource === 'bank' ? ((int) ($_POST['payment_method_id'] ?? 0) ?: null) : null,
            'amount'            => parseCurrencyInput($_POST['amount'] ?? 0),
            // Mata uang & kurs yang DIKIRIM client -- TIDAK dipercaya: mata uang pembayaran
            // SELALU ikut PO (lihat validateInput()), IDR dipaksa kurs 1. Nilai mentah dibawa
            // apa adanya (bisa array/teks) supaya validasi bisa menolak dengan pesan jelas.
            'currency_posted'   => $_POST['currency'] ?? '',
            'kurs_raw'          => $_POST['kurs'] ?? '',
            'payment_date'      => $_POST['payment_date'] ?? '',
            'notes'             => trim($_POST['notes'] ?? ''),
        ];
    }

    /**
     * $data DIISI ULANG oleh method ini: 'currency' (selalu = mata uang PO) dan 'kurs'
     * (IDR -> 1 apa pun yang dikirim; selain IDR -> wajib > 0). Dipanggil by-reference.
     */
    private function validateInput(array &$data, ?int $excludePaymentId = null): array
    {
        $errors = [];

        if ($data['purchase_order_id'] <= 0) {
            $errors[] = 'Purchase Order wajib dipilih.';
            return $errors; // stop di sini, PO wajib ada sebelum cek nominal
        }

        $po = $this->poModel->find($data['purchase_order_id']);
        if (!$po) {
            $errors[] = 'Purchase Order tidak ditemukan.';
            return $errors;
        }

        // --- Mata uang & Kurs (backend = otoritas; JS hanya UX) ---
        // Mata uang pembayaran = mata uang PO (sisa/pelunasan dihitung dalam satuan yang sama,
        // tanpa konversi). Request yang mengirim mata uang lain ditolak.
        $poCurrency = normalizeCurrency($po['currency'] ?? 'IDR');
        $postedCur = $data['currency_posted'] ?? '';
        if (!is_string($postedCur) || (trim($postedCur) !== '' && !isValidCurrency(trim($postedCur)))) {
            $errors[] = 'Mata uang tidak valid.';
        } elseif (trim($postedCur) !== '' && strtoupper(trim($postedCur)) !== $poCurrency) {
            $errors[] = "Mata uang pembayaran harus sama dengan mata uang PO ({$poCurrency}).";
        }
        $data['currency'] = $poCurrency;
        if ($poCurrency === 'IDR') {
            $data['kurs'] = 1.0; // IDR selalu 1, abaikan kiriman client
        } else {
            $kursRaw = $data['kurs_raw'] ?? '';
            if (is_string($kursRaw) && trim($kursRaw) === '') {
                $errors[] = 'Kurs wajib diisi untuk PO dengan mata uang selain IDR.';
            } else {
                $kurs = parseKursInput($kursRaw);
                if ($kurs === null || $kurs <= 0) {
                    $errors[] = 'Kurs harus berupa angka lebih besar dari 0.';
                } elseif ($kurs > 1000000000) {
                    $errors[] = 'Kurs melebihi batas wajar.';
                } else {
                    $data['kurs'] = $kurs;
                }
            }
        }

        if ($data['amount'] <= 0) {
            $errors[] = 'Nominal pembayaran harus lebih dari 0.';
        }
        if (!isValidDateString($data['payment_date'])) {
            $errors[] = 'Tanggal pembayaran wajib diisi dengan tanggal yang valid.';
        }
        if ($data['funding_source'] === 'bank' && empty($data['payment_method_id'])) {
            $errors[] = 'Jenis Pembayaran (Cek/Giro/Transfer Bank/Tunai) wajib dipilih untuk sumber dana Bank.';
        }

        $excludeAmount = $excludePaymentId ? (float) $this->paymentModel->find($excludePaymentId)['amount'] : 0;
        $remaining = $this->getRemaining($po, $excludeAmount);

        // Field "Nominal Pembayaran" diisi dalam RUPIAH (nilai uang yang benar-benar dibayar).
        // amount_idr = nilai itu; nominal dalam mata uang PO (amount, dasar sisa/termin/status)
        // DIHITUNG di sini = rupiah / kurs (bukan dari browser). IDR: kurs 1 -> keduanya sama.
        if ($data['amount'] > 0 && isset($data['kurs'])) {
            $data['amount_idr'] = round($data['amount'], 2);
            if ($data['amount_idr'] > 9999999999999.99) {
                $errors[] = 'Nilai pembayaran dalam IDR melebihi batas wajar.';
            }
            if ($poCurrency !== 'IDR') {
                $foreign = round($data['amount_idr'] / $data['kurs'], 2);
                // Pembulatan 2 desimal bisa meleset 1 sen dari sisa (mis. pelunasan penuh) -> rapatkan
                // ke sisa agar PO bisa lunas / tidak ditolak karena selisih pembulatan.
                if ($remaining !== null && abs($foreign - $remaining) <= 0.01) {
                    $foreign = round($remaining, 2);
                }
                $data['amount'] = $foreign;
                if ($foreign <= 0) {
                    $errors[] = 'Nominal pembayaran terlalu kecil untuk kurs yang dipakai.';
                }
            }
        }

        if ($data['amount'] > 0 && $remaining !== null && $data['amount'] > $remaining) {
            $errors[] = 'Nominal pembayaran (' . formatRupiah($data['amount_idr'] ?? $data['amount'])
                . ($poCurrency !== 'IDR' ? ' = ' . formatMoney($data['amount'], $poCurrency) : '')
                . ') melebihi sisa tagihan PO (' . formatMoney($remaining, $poCurrency)
                . ($poCurrency !== 'IDR' && !empty($data['kurs']) ? ' = ' . formatRupiah(convertToIdr($remaining, $data['kurs'])) : '') . ').';
        }

        return $errors;
    }
}
