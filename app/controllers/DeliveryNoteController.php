<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/DeliveryNote.php';
require_once ROOT_PATH . '/app/models/StockOut.php';
require_once ROOT_PATH . '/app/models/Client.php';
require_once ROOT_PATH . '/app/models/Project.php';
require_once ROOT_PATH . '/app/models/Signature.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

/**
 * DeliveryNoteController -- Surat Jalan.
 * TIDAK membuat transaksi pengeluaran barang baru. Modul ini murni mengelompokkan
 * baris stock_out yang sudah ada (dipilih via checkbox di Pengeluaran Barang)
 * jadi satu dokumen cetak. Lihat migration 2026_08_24_* untuk konteksnya.
 */
class DeliveryNoteController extends Controller
{
    private DeliveryNote $deliveryNoteModel;
    private StockOut $stockOutModel;
    private Client $clientModel;
    private Project $projectModel;
    private Signature $signatureModel;
    private ActivityLog $activityLog;

    public function __construct()
    {
        Middleware::requirePermission('delivery_note', 'view');

        $this->deliveryNoteModel = new DeliveryNote();
        $this->stockOutModel     = new StockOut();
        $this->clientModel       = new Client();
        $this->projectModel      = new Project();
        $this->signatureModel    = new Signature();
        $this->activityLog       = new ActivityLog();
    }

    public function index()
    {
        $filters = [
            'keyword'   => trim($_GET['keyword'] ?? ''),
            'date_from' => $_GET['date_from'] ?? '',
            'date_to'   => $_GET['date_to'] ?? '',
        ];

        $this->view('delivery_note/list', [
            'pageTitle' => 'Riwayat Surat Jalan',
            'notes'     => $this->deliveryNoteModel->listWithRelations($filters),
            'filters'   => $filters,
        ]);
    }

    /**
     * Form kecil: pilih tujuan (project/client) + kendaraan/driver/penerima/TTD
     * untuk baris stock_out yang sudah dicentang di Pengeluaran Barang.
     */
    public function select()
    {
        Middleware::requirePermission('delivery_note', 'create');

        $ids = $this->parseIds($_GET['ids'] ?? []);
        $rows = $this->validRows($ids);

        if (empty($rows)) {
            setFlash('error', 'Pilih minimal 1 baris Pengeluaran Barang yang belum masuk Surat Jalan manapun.');
            $this->redirect('stock_out', 'index');
        }
        // Daftar ID final (sudah diperluas ke seluruh barang satu dokumen).
        $ids = array_map(fn($r) => (int) $r['id'], $rows);

        $this->view('delivery_note/select', [
            'pageTitle' => 'Buat Surat Jalan',
            'rows'      => $rows,
            'ids'       => $ids,
            'clients'   => $this->clientModel->activeList(),
            'projects'  => $this->projectModel->activeList(),
            'signatures' => $this->signatureModel->activeList(),
        ]);
    }

    public function store()
    {
        Middleware::requirePermission('delivery_note', 'create');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('stock_out', 'index');
        }
        verifyCsrf();

        $ids = $this->parseIds($_POST['ids'] ?? []);
        $rows = $this->validRows($ids);

        if (empty($rows)) {
            setFlash('error', 'Baris Pengeluaran Barang yang dipilih tidak valid atau sudah masuk Surat Jalan lain.');
            $this->redirect('stock_out', 'index');
        }

        $destinationType = in_array($_POST['destination_type'] ?? 'project', ['client', 'manual'], true)
            ? $_POST['destination_type']
            : 'project';
        $clientId = !empty($_POST['client_id']) ? (int) $_POST['client_id'] : null;
        $projectId = !empty($_POST['project_id']) ? (int) $_POST['project_id'] : null;
        $destinationName = trim($_POST['destination_name'] ?? '');

        if ($destinationType === 'client' && ($clientId === null || !$this->clientModel->find($clientId))) {
            setFlash('error', 'Client tujuan wajib dipilih untuk pengiriman penjualan.');
            $this->redirect('delivery_note', 'select', ['ids' => implode(',', $ids)]);
        }

