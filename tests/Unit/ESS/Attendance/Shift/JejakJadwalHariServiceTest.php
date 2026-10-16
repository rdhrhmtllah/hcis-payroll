<?php

namespace Tests\Unit\ESS\Attendance\Shift;

use App\Services\ESS\Attendance\Shift\Schedule\JejakJadwalHariService as J;
use PHPUnit\Framework\TestCase;

class JejakJadwalHariServiceTest extends TestCase
{
    /**
     * N_HRIS_FN_GetStatusHari_Inline mengembalikan 'WORK' baik dari baris status
     * hari (prio 2) maupun dari bawaan (prio 99) — sebabnya tak bisa dibaca dari
     * nilainya saja.
     */
    public function test_sebab_status_hari_dibaca_dari_nilai_fungsi_dan_lapisan_aktif(): void
    {
        $this->assertSame(['LIBUR', 'KONVERSI_LEMBUR'], self::ks(J::sebabStatus('OFF-KONVERSI_LEMBUR', false)));
        $this->assertSame(['LIBUR', 'HARI_LIBUR'], self::ks(J::sebabStatus('OFF-Hari_Libur', false)));
        $this->assertSame(['LIBUR', 'CUTI_BERSAMA'], self::ks(J::sebabStatus('OFF-Cuti_Bersama', false)));
        $this->assertSame(['LIBUR', 'SHIFT_DEFAULT_OFF'], self::ks(J::sebabStatus('OFF-Default_Shift', false)));
        $this->assertSame(['LIBUR', 'STATUS_HARI'], self::ks(J::sebabStatus('OFF', true)));
        $this->assertSame(['MASUK', 'STATUS_HARI'], self::ks(J::sebabStatus('WORK', true)));
        $this->assertSame(['MASUK', 'BAWAAN'], self::ks(J::sebabStatus('WORK', false)));
        $this->assertSame(['MASUK', 'BAWAAN'], self::ks(J::sebabStatus(null, false)));
    }

    public function test_alasan_penonaktifan_shift_diterjemahkan(): void
    {
        $this->assertSame('Digantikan shift sementara yang lebih baru', J::alasanNonaktif('Replaced by new temporary shift'));
        $this->assertSame('Terhapus otomatis karena shift permanen baru dipasang', J::alasanNonaktif('Replaced by permanent shift'));
        $this->assertSame('alasan lain', J::alasanNonaktif('alasan lain'));
        $this->assertNull(J::alasanNonaktif(null));
    }

    /** Created_By status hari berisi Id user ("4") ATAU kode karyawan ("H45"). */
    public function test_pelaku_dikenali_sebagai_id_user_atau_kode_karyawan(): void
    {
        $this->assertSame(['id', '4'], J::jenisPelaku('4'));
        $this->assertSame(['id', '622'], J::jenisPelaku(' 622 '));
        $this->assertSame(['kode', 'H45'], J::jenisPelaku('H45'));
        $this->assertNull(J::jenisPelaku(''));
        $this->assertNull(J::jenisPelaku(null));
    }

    public function test_label_transaksi_menyebut_jalurnya(): void
    {
        $this->assertSame('Pengajuan karyawan (Self Shift)', J::labelTransaksi('SH10-26-0008', 'SELF_SHIFT', 'T'));
        $this->assertSame('Leader Shift', J::labelTransaksi('SH07-26-0231', 'LEADER_SHIFT', 'T'));
        $this->assertSame('Admin Shift', J::labelTransaksi('SH07-26-0300', 'LEADER_SHIFT', 'Y'));
        $this->assertSame('Status Hari oleh leader', J::labelTransaksi('TSH10-26-0001', 'STATUS_HARI', 'T'));
        $this->assertSame('Status Hari oleh admin', J::labelTransaksi('TSH10-26-0002', 'STATUS_HARI', 'Y'));
        $this->assertSame('Tukar Hari', J::labelTransaksi('TH10-26-0001', null, null));
        $this->assertSame('Konversi Lembur', J::labelTransaksi('KL26-08-0006', null, null));
        $this->assertSame('Tanpa transaksi (input langsung)', J::labelTransaksi(null, null, null));
    }

    public function test_penjelasan_libur_bawaan_shift_menyebut_shift_dan_harinya(): void
    {
        $p = J::penjelasan('SHIFT_DEFAULT_OFF', 'LIBUR', ['shift_default' => 'EMI-S1', 'hari' => 'Minggu']);

        $this->assertSame('Libur bawaan shift', $p['judul']);
        $this->assertContains('Shift permanen EMI-S1 menetapkan setiap hari Minggu sebagai hari libur.', $p['rincian']);
    }

