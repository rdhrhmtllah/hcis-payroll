<?php

namespace App\Services\VISITOR\Permit\VehiclePermit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Syarat kartu NFC untuk mengajukan permit kendaraan karyawan.
 *
 * Di layar kartu ini disebut "ID Card" (lebih dikenal pemakai); "NFC" hanya
 * nama teknis di kode dan tabel.
 *
 * Karyawan baru boleh mengisi form permit bila kartunya sudah dipasangkan
 * (N_EMI_Binding_NFC) ke kartu yang aktif dan tervalidasi sistem
 * (N_EMI_Master_NFC). Yang belum memenuhi diarahkan ke IT support terdekat.
 *
 * Pemohon dicocokkan lewat kode_karyawan, BUKAN NIK: NIK di tabel Karyawan
 * tidak dijamin unik (di development seluruh karyawan aktif ber-NIK sama).
 *
 * Keputusan user 28 Sep 2026: kartu yang belum divalidasi sistem dan kartu yang
 * sedang dipasangkan ke lebih dari satu pemegang aktif sama-sama DITOLAK sampai
 * IT membereskannya.
 */
class KartuNfcPemohon
{
    public const T_MASTER = 'N_EMI_Master_NFC';

    public const T_BINDING = 'N_EMI_Binding_NFC';

    public const TERDAFTAR = 'TERDAFTAR';

    public const BELUM_PAIRING = 'BELUM_PAIRING';

    public const KARTU_DIBLOKIR = 'KARTU_DIBLOKIR';

    public const MENUNGGU_VALIDASI = 'MENUNGGU_VALIDASI';

    public const KARTU_GANDA = 'KARTU_GANDA';

    /** Tabel kartu belum ada (DDL belum dijalankan) atau gagal dibaca. */
    public const TIDAK_TERSEDIA = 'TIDAK_TERSEDIA';

    private const ARAHAN = 'Silakan hubungi IT support terdekat untuk pairing ID Card Anda.';

    private const PESAN = [
        self::BELUM_PAIRING => 'ID Card Anda belum terdaftar, sehingga pengajuan permit belum bisa dilakukan.',
        self::KARTU_DIBLOKIR => 'ID Card Anda sedang dinonaktifkan, sehingga pengajuan permit belum bisa dilakukan.',
        self::MENUNGGU_VALIDASI => 'ID Card Anda belum divalidasi sistem, sehingga pengajuan permit belum bisa dilakukan.',
        self::KARTU_GANDA => 'ID Card Anda tercatat juga atas nama orang lain, sehingga pengajuan permit belum bisa dilakukan.',
        self::TIDAK_TERSEDIA => 'Pengecekan ID Card sedang tidak tersedia, sehingga pengajuan permit belum bisa dilakukan.',
    ];

    /** Urutan sebab yang dilaporkan bila tak satu pun kartu memenuhi syarat. */
    private const PRIORITAS = [self::KARTU_GANDA, self::MENUNGGU_VALIDASI, self::KARTU_DIBLOKIR];

    public static function wajib(): bool
    {
        return (bool) config('services.visitor.permit.wajib_kartu_nfc', true);
    }

