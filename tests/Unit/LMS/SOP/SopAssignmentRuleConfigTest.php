<?php

namespace Tests\Unit\LMS\SOP;

use App\Services\LMS\SOP\Support\SopAssignmentRuleConfig as Config;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Normalisasi jadwal dan kejujuran hash aturan otomasi SOP.
 *
 * Dua hal yang dijaga di sini gampang rusak tanpa gejala. Pertama, setelan
 * override per paket memakai validator yang sama dengan setelan induk — kalau
 * jalurnya bercabang, yang longgar justru jalur yang paling jarang dilihat
 * orang. Kedua, daftar paket tinggal di luar config JSON, jadi hash harus
 * menyerapnya; kalau tidak, lencana "draft berubah" diam tepat ketika paket
 * ditambah atau dibuang.
 *
 * Memakai Tests\TestCase, bukan PHPUnit polos: ValidationException::withMessages()
 * membangun Validator lewat container, jadi tanpa aplikasi yang ter-boot setiap
 * uji penolakan mati dengan "A facade root has not been set" — dan yang mati
 * justru semua penjaganya.
 */
class SopAssignmentRuleConfigTest extends TestCase
{
    public function test_normalize_schedule_bisa_dipakai_sendiri_untuk_override(): void
    {
        $hasil = Config::normalizeSchedule([
            'deadline_mode' => 'relative',
            'deadline_days' => 30,
            'reminder_enabled' => true,
            'reminder_interval_days' => 5,
        ]);

        $this->assertSame('RELATIVE', $hasil['deadline_mode']);
        $this->assertSame(30, $hasil['deadline_days']);
        $this->assertTrue($hasil['reminder_enabled']);
        $this->assertSame(5, $hasil['reminder_interval_days']);
    }

    /**
     * Override adalah PENGGANTIAN utuh setelan induk, bukan tambahan di atasnya.
     */
    public function test_override_bermode_none_sah_walau_induk_bertenggat(): void
    {
        $hasil = Config::normalizeSchedule(['deadline_mode' => 'NONE']);

        $this->assertSame('NONE', $hasil['deadline_mode']);
        $this->assertNull($hasil['deadline_days']);
        $this->assertNull($hasil['deadline_date']);
    }

    public function test_schedule_tak_valid_tetap_ditolak(): void
    {
        $this->expectException(ValidationException::class);
        Config::normalizeSchedule(['deadline_mode' => 'RELATIVE', 'deadline_days' => 0]);
    }

    public function test_normalize_induk_memakai_validator_yang_sama(): void
    {
        $this->expectException(ValidationException::class);
        Config::normalize([
            'groups' => [['tipe' => 'Karyawan']],
            'schedule' => ['deadline_mode' => 'RELATIVE', 'deadline_days' => 99999],
        ]);
    }

    public function test_hash_berubah_ketika_daftar_paket_berubah(): void
    {
        $config = Config::normalize(['groups' => [['tipe' => 'Karyawan']]]);

        $satu = Config::hash($config, Config::snapshotPaket([
            (object) ['Id_SOP_Paket' => 7, 'Urutan' => 1, 'Flag_Override_Jadwal' => 'T'],
        ]));
        $dua = Config::hash($config, Config::snapshotPaket([
            (object) ['Id_SOP_Paket' => 7, 'Urutan' => 1, 'Flag_Override_Jadwal' => 'T'],
            (object) ['Id_SOP_Paket' => 9, 'Urutan' => 2, 'Flag_Override_Jadwal' => 'T'],
        ]));

        $this->assertNotSame($satu, $dua, 'Hash tak berubah saat paket ditambah.');
    }

    public function test_hash_berubah_ketika_override_sebuah_paket_berubah(): void
    {
        $config = Config::normalize(['groups' => [['tipe' => 'Karyawan']]]);

        $ikut = Config::hash($config, Config::snapshotPaket([
            (object) ['Id_SOP_Paket' => 7, 'Urutan' => 1, 'Flag_Override_Jadwal' => 'T'],
        ]));
        $sendiri = Config::hash($config, Config::snapshotPaket([
            (object) [
                'Id_SOP_Paket' => 7, 'Urutan' => 1, 'Flag_Override_Jadwal' => 'Y',
                'Deadline_Mode' => 'RELATIVE', 'Deadline_Hari' => 30, 'Tanggal_Deadline' => null,
                'Flag_Reminder' => 'T', 'Irama_Reminder_Hari' => null,
            ],
        ]));

        $this->assertNotSame($ikut, $sendiri, 'Hash tak berubah saat override dinyalakan.');
    }

