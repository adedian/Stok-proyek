<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * RekapPpn -- register PPN Masukan + PPN Keluaran (acuan: sheet "PPN Masukan" & "PPN Keluaran" pada Pengajuan fee.xlsx).
 *
 * RUMUS (persis Excel; nominal dibulatkan per sen tiap baris):
 *   PPN MASUKAN (input: DPP)
 *     DPP Nilai Lain = DPP x 11/12                 (Excel: =H2*11/12)
 *     PPN            = DPP Nilai Lain x 12%        (Excel: =I2*12%)   -> sama dengan DPP x 11%
 *     Jumlah         = DPP + PPN                   (Excel: =H2+J2)
 *   PPN KELUARAN (input: Qty, Harga Satuan yang SUDAH termasuk PPN 11%; Pricelist opsional)
 *     Subtotal       = Harga Satuan x Qty          (Excel: =K2*H2)
 *     DPP            = Qty x (Harga Satuan / 1,11) (Excel: =H2*(K2/1.11))
 *     DPP Nilai Lain = DPP x 11/12                 (Excel: =M2*11/12)
 *     PPN            = DPP Nilai Lain x 12%        (Excel: =N2*12%)
 *     Total          = DPP + PPN                   (Excel: =M2+O2) -> sama dengan Subtotal
 *     Diskon %       = (Pricelist - Harga Satuan) / Pricelist   (Excel: =((I2-K2)/I2)*100%) -- hanya tampilan.
 *   PPN Keluaran dihitung dari Subtotal agar Total selalu = Subtotal tanpa selisih sen: DPP = round(Subtotal / 1,11, 2),
 *   PPN = Subtotal - DPP (selisih dengan Excel < Rp 0,01 per baris).
 *
 * RINGKASAN per masa (blok di bawah sheet "PPN Masukan"):
 *   Pajak Keluaran, Pajak Masukan, PPN Kurang (Lebih) Bayar = Keluaran - Masukan, dikurangi Lebih Bayar masa sebelumnya
 *   (kompensasi) = PPN yang harus disetor. Di Excel selisihnya dihitung Masukan - Keluaran sehingga tanda minus berarti
 *   "kurang bayar"; di sistem dibalik supaya angka positif = harus disetor, negatif = lebih bayar.
 */
class RekapPpn extends Model
{
    protected string $table = 'ppn_masukan';
    protected bool $softDelete = true;

    public const MAX_AMOUNT = 999999999999.0;
    public const PPN_DIVISOR = 1.11;

