<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/SalesInvoicePayment.php';
require_once ROOT_PATH . '/app/models/SalesInvoiceTerm.php';

/**
 * SalesInvoicePaymentController (Revisi 10 Fase 5)
 * Pencatatan uang masuk dari client per termin Invoice Keluar -- versi AR
 * dari PaymentController (yang sudah ada utk PO/AP). Pola CRUD & rangeDelete
 * disalin persis dari PaymentController, disederhanakan (tanpa funding_source/
 * payment_method_id -- itu konsep AP/pengeluaran, bukan uang masuk dari client).
 */
class SalesInvoicePaymentController extends Controller
{
    private SalesInvoicePayment $paymentModel;
    private SalesInvoiceTerm $termModel;

    public function __construct()
    {
        Middleware::requirePermission('sales_invoice_payment', 'view');

        $this->paymentModel = new SalesInvoicePayment();
        $this->termModel    = new SalesInvoiceTerm();
    }

    public function index()
    {
        $filters = [
            'keyword'   => trim($_GET['keyword'] ?? ''),
            'date_from' => $_GET['date_from'] ?? '',
            'date_to'   => $_GET['date_to'] ?? '',
        ];

        $this->view('sales_invoice_payment/list', [
            'pageTitle' => 'Pembayaran Invoice',
            'payments'  => $this->paymentModel->listWithRelations($filters),
            'filters'   => $filters,
        ]);
    }

    /**
     * Form tambah. Bisa diakses dengan sales_invoice_id sudah ditentukan (dari
     * tombol "Catat Pembayaran" di Detail Invoice) atau kosong (pilih termin
     * langsung dari dropdown lintas invoice).
     */
    public function create()
    {
        Middleware::requirePermission('sales_invoice_payment', 'create');

        $invoiceId = (int) ($_GET['sales_invoice_id'] ?? 0);
        $termId = (int) ($_GET['term_id'] ?? 0);

        $this->view('sales_invoice_payment/form', [
            'pageTitle'     => 'Tambah Pembayaran Invoice',
            'mode'          => 'create',
            'payment'       => null,
            'paymentNumber' => null,
            'termList'      => $this->getPayableTermList($invoiceId),
            'selectedTermId' => $termId,
        ]);
    }

    public function store()
    {
        Middleware::requirePermission('sales_invoice_payment', 'create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('sales_invoice_payment', 'create');
        }
        verifyCsrf();

        $data = $this->collectInput();
        $errors = $this->validateInput($data);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('sales_invoice_payment', 'create');
        }

        assertPeriodOpen('sales_invoice_payment', $data['payment_date'], 'sales_invoice_payment', 'create');

        try {
            $proofFile = handleFileUpload('proof_file', 'sales_invoice_payments', ['jpg', 'jpeg', 'png', 'webp', 'pdf'], 5);
        } catch (RuntimeException $e) {
            setFlash('error', $e->getMessage());
            $this->redirect('sales_invoice_payment', 'create');
        }

        $this->paymentModel->create([
            'sales_invoice_term_id' => $data['sales_invoice_term_id'],
            'payment_number'        => $this->paymentModel->generatePaymentNumber($data['payment_date']),
            'amount'                => $data['amount'],
            'payment_date'          => $data['payment_date'],
            'proof_file'            => $proofFile,
            'notes'                 => $data['notes'],
            'created_by'            => currentUserId(),
        ]);

