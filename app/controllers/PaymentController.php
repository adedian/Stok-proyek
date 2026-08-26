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
        $poId = (int) ($_GET['po_id'] ?? 0);
        $selectedPo = $poId ? $this->poModel->findWithRelations($poId) : null;

        $this->view('payment/form', [
            'pageTitle'     => 'Tambah Pembayaran',
            'mode'          => 'create',
            'payment'       => null,
            'paymentNumber' => $this->paymentModel->generatePaymentNumber(),
            'poList'        => $this->getPayablePoList(),
            'selectedPo'    => $selectedPo,
            'remaining'     => $selectedPo ? $this->getRemaining($selectedPo) : null,
            'progress'      => $selectedPo ? $this->paymentModel->poPaymentInfo((int) $selectedPo['id'], (float) $selectedPo['total_amount']) : null,
            'paymentMethods' => $this->methodModel->activeList(),
        ]);
    }

    public function store()
    {
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

        try {
            $proofFile = handleFileUpload('proof_file', 'payments', ['jpg', 'jpeg', 'png', 'pdf'], 5);
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
            'payment_number'    => $this->paymentModel->generatePaymentNumber(),
            'termin'            => $data['termin'],
            'payment_method_id' => $data['payment_method_id'],
            'amount'            => $data['amount'],
            'payment_date'      => $data['payment_date'],
            'proof_file'        => $proofFile,
            'status'            => $status,
            'notes'             => $data['notes'],
            'created_by'        => currentUserId(),
        ]);

        $this->historyModel->log(
            $data['purchase_order_id'],
            'payment_added',
            "Pembayaran termin {$data['termin']} sebesar " . formatRupiah($data['amount']) . " ditambahkan",
            currentUserId()
        );

        setFlash('success', 'Pembayaran berhasil disimpan.');
        $this->redirect('payment', 'index');
    }

    public function edit()
    {
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
        ]);
    }

    public function update()
    {
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

        try {
            $proofFile = handleFileUpload('proof_file', 'payments', ['jpg', 'jpeg', 'png', 'pdf'], 5);
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
            'amount'            => $data['amount'],
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
            "Pembayaran {$existing['payment_number']} diperbarui menjadi " . formatRupiah($data['amount']),
            currentUserId()
        );

        setFlash('success', 'Pembayaran berhasil diperbarui.');
        $this->redirect('payment', 'index');
    }

    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('payment', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $payment = $this->paymentModel->find($id);

        if ($payment) {
            $this->paymentModel->deleteById($id);
            $this->historyModel->log(
                $payment['purchase_order_id'],
                'payment_deleted',
                "Pembayaran {$payment['payment_number']} dihapus",
                currentUserId()
            );
            setFlash('success', 'Pembayaran berhasil dihapus.');
        } else {
            setFlash('error', 'Data pembayaran tidak ditemukan.');
        }

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
            'remaining_formatted' => formatRupiah($remaining),
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
        return [
            'purchase_order_id' => (int) ($_POST['purchase_order_id'] ?? 0),
            'termin'            => max(1, (int) ($_POST['termin'] ?? 1)),
            'payment_method_id' => (int) ($_POST['payment_method_id'] ?? 0) ?: null,
            'amount'            => parseCurrencyInput($_POST['amount'] ?? 0),
            'payment_date'      => $_POST['payment_date'] ?? '',
            'notes'             => trim($_POST['notes'] ?? ''),
        ];
    }

    private function validateInput(array $data, ?int $excludePaymentId = null): array
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

        if ($data['amount'] <= 0) {
            $errors[] = 'Nominal pembayaran harus lebih dari 0.';
        }
        if (empty($data['payment_date'])) {
            $errors[] = 'Tanggal pembayaran wajib diisi.';
        }
        if (empty($data['payment_method_id'])) {
            $errors[] = 'Metode pembayaran wajib dipilih.';
        }

        $excludeAmount = $excludePaymentId ? (float) $this->paymentModel->find($excludePaymentId)['amount'] : 0;
        $remaining = $this->getRemaining($po, $excludeAmount);

        if ($data['amount'] > 0 && $remaining !== null && $data['amount'] > $remaining) {
            $errors[] = 'Nominal pembayaran (' . formatRupiah($data['amount'])
                . ') melebihi sisa tagihan PO (' . formatRupiah($remaining) . ').';
        }

        return $errors;
    }
}