    public function test_penjelasan_status_hari_menyebut_pelaku_transaksi_rentang_dan_keterangan(): void
    {
        $p = J::penjelasan('STATUS_HARI', 'LIBUR', [
            'status_pelaku' => 'Aji',
            'status_transaksi' => 'Status Hari oleh leader · TSH10-26-0002',
            'status_rentang' => 'hanya tanggal ini',
            'status_keterangan' => 'Ganti libur minggu lalu',
        ]);

        $this->assertSame('Libur yang ditetapkan khusus', $p['judul']);
        $this->assertSame([
            'Ditetapkan oleh Aji lewat Status Hari oleh leader · TSH10-26-0002.',
            'Berlaku hanya tanggal ini.',
            'Keterangan: Ganti libur minggu lalu',
        ], $p['rincian']);
        $this->assertSame('Masuk kerja yang ditetapkan khusus', J::penjelasan('STATUS_HARI', 'MASUK', [])['judul']);
    }

    public function test_penjelasan_sebab_lain(): void
    {
        $this->assertSame(['Hari libur nasional', ['Kemerdekaan RI.']], array_values(J::penjelasan('HARI_LIBUR', 'LIBUR', ['libur_nasional' => 'Kemerdekaan RI'])));
        $this->assertSame(['Cuti bersama', ['Cuti Bersama Idul Fitri.']], array_values(J::penjelasan('CUTI_BERSAMA', 'LIBUR', ['cuti_bersama' => 'Cuti Bersama Idul Fitri'])));
        $this->assertSame(
            ['Libur pengganti lembur', ['Mengganti lembur tanggal 2026-09-01.', 'Dari pengajuan KL26-09-0003.']],
            array_values(J::penjelasan('KONVERSI_LEMBUR', 'LIBUR', ['konversi_asal' => '2026-09-01', 'konversi_no' => 'KL26-09-0003'])),
        );
        $this->assertSame(['Hari kerja biasa', ['Masuk sesuai shift EMI-S2.']], array_values(J::penjelasan('BAWAAN', 'MASUK', ['shift' => 'EMI-S2'])));
    }

    /** Aturan yang kalah tetap disebut, supaya "kok bukan libur nasional?" terjawab. */
    public function test_aturan_yang_tertimpa_ikut_disebut(): void
    {
        $p = J::penjelasan('STATUS_HARI', 'MASUK', ['status_pelaku' => 'Aji', 'tertimpa' => ['hari libur nasional (Kemerdekaan RI)', 'libur bawaan shift EMI-S1']]);

        $this->assertContains('Aturan lain yang tertimpa: hari libur nasional (Kemerdekaan RI); libur bawaan shift EMI-S1.', $p['rincian']);
    }

    public function test_linimasa_melebur_shift_sementara_yang_diperbarui_di_waktu_sama(): void
    {
        $svc = new J;
        $sementara = collect([
            (object) [
                'ID_Shift' => 109, 'Nama' => 'EMI-S1-TEMP', 'Flag_Aktif' => 'Y',
                'No_Transaksi_Reference' => 'SH10-26-1750', 'created_at' => '2026-10-11 21:39:28',
                'Nonaktif_By' => null, 'Nonaktif_At' => null, 'Nonaktif_Reason' => null, 'Urut' => 2,
            ],
            (object) [
                'ID_Shift' => 109, 'Nama' => 'EMI-S1-TEMP', 'Flag_Aktif' => 'T',
                'No_Transaksi_Reference' => 'SH10-26-0104', 'created_at' => '2026-10-01 16:24:11',
                'Nonaktif_By' => '245', 'Nonaktif_At' => '2026-10-11 21:39:28', 'Nonaktif_Reason' => 'Replaced by new temporary shift', 'Urut' => 1,
            ],
        ]);
        $tx = [
            'SH10-26-1750' => ['label' => 'Leader Shift', 'created_by' => '245'],
            'SH10-26-0104' => ['label' => 'Leader Shift', 'created_by' => '245'],
        ];
        $pelaku = ['id:245' => 'VENGINE ULYAMA'];

        $acara = $svc->susunLinimasa($sementara, collect(), collect(), collect(), collect(), $tx, $pelaku);

        // Hanya 2 event: pembaruan di 11 Okt dan pemasangan awal di 01 Okt (tanpa event nonaktif terpisah di 11 Okt)
        $this->assertCount(2, $acara);
        $this->assertSame('PERBARUI', $acara[0]['aksi']);
        $this->assertSame('Shift sementara EMI-S1-TEMP diperbarui', $acara[0]['judul']);
        $this->assertSame('SH10-26-1750', $acara[0]['transaksi']['no']);
        $this->assertSame('Menggantikan pengajuan sebelumnya (SH10-26-0104)', $acara[0]['alasan']);
        $this->assertSame('PASANG', $acara[1]['aksi']);
        $this->assertSame('SH10-26-0104', $acara[1]['transaksi']['no']);
    }

