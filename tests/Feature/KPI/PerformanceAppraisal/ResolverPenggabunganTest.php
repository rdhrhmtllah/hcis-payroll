<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaParticipantRuleResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Penggabungan salinan siklus dengan override per peserta.
 *
 * Lapisan ketiga berbentuk jarang: normalnya kosong, dan barisnya lahir hanya
 * saat satu orang menyimpang. Yang diuji di sini justru kelakuan penggabungnya,
 * karena di situlah seluruh risiko rancangan ini dipusatkan — kalau resolver
 * salah, semua peserta salah sekaligus.
 */
class ResolverPenggabunganTest extends TestCase
{
    use DatabaseTransactions;

    private PaParticipantRuleResolver $resolver;

    private int $cycleId;

    private int $levelId;

    private int $policySnapshotId;

    private int $participantId;

    /** @var array<string, int> kode kategori => id */
    private array $kategori = [];

    /** @var array<string, int> kode type => id */
    private array $type = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new PaParticipantRuleResolver;

        foreach (DB::table('N_PA_Rater_Category')->get() as $r) {
            $this->kategori[$r->Kode_Kategori] = (int) $r->Id_PA_Rater_Category;
        }

        foreach (DB::table('N_PA_Competency_Type')->get() as $r) {
            $this->type[$r->Kode_Type] = (int) $r->Id_PA_Competency_Type;
        }

        $this->levelId = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');

