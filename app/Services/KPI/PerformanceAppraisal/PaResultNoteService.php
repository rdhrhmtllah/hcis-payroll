<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Helpers\FormatTanggalHelper;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Catatan hasil yang DITULIS ADMIN — Kekuatan dan Area Pengembangan.
 *
 * DUA SUMBER, SATU TABEL, DAN PERBEDAANNYA DIJAGA.
 *
 * `N_PA_Result_Note` menampung dua hal yang terlihat sama di layar tetapi berbeda bobotnya:
 * kalimat yang diambil dari jawaban penilai (`PA_Assignment_Id` terisi) dan kalimat yang
 * diketik admin sendiri (`PA_Assignment_Id` NULL). Yang pertama observasi banyak orang,
 * yang kedua kesimpulan satu orang.
 *
 * Layanan ini HANYA menyentuh yang kedua. Perhitungan ulang pun sudah menjaga batas yang
 * sama dari arah sebaliknya: ia menonaktifkan catatan berasal-penilai saja, dan catatan
 * admin selamat. Tanpa dua penjagaan itu, satu kali hitung ulang menghapus tulisan admin
 * tanpa ada yang tahu.
 *
 * DIGANTI SELURUHNYA, BUKAN DITAMBAHKAN. Layarnya berbentuk kotak teks berisi seluruh
 * daftar, jadi yang dikirim memang keadaan akhir yang diinginkan — menambahkannya baris
 * demi baris akan menggandakan isi setiap kali admin menekan simpan dua kali.
 */
class PaResultNoteService
{
    /** Hasil yang sudah terbit tidak boleh diubah catatannya. */
    private const STATUS_TERKUNCI = ['TERBIT'];

    /**
     * Menyimpan catatan admin untuk satu peserta.
     *
     * @param  array<string, list<string>>  $catatan  Kode kompetensi kualitatif => baris-barisnya.
     * @return array<string, mixed>
     */
    public function simpanCatatanAdmin(int $participantId, array $catatan, ?int $olehUserId = null): array
    {
        $hasil = DB::table('N_PA_Result')
            ->where('PA_Participant_Id', $participantId)
            ->where('Flag_Aktif', 'Y')
            ->first(['Id_PA_Result', 'Status_Hasil']);

        if ($hasil === null) {
            throw new RuntimeException(
                "Peserta {$participantId} belum punya baris hasil, sehingga catatan tidak "
                .'punya tempat menempel. Jalankan perhitungan lebih dulu.'
            );
        }

        if (in_array($hasil->Status_Hasil, self::STATUS_TERKUNCI, true)) {
            throw new RuntimeException(
                'Hasil peserta ini sudah TERBIT. Catatannya tidak bisa diubah lagi karena '
                .'orangnya sudah membacanya; batalkan penerbitannya lebih dulu bila memang '
                .'perlu diperbaiki.'
            );
        }

        /* KATEGORINYA KOMPETENSI KUALITATIF, bukan master jenis catatan tersendiri.
           Sampai 7 September 2026 ada dua daftar yang menjawab pertanyaan yang sama, dan
           harus dijaga sinkron dengan tangan lewat sebuah config pemetaan — ketidak-
           sinkronannya membuat dua dari empat pertanyaan kualitatif dijawab penilai lalu
           tidak pernah sampai ke report. Satu master saja sekarang. */
        $jenis = DB::table('N_PA_Competency as k')
            ->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'k.PA_Competency_Type_Id')
            ->where('k.Flag_Aktif', 'Y')
            // Definisi yang sama dengan pembacanya (PaKompetensiKualitatif): KUALITATIF atau berjawaban esai.
            ->whereRaw(PaKompetensiKualitatif::sqlKompetensi('k', 't'))
            ->pluck('k.Id_PA_Competency', 'k.Kode_Competency');

        $sisip = [];
        $urutan = 0;

        foreach ($catatan as $kodeJenis => $baris) {
            $kodeJenis = strtoupper(trim((string) $kodeJenis));

            if (! $jenis->has($kodeJenis)) {
                throw new RuntimeException(sprintf(
                    "Kategori catatan '%s' tidak dikenal. Yang ada: %s.",
                    $kodeJenis,
                    $jenis->keys()->implode(', '),
                ));
            }

            foreach ((array) $baris as $isi) {
                $isi = trim((string) $isi);

                /* Baris kosong dibuang, bukan disimpan sebagai catatan hampa. Kotak teks
                   berbaris banyak hampir selalu menyisakan baris kosong di ujungnya. */
                if ($isi === '') {
                    continue;
                }

                $urutan++;

                $sisip[] = [
                    'PA_Result_Id' => (int) $hasil->Id_PA_Result,
                    'PA_Competency_Id' => (int) $jenis[$kodeJenis],

                    // NULL: inilah penanda "ditulis admin", dan satu-satunya pembeda yang
                    // dipakai perhitungan ulang untuk tidak menghapusnya.
                    'PA_Assignment_Id' => null,
                    'Isi' => $isi,
                    'Urutan_Tampil' => $urutan,
                    'Flag_Aktif' => 'Y',
                    'Created_By' => $olehUserId,
                ];
            }
        }

        $sekarang = FormatTanggalHelper::getCurrentTime();

        return DB::transaction(function () use ($hasil, $sisip, $sekarang, $olehUserId) {
            /* Yang lama dinonaktifkan, bukan dihapus: enam bulan kemudian, satu-satunya
               keterangan bahwa catatan seseorang pernah berbunyi lain adalah baris itu. */
            $dicabut = DB::table('N_PA_Result_Note')
                ->where('PA_Result_Id', $hasil->Id_PA_Result)
                ->whereNull('PA_Assignment_Id')
                ->where('Flag_Aktif', 'Y')
                ->update([
                    'Flag_Aktif' => 'T',
                    'Updated_At' => $sekarang,
                    'Updated_By' => $olehUserId,
                ]);

            foreach (array_chunk($sisip, 100) as $potongan) {
                DB::table('N_PA_Result_Note')->insert($potongan);
            }

            return [
                'result_id' => (int) $hasil->Id_PA_Result,
                'ditulis' => count($sisip),
                'dicabut' => $dicabut,
            ];
        });
    }
}
