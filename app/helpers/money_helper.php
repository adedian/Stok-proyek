<?php
/**
 * Money -- SATU sumber perhitungan nominal & progres pembayaran.
 *
 * Kenapa ada: dulu status & persentase dihitung dengan float PHP + round(x, 1) + min(100, ...).
 * Akibatnya PO Rp2.029.028,15 yang baru dibayar Rp2.029.028,00 tampil "100%" (dibulatkan) padahal
 * statusnya "Dibayar Sebagian". Di sini SEMUA nominal dihitung dalam SEN (integer) -- bukan float --
 * dan persentase dihitung dengan pembagian panjang (long division) integer, jadi nominal sekecil
 * apa pun selisihnya tetap terlihat. Tidak butuh ekstensi bcmath (tidak selalu ada di hosting).
 *
 * Nilai masuk boleh: string DECIMAL dari MySQL ("2029028.15"), int, atau float. String adalah
 * jalur paling akurat (dibaca digit demi digit, tanpa float sama sekali).
 *
 * Batas aman: |nominal| < 9.000.000.000.000.000 sen (Rp90 triliun) -- jauh di atas batas input
 * aplikasi (Rp9,99 triliun). Di atas itu, hitung ulang manual/bcmath.
 */
final class Money
{
    /** Jumlah desimal persentase pada tampilan normal (dibulatkan half-up). */
    public const DISPLAY_DECIMALS = 2;

    public const STATUS_PENDING  = 'pending';   // belum dibayar
    public const STATUS_PARTIAL  = 'partial';   // dibayar sebagian
    public const STATUS_PAID     = 'paid';      // lunas (nominal PERSIS sama)
    public const STATUS_OVERPAID = 'overpaid';  // kelebihan bayar

    /** Nominal -> sen (int). String dibaca per digit; sen ke-3 dst dibulatkan half-up. */
    public static function cents($value): int
    {
        if (is_int($value)) {
            return $value * 100;
        }
        if (is_float($value)) {
            return (int) round($value * 100);
        }
        $s = trim((string) $value);
        if ($s === '' || $s === '-') {
            return 0;
        }
        $neg = false;
        if ($s[0] === '-') {
            $neg = true;
            $s = substr($s, 1);
        } elseif ($s[0] === '+') {
            $s = substr($s, 1);
        }
        if (!preg_match('/^(\d*)(?:\.(\d*))?$/', $s, $m)) {
            // Format tak dikenal (mis. "1e5") -> jalur float sebagai pilihan terakhir.
            return (int) round(((float) $value) * 100);
        }
        $int  = $m[1] === '' ? '0' : $m[1];
        $frac = $m[2] ?? '';
        $c = (int) $int * 100 + (int) str_pad(substr($frac, 0, 2), 2, '0');
        if (strlen($frac) > 2 && (int) $frac[2] >= 5) {
            $c++;
        }
        return $neg ? -$c : $c;
    }

    /** Sen -> string desimal 2 digit ("202902815" -> "2029028.15"). */
    public static function fromCents(int $cents): string
    {
        $neg = $cents < 0;
        $abs = abs($cents);
        $str = intdiv($abs, 100) . '.' . str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
        return $neg ? '-' . $str : $str;
    }

    public static function add($a, $b): string
    {
        return self::fromCents(self::cents($a) + self::cents($b));
    }

    public static function sub($a, $b): string
    {
        return self::fromCents(self::cents($a) - self::cents($b));
    }

    /** Sisa tagihan = max(total - dibayar, 0). */
    public static function remaining($total, $paid): string
    {
        return self::fromCents(max(0, self::cents($total) - self::cents($paid)));
    }

    /** -1 / 0 / 1 -- perbandingan nominal PERSIS (per sen). */
    public static function cmp($a, $b): int
    {
        return self::cents($a) <=> self::cents($b);
    }

    /** Float HANYA untuk tampilan/legacy (number_format, JS). Jangan dipakai menentukan status. */
    public static function toFloat($value): float
    {
        return self::cents($value) / 100;
    }

    /**
     * Status pembayaran dari perbandingan nominal AKTUAL (bukan dari persentase tampilan):
     *   dibayar <= 0           -> pending
     *   dibayar <  total       -> partial
     *   dibayar == total       -> paid      (satu-satunya jalan menuju Lunas)
     *   dibayar >  total       -> overpaid
     */
    public static function paymentStatus($paid, $total): string
    {
        $p = self::cents($paid);
        $t = self::cents($total);
        if ($p <= 0) {
            return self::STATUS_PENDING;
        }
        if ($p === $t) {
            return self::STATUS_PAID;
        }
        return $p < $t ? self::STATUS_PARTIAL : self::STATUS_OVERPAID;
    }

