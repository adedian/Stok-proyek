<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/CashTransaction.php';
require_once ROOT_PATH . '/app/models/CashTransactionItem.php';
require_once ROOT_PATH . '/app/models/CashCategory.php';
require_once ROOT_PATH . '/app/models/UserPicAssignment.php';
require_once ROOT_PATH . '/app/models/User.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

/**
 * Modul Kas (Revisi 9) -- catatan kas masuk & kas keluar.
 *
 * Satu Kas = 1 header (tanggal / PIC / No Bukti / kategori / mutasi) +
 * banyak baris item {uraian, qty, satuan(=harga satuan Rp), jumlah}.
 * `total_amount` header = SUM(jumlah).
 *
 * Visibilitas per-PIC (server-side, tidak dari frontend):
 *   Super Admin / Accounting / Project Manager  -> semua transaksi Kas.
 *   Purchase / PIC Project / Admin Project       -> hanya pic ∈ mapping user
 *   (tabel user_pic_assignments). `assertCanTouch()` cegah IDOR;
 *   validasi PIC saat store cegah selundupan lewat POST.
 */
class CashController extends Controller
{
    private CashTransaction $cashModel;
    private CashTransactionItem $itemModel;
    private CashCategory $categoryModel;
    private UserPicAssignment $picModel;
    private ActivityLog $activityLog;

    public function __construct()
    {
        Middleware::requirePermission('cash', 'view');

        $this->cashModel     = new CashTransaction();
        $this->itemModel     = new CashTransactionItem();
        $this->categoryModel = new CashCategory();
        $this->picModel      = new UserPicAssignment();
        $this->activityLog   = new ActivityLog();
    }

    // ===================== LIST =====================

    public function index(): void
    {
        $filters = $this->collectFilters();
        $scope = $this->scopePics();
        $rows  = $this->cashModel->listFiltered($filters, $scope);

        $summary = ['masuk' => 0.0, 'keluar' => 0.0];
        foreach ($rows as $r) {
            $summary[$r['mutasi']] += (float) $r['total_amount'];
        }

        $this->view('cash/list', [
            'pageTitle'  => 'Kas',
            'rows'       => $rows,
            'filters'    => $filters,
            'categories' => $this->categoryModel->activeList(),
            'picOptions' => $scope === null ? $this->cashModel->distinctPics(null) : $scope,
            'scoped'     => $scope !== null,
            'summary'    => $summary,
        ]);
    }

    // ===================== LAPORAN KAS =====================

    public function report(): void
    {
        $filters = $this->collectFilters();
        $scope   = $this->scopePics();
        $saldoAwal = $this->cashModel->saldoAwal($filters, $scope);
        $ledger  = $this->cashModel->reportLedger($filters, $scope, $saldoAwal);

        $this->view('cash/report', [
            'pageTitle'  => 'Laporan Kas',
            'ledger'     => $ledger,
            'filters'    => $filters,
            'categories' => $this->categoryModel->activeList(),
            'picOptions' => $scope === null ? $this->cashModel->distinctPics(null) : $scope,
        ]);
    }

    public function printReport(): void
    {
        [$ledger, $meta] = $this->buildReportData();
        ob_start();
        $company = $meta['company'];
        $periodText = $meta['period'];
        require ROOT_PATH . '/app/views/cash/_report_pdf.php';
        $html = ob_get_clean();
        streamPdf($html, 'laporan_kas_' . date('Ymd_His'));
    }

    public function exportReport(): void
    {
        [$ledger, $meta] = $this->buildReportData();
        streamCashReportExcel(
            $ledger,
            $meta['company'],
            $meta['period'],
            'laporan_kas_' . date('Ymd_His')
        );
    }

    // ===================== CREATE =====================

    public function create(): void
    {
        Middleware::requirePermission('cash', 'create');

        $this->view('cash/form', [
            'pageTitle'  => 'Tambah Kas',
            'mode'       => 'create',
            'cash'       => null,
            'items'      => [],
            'categories' => $this->categoryModel->activeList(),
            'picOptions' => $this->picFieldOptions(),
        ]);
    }

    public function store(): void
    {
        Middleware::requirePermission('cash', 'create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('cash', 'create');
        }
        verifyCsrf();

        $data  = $this->collectInput();
        $items = $this->collectItems();
        $errors = $this->validateInput($data, $items, null);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('cash', 'create');
        }

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            $trxId = $this->cashModel->create([
                'trx_date'     => $data['trx_date'],
                'pic'          => $data['pic'],
                'no_bukti'     => $data['no_bukti'],
                'category_id'  => $data['category_id'],
                'mutasi'       => $data['mutasi'],
                'total_amount' => $this->sumItems($items),
                'created_by'   => currentUserId(),
            ]);
            $this->saveItems($trxId, $items);

