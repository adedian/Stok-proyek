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

        $data = [
            'delivery_number'  => $this->deliveryNoteModel->generateDeliveryNumber($deliveryDate),
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

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            // Lepaskan baris stock_out yang terkelompok di sini -- transaksi
            // pengeluaran barangnya sendiri TIDAK dihapus, cuma dilepas dari Surat
            // Jalan supaya bisa dikelompokkan ulang.
            $this->stockOutModel->unlinkDeliveryNote($id);

            $this->deliveryNoteModel->deleteById($id);
            $this->activityLog->log(currentUserId(), 'delivery_note', 'delete', "Surat Jalan {$note['delivery_number']} dihapus");

            $pdo->commit();
            setFlash('success', 'Surat Jalan berhasil dihapus. Baris Pengeluaran Barang terkait dikembalikan sebagai belum dikelompokkan.');
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('DeliveryNote delete error: ' . $e->getMessage());
            setFlash('error', 'Gagal menghapus Surat Jalan.');
        }

        $this->redirect('delivery_note', 'index');
    }

    // ================= Helper privat =================

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
        foreach ($ids as $id) {
            $row = $this->stockOutModel->findWithRelations($id);
            if ($row && empty($row['delivery_note_id'])) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
}
