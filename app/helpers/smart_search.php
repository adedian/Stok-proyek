<?php

/**
 * SmartSearch -- pencarian kode & data yang fleksibel di SELURUH aplikasi.
 *
 * Prinsip: USER TIDAK PERLU MENGINGAT FORMAT KODE SECARA LENGKAP.
 * Kode "AM.0001.SUP" harus bisa ditemukan dengan:
 *   AM.0001.SUP / AM 0001 SUP / am 01 sup / AM 01 / 01 SUP / AM SUP / 0001 / SUP / am / 01
 *
 * Cara kerja (semua di backend, prepared statement):
 *   1. tokenize()  -> keyword dipecah jadi token: pada spasi & separator
 *      (. - / _ \), lalu dipecah lagi pada batas huruf<->angka ("am1sup" -> am,1,sup).
 *   2. clause()    -> tiap token dicocokkan (OR) ke SEMUA kolom yang diberikan;
 *      antar-token digabung AND (jadi urutan token tidak penting, semua wajib cocok).
 *      Kolom KODE dibandingkan SETELAH separatornya di-strip di sisi SQL
 *      (REPLACE bertingkat) -> "AM.0001.SUP" dianggap sama dg "am0001sup".
 *      DATA ASLI TIDAK PERNAH DIUBAH -- normalisasi hanya di ekspresi perbandingan.
 *   3. relevanceExpr() -> skor 0..100 opsional untuk ORDER BY (master data).
 *
 * Catatan performa: pembandingan kolom kode memakai REPLACE()/LOWER() sehingga
 * index kolom tsb tidak terpakai untuk token itu. Di skala aplikasi ini (tabel
 * terbesar beberapa ribu baris) dampaknya < beberapa ms. Untuk tabel yang jauh
 * lebih besar nanti, pertimbangkan kolom "kode ternormalisasi" + index terpisah.
 */
class SmartSearch
{
    /** Karakter separator yang dibuang saat membandingkan KODE (kolom & token). */
    private const CODE_STRIP = ['.', '-', '/', '_', '\\', ' '];

    /** Batas jumlah token supaya query tidak membengkak untuk input ekstrem. */
    private const MAX_TOKENS = 8;