    /**
     * @return array{boleh: bool, status: string, pesan: string|null, nfc_hex: string|null}
     */
    public function periksa(?string $kodeKaryawan): array
    {
        if (! self::wajib()) {
            return $this->hasil(self::TERDAFTAR);
        }

        $kode = trim((string) $kodeKaryawan);
        if ($kode === '') {
            return $this->hasil(self::BELUM_PAIRING);
        }

        try {
            $kartu = DB::table(self::T_BINDING.' as b')
                ->join(self::T_MASTER.' as m', 'm.id', '=', 'b.id_nfc')
                ->where('b.kode_karyawan', $kode)
                ->where('b.flag_aktif', 'Y')
                ->select(
                    'm.id',
                    'm.nfc_hex',
                    'm.flag_aktif',
                    'm.flag_validasi_sistem',
                    // Pemegang aktif LAIN pada kartu yang sama (karyawan lain atau mitra).
                    DB::raw('(SELECT COUNT(*) FROM '.self::T_BINDING.' b2
                        WHERE b2.id_nfc = b.id_nfc AND b2.flag_aktif = \'Y\' AND b2.id <> b.id
                          AND ISNULL(b2.kode_karyawan, \'\') <> ?) as pemegang_lain'),
                )
                ->addBinding($kode, 'select')
                ->orderByDesc('b.created_at')
                ->get();
        } catch (\Throwable $e) {
            Log::error('Pengecekan kartu NFC pemohon permit gagal', ['kode_karyawan' => $kode, 'error' => $e->getMessage()]);

            return $this->hasil(self::TIDAK_TERSEDIA);
        }

        if ($kartu->isEmpty()) {
            return $this->hasil(self::BELUM_PAIRING);
        }

        $sebab = [];
        foreach ($kartu as $k) {
            $status = match (true) {
                $k->flag_aktif !== 'Y' => self::KARTU_DIBLOKIR,
                $k->flag_validasi_sistem !== 'Y' => self::MENUNGGU_VALIDASI,
                (int) $k->pemegang_lain > 0 => self::KARTU_GANDA,
                default => self::TERDAFTAR,
            };

            if ($status === self::TERDAFTAR) {
                return $this->hasil(self::TERDAFTAR, (string) $k->nfc_hex);
            }

            $sebab[$status] = (string) $k->nfc_hex;
        }

        foreach (self::PRIORITAS as $status) {
            if (isset($sebab[$status])) {
                return $this->hasil($status, $sebab[$status]);
            }
        }

        return $this->hasil(self::BELUM_PAIRING);
    }

    /** @return array{boleh: bool, status: string, pesan: string|null, nfc_hex: string|null} */
    /**
     * Nomor kartu (nfc_inbio) yang dipakai palang untuk karyawan ini, untuk
     * kolom N_VISITOR_Permit_Efektif.NFC_Inbio (permintaan user 29 Sep 2026).
     *
     * Kartu yang sama yang dinyatakan TERDAFTAR oleh periksa(): binding aktif,
     * kartu aktif & tervalidasi sistem, tidak dipegang orang lain; binding
     * terbaru menang. Kartu yang tidak lolos TIDAK ditulis — palang tidak boleh
     * menerima kartu yang diblokir atau ganda hanya karena nomornya tersalin.
     *
     * Berbeda dari periksa(), tidak bergantung pada saklar wajib_kartu_nfc:
     * saklar itu mengatur boleh-tidaknya MENGAJUKAN, bukan kartu mana yang
     * dibaca palang. Galat baca menghasilkan null (dicatat), bukan melempar —
     * penerbitan izin tidak boleh gagal karena kolom pelengkap ini.
     */
    public function nfcInbio(?string $kodeKaryawan): ?string
    {
        $kode = trim((string) $kodeKaryawan);
        if ($kode === '') {
            return null;
        }

        try {
            $nomor = DB::table(self::T_BINDING.' as b')
                ->join(self::T_MASTER.' as m', 'm.id', '=', 'b.id_nfc')
                ->where('b.kode_karyawan', $kode)
                ->where('b.flag_aktif', 'Y')
                ->where('m.flag_aktif', 'Y')
                ->where('m.flag_validasi_sistem', 'Y')
                ->whereNotNull('m.nfc_inbio')
                ->whereNotExists(fn ($q) => $q->from(self::T_BINDING.' as b2')
                    ->whereColumn('b2.id_nfc', 'b.id_nfc')
                    ->where('b2.flag_aktif', 'Y')
                    ->whereColumn('b2.id', '<>', 'b.id')
                    ->whereRaw("ISNULL(b2.kode_karyawan, '') <> ?", [$kode]))
                ->orderByDesc('b.created_at')
                ->orderByDesc('b.id')
                ->value('m.nfc_inbio');
        } catch (\Throwable $e) {
            Log::error('Nomor kartu NFC (inbio) pemohon permit gagal dibaca', ['kode_karyawan' => $kode, 'error' => $e->getMessage()]);

            return null;
        }

        return $nomor !== null ? (string) $nomor : null;
    }

    private function hasil(string $status, ?string $nfcHex = null): array
    {
        $boleh = $status === self::TERDAFTAR;

        return [
            'boleh' => $boleh,
            'status' => $status,
            'pesan' => $boleh ? null : self::PESAN[$status].' '.self::ARAHAN,
            // Hanya 4 digit terakhir yang dikirim ke layar; UID utuh tidak perlu
            // diketahui pemohon.
            'nfc_hex' => $nfcHex !== null ? '••••'.substr($nfcHex, -4) : null,
        ];
    }
}
