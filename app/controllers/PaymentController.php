<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/Payment.php';
require_once ROOT_PATH . '/app/models/PurchaseOrder.php';
require_once ROOT_PATH . '/app/models/PurchaseOrderHistory.php';
require_once ROOT_PATH . '/app/models/PaymentMethod.php';
require_once ROOT_PATH . '/app/helpers/money_helper.php';

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
            'status'  => is_string($_GET['status'] ?? '') ? ($_GET['status'] ?? '') : '',
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
        $type = $_GET['type'] ?? Payment::TYPE_TERMIN;
        if (!is_string($type) || !isset($this->paymentModel->typeLabels[$type])) {
            $type = Payment::TYPE_TERMIN;
        }

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
            'progress'      => $selectedPo ? $this->paymentModel->poPaymentInfo((int) $selectedPo['id'], $selectedPo['total_amount']) : null,
            'nextTermin'    => $selectedPo ? $this->paymentModel->previewNextTermin((int) $selectedPo['id']) : null,
            'paymentType'   => $type,
            'typeLabels'    => $this->paymentModel->typeLabels,
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
        $back = $data['purchase_order_id'] > 0 ? ['po_id' => $data['purchase_order_id']] : [];

        // Pra-cek tanpa kunci (murah): PO dipilih + tanggal valid + periode terbuka.
        if ($data['purchase_order_id'] <= 0) {
            setFlash('error', 'Purchase Order wajib dipilih.');
            $this->redirect('payment', 'create');
        }
        if (!isValidDateString($data['payment_date'])) {
            setFlash('error', 'Tanggal pembayaran wajib diisi dengan tanggal yang valid.');
            $this->redirect('payment', 'create', $back);
        }
        assertPeriodOpen('payment', $data['payment_date'], 'payment', 'create');
        BackDate::enforce('payment', $data['payment_date'], null, 'payment', 'create', $back);

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            // KUNCI baris PO: pembayar lain pada PO yang sama menunggu di sini, lalu membaca sisa
            // tagihan & nomor termin TERBARU. Seluruh validasi nominal dilakukan SETELAH kunci --
            // jadi sisa tagihan yang dipakai selalu sisa yang benar-benar berlaku saat disimpan.
            $po = $this->paymentModel->lockPo($data['purchase_order_id']);
            if (!$po) {
                $pdo->rollBack();
                setFlash('error', 'Purchase Order tidak ditemukan.');
                $this->redirect('payment', 'create');
            }

            $errors = $this->validateInput($data, null, $po);
            if (!empty($errors)) {
                $pdo->rollBack();
                setFlash('error', implode(' ', $errors));
                $this->redirect('payment', 'create', $back + ['type' => $data['payment_type']]);
            }

            $proofFile = handleFileUpload('proof_file', 'payments', ['jpg', 'jpeg', 'png', 'webp', 'pdf'], 5);

            // Termin: nomor otomatis & tak pernah dipakai ulang. Pelunasan: bukan termin (0).
            $terminNo = $data['payment_type'] === Payment::TYPE_TERMIN
                ? $this->paymentModel->nextTerminNumber((int) $po['id'])
                : 0;

            $paymentId = $this->paymentModel->create([
                'purchase_order_id' => (int) $po['id'],
                'payment_number'    => $this->paymentModel->generatePaymentNumber($data['funding_source'], $data['payment_date']),
                'termin'            => $terminNo,
                'payment_type'      => $data['payment_type'],
                'payment_method_id' => $data['payment_method_id'],
                'funding_source'    => $data['funding_source'],
                'amount'            => $data['amount'],
                'currency'          => $data['currency'],
                'kurs'              => $data['kurs'],
                'amount_idr'        => $data['amount_idr'],
                'payment_date'      => $data['payment_date'],
                'proof_file'        => $proofFile,
                'status'            => 'pending', // disinkronkan ke status PO terkini tepat di bawah
                'notes'             => $data['notes'],
                'created_by'        => currentUserId(),
            ]);
            $newStatus = $this->paymentModel->syncStatusForPo((int) $po['id'], $po['total_amount']);

            $label = $data['payment_type'] === Payment::TYPE_PELUNASAN ? 'Pelunasan' : "Pembayaran termin {$terminNo}";
            $this->historyModel->log(
                (int) $po['id'],
                'payment_added',
                "{$label} sebesar " . formatMoney($data['amount'], $data['currency'])
                    . ($data['currency'] !== 'IDR' ? ' (kurs ' . formatKurs($data['kurs']) . ' = ' . formatRupiah($data['amount_idr']) . ')' : '')
                    . " ditambahkan" . ($newStatus === Money::STATUS_PAID ? ' -- PO LUNAS' : ''),
                currentUserId()
            );

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Payment store error: ' . $e->getMessage());
            setFlash('error', $e instanceof RuntimeException ? $e->getMessage() : 'Gagal menyimpan pembayaran. Silakan coba lagi.');
            $this->redirect('payment', 'create', $back);
        }

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
            'poList'        => $this->getPayablePoList((int) $payment['purchase_order_id']),
            'selectedPo'    => $po,
            'remaining'     => $this->getRemaining($po, (int) $payment['id']),
            'progress'      => $po ? $this->paymentModel->poPaymentInfo((int) $po['id'], $po['total_amount']) : null,
            'nextTermin'    => null,
            'paymentType'   => $payment['payment_type'] ?? Payment::TYPE_TERMIN,
            'typeLabels'    => $this->paymentModel->typeLabels,
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
        $backEdit = ['id' => $id];

        // Pembayaran melekat pada PO-nya & nomor termin-nya (urutan per PO): tidak boleh dipindah.
        if ($data['purchase_order_id'] !== (int) $existing['purchase_order_id']) {
            setFlash('error', 'Purchase Order pada pembayaran tidak dapat diubah. Hapus pembayaran ini lalu buat baru pada PO yang benar.');
            $this->redirect('payment', 'edit', $backEdit);
        }
        if (!isValidDateString($data['payment_date'])) {
            setFlash('error', 'Tanggal pembayaran wajib diisi dengan tanggal yang valid.');
            $this->redirect('payment', 'edit', $backEdit);
        }

        assertPeriodOpen('payment', $existing['payment_date'], 'payment', 'edit', $backEdit);
        assertPeriodOpen('payment', $data['payment_date'], 'payment', 'edit', $backEdit);
        BackDate::enforce('payment', $data['payment_date'], $existing['payment_date'], 'payment', 'edit', $backEdit);

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            $po = $this->paymentModel->lockPo((int) $existing['purchase_order_id']);
            if (!$po) {
                $pdo->rollBack();
                setFlash('error', 'Purchase Order tidak ditemukan.');
                $this->redirect('payment', 'index');
            }

            $errors = $this->validateInput($data, $existing, $po);
            if (!empty($errors)) {
                $pdo->rollBack();
                setFlash('error', implode(' ', $errors));
                $this->redirect('payment', 'edit', $backEdit);
            }

            $proofFile = handleFileUpload('proof_file', 'payments', ['jpg', 'jpeg', 'png', 'webp', 'pdf'], 5);

            // Jenis & nomor termin TIDAK diubah saat edit (identitas pembayaran).
            $updateData = [
                'payment_method_id' => $data['payment_method_id'],
                'funding_source'    => $data['funding_source'],
                'amount'            => $data['amount'],
                'currency'          => $data['currency'],
                'kurs'              => $data['kurs'],
                'amount_idr'        => $data['amount_idr'],
                'payment_date'      => $data['payment_date'],
                'notes'             => $data['notes'],
            ];
            if ($proofFile !== null) {
                $updateData['proof_file'] = $proofFile; // hanya update kalau upload file baru
            }

            $this->paymentModel->updateById($id, $updateData);
            $this->paymentModel->syncStatusForPo((int) $po['id'], $po['total_amount']);

            $this->historyModel->log(
                (int) $po['id'],
                'payment_updated',
                "Pembayaran {$existing['payment_number']} (" . $this->paymentModel->typeNumberLabel($existing) . ") diperbarui menjadi " . formatMoney($data['amount'], $data['currency'])
                    . ($data['currency'] !== 'IDR' ? ' (kurs ' . formatKurs($data['kurs']) . ' = ' . formatRupiah($data['amount_idr']) . ')' : ''),
                currentUserId()
            );

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Payment update error: ' . $e->getMessage());
            setFlash('error', $e instanceof RuntimeException ? $e->getMessage() : 'Gagal memperbarui pembayaran. Silakan coba lagi.');
            $this->redirect('payment', 'edit', $backEdit);
        }

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

    /**
     * Batalkan/hapus 1 pembayaran (soft-delete ke Tempat Sampah -- barisnya TIDAK hilang, jadi nomor
     * termin yang pernah dipakai tetap tercatat & counter tidak mundur). true = sukses, string = alasan skip.
     */
    private function deleteOneRecord(int $id)
    {
        $payment = $this->paymentModel->find($id);
        if (!$payment) {
            return 'gagal';
        }
        // Soft-delete ke Tempat Sampah aman walau periode terkunci -- gerbang
        // Tutup Bulan tetap berlaku untuk hapus per-baris lewat delete().
        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $po = $this->paymentModel->lockPo((int) $payment['purchase_order_id']);
            $this->paymentModel->deleteById($id);
            if ($po) {
                $this->paymentModel->syncStatusForPo((int) $po['id'], $po['total_amount']);
            }
            $this->historyModel->log(
                (int) $payment['purchase_order_id'],
                'payment_deleted',
                "Pembayaran {$payment['payment_number']} (" . $this->paymentModel->typeNumberLabel($payment) . ") dibatalkan/dihapus -- tidak lagi dihitung dalam total pembayaran",
                currentUserId()
            );
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
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
     * user memilih PO di dropdown form pembayaran. Semua angka dihitung server (Money) --
     * JS hanya menampilkan, tidak menghitung ulang persentase/status.
     */
    public function ajaxRemaining()
    {
        $poId = (int) ($_GET['po_id'] ?? 0);
        $excludePaymentId = (int) ($_GET['exclude_payment_id'] ?? 0);

        $po = $this->poModel->find($poId);
        if (!$po) {
            $this->json(['error' => 'PO tidak ditemukan'], 404);
        }

        $totalPaid = $this->paymentModel->totalPaidByPo($poId, $excludePaymentId ?: null);
        $prog = $this->paymentModel->progressFor($po['total_amount'], $totalPaid);
        $currency = normalizeCurrency($po['currency'] ?? 'IDR');

        $this->json([
            'total_amount'        => Money::toFloat($po['total_amount']),
            'total_amount_exact'  => $prog['total_amount'],
            'total_paid'          => Money::toFloat($prog['total_paid']),
            'total_paid_exact'    => $prog['total_paid'],
            'remaining'           => Money::toFloat($prog['remaining']),
            'remaining_exact'     => $prog['remaining'],   // string desimal persis -- untuk isi nominal Pelunasan
            'remaining_formatted' => formatMoney($prog['remaining'], $currency),
            'currency'            => $currency,
            'currency_prefix'     => currencyPrefix($currency),
            'kurs_required'       => $currency !== 'IDR',
            'percentage'          => $prog['percentage'],       // string titik, presisi penuh
            'percentage_text'     => $prog['percentage_text'],  // string Indonesia (koma)
            'percentage_bar'      => $prog['percentage_bar'],
            'payment_status'      => $prog['status'],
            'payment_status_label' => $this->paymentModel->statusLabels[$prog['status']] ?? $prog['status'],
            'overpaid_amount'     => $prog['overpaid_amount'],
            'next_termin'         => $this->paymentModel->previewNextTermin($poId),
        ]);
    }

    // ================= Helper privat =================

    /**
     * PO yang boleh dibuatkan pembayaran: bukan draft/cancelled
     */
    private function getPayablePoList(int $keepPoId = 0): array
    {
        return $this->poModel->payablePoList($keepPoId);
    }

    /** Sisa tagihan (string desimal) = total PO - pembayaran aktif (kecuali $excludePaymentId). */
    private function getRemaining(?array $po, ?int $excludePaymentId = null): ?string
    {
        if (!$po) {
            return null;
        }
        return Money::remaining($po['total_amount'], $this->paymentModel->totalPaidByPo((int) $po['id'], $excludePaymentId));
    }

    private function collectInput(): array
    {
        $fundingSource = $_POST['funding_source'] ?? 'bank';
        if (!is_string($fundingSource) || !array_key_exists($fundingSource, $this->paymentModel->fundingSourceLabels)) {
            $fundingSource = 'bank';
        }
        $amountRaw = $_POST['amount'] ?? 0;
        $poIdRaw = $_POST['purchase_order_id'] ?? 0;

        return [
            'purchase_order_id' => is_scalar($poIdRaw) ? (int) $poIdRaw : 0,
            // Jenis Pembayaran (Termin/Pelunasan). Nomor termin TIDAK dibaca dari form -- server
            // yang menerbitkannya (Payment::nextTerminNumber()).
            'payment_type_raw'  => $_POST['payment_type'] ?? Payment::TYPE_TERMIN,
            'payment_type'      => Payment::TYPE_TERMIN,
            'funding_source'    => $fundingSource,
            // Jenis Bank (payment_method_id, master Cek/Giro/Transfer Bank/Tunai) HANYA
            // relevan kalau sumber dana = Bank -- kalau Kas Kecil/Kas Project, dipaksa
            // null di sini (backend, bukan cuma disembunyikan di UI) supaya data tidak
            // menyesatkan (misal Jenis "Tunai" nempel di pembayaran Kas Project).
            'payment_method_id' => $fundingSource === 'bank' ? ((int) ($_POST['payment_method_id'] ?? 0) ?: null) : null,
            'amount'            => is_scalar($amountRaw) ? parseCurrencyInput($amountRaw) : 0.0,
            // Mata uang & kurs yang DIKIRIM client -- TIDAK dipercaya: mata uang pembayaran
            // SELALU ikut PO (lihat validateInput()), IDR dipaksa kurs 1. Nilai mentah dibawa
            // apa adanya (bisa array/teks) supaya validasi bisa menolak dengan pesan jelas.
            'currency_posted'   => $_POST['currency'] ?? '',
            'kurs_raw'          => $_POST['kurs'] ?? '',
            'payment_date'      => is_string($_POST['payment_date'] ?? '') ? ($_POST['payment_date'] ?? '') : '',
            'notes'             => is_string($_POST['notes'] ?? '') ? trim($_POST['notes'] ?? '') : '',
        ];
    }

    /**
     * Validasi di DALAM transaksi, setelah baris PO dikunci ($po = hasil Payment::lockPo()).
     *
     * $data DIISI ULANG oleh method ini: 'currency' (= mata uang PO), 'kurs' (IDR -> 1; selain IDR
     * -> wajib > 0), 'payment_type', dan 'amount' / 'amount_idr' sebagai STRING desimal 2 digit
     * yang dihitung server (amount = nilai dalam mata uang PO; amount_idr = Rupiah). Dipanggil
     * by-reference. $existing = pembayaran yang sedang diedit (null = pembayaran baru).
     */
    private function validateInput(array &$data, ?array $existing, array $po): array
    {
        $errors = [];
        $isEdit = $existing !== null;
        $poId = (int) $po['id'];

        // Jenis pembayaran: pembayaran baru -> dari form (harus valid); edit -> tetap jenis semula.
        if ($isEdit) {
            $data['payment_type'] = $existing['payment_type'] ?? Payment::TYPE_TERMIN;
        } else {
            $rawType = $data['payment_type_raw'] ?? '';
            if (!is_string($rawType) || !isset($this->paymentModel->typeLabels[$rawType])) {
                $errors[] = 'Jenis pembayaran tidak valid (pilih Termin atau Pelunasan).';
                return $errors;
            }
            $data['payment_type'] = $rawType;
        }
        $isPelunasan = $data['payment_type'] === Payment::TYPE_PELUNASAN;

        // PO draft/dibatalkan tidak bisa dibayar -- dijaga di server, bukan cuma disembunyikan dari dropdown.
        if (!$isEdit && in_array($po['status'], ['draft', 'cancelled'], true)) {
            $errors[] = 'PO berstatus Draft/Dibatalkan tidak dapat dibayar.';
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

        if (!isValidDateString($data['payment_date'])) {
            $errors[] = 'Tanggal pembayaran wajib diisi dengan tanggal yang valid.';
        }
        if ($data['funding_source'] === 'bank' && empty($data['payment_method_id'])) {
            $errors[] = 'Metode Pembayaran (Cek/Giro/Transfer Bank/Tunai) wajib dipilih untuk sumber dana Bank.';
        }
        if (!empty($errors)) {
            return $errors; // nominal baru dihitung kalau kurs/tanggal sudah beres
        }

        // --- Sisa tagihan AKTUAL (di bawah kunci PO), dalam sen -- tanpa float ---
        $remaining = Money::remaining($po['total_amount'], $this->paymentModel->totalPaidByPo($poId, $isEdit ? (int) $existing['id'] : null));
        $remC = Money::cents($remaining);

        // Edit termin yang nilainya kini melebihi sisa jatuh ke pesan "melebihi sisa" di bawah.
        if ($remC <= 0 && (!$isEdit || $isPelunasan)) {
            $errors[] = $isPelunasan
                ? 'Sisa tagihan PO ini sudah 0 -- pelunasan tidak dapat dibuat.'
                : 'PO ini sudah lunas (sisa tagihan 0) -- pembayaran baru tidak dapat dibuat.';
            return $errors;
        }

        // Field "Nominal Pembayaran" diisi dalam RUPIAH (nilai uang yang benar-benar dibayar) =
        // amount_idr. Nominal dalam mata uang PO (amount, dasar sisa/termin/status) DIHITUNG di sini
        // = rupiah / kurs (bukan dari browser). IDR: kurs 1 -> keduanya sama.
        $idrC = Money::cents($data['amount']);
        if ($idrC <= 0) {
            $errors[] = 'Nominal pembayaran harus lebih dari 0.';
            return $errors;
        }
        if ($idrC > 999999999999999) { // Rp 9.999.999.999.999,99
            $errors[] = 'Nilai pembayaran dalam IDR melebihi batas wajar.';
            return $errors;
        }

        if ($isPelunasan) {
            // Pelunasan = SELURUH sisa tagihan, PERSIS (tanpa pembulatan). Nominal yang dikirim form
            // hanya diperiksa: bila sisa berubah (mis. pembayaran lain masuk) -> tolak & minta cek ulang.
            $expectedIdrC = $poCurrency === 'IDR'
                ? $remC
                : Money::cents(convertToIdr($remaining, $data['kurs']));
            $tolerance = $poCurrency === 'IDR' ? 0 : 1; // konversi kurs bisa selisih 1 sen karena pembulatan
            if (abs($idrC - $expectedIdrC) > $tolerance) {
                $errors[] = 'Nominal pelunasan harus sama persis dengan sisa tagihan saat ini ('
                    . formatMoney($remaining, $poCurrency)
                    . ($poCurrency !== 'IDR' ? ' = ' . formatRupiah(Money::fromCents($expectedIdrC)) : '')
                    . '). Sisa tagihan dapat berubah karena pembayaran lain -- periksa nominalnya lalu simpan ulang.';
                return $errors;
            }
            $data['amount'] = $remaining;                              // mata uang PO, persis sisa
            $data['amount_idr'] = Money::fromCents($idrC);
            return [];
        }

        // --- Termin ---
        if ($poCurrency !== 'IDR') {
            $foreignC = Money::cents(round($idrC / 100 / $data['kurs'], 2));
            // Pembulatan 2 desimal bisa meleset 1 sen dari sisa (mis. termin terakhir) -> rapatkan
            // ke sisa agar PO bisa lunas / tidak ditolak karena selisih pembulatan.
            if (abs($foreignC - $remC) <= 1) {
                $foreignC = $remC;
            }
            if ($foreignC <= 0) {
                $errors[] = 'Nominal pembayaran terlalu kecil untuk kurs yang dipakai.';
                return $errors;
            }
        } else {
            $foreignC = $idrC;
        }

        if ($foreignC > $remC) {
            $errors[] = 'Nominal pembayaran (' . formatRupiah(Money::fromCents($idrC))
                . ($poCurrency !== 'IDR' ? ' = ' . formatMoney(Money::fromCents($foreignC), $poCurrency) : '')
                . ') melebihi sisa tagihan PO (' . formatMoney($remaining, $poCurrency)
                . ($poCurrency !== 'IDR' && !empty($data['kurs']) ? ' = ' . formatRupiah(convertToIdr($remaining, $data['kurs'])) : '') . ').';
            return $errors;
        }

        $data['amount'] = Money::fromCents($foreignC);
        $data['amount_idr'] = Money::fromCents($idrC);
        return [];
    }
}
