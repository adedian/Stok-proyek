<?php
$ttItems = $d['items'] ?? [];
$ttRows = max(3, count($ttItems)); // minimal 3 baris (data + baris kosong) supaya tetap muat setengah F4
?>
<div class="tt-doc">
    <div class="tt-header">
        <?php if (!empty($company['company_logo'])): ?>
            <img src="<?= BASE_URL ?>/<?= e($company['company_logo']) ?>" alt="Logo">
        <?php else: ?>
            <p class="tt-company-name"><?= e($company['company_name'] ?? 'Perusahaan') ?></p>
        <?php endif; ?>
    </div>

    <div class="tt-title">Tanda Terima Dokumen</div>

    <table class="tt-info">
        <tr><td>Kepada</td><td>:</td><td><strong><?= e($d['to_name'] ?? '') ?></strong></td></tr>
        <tr><td>Alamat</td><td>:</td><td><?= nl2br(e($d['address'] ?? '')) ?></td></tr>
    </table>

    <table class="tt-table">
        <tr>
            <th style="width: 10mm;">No</th>
            <th>Jenis Dokumen</th>
            <th style="width: 32mm;">Jumlah</th>
            <th style="width: 55mm;">Keterangan</th>
        </tr>
        <?php for ($i = 0; $i < $ttRows; $i++): $it = $ttItems[$i] ?? null; ?>
            <tr class="tt-row">
                <td><?= $it ? $i + 1 : '' ?></td>
                <td><?= $it ? e($it['document']) : '' ?></td>
                <td><?= $it ? e($it['qty']) : '' ?></td>
                <td><?= $it ? e($it['note']) : '' ?></td>
            </tr>
        <?php endfor; ?>
    </table>

    <div class="tt-spacer"></div>

    <div class="tt-signoff">
        <div>
            <div class="tt-signoff-label">Penerima,</div>
            <div class="tt-signoff-caption">(Nama, Tanda Tangan dan stempel)</div>
        </div>
        <div>
            <div class="tt-signoff-label">Hormat Kami,</div>
        </div>
    </div>

    <div class="tt-note">*Tanda Terima ini Mohon di emailkan ke : accounting@hexamultienergi.com</div>

    <?php if (!empty($company['company_address']) || !empty($company['company_phone']) || !empty($company['company_email'])): ?>
        <div class="tt-footer">
            <div><?= nl2br(e($company['company_address'] ?? '')) ?></div>
            <div>
                <?= e($company['company_phone'] ?? '') ?>
                <?php if (!empty($company['company_email'])): ?><br><?= e($company['company_email']) ?><?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