    public function test_snapshot_paket_deterministik_terhadap_urutan_baris(): void
    {
        $a = Config::snapshotPaket([
            (object) ['Id_SOP_Paket' => 9, 'Urutan' => 2, 'Flag_Override_Jadwal' => 'T'],
            (object) ['Id_SOP_Paket' => 7, 'Urutan' => 1, 'Flag_Override_Jadwal' => 'T'],
        ]);
        $b = Config::snapshotPaket([
            (object) ['Id_SOP_Paket' => 7, 'Urutan' => 1, 'Flag_Override_Jadwal' => 'T'],
            (object) ['Id_SOP_Paket' => 9, 'Urutan' => 2, 'Flag_Override_Jadwal' => 'T'],
        ]);

        $this->assertSame($a, $b, 'Urutan baris dari basis data mengubah snapshot.');
    }

    /**
     * Hash tanpa snapshot harus tetap bekerja: saveDraft memakainya sekali,
     * pada baris aturan yang baru lahir dan belum punya paket sama sekali.
     */
    public function test_hash_tanpa_snapshot_tetap_menghasilkan_nilai(): void
    {
        $config = Config::normalize(['groups' => [['tipe' => 'Karyawan']]]);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', Config::hash($config));
    }

    public function test_exclude_groups_dinormalkan_dengan_bentuk_yang_sama(): void
    {
        $hasil = Config::normalize([
            'groups' => [['tipe' => 'Karyawan']],
            'exclude_groups' => [['level' => ['Manager', 'Manager', ' '], 'tipe' => 'bukan-nilai-sah']],
        ]);

        $this->assertCount(1, $hasil['exclude_groups']);
        $this->assertSame(['Manager'], $hasil['exclude_groups'][0]['level'],
            'Nilai kembar dan kosong tidak dibersihkan pada grup pengecualian.');
        $this->assertSame('Semua', $hasil['exclude_groups'][0]['tipe'],
            'Tipe pekerja tak sah tidak dikembalikan ke Semua pada grup pengecualian.');
        $this->assertArrayHasKey('jabatan', $hasil['exclude_groups'][0],
            'Grup pengecualian tidak memiliki saringan yang sama dengan grup target.');
    }

    public function test_tanpa_exclude_groups_tetap_sah_dan_berisi_array_kosong(): void
    {
        $hasil = Config::normalize(['groups' => [['tipe' => 'Karyawan']]]);

        $this->assertSame([], $hasil['exclude_groups'],
            'Aturan tanpa pengecualian adalah keadaan wajar dan tak boleh ditolak.');
    }

    /**
     * PENJAGA. Grup pengecualian kosong mengecualikan SEMUA orang, sehingga
     * aturan tampak aktif tetapi tak pernah menugaskan siapa pun — kegagalan
     * yang tak menghasilkan satu pun galat.
     */
    public function test_grup_pengecualian_tanpa_kriteria_ditolak(): void
    {
        try {
            Config::normalize([
                'groups' => [['tipe' => 'Karyawan']],
                'exclude_groups' => [['divisi' => [], 'tipe' => 'Semua']],
            ]);
            $this->fail('Grup pengecualian kosong LOLOS; aturan akan mengecualikan seluruh karyawan.');
        } catch (ValidationException $e) {
            $pesan = implode(' ', array_merge(...array_values($e->errors())));
            $this->assertStringContainsString('MENGECUALIKAN SELURUH', $pesan,
                'Pesan penolakan tidak menjelaskan akibatnya.');
        }
    }

    public function test_grup_target_kosong_tetap_ditolak_dengan_pesannya_sendiri(): void
    {
        try {
            Config::normalize(['groups' => [['divisi' => [], 'tipe' => 'Semua']]]);
            $this->fail('Grup target kosong LOLOS.');
        } catch (ValidationException $e) {
            $pesan = implode(' ', array_merge(...array_values($e->errors())));
            $this->assertStringContainsString('SELURUH karyawan aktif', $pesan);
            $this->assertStringNotContainsString('MENGECUALIKAN', $pesan,
                'Grup target memakai pesan milik grup pengecualian.');
        }
    }

    public function test_hash_berubah_ketika_grup_pengecualian_ditambah(): void
    {
        $tanpa = Config::normalize(['groups' => [['tipe' => 'Karyawan']]]);
        $dengan = Config::normalize([
            'groups' => [['tipe' => 'Karyawan']],
            'exclude_groups' => [['level' => ['Manager']]],
        ]);

        $this->assertNotSame(Config::hash($tanpa), Config::hash($dengan),
            'Menambah pengecualian tak mengubah hash; lencana "draft berubah" akan diam.');
    }
}
