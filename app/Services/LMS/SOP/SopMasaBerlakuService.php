<?php

namespace App\Services\LMS\SOP;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Baku masa berlaku & jadwal review sebuah dokumen SOP.
 *
 * KENAPA DATA, BUKAN KONSTANTA. Modul ini konsisten menaruh aturan sebagai
 * baris master — Cap_Tanggal, Status_Teknis, Butuh_Berkas, seluruh tabel
 * transisi dan syarat. Konstanta 24 di dalam kelas ini akan menjadi
 * satu-satunya aturan SOP yang butuh rilis untuk diubah, padahal biaya kolomnya
 * sama saja. Dengan Masa_Berlaku_Bulan di master jenis, Kebijakan bisa 3 tahun
 * dan Instruksi Kerja 1 tahun tanpa satu baris kode pun berubah.
 *
 * FALLBACK BERLAPIS, DAN ITU DISENGAJA. N_SOP_Dokumen.Jenis_Dokumen_Id boleh
 * NULL, dan jenis yang ada boleh belum disetel. Tanpa nilai terakhir yang
 * selalu ada, dokumen tanpa jenis akan lahir tanpa tanggal kadaluarsa dan lolos
 * DIAM-DIAM dari seluruh mekanisme peringatan yang dibangun di atas kolom ini —
 * persis kegagalan yang rancangan ini ada untuk menutupnya.
 *
 * @see plans/sop/2026-08-27-masa-berlaku-versi-design.md §4
 */
class SopMasaBerlakuService
{
    /** Jaring terakhir ketika master tak menyebut apa pun. */
    private const BAKU_BERLAKU_BULAN = 24;

    private const BAKU_REVIEW_BULAN = 12;

    /**
     * Dua angka bulan yang berlaku bagi sebuah dokumen.
     *
     * @return array{Masa_Berlaku_Bulan: int, Siklus_Review_Bulan: int}
     */
    public function aturanDokumen(int $idDokumen): array
    {
        $r = DB::table('N_SOP_Dokumen as d')
            ->leftJoin('N_SOP_Master_Jenis_Dokumen as j', 'j.Id_SOP_Jenis_Dokumen', '=', 'd.Jenis_Dokumen_Id')
            ->where('d.Id_SOP_Dokumen', $idDokumen)
            ->first(['j.Masa_Berlaku_Bulan', 'j.Siklus_Review_Bulan']);

        /* Nol dan negatif diperlakukan sama dengan kosong. "Berlaku 0 bulan"
           bukan kebijakan yang mungkin diambil siapa pun; itu baris master yang
           salah isi, dan menghormatinya berarti melahirkan versi yang kadaluarsa
           pada hari ia mulai berlaku. */
        $bulan = fn ($v, int $baku) => ((int) $v) > 0 ? (int) $v : $baku;

        return [
            'Masa_Berlaku_Bulan' => $bulan($r->Masa_Berlaku_Bulan ?? null, self::BAKU_BERLAKU_BULAN),
            'Siklus_Review_Bulan' => $bulan($r->Siklus_Review_Bulan ?? null, self::BAKU_REVIEW_BULAN),
        ];
    }

    /**
     * Dua tanggal turunan dari tanggal mulai berlaku.
     *
     * `addMonthsNoOverflow`, bukan `addMonths`: 31 Jan + 1 bulan meluber ke
     * 3 Mar pada perilaku baku PHP, dan "sebulan lagi" yang melompati satu bulan
     * penuh bukan yang dimaksud siapa pun. Layar menjepit dengan cara yang sama,
     * sehingga tanggal yang ditawarkan browser dan yang dihitung server tak
     * pernah berselisih di tanggal-tanggal ujung bulan.
     *
     * @return array{Tanggal_Kadaluarsa: ?string, Tanggal_Review_Berikutnya: ?string}
     */
    public function turunanDari(int $idDokumen, ?string $tanggalBerlaku): array
    {
        if (! filled($tanggalBerlaku)) {
            /* Menghitungnya dari HARI INI berarti mengarang fakta: tanggal
               berlakunya memang belum diputuskan, dan kadaluarsa yang lahir dari
               tebakan akan memicu notifikasi atas keputusan yang tak pernah
               diambil siapa pun. */
            return ['Tanggal_Kadaluarsa' => null, 'Tanggal_Review_Berikutnya' => null];
        }

        $aturan = $this->aturanDokumen($idDokumen);
        $mulai = CarbonImmutable::parse($tanggalBerlaku)->startOfDay();

        return [
            'Tanggal_Kadaluarsa' => $mulai
                ->addMonthsNoOverflow($aturan['Masa_Berlaku_Bulan'])->format('Y-m-d'),
            'Tanggal_Review_Berikutnya' => $mulai
                ->addMonthsNoOverflow($aturan['Siklus_Review_Bulan'])->format('Y-m-d'),
        ];
    }

    /**
     * Mengisi yang KOSONG saja pada muatan simpan versi.
     *
     * Dipanggil di jalur simpan supaya API di luar wizard tak melahirkan versi
     * tanpa masa berlaku — layar bukan satu-satunya pintu, dan aturan yang cuma
     * dipasang di satu dari dua pintu setara adalah cacat yang sudah berulang
     * di modul ini.
     *
     * Isian admin TIDAK pernah ditimpa: baku adalah usulan, bukan koreksi.
     */
    public function lengkapiBaku(int $idDokumen, array $data): array
    {
        $turunan = $this->turunanDari($idDokumen, $data['Tanggal_Berlaku'] ?? null);

        foreach ($turunan as $kunci => $nilai) {
            if ($nilai !== null && ! filled($data[$kunci] ?? null)) {
                $data[$kunci] = $nilai;
            }
        }

        return $data;
    }
}