        $this->siapkanSiklus();
    }

    public function test_tanpa_override_semuanya_datang_dari_salinan(): void
    {
        $hasil = $this->resolver->raterRules($this->participantId);

        // Urutannya mengikuti Urutan_Tampil di salinan (SELF 1, ATASAN 2, BAWAHAN 3),
        // BUKAN abjad. Fixture memang sengaja dibuat tidak searah abjad supaya
        // pengurutannya benar-benar teruji.
        $this->assertSame(['SELF', 'ATASAN', 'BAWAHAN'], array_keys($hasil));

        $this->assertSame(50.0, $hasil['ATASAN']['bobot_persen']);
        $this->assertSame(PaParticipantRuleResolver::SUMBER_SALINAN, $hasil['ATASAN']['sumber']);
        $this->assertNull($hasil['ATASAN']['alasan_perubahan']);

        $this->assertSame(
            [PaParticipantRuleResolver::SUMBER_SALINAN],
            array_values(array_unique(array_column($hasil, 'sumber')))
        );
    }

    public function test_override_mengubah_bobot_dan_menandai_sumbernya(): void
    {
        $this->overrideRater('ATASAN', ['Bobot_Persen' => 70, 'Min_Rater' => 1]);

        $hasil = $this->resolver->raterRules($this->participantId);

        $this->assertSame(70.0, $hasil['ATASAN']['bobot_persen']);
        $this->assertSame(PaParticipantRuleResolver::SUMBER_OVERRIDE, $hasil['ATASAN']['sumber']);
        $this->assertSame('Atasan kedua sedang cuti panjang', $hasil['ATASAN']['alasan_perubahan']);

        // Yang tidak disentuh override tetap mengikuti salinan.
        $this->assertSame(PaParticipantRuleResolver::SUMBER_SALINAN, $hasil['BAWAHAN']['sumber']);
        $this->assertSame(30.0, $hasil['BAWAHAN']['bobot_persen']);
    }

    public function test_kolom_yang_tidak_pernah_menyimpang_tetap_diambil_dari_salinan(): void
    {
        $this->overrideRater('ATASAN', ['Bobot_Persen' => 70, 'Min_Rater' => 1]);

        $hasil = $this->resolver->raterRules($this->participantId);

        // Urutan tampil dan izin memilih manual sengaja tidak ada di tabel
        // override (K32). Kalau nilainya jadi kosong, berarti penggabungnya
        // memperlakukan override sebagai pengganti seluruh baris.
        $this->assertSame(2, $hasil['ATASAN']['urutan_tampil']);
        $this->assertSame('Y', $hasil['ATASAN']['flag_izinkan_manual']);
    }

    public function test_override_mencabut_kategori_untuk_satu_orang(): void
    {
        $this->overrideRater('BAWAHAN', ['Flag_Dipakai' => 'T']);

        $hasil = $this->resolver->raterRules($this->participantId);

        $this->assertArrayNotHasKey('BAWAHAN', $hasil);
        $this->assertArrayHasKey('ATASAN', $hasil);
    }

    public function test_kategori_yang_tidak_ada_di_salinan_ditandai_tambahan(): void
    {
        $this->overrideRater('PEER', ['Bobot_Persen' => 20, 'Min_Rater' => 2]);

        $hasil = $this->resolver->raterRules($this->participantId);

        $this->assertArrayHasKey('PEER', $hasil);
        $this->assertSame(PaParticipantRuleResolver::SUMBER_TAMBAHAN, $hasil['PEER']['sumber']);
        $this->assertSame(20.0, $hasil['PEER']['bobot_persen']);

        // Tidak ada baris salinan yang bisa memberi urutannya, jadi kosong —
        // dan karena kosong, ia jatuh ke urutan paling belakang.
        $this->assertNull($hasil['PEER']['urutan_tampil']);
        $this->assertSame('PEER', array_key_last($hasil));
    }

    public function test_nama_beku_di_salinan_menang_atas_master_yang_berganti_nama(): void
    {
        DB::table('N_PA_Rater_Category')
            ->where('Id_PA_Rater_Category', $this->kategori['ATASAN'])
            ->update(['Nama_Kategori' => 'Nama Baru Setelah Siklus']);

        $hasil = $this->resolver->raterRules($this->participantId);

        // Laporan siklus lama harus terbaca dengan nama yang berlaku saat itu.
        $this->assertSame('Atasan Salinan', $hasil['ATASAN']['nama_kategori']);
    }

    public function test_bobot_pilar_mengikuti_salinan_lalu_override(): void
    {
        $sebelum = $this->resolver->pillarWeight($this->participantId);

        $this->assertSame(60.0, $sebelum['bobot_kpi']);
        $this->assertSame(40.0, $sebelum['bobot_kompetensi']);
        $this->assertSame(PaParticipantRuleResolver::SUMBER_SALINAN, $sebelum['sumber']);

        DB::table('N_PA_Participant_Pillar_Override')->insert([
            'PA_Participant_Id' => $this->participantId,
            'Bobot_KPI' => 0,
            'Bobot_Kompetensi' => 100,
            'Alasan_Perubahan' => 'Tidak memegang KPI pada siklus ini',
        ]);

        $this->resolver->lupakanMemo();
        $sesudah = $this->resolver->pillarWeight($this->participantId);

        $this->assertSame(0.0, $sesudah['bobot_kpi']);
        $this->assertSame(100.0, $sesudah['bobot_kompetensi']);
        $this->assertSame(PaParticipantRuleResolver::SUMBER_OVERRIDE, $sesudah['sumber']);
        $this->assertSame('Tidak memegang KPI pada siklus ini', $sesudah['alasan_perubahan']);
    }

    public function test_kebutuhan_kompetensi_bisa_dicabut_untuk_satu_orang(): void
    {
        $kodeType = array_key_first($this->type);

        $sebelum = $this->resolver->competencyRequirements($this->participantId);
        $this->assertArrayHasKey($kodeType, $sebelum);

        DB::table('N_PA_Participant_Requirement_Override')->insert([
            'PA_Participant_Id' => $this->participantId,
            'PA_Competency_Type_Id' => $this->type[$kodeType],
            'Flag_Dipakai' => 'T',
            'Alasan_Perubahan' => 'Officer specialist, tidak memimpin siapa pun',
        ]);

        $this->resolver->lupakanMemo();
        $sesudah = $this->resolver->competencyRequirements($this->participantId);

        $this->assertArrayNotHasKey($kodeType, $sesudah);
    }

    public function test_peserta_tanpa_salinan_aturan_menghasilkan_himpunan_kosong(): void
    {
        DB::table('N_PA_Participant')
            ->where('Id_PA_Participant', $this->participantId)
            ->update([
                'PA_Cycle_Rater_Policy_Snapshot_Id' => null,
                'Status_Peserta' => 'TERTUNDA',
            ]);

        $this->resolver->lupakanMemo();

        // Peserta TERTUNDA memang belum punya aturan penilai. Itu keadaan sah,
        // bukan kegagalan — yang salah justru kalau ia melempar.
        $this->assertSame([], $this->resolver->raterRules($this->participantId));
    }

    public function test_bobot_pilar_yang_tidak_bisa_diresolusi_melempar(): void
    {
        DB::table('N_PA_Cycle_Pillar_Weight_Snapshot')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->delete();

        $this->resolver->lupakanMemo();

        // Diam-diam mengembalikan 50/50 jauh lebih berbahaya daripada gagal.
        $this->expectException(RuntimeException::class);
        $this->resolver->pillarWeight($this->participantId);
    }

    public function test_peserta_yang_tidak_ada_melempar(): void
    {
        $this->expectException(RuntimeException::class);
        $this->resolver->raterRules(999999999);
    }

    public function test_penyimpangan_menghitung_baris_override(): void
    {
        $this->assertSame(
            ['rater' => 0, 'pilar' => 0, 'kompetensi' => 0, 'total' => 0],
            $this->resolver->penyimpangan($this->participantId)
        );

        $this->overrideRater('ATASAN', ['Bobot_Persen' => 70, 'Min_Rater' => 1]);

        DB::table('N_PA_Participant_Pillar_Override')->insert([
            'PA_Participant_Id' => $this->participantId,
            'Bobot_KPI' => 0,
            'Bobot_Kompetensi' => 100,
            'Alasan_Perubahan' => 'Tidak memegang KPI',
        ]);

        $this->assertSame(
            ['rater' => 1, 'pilar' => 1, 'kompetensi' => 0, 'total' => 2],
            $this->resolver->penyimpangan($this->participantId)
        );
    }

    public function test_override_yang_dinonaktifkan_mengembalikan_orang_ke_aturan_siklus(): void
    {
        $this->overrideRater('ATASAN', ['Bobot_Persen' => 70, 'Min_Rater' => 1]);

        DB::table('N_PA_Participant_Rater_Override')
            ->where('PA_Participant_Id', $this->participantId)
            ->update(['Flag_Aktif' => 'T']);

        $this->resolver->lupakanMemo();
        $hasil = $this->resolver->raterRules($this->participantId);

        $this->assertSame(50.0, $hasil['ATASAN']['bobot_persen']);
        $this->assertSame(PaParticipantRuleResolver::SUMBER_SALINAN, $hasil['ATASAN']['sumber']);
    }

    public function test_salinan_dibaca_sekali_lalu_dipakai_ulang(): void
    {
        $jumlah = 0;

        DB::listen(function () use (&$jumlah): void {
            $jumlah++;
        });

        $this->resolver->raterRules($this->participantId);
        $pertama = $jumlah;

        $this->resolver->raterRules($this->participantId);
        $kedua = $jumlah - $pertama;

        // Panggilan kedua hanya perlu membaca override-nya lagi; peserta dan
        // salinan aturan sudah dimemo. Kalau jumlahnya sama, memonya mati.
        $this->assertLessThan(
            $pertama,
            $kedua,
            'Panggilan kedua tidak lebih hemat kueri — memo salinan siklus tidak bekerja.'
        );
    }

    /**
     * Satu siklus dengan salinan aturan lengkap dan satu peserta yang mengikuti
     * standar levelnya. Seluruhnya digulung kembali setelah tiap uji.
     */
    private function siapkanSiklus(): void
    {
        $kode = 'UJI-RSV-'.substr(bin2hex(random_bytes(4)), 0, 8);

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => $kode,
            'Nama_Cycle' => 'Siklus Uji Resolver',
            'Tahun' => 2026,
        ]);

        $this->cycleId = (int) DB::table('N_PA_Cycle')->where('Kode_Cycle', $kode)->value('Id_PA_Cycle');

        DB::table('N_PA_Rater_Policy')->insert([
            'Kode_Policy' => $kode,
            'Nama_Policy' => 'Policy Uji Resolver',
            'ID_Level' => $this->levelId,
            'Berlaku_Dari' => '2026-01-01',
        ]);

        $policyId = (int) DB::table('N_PA_Rater_Policy')->where('Kode_Policy', $kode)->value('Id_PA_Rater_Policy');

        DB::table('N_PA_Cycle_Rater_Policy_Snapshot')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'ID_Level' => $this->levelId,
            'PA_Rater_Policy_Id' => $policyId,
            'Kode_Policy_Salinan' => $kode,
            'Nama_Policy_Salinan' => 'Policy Uji Resolver',
            'Versi_Policy_Salinan' => 1,
        ]);

        $this->policySnapshotId = (int) DB::table('N_PA_Cycle_Rater_Policy_Snapshot')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->value('Id_PA_Cycle_Rater_Policy_Snapshot');

        // Tiga kategori dengan urutan sengaja tidak searah abjad, supaya urutan
        // hasil benar-benar diuji dan bukan kebetulan cocok.
        $detail = [
            ['SELF', 'Diri Sendiri Salinan', 20, 1, 1],
            ['ATASAN', 'Atasan Salinan', 50, 1, 2],
            ['BAWAHAN', 'Bawahan Salinan', 30, 2, 3],
        ];

        foreach ($detail as [$kodeKategori, $nama, $bobot, $min, $urutan]) {
            DB::table('N_PA_Rater_Policy_Detail')->insert([
                'PA_Rater_Policy_Id' => $policyId,
                'PA_Rater_Category_Id' => $this->kategori[$kodeKategori],
                'Bobot_Persen' => $bobot,
                'Min_Rater' => $min,
                'Urutan_Tampil' => $urutan,
            ]);

            $detailId = (int) DB::table('N_PA_Rater_Policy_Detail')
                ->where('PA_Rater_Policy_Id', $policyId)
                ->where('PA_Rater_Category_Id', $this->kategori[$kodeKategori])
                ->value('Id_PA_Rater_Policy_Detail');

            DB::table('N_PA_Cycle_Rater_Policy_Detail_Snapshot')->insert([
                'PA_Cycle_Rater_Policy_Snapshot_Id' => $this->policySnapshotId,
                'PA_Rater_Policy_Detail_Id' => $detailId,
                'PA_Rater_Category_Id' => $this->kategori[$kodeKategori],
                'Kode_Kategori_Salinan' => $kodeKategori,
                'Nama_Kategori_Salinan' => $nama,
                'Bobot_Persen' => $bobot,
                'Min_Rater' => $min,
                'Flag_Izinkan_Manual' => 'Y',
                'Urutan_Tampil' => $urutan,
            ]);
        }

        DB::table('N_PA_Cycle_Pillar_Weight_Snapshot')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'ID_Level' => $this->levelId,
            'Bobot_KPI' => 60,
            'Bobot_Kompetensi' => 40,
        ]);

        foreach ($this->type as $kodeType => $typeId) {
            DB::table('N_PA_Cycle_Competency_Requirement_Snapshot')->insert([
                'PA_Cycle_Id' => $this->cycleId,
                'ID_Level' => $this->levelId,
                'PA_Competency_Type_Id' => $typeId,
                'Kode_Type_Salinan' => $kodeType,
                'Nama_Type_Salinan' => "Salinan {$kodeType}",
                'Flag_Wajib' => 'Y',
            ]);
        }

        $karyawan = DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->orderBy('Kode_Karyawan')
            ->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan']);

        DB::table('N_PA_Participant')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'Kode_Perusahaan' => $karyawan->Kode_Perusahaan,
            'Kode_Karyawan' => $karyawan->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $karyawan->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $karyawan->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => $this->levelId,
            'PA_Cycle_Rater_Policy_Snapshot_Id' => $this->policySnapshotId,
            'Status_Peserta' => 'DIIKUTKAN',
        ]);

        $this->participantId = (int) DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->value('Id_PA_Participant');
    }

    /**
     * @param  array<string, mixed>  $nilai
     */
    private function overrideRater(string $kodeKategori, array $nilai): void
    {
        DB::table('N_PA_Participant_Rater_Override')->insert(array_merge([
            'PA_Participant_Id' => $this->participantId,
            'PA_Rater_Category_Id' => $this->kategori[$kodeKategori],
            'Alasan_Perubahan' => 'Atasan kedua sedang cuti panjang',
        ], $nilai));

        $this->resolver->lupakanMemo();
    }
}