    public const MONTHS = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];

    public static function monthName(int $m): string
    {
        return self::MONTHS[$m] ?? '';
    }

    private static function r2(float $x): float
    {
        return round($x + 0.0, 2);
    }

    // =================== Rumus ===================

    public static function computeMasukan(float $dpp): array
    {
        $dnl = self::r2($dpp * 11 / 12);
        $ppn = self::r2($dpp * 11 / 100);   // = DPP Nilai Lain x 12% (tanpa pembulatan antara)
        return ['dpp' => self::r2($dpp), 'dpp_nilai_lain' => $dnl, 'ppn_amount' => $ppn, 'total_amount' => self::r2($dpp + $ppn)];
    }

    public static function computeKeluaranRow(float $qty, float $unitPrice): array
    {
        $subtotal = self::r2($qty * $unitPrice);
        $dpp = self::r2($subtotal / self::PPN_DIVISOR);
        return [
            'subtotal'       => $subtotal,
            'dpp'            => $dpp,
            'dpp_nilai_lain' => self::r2($dpp * 11 / 12),
            'ppn_amount'     => self::r2($subtotal - $dpp),
            'total_amount'   => $subtotal,
        ];
    }

    /** Diskon % tampilan: (Pricelist - Harga) / Pricelist x 100; null bila pricelist kosong. */
    public static function discountPercent($pricelist, $unitPrice): ?float
    {
        $pl = (float) $pricelist;
        return $pl > 0 ? round(($pl - (float) $unitPrice) / $pl * 100, 2) : null;
    }

    /** Jumlahkan baris keluaran -> total header. */
    public static function sumKeluaran(array $rows): array
    {
        $t = ['total_subtotal' => 0.0, 'total_dpp' => 0.0, 'total_dpp_nilai_lain' => 0.0, 'total_ppn' => 0.0, 'total_amount' => 0.0];
        foreach ($rows as $r) {
            $t['total_subtotal'] += $r['subtotal'];
            $t['total_dpp'] += $r['dpp'];
            $t['total_dpp_nilai_lain'] += $r['dpp_nilai_lain'];
            $t['total_ppn'] += $r['ppn_amount'];
            $t['total_amount'] += $r['total_amount'];
        }
        return array_map(fn($v) => self::r2($v), $t);
    }

    // =================== Periode ===================

    /** Masa terbaru yang punya data (Masukan atau Keluaran); bila belum ada data, bulan berjalan. */
    public function latestPeriod(): array
    {
        $a = $this->db->fetchOne("SELECT tahun, masa FROM ppn_masukan WHERE deleted_at IS NULL ORDER BY tahun DESC, masa DESC LIMIT 1");
        $b = $this->db->fetchOne("SELECT YEAR(entry_date) AS tahun, MONTH(entry_date) AS masa FROM ppn_keluaran WHERE deleted_at IS NULL ORDER BY entry_date DESC LIMIT 1");
        $best = null;
        foreach ([$a, $b] as $p) {
            if ($p && ($best === null || ((int) $p['tahun'] * 100 + (int) $p['masa']) > ((int) $best['tahun'] * 100 + (int) $best['masa']))) {
                $best = $p;
            }
        }
        return $best ? ['tahun' => (int) $best['tahun'], 'masa' => (int) $best['masa']] : ['tahun' => (int) date('Y'), 'masa' => (int) date('n')];
    }

    /** Tahun-tahun yang punya data + tahun berjalan, terbaru dulu (untuk dropdown filter). */
    public function years(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT tahun AS y FROM ppn_masukan WHERE deleted_at IS NULL
             UNION SELECT YEAR(entry_date) FROM ppn_keluaran WHERE deleted_at IS NULL"
        );
        $years = array_map(fn($r) => (int) $r['y'], $rows);
        $years[] = (int) date('Y');
        $years = array_values(array_unique($years));
        rsort($years);
        return $years;
    }

    public function getPeriod(int $tahun, int $masa): array
    {
        $r = $this->db->fetchOne("SELECT * FROM ppn_periods WHERE tahun = :t AND masa = :m", ['t' => $tahun, 'm' => $masa]);
        return $r ?: ['tahun' => $tahun, 'masa' => $masa, 'lebih_bayar_sebelumnya' => '0.00', 'notes' => null];
    }

    public function savePeriod(int $tahun, int $masa, float $lebihBayar, ?string $notes, ?int $userId): void
    {
        $this->db->query(
            "INSERT INTO ppn_periods (tahun, masa, lebih_bayar_sebelumnya, notes, updated_by)
             VALUES (:t, :m, :lb, :n, :u)
             ON DUPLICATE KEY UPDATE lebih_bayar_sebelumnya = VALUES(lebih_bayar_sebelumnya), notes = VALUES(notes), updated_by = VALUES(updated_by)",
            ['t' => $tahun, 'm' => $masa, 'lb' => $lebihBayar, 'n' => $notes, 'u' => $userId]
        );
    }

    /** Ringkasan setoran. $masa = 0 -> seluruh tahun (tanpa kompensasi). */
    public function summary(int $tahun, int $masa): array
    {
        $mWhere = $masa > 0 ? ' AND masa = :m' : '';
        $kWhere = $masa > 0 ? ' AND MONTH(entry_date) = :m' : '';
        $p = ['t' => $tahun] + ($masa > 0 ? ['m' => $masa] : []);
        $mk = $this->db->fetchOne("SELECT COUNT(*) AS n, COALESCE(SUM(dpp),0) AS dpp, COALESCE(SUM(ppn_amount),0) AS ppn, COALESCE(SUM(total_amount),0) AS total
                                     FROM ppn_masukan WHERE deleted_at IS NULL AND tahun = :t{$mWhere}", $p);
        $kl = $this->db->fetchOne("SELECT COUNT(*) AS n, COALESCE(SUM(total_dpp),0) AS dpp, COALESCE(SUM(total_ppn),0) AS ppn, COALESCE(SUM(total_amount),0) AS total
                                     FROM ppn_keluaran WHERE deleted_at IS NULL AND YEAR(entry_date) = :t{$kWhere}", $p);
        $period = $masa > 0 ? $this->getPeriod($tahun, $masa) : null;
        $carry = $period ? (float) $period['lebih_bayar_sebelumnya'] : 0.0;
        $selisih = self::r2((float) $kl['ppn'] - (float) $mk['ppn']);
        return [
            'masukan'  => $mk,
            'keluaran' => $kl,
            'selisih'  => $selisih,                       // Keluaran - Masukan (positif = kurang bayar)
            'carry'    => $carry,
            'setor'    => self::r2($selisih - $carry),    // yang harus disetor (negatif = lebih bayar)
            'period'   => $period,
        ];
    }

    // =================== PPN Masukan ===================

    private function masukanWhere(array $f, array &$params): string
    {
        $w = 'deleted_at IS NULL';
        if ($f['tahun'] > 0) {
            $w .= ' AND tahun = :tahun';
            $params['tahun'] = $f['tahun'];
        }
        if ($f['masa'] > 0) {
            $w .= ' AND masa = :masa';
            $params['masa'] = $f['masa'];
        }
        if ($f['q'] !== '') {
            $w .= ' AND (faktur_number LIKE :q1 OR vendor_name LIKE :q2 OR npwp LIKE :q3 OR project_name LIKE :q4 OR notes LIKE :q5)';
            foreach (range(1, 5) as $i) {
                $params["q{$i}"] = '%' . $f['q'] . '%';
            }
        }
        return $w;
    }

    public function masukanList(array $f): array
    {
        $params = [];
        $w = $this->masukanWhere($f, $params);
        return $this->db->fetchAll("SELECT * FROM ppn_masukan WHERE {$w} ORDER BY faktur_date, id", $params);
    }

    public function findMasukan(int $id)
    {
        return $this->find($id);
    }

    /** Faktur dengan nomor sama (aktif) -- peringatan duplikat; $exceptId mengabaikan baris yang sedang diedit. */
    public function masukanDuplicate(string $faktur, int $exceptId = 0): bool
    {
        return (bool) $this->db->fetchOne(
            "SELECT id FROM ppn_masukan WHERE deleted_at IS NULL AND faktur_number = :f AND id <> :id LIMIT 1",
            ['f' => $faktur, 'id' => $exceptId]
        );
    }

    public function vendorNames(): array
    {
        return array_column($this->db->fetchAll("SELECT DISTINCT vendor_name FROM ppn_masukan WHERE deleted_at IS NULL ORDER BY vendor_name"), 'vendor_name');
    }

    /** NPWP terakhir dipakai per nama vendor/customer (untuk isi otomatis di form). */
    public function npwpByVendor(): array
    {
        $out = [];
        foreach ($this->db->fetchAll("SELECT vendor_name, npwp FROM ppn_masukan WHERE deleted_at IS NULL AND npwp IS NOT NULL AND npwp <> '' ORDER BY id") as $r) {
            $out[$r['vendor_name']] = $r['npwp'];
        }
        return $out;
    }

    public function projectNames(): array
    {
        return array_column($this->db->fetchAll("SELECT DISTINCT project_name FROM ppn_masukan WHERE deleted_at IS NULL AND project_name IS NOT NULL AND project_name <> '' ORDER BY project_name"), 'project_name');
    }

    // =================== PPN Keluaran ===================

    private function keluaranWhere(array $f, array &$params): string
    {
        $w = 'k.deleted_at IS NULL';
        if ($f['tahun'] > 0) {
            $w .= ' AND YEAR(k.entry_date) = :tahun';
            $params['tahun'] = $f['tahun'];
        }
        if ($f['masa'] > 0) {
            $w .= ' AND MONTH(k.entry_date) = :masa';
            $params['masa'] = $f['masa'];
        }
        if ($f['q'] !== '') {
            $w .= ' AND (k.invoice_number LIKE :q1 OR k.faktur_number LIKE :q2 OR k.customer_name LIKE :q3 OR k.npwp LIKE :q4 OR k.notes LIKE :q5
                         OR EXISTS (SELECT 1 FROM ppn_keluaran_items i WHERE i.ppn_keluaran_id = k.id AND i.item_name LIKE :q6))';
            foreach (range(1, 6) as $i) {
                $params["q{$i}"] = '%' . $f['q'] . '%';
            }
        }
        return $w;
    }

    /** Header keluaran + item-nya (tiap header punya kunci 'items'). */
    public function keluaranList(array $f): array
    {
        $params = [];
        $w = $this->keluaranWhere($f, $params);
        $rows = $this->db->fetchAll("SELECT k.* FROM ppn_keluaran k WHERE {$w} ORDER BY k.entry_date, k.id", $params);
        if (!$rows) {
            return [];
        }
        $byId = [];
        foreach ($rows as $i => $r) {
            $rows[$i]['items'] = [];
            $byId[(int) $r['id']] = $i;
        }
        $ids = implode(',', array_map('intval', array_keys($byId)));
        foreach ($this->db->fetchAll("SELECT * FROM ppn_keluaran_items WHERE ppn_keluaran_id IN ({$ids}) ORDER BY id") as $it) {
            $rows[$byId[(int) $it['ppn_keluaran_id']]]['items'][] = $it;
        }
        return $rows;
    }

    public function findKeluaran(int $id)
    {
        return $this->db->fetchOne("SELECT * FROM ppn_keluaran WHERE id = :id AND deleted_at IS NULL", ['id' => $id]);
    }

    public function keluaranItems(int $id): array
    {
        return $this->db->fetchAll("SELECT * FROM ppn_keluaran_items WHERE ppn_keluaran_id = :id ORDER BY id", ['id' => $id]);
    }

    public function keluaranDuplicate(string $faktur, int $exceptId = 0): bool
    {
        return $faktur !== '' && (bool) $this->db->fetchOne(
            "SELECT id FROM ppn_keluaran WHERE deleted_at IS NULL AND faktur_number = :f AND id <> :id LIMIT 1",
            ['f' => $faktur, 'id' => $exceptId]
        );
    }

    public function customerNames(): array
    {
        return array_column($this->db->fetchAll("SELECT DISTINCT customer_name FROM ppn_keluaran WHERE deleted_at IS NULL ORDER BY customer_name"), 'customer_name');
    }

    public function npwpByCustomer(): array
    {
        $out = [];
        foreach ($this->db->fetchAll("SELECT customer_name, npwp FROM ppn_keluaran WHERE deleted_at IS NULL AND npwp IS NOT NULL AND npwp <> '' ORDER BY id") as $r) {
            $out[$r['customer_name']] = $r['npwp'];
        }
        return $out;
    }

    public function createKeluaran(array $header, array $items): int
    {
        $id = $this->db->insert('ppn_keluaran', $header);
        $this->replaceKeluaranItems($id, $items);
        return $id;
    }

    public function updateKeluaran(int $id, array $header, array $items): void
    {
        $this->db->update('ppn_keluaran', $header, 'id = :pk', ['pk' => $id]);
        $this->replaceKeluaranItems($id, $items);
    }

    private function replaceKeluaranItems(int $id, array $items): void
    {
        $this->db->query("DELETE FROM ppn_keluaran_items WHERE ppn_keluaran_id = :id", ['id' => $id]);
        foreach ($items as $it) {
            $this->db->insert('ppn_keluaran_items', [
                'ppn_keluaran_id' => $id,
                'item_name'       => $it['item_name'],
                'qty'             => $it['qty'],
                'pricelist'       => $it['pricelist'],
                'unit_price'      => $it['unit_price'],
                'subtotal'        => $it['subtotal'],
                'dpp'             => $it['dpp'],
                'dpp_nilai_lain'  => $it['dpp_nilai_lain'],
                'ppn_amount'      => $it['ppn_amount'],
                'total_amount'    => $it['total_amount'],
            ]);
        }
    }

    public function softDeleteKeluaran(int $id, ?int $userId): int
    {
        return $this->db->update('ppn_keluaran', ['deleted_at' => date('Y-m-d H:i:s'), 'deleted_by' => $userId], 'id = :pk AND deleted_at IS NULL', ['pk' => $id]);
    }

    public function softDeleteMasukan(int $id, ?int $userId): int
    {
        return $this->db->update('ppn_masukan', ['deleted_at' => date('Y-m-d H:i:s'), 'deleted_by' => $userId], 'id = :pk AND deleted_at IS NULL', ['pk' => $id]);
    }
}
