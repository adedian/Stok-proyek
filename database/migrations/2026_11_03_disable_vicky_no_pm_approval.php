<?php
/**
 * Akun Vicky (Project Manager) dimatikan & seluruh alur yang meminta persetujuannya dibuat TANPA persetujuan (2026-11-03).
 *
 * Kode (bukan data) yang mengubah alur baru:
 *   - Request Budget: slot approval 'pm' otomatis APPROVED saat diajukan (RequestBudget::createApprovals) -> cukup Purchase.
 *   - Kas divisi Project: langsung 'tidak_perlu' (tanpa validasi) -- CashTransaction::create/validationStatusPatch.
 *
 * Migrasi ini membereskan DATA yang sudah ada:
 *   1. Kas divisi Project yang menunggu/ditolak validasi -> 'tidak_perlu' (jejak catatan penolakan dibiarkan).
 *      Yang sudah 'tervalidasi' tidak disentuh.
 *   2. Request Budget yang slot PM-nya masih PENDING -> APPROVED otomatis (peran 'Otomatis'); bila slot Purchase juga
 *      sudah APPROVED, status request ikut naik ke APPROVED (Menunggu Proses Purchase) lengkap dengan riwayatnya.
 *   3. Akun 'vicky' -> inactive (login & sesi yang sedang berjalan langsung ditolak), override izin per-akun dan
 *      langganan push miliknya dihapus. Akun TIDAK dihapus (riwayat approval/validasi mengacu ke user ini).
 *
 * Idempotent; dilewati diam-diam bila akun/tabel tidak ada di database ini.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$vPdo = getPDO();
$vPdo->beginTransaction();
try {
    // 1) Kas divisi Project
    $kas = $vPdo->exec(
        "UPDATE cash_transactions SET validation_status = 'tidak_perlu', updated_at = NOW()
          WHERE division = 'project' AND validation_status IN ('menunggu', 'ditolak')
            AND related_bank_transaction_id IS NULL"
    );

    // 2) Request Budget: slot PM yang masih menunggu
    $vNow = date('Y-m-d H:i:s');
    $pmOpen = $vPdo->query(
        "SELECT a.id, a.request_budget_id FROM request_budget_approvals a
           JOIN request_budgets rb ON rb.id = a.request_budget_id AND rb.deleted_at IS NULL
          WHERE a.slot = 'pm' AND a.status = 'PENDING' AND rb.status = 'PENDING_APPROVAL'"
    )->fetchAll(PDO::FETCH_ASSOC);
    $setPm = $vPdo->prepare(
        "UPDATE request_budget_approvals SET status = 'APPROVED', approver_user_id = NULL, approver_role = 'Otomatis',
                note = NULL, acted_at = :t WHERE id = :id AND status = 'PENDING'"
    );
    $left = $vPdo->prepare("SELECT COUNT(*) FROM request_budget_approvals WHERE request_budget_id = :rb AND status = 'PENDING'");
    $buyer = $vPdo->prepare("SELECT approver_user_id, approver_role FROM request_budget_approvals WHERE request_budget_id = :rb AND slot = 'purchase' AND status = 'APPROVED'");
    $up = $vPdo->prepare(
        "UPDATE request_budgets SET status = 'APPROVED', approved_by = :u, approved_at = :t, approved_by_role = :r
          WHERE id = :id AND status = 'PENDING_APPROVAL'"
    );
    $hist = $vPdo->prepare(
        "INSERT INTO request_budget_history (request_budget_id, user_id, role_name, action, old_status, new_status, notes)
         VALUES (:rb, NULL, 'Otomatis', :a, :o, :n, :notes)"
    );
    $rbAuto = 0;
    $rbMoved = 0;
    foreach ($pmOpen as $row) {
        $rb = (int) $row['request_budget_id'];
        $setPm->execute(['t' => $vNow, 'id' => $row['id']]);
        $rbAuto++;
        $hist->execute(['rb' => $rb, 'a' => 'approve', 'o' => 'PENDING_APPROVAL', 'n' => 'PENDING_APPROVAL',
            'notes' => 'Approval Project Manager tidak diperlukan lagi (otomatis)']);
        $left->execute(['rb' => $rb]);
        if ((int) $left->fetchColumn() === 0) {
            $buyer->execute(['rb' => $rb]);
            $b = $buyer->fetch(PDO::FETCH_ASSOC) ?: [];
            $up->execute(['u' => $b['approver_user_id'] ?? null, 't' => $vNow, 'r' => $b['approver_role'] ?? 'Otomatis', 'id' => $rb]);
            $hist->execute(['rb' => $rb, 'a' => 'approved_all', 'o' => 'PENDING_APPROVAL', 'n' => 'APPROVED',
                'notes' => 'Seluruh approval selesai -- menunggu proses Purchase']);
            $rbMoved++;
        }
    }

    // 3) Akun Vicky
    $vFind = $vPdo->prepare("SELECT id FROM users WHERE username = 'vicky' AND deleted_at IS NULL");
    $vFind->execute();
    $vid = (int) $vFind->fetchColumn();
    $off = 0;
    $ovr = 0;
    $push = 0;
    if ($vid) {
        $off = $vPdo->exec("UPDATE users SET status = 'inactive' WHERE id = {$vid} AND status <> 'inactive'");
        $ovr = $vPdo->exec("DELETE FROM user_permissions WHERE user_id = {$vid}");
        try {
            $push = $vPdo->exec("DELETE FROM push_subscriptions WHERE user_id = {$vid}");
        } catch (Throwable $e) {
            $push = 0;
        }
    }

    $vPdo->commit();
    echo "Kas Project -> tanpa validasi: {$kas} baris; Request Budget slot PM otomatis: {$rbAuto} (naik ke APPROVED: {$rbMoved}); "
        . ($vid ? "akun vicky dinonaktifkan: {$off}, override dihapus: {$ovr}, langganan push dihapus: {$push}" : 'akun vicky tidak ada (dilewati)') . "\n";
} catch (Throwable $e) {
    if ($vPdo->inTransaction()) {
        $vPdo->rollBack();
    }
    throw $e;
}