    /**
     * Persentase dibayar = dibayar / total x 100, presisi penuh.
     *
     * Hasil array:
     *   value  string desimal TITIK ("99.9999926"), tanpa nol di belakang  -> untuk CSS/JS/Excel
     *   text   string tampilan Indonesia ("99,9999926") -- koma desimal
     *   bar    angka 0..100 untuk lebar progress bar (dipotong, TIDAK dibulatkan ke atas)
     *   exact  true bila dibayar == total (hanya saat itu value = "100")
     *
     * Aturan: normalnya 2 desimal (dibulatkan half-up). Pembulatan TIDAK BOLEH membuat nominal
     * yang belum lunas tampil 100% (atau yang sudah > 0 tampil 0%): bila terjadi, tampilan
     * dipaksa 99,99 / 0,01 (maksimal tetap 2 desimal); kelebihan bayar minimal 100,01.
     */
    public static function percent($paid, $total): array
    {
        $p = self::cents($paid);
        $t = self::cents($total);

        if ($p <= 0 || $t <= 0) {
            // Tak ada pembayaran -> 0%. Total 0 tapi ada pembayaran: tak terdefinisi -> 0 (status = overpaid).
            return ['value' => '0', 'text' => '0', 'bar' => 0.0, 'exact' => false];
        }
        if ($p === $t) {
            return ['value' => '100', 'text' => '100', 'bar' => 100.0, 'exact' => true];
        }

        $num = $p * 100;
        $intPart = intdiv($num, $t);
        $rem = $num % $t;

        // Digit desimal (dipotong) sebanyak 30; bagian pertama (8) dipakai sebagai tampilan normal.
        $digits = '';
        for ($i = 0; $i < 30; $i++) {
            $rem *= 10;
            $digits .= (string) intdiv($rem, $t);
            $rem %= $t;
        }

        // Pembulatan half-up ke DISPLAY_DECIMALS desimal memakai digit berikutnya.
        $D = self::DISPLAY_DECIMALS;
        $int8 = (string) $intPart;
        $frac8 = substr($digits, 0, $D);
        if ((int) $digits[$D] >= 5) {
            [$int8, $frac8] = self::incrementDecimal($int8, $frac8);
        }
        $rounded = self::trimDecimal($int8, $frac8);

        $needMore = false;
        if ($p < $t && $rounded === '100') {
            $needMore = true;               // kurang dari total tapi terbulat jadi 100
        } elseif ($p > $t && $rounded === '100') {
            $needMore = true;               // lebih dari total tapi terbulat jadi tepat 100
        } elseif ($rounded === '0') {
            $needMore = true;               // sudah ada pembayaran tapi terbulat jadi 0
        }

        if ($needMore) {
            // Tampilan TETAP maksimal 2 desimal, tapi tidak boleh menyesatkan:
            //   belum lunas yang terbulat jadi 100  -> 99,99 (dipotong ke bawah)
            //   kelebihan yang terbulat jadi 100    -> 100,01
            //   sudah dibayar tapi terbulat jadi 0  -> 0,01
            if ($p < $t) {
                $rounded = $rounded === '100' ? '99.99' : '0.01';
            } else {
                $rounded = '100.01';
            }
        }

        // Lebar bar: dipotong (bukan dibulatkan), maksimum 100.
        $bar = $p >= $t ? 100.0 : (float) self::trimDecimal((string) $intPart, substr($digits, 0, 8));
        if ($bar >= 100.0 && $p < $t) {
            $bar = 99.99999999;
        }

        return [
            'value' => $rounded,
            'text'  => str_replace('.', ',', $rounded),
            'bar'   => $bar,
            'exact' => false,
        ];
    }

    /** Tambah 1 pada digit terakhir bilangan desimal (string), dengan carry. */
    private static function incrementDecimal(string $int, string $frac): array
    {
        $all = $int . $frac;
        $i = strlen($all) - 1;
        while ($i >= 0) {
            if ($all[$i] === '9') {
                $all[$i] = '0';
                $i--;
            } else {
                $all[$i] = (string) ((int) $all[$i] + 1);
                break;
            }
        }
        if ($i < 0) {
            $all = '1' . $all;
        }
        $fl = strlen($frac);
        return [substr($all, 0, strlen($all) - $fl), substr($all, strlen($all) - $fl)];
    }

    /** "99" + "99999260" -> "99.9999926" (buang nol di belakang & titik yatim). */
    private static function trimDecimal(string $int, string $frac): string
    {
        $frac = rtrim($frac, '0');
        return $frac === '' ? $int : $int . '.' . $frac;
    }
}
