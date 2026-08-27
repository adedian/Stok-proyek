<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * CodeConfig
 * Master Kode v2: menyimpan POLA kode (prefix/digit_length/counter) per kelompok
 * entity -- BUKAN daftar kode itu sendiri. Kode aktual TETAP di items.item_code /
 * suppliers.supplier_code / clients.client_code / warehouses.warehouse_code /
 * projects.project_code masing-masing (single source of truth di tabel master,
 * lihat entityMeta()). Form Tambah Barang/Supplier/Client/Gudang/Project memanggil
 * nextCode() untuk generate kode -- user tidak pernah mengetik nomor manual.
 */
class CodeConfig extends Model
{
    protected string $table = 'code_configs';

    /**
     * Satu-satunya tempat nama tabel/kolom fisik entity dipetakan dari $entityType --
     * whitelist ini mencegah SQL injection lewat nama tabel/kolom dinamis dan jadi
     * acuan bersama untuk MasterKodeController (listing per kelompok) & controller
     * masing-masing entity (generate kode saat create).
     */
    private array $entities = [
        // Barang dipecah 3 kelompok kode (Revisi 7 #23-33) -- masing-masing prefix/
        // sequence SENDIRI-SENDIRI, tapi tetap satu tabel/kolom kode fisik (items.item_code)
        // supaya keunikan kode tetap GLOBAL (dipakai lintas PO/Inventory/Validasi/Laporan) --
        // nextCode() sudah cek collision langsung ke items.item_code apa pun kelompoknya,
        // jadi dua kelompok kebetulan menghasilkan kode yang sama otomatis dihindari.
        'item_stok_proyek' => [
            'table' => 'items', 'code_col' => 'item_code', 'name_col' => 'item_name',
            'label' => 'Barang - Stok Proyek', 'module' => 'item', 'stock_type' => 'stok_proyek',
        ],
        'item_stok_lampu' => [
            'table' => 'items', 'code_col' => 'item_code', 'name_col' => 'item_name',
            'label' => 'Barang - Stok Lampu', 'module' => 'item', 'stock_type' => 'stok_lampu',
        ],
        'item_inventory_kantor' => [
            'table' => 'items', 'code_col' => 'item_code', 'name_col' => 'item_name',
            'label' => 'Barang - Inventory Kantor', 'module' => 'item', 'stock_type' => 'inventory_kantor',
        ],
        'supplier' => [
            'table' => 'suppliers', 'code_col' => 'supplier_code', 'name_col' => 'supplier_name',
            'label' => 'Supplier', 'module' => 'supplier',
        ],
        'client' => [
            'table' => 'clients', 'code_col' => 'client_code', 'name_col' => 'client_name',
            'label' => 'Client', 'module' => 'client',
        ],
        'warehouse' => [
            'table' => 'warehouses', 'code_col' => 'warehouse_code', 'name_col' => 'warehouse_name',
            'label' => 'Gudang', 'module' => 'warehouse',
        ],
        'project' => [
            'table' => 'projects', 'code_col' => 'project_code', 'name_col' => 'project_name',
            'label' => 'Project', 'module' => 'project',
        ],
    ];

    public function entityMeta(string $entityType): ?array
    {
        return $this->entities[$entityType] ?? null;
    }

    public function entityOptions(): array
    {
        $out = [];
        foreach ($this->entities as $key => $meta) {
            $out[$key] = $meta['label'];
        }
        return $out;
    }

    public function getConfig(string $entityType): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM code_configs WHERE entity_type = :t",
            ['t' => $entityType]
        ) ?: null;
    }

    /**
     * Preview format kode dari config saat ini, mis. "BRG-0001" -- HANYA untuk
     * ditampilkan di form (bukan kode final, karena next_number bisa berubah kalau
     * ada request lain lolos duluan). Kode final tetap dari nextCode().
     */
    public function previewFormat(array $config): string
    {
        return $config['prefix'] . '-' . str_pad((string) $config['next_number'], (int) $config['digit_length'], '0', STR_PAD_LEFT);
    }

    public function saveConfig(string $entityType, string $prefix, int $digitLength, ?int $userId): bool
    {
        if (!$this->entityMeta($entityType)) {
            return false;
        }
        $prefix = strtoupper(trim($prefix));
        if ($prefix === '') {
            return false;
        }
        $digitLength = max(1, min(10, $digitLength));

        $existing = $this->getConfig($entityType);
        if ($existing) {
            $this->db->update(
                'code_configs',
                ['prefix' => $prefix, 'digit_length' => $digitLength, 'updated_at' => date('Y-m-d H:i:s')],
                'entity_type = :t',
                ['t' => $entityType]
            );
        } else {
            $this->db->insert('code_configs', [
                'entity_type'  => $entityType,
                'prefix'       => $prefix,
                'digit_length' => $digitLength,
                'next_number'  => 1,
                'status'       => 'active',
                'created_by'   => $userId,
            ]);
        }
        return true;
    }

    /**
     * Generate kode berikutnya untuk $entityType secara ATOMIC (transaction + row
     * lock "FOR UPDATE" pada baris config-nya) -- aman kalau dua user submit form
     * Tambah Barang/Supplier/dst bersamaan, BUKAN naive MAX(code)+1 yang bisa
     * menghasilkan kode duplicate pada request paralel.
     *
     * Return null kalau kelompok ini belum dikonfigurasi (prefix belum diatur) atau
     * nonaktif -- caller WAJIB menolak penyimpanan (lihat instruksi "jika prefix
     * belum diatur, jangan membuat kode sembarangan"), jangan fallback ke apapun.
     *
     * Ada pengecekan collision tambahan langsung ke tabel entity asli (bukan cuma
     * counter) sebagai lapisan kedua kalau ada kode lama yang kebetulan bentrok
     * dengan hasil counter (mis. data lama hasil input manual/seed sebelum fitur ini).
     */
    public function nextCode(string $entityType): ?string
    {
        $meta = $this->entityMeta($entityType);
        if (!$meta) {
            return null;
        }

        // Nesting-safe: kalau caller sudah membuka transaction sendiri, ikut
        // transaction itu (PDO tidak mendukung nested transaction). Commit/rollback
        // diserahkan ke caller; di sini cukup TIDAK menyentuh transaction sama sekali.
        $manageTx = !$this->db->inTransaction();
        if ($manageTx) {
            $this->db->beginTransaction();
        }
        try {
            $config = $this->db->fetchOne(
                "SELECT * FROM code_configs WHERE entity_type = :t AND status = 'active' FOR UPDATE",
                ['t' => $entityType]
            );
            if (!$config) {
                if ($manageTx) {
                    $this->db->rollBack();
                }
                return null;
            }

            $number = (int) $config['next_number'];
            $code = null;
            for ($attempt = 0; $attempt < 50; $attempt++) {
                $candidate = $config['prefix'] . '-' . str_pad((string) $number, (int) $config['digit_length'], '0', STR_PAD_LEFT);
                $collides = $this->db->fetchOne(
                    "SELECT id FROM {$meta['table']} WHERE {$meta['code_col']} = :code",
                    ['code' => $candidate]
                );
                if (!$collides) {
                    $code = $candidate;
                    break;
                }
                $number++;
            }

            if ($code === null) {
                if ($manageTx) {
                    $this->db->rollBack();
                }
                return null;
            }

            $this->db->query(
                "UPDATE code_configs SET next_number = :next WHERE entity_type = :t",
                ['next' => $number + 1, 't' => $entityType]
            );
            if ($manageTx) {
                $this->db->commit();
            }
            return $code;
        } catch (Throwable $e) {
            if ($manageTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
