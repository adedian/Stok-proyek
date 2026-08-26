<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * CompanyBankAccount
 * Master rekening perusahaan -- menggantikan 2 field flat company_bank_name/
 * company_bank_account (belum sempat dipakai) supaya bisa >1 rekening dengan
 * TEPAT SATU yang aktif. Rekening aktif itulah yang dipakai di cetak Invoice
 * Keluar (blok "Transfer ke") -- lihat activeAccount().
 */
class CompanyBankAccount extends Model
{
    protected string $table = 'company_bank_accounts';
    protected bool $softDelete = true;

    public function all(string $orderBy = 'id DESC'): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM company_bank_accounts WHERE deleted_at IS NULL ORDER BY {$orderBy}"
        );
    }

    public function activeAccount()
    {
        return $this->db->fetchOne(
            "SELECT * FROM company_bank_accounts WHERE is_active = 1 AND deleted_at IS NULL LIMIT 1"
        );
    }

    /**
     * Aktifkan satu rekening, non-aktifkan sisanya -- SELALU tepat 0 atau 1
     * rekening aktif dalam satu waktu (business rule: Invoice pakai "rekening
     * yang aktif", bukan daftar untuk dipilih per-invoice).
     */
    public function activate(int $id): void
    {
        $this->db->query("UPDATE company_bank_accounts SET is_active = 0 WHERE deleted_at IS NULL");
        $this->db->query(
            "UPDATE company_bank_accounts SET is_active = 1 WHERE id = :id",
            ['id' => $id]
        );
    }
}