        setFlash('success', 'Pembayaran berhasil disimpan.');
        $this->redirect('sales_invoice_payment', 'index');
    }

    public function edit()
    {
        Middleware::requirePermission('sales_invoice_payment', 'edit');
        $id = (int) ($_GET['id'] ?? 0);
        $payment = $this->paymentModel->findWithRelations($id);

        if (!$payment) {
            setFlash('error', 'Data pembayaran tidak ditemukan.');
            $this->redirect('sales_invoice_payment', 'index');
        }

        $this->view('sales_invoice_payment/form', [
            'pageTitle'     => 'Edit Pembayaran Invoice',
            'mode'          => 'edit',
            'payment'       => $payment,
            'paymentNumber' => $payment['payment_number'],
            'termList'      => $this->getPayableTermList(0, (int) $payment['sales_invoice_term_id']),
            'selectedTermId' => (int) $payment['sales_invoice_term_id'],
        ]);
    }

    public function update()
    {
        Middleware::requirePermission('sales_invoice_payment', 'edit');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('sales_invoice_payment', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->paymentModel->find($id);

        if (!$existing) {
            setFlash('error', 'Data pembayaran tidak ditemukan.');
            $this->redirect('sales_invoice_payment', 'index');
        }

        $data = $this->collectInput();
        $errors = $this->validateInput($data, $id);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('sales_invoice_payment', 'edit', ['id' => $id]);
        }

        assertPeriodOpen('sales_invoice_payment', $existing['payment_date'], 'sales_invoice_payment', 'edit', ['id' => $id]);
        assertPeriodOpen('sales_invoice_payment', $data['payment_date'], 'sales_invoice_payment', 'edit', ['id' => $id]);

        try {
            $proofFile = handleFileUpload('proof_file', 'sales_invoice_payments', ['jpg', 'jpeg', 'png', 'webp', 'pdf'], 5);
        } catch (RuntimeException $e) {
            setFlash('error', $e->getMessage());
            $this->redirect('sales_invoice_payment', 'edit', ['id' => $id]);
        }

        $updateData = [
            'sales_invoice_term_id' => $data['sales_invoice_term_id'],
            'amount'                => $data['amount'],
            'payment_date'          => $data['payment_date'],
            'notes'                 => $data['notes'],
        ];
        if ($proofFile !== null) {
            $updateData['proof_file'] = $proofFile;
        }

        $this->paymentModel->updateById($id, $updateData);

        setFlash('success', 'Pembayaran berhasil diperbarui.');
        $this->redirect('sales_invoice_payment', 'index');
    }

    public function delete()
    {
        Middleware::requirePermission('sales_invoice_payment', 'delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('sales_invoice_payment', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $payment = $this->paymentModel->find($id);

        if (!$payment) {
            setFlash('error', 'Data pembayaran tidak ditemukan.');
            $this->redirect('sales_invoice_payment', 'index');
        }

        assertPeriodOpen('sales_invoice_payment', $payment['payment_date'], 'sales_invoice_payment', 'index');
        $res = $this->deleteOneRecord($id);
        setFlash($res === true ? 'success' : 'error',
            $res === true ? 'Pembayaran berhasil dihapus.' : 'Gagal menghapus pembayaran.');

        $this->redirect('sales_invoice_payment', 'index');
    }

    /** Hapus 1 pembayaran ke Tempat Sampah. true = sukses, string = alasan skip. */
    private function deleteOneRecord(int $id)
    {
        $payment = $this->paymentModel->find($id);
        if (!$payment) {
            return 'gagal';
        }
        try {
            $this->paymentModel->deleteById($id);
            return true;
        } catch (Throwable $e) {
            error_log('SalesInvoicePayment deleteOneRecord error: ' . $e->getMessage());
            return 'gagal';
        }
    }

    /** Hapus semua pembayaran dalam rentang tanggal ke Tempat Sampah -- KHUSUS Super Admin. */
    public function rangeDelete()
    {
        rangeDeleteGuardSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('sales_invoice_payment', 'index');
        }
        verifyCsrf();

        [$from, $to] = rangeDeleteReadDates();
        if ($err = rangeDeleteValidate($from, $to)) {
            setFlash('error', $err);
            $this->redirect('sales_invoice_payment', 'index');
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

        rangeDeleteLog('sales_invoice_payment', $from, $to, $deleted, array_sum($skipped));
        rangeDeleteFlash($deleted, $skipped);
        $this->redirect('sales_invoice_payment', 'index');
    }

    /**
     * AJAX: kembalikan sisa tagihan termin tertentu -- dipanggil saat user
     * memilih termin di dropdown form pembayaran.
     */
    public function ajaxRemaining()
    {
        $termId = (int) ($_GET['term_id'] ?? 0);
        $excludePaymentId = (int) ($_GET['exclude_payment_id'] ?? 0);

        $term = $this->termModel->find($termId);
        if (!$term) {
            $this->json(['error' => 'Termin tidak ditemukan'], 404);
        }

        $totalPaid = $this->paymentModel->totalPaidByTerm($termId);
        if ($excludePaymentId) {
            $existing = $this->paymentModel->find($excludePaymentId);
            if ($existing && (int) $existing['sales_invoice_term_id'] === $termId) {
                $totalPaid -= (float) $existing['amount'];
            }
        }

        $totalAmount = (float) $term['total_amount'];
        $remaining = max(0, $totalAmount - $totalPaid);
        $percentage = $totalAmount > 0 ? min(100, round($totalPaid / $totalAmount * 100, 1)) : 0.0;

        $this->json([
            'total_amount'        => $totalAmount,
            'total_paid'          => $totalPaid,
            'remaining'           => $remaining,
            'remaining_formatted' => formatRupiah($remaining),
            'percentage'          => $percentage,
        ]);
    }

    // ================= Helper privat =================

    /**
     * Termin yang boleh dibuatkan pembayaran, ATAU (edit) termin milik
     * pembayaran yang sedang diedit ($includeTermId) walau sudah lunas --
     * supaya dropdown tetap menampilkan pilihan yang sedang tersimpan.
     * $invoiceId > 0 -> filter ke 1 invoice saja (datang dari tombol "Catat
     * Pembayaran" di Detail Invoice).
     */
    private function getPayableTermList(int $invoiceId = 0, int $includeTermId = 0): array
    {
        $terms = $this->termModel->payableTermsList();
        if ($invoiceId > 0) {
            $terms = array_values(array_filter($terms, fn($t) => (int) $t['sales_invoice_id'] === $invoiceId));
        }
        return $terms;
    }

    private function getRemaining(?array $term, float $excludeAmount = 0): ?float
    {
        if (!$term) {
            return null;
        }
        $totalPaid = $this->paymentModel->totalPaidByTerm((int) $term['id']) - $excludeAmount;
        return max(0, (float) $term['total_amount'] - $totalPaid);
    }

    private function collectInput(): array
    {
        return [
            'sales_invoice_term_id' => (int) ($_POST['sales_invoice_term_id'] ?? 0),
            'amount'                => parseCurrencyInput($_POST['amount'] ?? 0),
            'payment_date'          => $_POST['payment_date'] ?? '',
            'notes'                 => trim($_POST['notes'] ?? ''),
        ];
    }

    private function validateInput(array $data, ?int $excludePaymentId = null): array
    {
        $errors = [];

        if ($data['sales_invoice_term_id'] <= 0) {
            $errors[] = 'Termin invoice wajib dipilih.';
            return $errors;
        }

        $term = $this->termModel->find($data['sales_invoice_term_id']);
        if (!$term) {
            $errors[] = 'Termin invoice tidak ditemukan.';
            return $errors;
        }

        if ($data['amount'] <= 0) {
            $errors[] = 'Nominal pembayaran harus lebih dari 0.';
        }
        if (empty($data['payment_date'])) {
            $errors[] = 'Tanggal pembayaran wajib diisi.';
        }

        $excludeAmount = $excludePaymentId ? (float) $this->paymentModel->find($excludePaymentId)['amount'] : 0;
        $remaining = $this->getRemaining($term, $excludeAmount);

        if ($data['amount'] > 0 && $remaining !== null && $data['amount'] > $remaining) {
            $errors[] = 'Nominal pembayaran (' . formatRupiah($data['amount'])
                . ') melebihi sisa tagihan termin ini (' . formatRupiah($remaining) . ').';
        }

        return $errors;
    }
}