    /**
     * Pecah keyword jadi daftar token alfanumerik huruf-kecil.
     * "AM.0001.SUP" -> ["am","0001","sup"] ; "am 01 sup" -> ["am","01","sup"]
     * "am1sup" -> ["am","1","sup"] ; "  " -> []
     *
     * @return string[]
     */
    public static function tokenize(string $keyword): array
    {
        $keyword = strtolower(trim($keyword));
        if ($keyword === '') {
            return [];
        }

        $parts = preg_split('#[\s./_\\\\-]+#', $keyword, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $tokens = [];
        foreach ($parts as $part) {
            // pecah pada peralihan huruf<->angka
            $sub = preg_split('/(?<=[a-z])(?=\d)|(?<=\d)(?=[a-z])/', $part, -1, PREG_SPLIT_NO_EMPTY) ?: [$part];
            foreach ($sub as $s) {
                $s = preg_replace('/[^a-z0-9]/', '', $s);
                if ($s !== '') {
                    $tokens[] = $s;
                }
            }
        }

        $tokens = array_values(array_unique($tokens));
        return array_slice($tokens, 0, self::MAX_TOKENS);
    }

    /**
     * Bangun fragment SQL pencarian. Pemakaian di model:
     *
     *   [$ss, $p] = SmartSearch::clause($filters['keyword'],
     *                  ['supplier_name', 'contact_person'],   // kolom teks/nama
     *                  ['supplier_code'],                     // kolom kode/nomor
     *                  'skw');
     *   if ($ss !== '') { $sql .= " AND {$ss}"; $params += $p; }
     *
     * @param string   $keyword   input mentah dari user
     * @param string[] $textCols  ekspresi kolom teks (nama, PIC, uraian, dll) -- LIKE biasa
     * @param string[] $codeCols  ekspresi kolom kode/nomor dokumen -- dibandingkan strip-separator
     * @param string   $prefix    prefiks nama parameter (unik per pemakaian dalam 1 query)
     * @return array{0:string,1:array<string,string>}  [sqlFragment, params]  -- ['', []] bila tak ada token
     */
    public static function clause(string $keyword, array $textCols, array $codeCols = [], string $prefix = 'ss'): array
    {
        $tokens = self::tokenize($keyword);
        if (!$tokens) {
            return ['', []];
        }

        $prefix = preg_replace('/[^A-Za-z0-9_]/', '', $prefix);
        if ($prefix === '') {
            $prefix = 'ss';
        }

        $params = [];
        $andParts = [];

        foreach ($tokens as $ti => $tok) {
            $ors = [];
            $like = '%' . $tok . '%'; // token sudah [a-z0-9] -> tidak ada wildcard user

            foreach (array_values($textCols) as $ci => $col) {
                $pn = "{$prefix}_{$ti}_t{$ci}";
                $ors[] = "LOWER({$col}) LIKE :{$pn}";
                $params[$pn] = $like;
            }

            foreach (array_values($codeCols) as $ci => $col) {
                // (a) kode dg separator dibuang
                $pnStrip = "{$prefix}_{$ti}_c{$ci}s";
                $ors[] = self::stripExpr($col) . " LIKE :{$pnStrip}";
                $params[$pnStrip] = $like;
                // (b) kode apa adanya (mis. user ketik potongan yang mengandung separator asli)
                $pnRaw = "{$prefix}_{$ti}_c{$ci}r";
                $ors[] = "LOWER({$col}) LIKE :{$pnRaw}";
                $params[$pnRaw] = $like;
            }

            if ($ors) {
                $andParts[] = '(' . implode(' OR ', $ors) . ')';
            }
        }

        if (!$andParts) {
            return ['', []];
        }

        return ['(' . implode(' AND ', $andParts) . ')', $params];
    }

    /**
     * Ekspresi skor relevansi (0..100) untuk ORDER BY. Opsional -- dipakai di
     * list master data supaya hasil paling cocok naik ke atas. Tetap aman kalau
     * tak dipakai (WHERE dari clause() sudah menyaring).
     *
     *   [$rel, $rp] = SmartSearch::relevanceExpr($kw, 'supplier_code', 'supplier_name', 'srel');
     *   ... " ORDER BY {$rel} DESC, supplier_name ASC ..." ; $params += $rp;
     *
     * @return array{0:string,1:array<string,string>}  ['0', []] bila tak ada token
     */
    public static function relevanceExpr(string $keyword, string $codeCol, string $nameCol, string $prefix = 'srel'): array
    {
        $tokens = self::tokenize($keyword);
        if (!$tokens) {
            return ['0', []];
        }
        $prefix = preg_replace('/[^A-Za-z0-9_]/', '', $prefix) ?: 'srel';

        $full = implode('', $tokens);                 // "am0001sup"
        $first = $tokens[0];
        $nameKw = strtolower(trim($keyword));

        $p = [
            "{$prefix}_full"   => $full,
            "{$prefix}_fulll"  => $full . '%',
            "{$prefix}_first"  => $first . '%',
            "{$prefix}_name"   => $nameKw,
            "{$prefix}_namel"  => $nameKw . '%',
            "{$prefix}_namec"  => '%' . $nameKw . '%',
        ];
        $codeStrip = self::stripExpr($codeCol);

        $expr = "(CASE"
            . " WHEN {$codeStrip} = :{$prefix}_full THEN 100"
            . " WHEN LOWER({$nameCol}) = :{$prefix}_name THEN 95"
            . " WHEN {$codeStrip} LIKE :{$prefix}_fulll THEN 80"
            . " WHEN {$codeStrip} LIKE :{$prefix}_first THEN 60"
            . " WHEN LOWER({$nameCol}) LIKE :{$prefix}_namel THEN 55"
            . " WHEN LOWER({$nameCol}) LIKE :{$prefix}_namec THEN 40"
            . " ELSE 10 END)";

        return [$expr, $p];
    }

    /** LOWER( REPLACE(REPLACE(... col ...)) ) -- buang semua separator kode. */
    private static function stripExpr(string $col): string
    {
        $e = $col;
        foreach (self::CODE_STRIP as $ch) {
            $lit = $ch === '\\' ? "'\\\\'" : "'{$ch}'";
            $e = "REPLACE({$e}, {$lit}, '')";
        }
        return "LOWER({$e})";
    }
}