    public function test_linimasa_melebur_shift_sementara_yang_diganti_shift_berbeda(): void
    {
        $svc = new J;
        $sementara = collect([
            (object) [
                'ID_Shift' => 110, 'Nama' => 'EMI-S2-TEMP', 'Flag_Aktif' => 'Y',
                'No_Transaksi_Reference' => 'SH10-26-1750', 'created_at' => '2026-10-11 21:39:28',
                'Nonaktif_By' => null, 'Nonaktif_At' => null, 'Nonaktif_Reason' => null, 'Urut' => 2,
            ],
            (object) [
                'ID_Shift' => 109, 'Nama' => 'EMI-S1-TEMP', 'Flag_Aktif' => 'T',
                'No_Transaksi_Reference' => 'SH10-26-0104', 'created_at' => '2026-10-01 16:24:11',
                'Nonaktif_By' => '245', 'Nonaktif_At' => '2026-10-11 21:39:28', 'Nonaktif_Reason' => 'Replaced by new temporary shift', 'Urut' => 1,
            ],
        ]);
        $tx = [
            'SH10-26-1750' => ['label' => 'Leader Shift', 'created_by' => '245'],
            'SH10-26-0104' => ['label' => 'Leader Shift', 'created_by' => '245'],
        ];
        $pelaku = ['id:245' => 'VENGINE ULYAMA'];

        $acara = $svc->susunLinimasa($sementara, collect(), collect(), collect(), collect(), $tx, $pelaku);

        $this->assertCount(2, $acara);
        $this->assertSame('GANTI', $acara[0]['aksi']);
        $this->assertSame('Shift sementara diganti: EMI-S1-TEMP → EMI-S2-TEMP', $acara[0]['judul']);
        $this->assertSame('SH10-26-1750', $acara[0]['transaksi']['no']);
        $this->assertSame('Menggantikan pengajuan sebelumnya (SH10-26-0104)', $acara[0]['alasan']);
    }

    public function test_linimasa_tetap_menampilkan_nonaktif_murni_jika_tidak_ada_pengganti(): void
    {
        $svc = new J;
        $sementara = collect([
            (object) [
                'ID_Shift' => 109, 'Nama' => 'EMI-S1-TEMP', 'Flag_Aktif' => 'T',
                'No_Transaksi_Reference' => 'SH10-26-0104', 'created_at' => '2026-10-01 16:24:11',
                'Nonaktif_By' => '245', 'Nonaktif_At' => '2026-10-05 10:00:00', 'Nonaktif_Reason' => 'Dibatalkan oleh leader', 'Urut' => 1,
            ],
        ]);
        $tx = ['SH10-26-0104' => ['label' => 'Leader Shift', 'created_by' => '245']];
        $pelaku = ['id:245' => 'VENGINE ULYAMA'];

        $acara = $svc->susunLinimasa($sementara, collect(), collect(), collect(), collect(), $tx, $pelaku);

        $this->assertCount(2, $acara);
        $this->assertSame('NONAKTIF', $acara[0]['aksi']);
        $this->assertSame('Shift sementara EMI-S1-TEMP dinonaktifkan', $acara[0]['judul']);
        $this->assertSame('Dibatalkan oleh leader', $acara[0]['alasan']);
        $this->assertSame('PASANG', $acara[1]['aksi']);
    }

    public function test_linimasa_menandai_shift_permanen_aktif_dan_lampau(): void
    {
        $svc = new J;
        $pAktif = (object) [
            'ID_Shift' => 50, 'Nama' => 'EMI-LS2', 'Flag_Aktif' => 'Y',
            'No_Transaksi_Reference' => 'SH02-26-0054', 'Periode' => '2026-09-09',
            'created_at' => '2026-02-26 15:24:36', 'Punya_Hari' => 1,
        ];
        $pLampau = (object) [
            'ID_Shift' => 44, 'Nama' => 'EMI-S1', 'Flag_Aktif' => 'Y',
            'No_Transaksi_Reference' => 'SH06-26-0003', 'Periode' => '2026-06-05',
            'created_at' => '2026-06-04 10:33:49', 'Punya_Hari' => 1,
        ];
        $permanen = collect([$pAktif, $pLampau]);

        $acara = $svc->susunLinimasa(collect(), $permanen, collect(), collect(), collect(), [], [], $pAktif);

        // Linimasa diurutkan berdasarkan tanggal efektif (Periode): EMI-LS2 (Sep) tampil di atas EMI-S1 (Jun)
        // dan berstatus BERLAKU, sedangkan EMI-S1 yang mendahului berstatus LAMPAU
        $this->assertCount(2, $acara);
        $this->assertSame('BERLAKU', $acara[0]['aksi']);
        $this->assertSame('Shift permanen EMI-LS2 berlaku mulai 2026-09-09', $acara[0]['judul']);
        $this->assertStringContainsString('Shift aktif yang menaungi tanggal ini', $acara[0]['alasan']);

        $this->assertSame('LAMPAU', $acara[1]['aksi']);
        $this->assertSame('Shift permanen EMI-S1 berlaku mulai 2026-06-05', $acara[1]['judul']);
        $this->assertStringContainsString('terlewati oleh periode shift berikutnya', $acara[1]['alasan']);
    }

    private static function ks(array $s): array
    {
        return [$s['kategori'], $s['sebab']];
    }
}