            $this->activityLog->log(
                currentUserId(),
                'cash',
                'create',
                "Kas {$data['mutasi']} '{$data['no_bukti']}' (PIC {$data['pic']}) dibuat, "
                    . count($items) . ' item, total ' . formatRupiah($this->sumItems($items))
            );

            $pdo->commit();
            setFlash('success', 'Transaksi Kas berhasil disimpan.');
            $this->redirect('cash', 'index');
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Cash store error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan transaksi Kas. Silakan coba lagi.');
            $this->redirect('cash', 'create');
        }
    }

    // ===================== EDIT =====================

    public function edit(): void
    {
        Middleware::requirePermission('cash', 'edit');

        $id = (int) ($_GET['id'] ?? 0);
        $row = $this->cashModel->findWithRelations($id);
        if (!$row) {
            setFlash('error', 'Transaksi Kas tidak ditemukan.');
            $this->redirect('cash', 'index');
        }
        $this->assertCanTouch($row);

        $this->view('cash/form', [
            'pageTitle'  => 'Edit Kas',
            'mode'       => 'edit',
            'cash'       => $row,
            'items'      => $this->itemModel->byTransaction($id),
            'categories' => $this->categoryModel->activeList(),
            'picOptions' => $this->picFieldOptions($row['pic']),
        ]);
    }

    public function update(): void
    {
        Middleware::requirePermission('cash', 'edit');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('cash', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->cashModel->findWithRelations($id);
        if (!$existing) {
            setFlash('error', 'Transaksi Kas tidak ditemukan.');
            $this->redirect('cash', 'index');
        }
        $this->assertCanTouch($existing);

        $data  = $this->collectInput();
        $items = $this->collectItems();
        $errors = $this->validateInput($data, $items, $id);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('cash', 'edit', ['id' => $id]);
        }

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            $this->cashModel->updateById($id, [
                'trx_date'     => $data['trx_date'],
                'pic'          => $data['pic'],
                'no_bukti'     => $data['no_bukti'],
                'category_id'  => $data['category_id'],
                'mutasi'       => $data['mutasi'],
                'total_amount' => $this->sumItems($items),
            ]);
            $this->itemModel->deleteByTransaction($id);
            $this->saveItems($id, $items);

            $this->activityLog->log(currentUserId(), 'cash', 'update', "Kas #{$id} ('{$data['no_bukti']}') diperbarui");

            $pdo->commit();
            setFlash('success', 'Transaksi Kas berhasil diperbarui.');
            $this->redirect('cash', 'index');
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Cash update error: ' . $e->getMessage());
            setFlash('error', 'Gagal memperbarui transaksi Kas.');
            $this->redirect('cash', 'edit', ['id' => $id]);
        }
    }

    // ===================== DELETE (soft -> Trash) =====================

    public function delete(): void
    {
        Middleware::requirePermission('cash', 'delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('cash', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $row = $this->cashModel->findWithRelations($id);
        if (!$row) {
            setFlash('error', 'Transaksi Kas tidak ditemukan.');
            $this->redirect('cash', 'index');
        }
        $this->assertCanTouch($row);

        $this->cashModel->deleteById($id);
        $this->activityLog->log(currentUserId(), 'cash', 'delete', "Kas #{$id} ('{$row['no_bukti']}') dihapus ke Tempat Sampah");
        setFlash('success', 'Transaksi Kas dipindahkan ke Tempat Sampah.');
        $this->redirect('cash', 'index');
    }

    // ===================== AJAX: baris item baru =====================

    public function ajaxItemRow(): void
    {
        Middleware::requirePermission('cash', 'create');
        $index = (int) ($_GET['index'] ?? 0);
        $item = null;
        ob_start();
        require ROOT_PATH . '/app/views/cash/_item_row.php';
        $html = ob_get_clean();
        $this->json(['html' => $html]);
    }

    // ===================== Helper privat =====================

    private function collectFilters(): array
    {
        return [
            'date_from'   => $_GET['date_from'] ?? '',
            'date_to'     => $_GET['date_to'] ?? '',
            'pic'         => trim($_GET['pic'] ?? ''),
            'category_id' => $_GET['category_id'] ?? '',
            'mutasi'      => $_GET['mutasi'] ?? '',
        ];
    }

    /** [$ledger, ['company'=>.., 'period'=>..]] untuk PDF/Excel laporan. */
    private function buildReportData(): array
    {
        $filters = $this->collectFilters();
        $scope   = $this->scopePics();
        $saldoAwal = $this->cashModel->saldoAwal($filters, $scope);
        $ledger  = $this->cashModel->reportLedger($filters, $scope, $saldoAwal);

        $company = (new SystemSetting())->getGroup('company');
        $companyName = $company['company_name'] ?: 'Perusahaan';
        $period = 'Periode : '
            . (!empty($filters['date_from']) ? formatTanggal($filters['date_from']) : 'Awal')
            . ' - '
            . (!empty($filters['date_to']) ? formatTanggal($filters['date_to']) : 'Sekarang');

        return [$ledger, ['company' => $companyName, 'period' => $period]];
    }

    /** null = lihat semua; array = daftar PIC terkait (bisa kosong). */
    private function scopePics(): ?array
    {
        $role = currentUserRole();
        if (in_array($role, [ROLE_SUPER_ADMIN, ROLE_ACCOUNTING, ROLE_PROJECT_MANAGER], true)) {
            return null;
        }
        return $this->picModel->picNamesForUser((int) currentUserId());
    }

    /** Pilihan dropdown PIC di form. Role ber-scope: hanya PIC terkaitnya.
     *  Role lihat-semua: semua PIC di master mapping. $current dipertahankan
     *  supaya data lama tetap terpilih walau tidak lagi di daftar. */
    private function picFieldOptions(?string $current = null): array
    {
        $scope = $this->scopePics();
        $opts = $scope !== null ? $scope : $this->picModel->allPicNames();
        if ($current !== null && $current !== '' && !in_array($current, $opts, true)) {
            $opts[] = $current;
        }
        sort($opts);
        return $opts;
    }

    private function assertCanTouch(array $row): void
    {
        $scope = $this->scopePics();
        if ($scope !== null && !in_array($row['pic'], $scope, true)) {
            denyAccess('Percobaan akses transaksi Kas milik PIC lain');
        }
    }

    private function collectInput(): array
    {
        return [
            'trx_date'    => trim($_POST['trx_date'] ?? ''),
            'pic'         => trim($_POST['pic'] ?? ''),
            'no_bukti'    => trim($_POST['no_bukti'] ?? ''),
            'category_id' => (int) ($_POST['category_id'] ?? 0),
            'mutasi'      => ($_POST['mutasi'] ?? '') === 'masuk' ? 'masuk'
                : (($_POST['mutasi'] ?? '') === 'keluar' ? 'keluar' : ''),
        ];
    }

    /** Array baris item bersih dari POST (uraian[]/qty[]/satuan[]). */
    private function collectItems(): array
    {
        $uraian = $_POST['item_uraian'] ?? [];
        $qty    = $_POST['item_qty'] ?? [];
        $satuan = $_POST['item_satuan'] ?? [];
        $out = [];
        for ($i = 0; $i < count($uraian); $i++) {
            $u = trim((string) ($uraian[$i] ?? ''));
            $q = (float) ($qty[$i] ?? 0);
            $s = parseCurrencyInput($satuan[$i] ?? 0);
            if ($u === '' && $q <= 0 && $s <= 0) {
                continue; // baris kosong -> abaikan
            }
            $out[] = ['uraian' => $u, 'qty' => $q, 'satuan' => $s, 'jumlah' => round($q * $s, 2)];
        }
        return $out;
    }

    private function sumItems(array $items): float
    {
        return round(array_sum(array_column($items, 'jumlah')), 2);
    }

    private function saveItems(int $trxId, array $items): void
    {
        foreach ($items as $it) {
            $this->itemModel->create([
                'cash_transaction_id' => $trxId,
                'uraian' => $it['uraian'],
                'qty'    => $it['qty'],
                'satuan' => $it['satuan'],
                'jumlah' => $it['jumlah'],
            ]);
        }
    }

    private function validateInput(array $d, array $items, ?int $excludeId): array
    {
        $errors = [];

        if ($d['trx_date'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['trx_date'])) {
            $errors[] = 'Tanggal wajib diisi.';
        }

        $scope = $this->scopePics();
        if ($d['pic'] === '') {
            $errors[] = 'PIC wajib diisi.';
        } elseif ($scope !== null && !in_array($d['pic'], $scope, true)) {
            $errors[] = 'PIC tidak valid untuk akun Anda.';
        }

        if ($d['no_bukti'] === '') {
            $errors[] = 'No Bukti wajib diisi.';
        } elseif ($this->cashModel->noBuktiExists($d['no_bukti'], $excludeId)) {
            $errors[] = 'No Bukti sudah dipakai transaksi lain.';
        }

        if ($d['category_id'] <= 0 || !$this->categoryModel->find($d['category_id'])) {
            $errors[] = 'Kategori Kas wajib dipilih.';
        }

        if ($d['mutasi'] === '') {
            $errors[] = 'Mutasi wajib dipilih (Masuk / Keluar).';
        }

        if (empty($items)) {
            $errors[] = 'Minimal 1 baris rincian (Uraian, Qty, Satuan) wajib diisi.';
        } else {
            foreach ($items as $i => $it) {
                $n = $i + 1;
                if ($it['uraian'] === '') {
                    $errors[] = "Baris {$n}: Uraian wajib diisi.";
                }
                if ($it['qty'] <= 0) {
                    $errors[] = "Baris {$n}: Qty harus lebih dari 0.";
                }
                if ($it['satuan'] < 0) {
                    $errors[] = "Baris {$n}: Satuan tidak valid.";
                }
            }
        }

        return $errors;
    }
}