        if ($destinationType === 'manual' && $destinationName === '') {
            setFlash('error', 'Nama Tujuan wajib diisi untuk tujuan pengiriman manual.');
            $this->redirect('delivery_note', 'select', ['ids' => implode(',', $ids)]);
        }

        $deliveryDate = $_POST['delivery_date'] ?: date('Y-m-d');
        if (!isValidDateString($deliveryDate)) {
            setFlash('error', 'Tanggal Surat Jalan tidak valid.');
            $this->redirect('delivery_note', 'select', ['ids' => implode(',', $ids)]);
        }

        // No. Surat Jalan diketik manual -- wajib, maks 50 karakter, unik (termasuk Tempat Sampah).
        $numberRaw = $_POST['delivery_number'] ?? '';
        $deliveryNumber = is_string($numberRaw) ? trim(preg_replace('/\s+/', ' ', $numberRaw)) : '';
        $numberError = null;
        if ($deliveryNumber === '') {
            $numberError = 'No. Surat Jalan wajib diisi.';
        } elseif (mb_strlen($deliveryNumber) > 50) {
            $numberError = 'No. Surat Jalan maksimal 50 karakter.';
        } elseif ($this->deliveryNoteModel->numberTaken($deliveryNumber)) {
            $numberError = "No. Surat Jalan {$deliveryNumber} sudah dipakai Surat Jalan lain (termasuk yang ada di Tempat Sampah).";
        }
        if ($numberError !== null) {
            setFlash('error', $numberError);
            $this->redirect('delivery_note', 'select', ['ids' => implode(',', $ids)]);
        }

        $data = [
            'delivery_number'  => $deliveryNumber,
            'delivery_date'    => $deliveryDate,
            'destination_type' => $destinationType,
            'client_id'        => $destinationType === 'client' ? $clientId : null,
            'project_id'       => $destinationType === 'project' ? $projectId : null,
            'destination_name' => $destinationName ?: null,
            // Kota tempat serah terima ditandatangani (baris penutup Surat Jalan) --
            // SENGAJA field terpisah dari destination_name/project/client: nama
            // project/client bisa saja bukan nama kota (mis. "PT Pei Hai Phase 2"),
            // itu bug yang diperbaiki di revisi ini. Di-prefill dari lokasi project
            // di form (lihat select.php), tapi tetap bebas diedit manual.
            'city'             => trim($_POST['city'] ?? '') ?: null,
            'vehicle_number'   => trim($_POST['vehicle_number'] ?? '') ?: null,
            'driver_name'      => trim($_POST['driver_name'] ?? '') ?: null,
            'sender_name'      => trim($_POST['sender_name'] ?? '') ?: null,
            'recipient_name'   => trim($_POST['recipient_name'] ?? '') ?: null,
            'signature_id'     => !empty($_POST['signature_id']) ? (int) $_POST['signature_id'] : null,
            'notes'            => trim($_POST['notes'] ?? '') ?: null,
            'created_by'       => currentUserId(),
        ];

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            $deliveryNoteId = $this->deliveryNoteModel->create($data);

            foreach ($rows as $row) {
                $this->stockOutModel->updateById((int) $row['id'], ['delivery_note_id' => $deliveryNoteId]);
            }

            $this->activityLog->log(
                currentUserId(),
                'delivery_note',
                'create',
                "Surat Jalan {$data['delivery_number']} dibuat dari " . count($rows) . ' baris Pengeluaran Barang'
            );

            $pdo->commit();
            setFlash('success', "Surat Jalan {$data['delivery_number']} berhasil dibuat.");
            $this->redirect('delivery_note', 'print', ['id' => $deliveryNoteId]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('DeliveryNote store error: ' . $e->getMessage());
            setFlash('error', 'Gagal membuat Surat Jalan. Silakan coba lagi.');
            $this->redirect('stock_out', 'index');
        }
    }

    /**
     * Edit header Surat Jalan (nomor, tanggal, tujuan, kendaraan, driver, dst).
     * Barang di dalamnya TIDAK diubah di sini -- itu tetap hasil pengelompokan
     * Pengeluaran Barang. Izin delivery_note.edit: Super Admin + akun Nissa.
     */
    public function edit()
    {
        Middleware::requirePermission('delivery_note', 'edit');

        $id = (int) ($_GET['id'] ?? 0);
        $note = $this->deliveryNoteModel->findWithRelations($id);
        if (!$note) {
            setFlash('error', 'Surat Jalan tidak ditemukan.');
            $this->redirect('stock_out', 'index');
        }

        $this->view('delivery_note/edit', [
            'pageTitle'  => 'Edit Surat Jalan',
            'note'       => $note,
            'items'      => $this->deliveryNoteModel->itemsByDeliveryNote($id),
            'clients'    => $this->clientModel->activeList(),
            'projects'   => $this->projectModel->activeList(),
            'signatures' => $this->signatureModel->activeList(),
        ]);
    }

    public function update()
    {
        Middleware::requirePermission('delivery_note', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('stock_out', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->deliveryNoteModel->find($id);
        if (!$existing) {
            setFlash('error', 'Surat Jalan tidak ditemukan.');
            $this->redirect('stock_out', 'index');
        }

        $destinationType = in_array($_POST['destination_type'] ?? 'project', ['client', 'manual'], true)
            ? $_POST['destination_type']
            : 'project';
        $clientId = !empty($_POST['client_id']) ? (int) $_POST['client_id'] : null;
        $projectId = !empty($_POST['project_id']) ? (int) $_POST['project_id'] : null;
        $destinationName = trim($_POST['destination_name'] ?? '');

        $error = null;
        $deliveryDate = $_POST['delivery_date'] ?: date('Y-m-d');
        $numberRaw = $_POST['delivery_number'] ?? '';
        $deliveryNumber = is_string($numberRaw) ? trim(preg_replace('/\s+/', ' ', $numberRaw)) : '';

        if ($destinationType === 'client' && ($clientId === null || !$this->clientModel->find($clientId))) {
            $error = 'Client tujuan wajib dipilih untuk pengiriman penjualan.';
        } elseif ($destinationType === 'manual' && $destinationName === '') {
            $error = 'Nama Tujuan wajib diisi untuk tujuan pengiriman manual.';
        } elseif (!isValidDateString($deliveryDate)) {
            $error = 'Tanggal Surat Jalan tidak valid.';
        } elseif ($deliveryNumber === '') {
            $error = 'No. Surat Jalan wajib diisi.';
        } elseif (mb_strlen($deliveryNumber) > 50) {
            $error = 'No. Surat Jalan maksimal 50 karakter.';
        } elseif ($this->deliveryNoteModel->numberTaken($deliveryNumber, $id)) {
            $error = "No. Surat Jalan {$deliveryNumber} sudah dipakai Surat Jalan lain (termasuk yang ada di Tempat Sampah).";
        }
        if ($error !== null) {
            setFlash('error', $error);
            $this->redirect('delivery_note', 'edit', ['id' => $id]);
        }

        $data = [
            'delivery_number'  => $deliveryNumber,
            'delivery_date'    => $deliveryDate,
            'destination_type' => $destinationType,
            'client_id'        => $destinationType === 'client' ? $clientId : null,
            'project_id'       => $destinationType === 'project' ? $projectId : null,
            'destination_name' => $destinationName ?: null,
            'city'             => trim($_POST['city'] ?? '') ?: null,
            'vehicle_number'   => trim($_POST['vehicle_number'] ?? '') ?: null,
            'driver_name'      => trim($_POST['driver_name'] ?? '') ?: null,
            'sender_name'      => trim($_POST['sender_name'] ?? '') ?: null,
            'recipient_name'   => trim($_POST['recipient_name'] ?? '') ?: null,
            'signature_id'     => !empty($_POST['signature_id']) ? (int) $_POST['signature_id'] : null,
            'notes'            => trim($_POST['notes'] ?? '') ?: null,
        ];

        try {
            $this->deliveryNoteModel->updateById($id, $data);
            $logMsg = $deliveryNumber !== $existing['delivery_number']
                ? "Surat Jalan {$existing['delivery_number']} diperbarui (nomor diubah menjadi {$deliveryNumber})"
                : "Surat Jalan {$existing['delivery_number']} diperbarui";
            $this->activityLog->log(currentUserId(), 'delivery_note', 'update', $logMsg);
            setFlash('success', "Surat Jalan {$deliveryNumber} berhasil diperbarui.");
            $this->redirect('stock_out', 'index');
        } catch (Throwable $e) {
            error_log('DeliveryNote update error: ' . $e->getMessage());
            setFlash('error', 'Gagal memperbarui Surat Jalan. Silakan coba lagi.');
            $this->redirect('delivery_note', 'edit', ['id' => $id]);
        }
    }

    public function print()
    {
        $id = (int) ($_GET['id'] ?? 0);
        $note = $this->deliveryNoteModel->findWithRelations($id);

        if (!$note) {
            setFlash('error', 'Surat Jalan tidak ditemukan.');
            $this->redirect('delivery_note', 'index');
        }

        $note['items'] = $this->deliveryNoteModel->itemsByDeliveryNote($id);

        $this->view('delivery_note/print', [
            'pageTitle' => 'Cetak Surat Jalan',
            'documentTitle' => $this->printFileTitle([$note]),
            'notes'     => [$note],
            'company'   => (new SystemSetting())->getGroup('company'),
        ]);
    }

    /**
     * Cetak Terpilih/Cetak Semua dari Riwayat Surat Jalan -- beberapa Surat Jalan
     * sekaligus, template yang sama, page-break antar dokumen (lihat
     * delivery_note/print.php). Tanpa ?ids= sama sekali -> "Cetak Semua" ikut
     * filter tanggal/keyword yang aktif saat itu, BUKAN seluruh tabel.
     */
    public function printMany()
    {
        $idsParam = $_GET['ids'] ?? [];
        $ids = $this->parseIds($idsParam);

        if (!empty($ids)) {
            $noteIds = $ids;
        } else {
            $filters = [
                'keyword'   => trim($_GET['keyword'] ?? ''),
                'date_from' => $_GET['date_from'] ?? '',
                'date_to'   => $_GET['date_to'] ?? '',
            ];
            $noteIds = array_column($this->deliveryNoteModel->listWithRelations($filters), 'id');
        }

        $notes = [];
        foreach ($noteIds as $id) {
            $note = $this->deliveryNoteModel->findWithRelations((int) $id);
            if ($note) {
                $note['items'] = $this->deliveryNoteModel->itemsByDeliveryNote((int) $id);
                $notes[] = $note;
            }
        }

        if (empty($notes)) {
            setFlash('error', 'Surat Jalan yang dipilih tidak ditemukan.');
            $this->redirect('delivery_note', 'index');
        }

        $this->view('delivery_note/print', [
            'pageTitle' => 'Cetak Surat Jalan',
            'documentTitle' => $this->printFileTitle($notes),
            'notes'     => $notes,
            'company'   => (new SystemSetting())->getGroup('company'),
        ]);
    }

    public function delete()
    {
        Middleware::requirePermission('delivery_note', 'delete');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('delivery_note', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $note = $this->deliveryNoteModel->find($id);

        if (!$note) {
            setFlash('error', 'Surat Jalan tidak ditemukan.');
            $this->redirect('delivery_note', 'index');
        }

        $res = $this->deleteOneRecord($id);
        setFlash($res === true ? 'success' : 'error',
            $res === true
                ? 'Surat Jalan berhasil dihapus. Baris Pengeluaran Barang terkait dikembalikan sebagai belum dikelompokkan.'
                : 'Gagal menghapus Surat Jalan.');

        $this->redirect('delivery_note', 'index');
    }

    /**
     * Hapus 1 Surat Jalan ke Tempat Sampah + lepaskan baris Pengeluaran Barang
     * yang terkelompok di sini. true = sukses, string = alasan skip.
     */
    private function deleteOneRecord(int $id)
    {
        $note = $this->deliveryNoteModel->find($id);
        if (!$note) {
            return 'gagal';
        }
        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            // Transaksi pengeluaran barangnya TIDAK dihapus, cuma dilepas dari
            // Surat Jalan supaya bisa dikelompokkan ulang.
            $this->stockOutModel->unlinkDeliveryNote($id);
            $this->deliveryNoteModel->deleteById($id);
            $this->activityLog->log(currentUserId(), 'delivery_note', 'delete', "Surat Jalan {$note['delivery_number']} dihapus");
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('DeliveryNote deleteOneRecord error: ' . $e->getMessage());
            return 'gagal';
        }
    }

    /** Hapus semua Surat Jalan dalam rentang tanggal ke Tempat Sampah -- KHUSUS Super Admin. */
    public function rangeDelete()
    {
        rangeDeleteGuardSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('delivery_note', 'index');
        }
        verifyCsrf();

        [$from, $to] = rangeDeleteReadDates();
        if ($err = rangeDeleteValidate($from, $to)) {
            setFlash('error', $err);
            $this->redirect('delivery_note', 'index');
        }

        $deleted = 0;
        $skipped = [];
        foreach ($this->deliveryNoteModel->idsByDateRange('delivery_date', $from, $to) as $id) {
            $r = $this->deleteOneRecord($id);
            if ($r === true) {
                $deleted++;
            } else {
                $skipped[$r] = ($skipped[$r] ?? 0) + 1;
            }
        }

        rangeDeleteLog('delivery_note', $from, $to, $deleted, array_sum($skipped));
        rangeDeleteFlash($deleted, $skipped);
        $this->redirect('delivery_note', 'index');
    }

    // ================= Helper privat =================

    /**
     * Judul halaman cetak = nama file default saat "Save as PDF" di browser.
     * Satu dokumen: "Surat_Jalan 011_SJ.HME_X_2026" (garis miring diganti garis bawah
     * karena tak boleh ada di nama file). Banyak dokumen: ringkasan jumlahnya.
     */
    private function printFileTitle(array $notes): string
    {
        if (count($notes) === 1) {
            return 'Surat_Jalan ' . str_replace(['/', '\\'], '_', (string) $notes[0]['delivery_number']);
        }
        return 'Surat_Jalan (' . count($notes) . ' dokumen)';
    }

    private function parseIds($idsParam): array
    {
        if (is_string($idsParam)) {
            $idsParam = explode(',', $idsParam);
        }
        return array_values(array_unique(array_filter(array_map('intval', $idsParam), fn($id) => $id > 0)));
    }

    /**
     * Ambil baris stock_out yang valid untuk dikelompokkan: ID ada, belum
     * dihapus, dan belum terpasang ke Surat Jalan manapun (delivery_note_id
     * IS NULL) -- baris yang sudah dipakai TIDAK BOLEH dobel-masuk dokumen lain.
     */
    private function validRows(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $rows = [];
        $seenDocs = [];
        foreach ($ids as $id) {
            $row = $this->stockOutModel->findWithRelations($id);
            if (!$row) {
                continue;
            }
            // Satu dokumen pengeluaran (banyak barang, 1 nomor) = SATU Surat Jalan:
            // mencentang salah satu barangnya otomatis menyertakan barang lain dari
            // dokumen yang sama, jadi tidak mungkin terbit SJ berbeda per barang.
            $docId = (int) ($row['document_id'] ?: $row['id']);
            if (isset($seenDocs[$docId])) {
                continue;
            }
            $seenDocs[$docId] = true;
            foreach ($this->stockOutModel->documentRows($docId) as $docRow) {
                if (empty($docRow['delivery_note_id'])) {
                    $rows[] = $this->stockOutModel->findWithRelations((int) $docRow['id']);
                }
            }
        }
        return $rows;
    }
}
