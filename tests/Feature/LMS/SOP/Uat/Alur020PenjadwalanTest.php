<?php

namespace Tests\Feature\LMS\SOP\Uat;

use App\Services\LMS\SOP\SopPenjadwalanService;
use App\Services\LMS\SOP\SopPenjadwalanTargetService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Feature\LMS\SOP\Uat\Support\UatAkun;
use Tests\Feature\LMS\SOP\Uat\Support\UatDunia;
use Tests\TestCase;

/**
 * Penjadwalan & penugasan — jalur normal.
 *
 * UAT-SOP-020 menyasar unit berikut tenggat · 021 menyasar orang tertentu ·
 * 023 menambah karyawan ke penjadwalan yang sudah berjalan.
 *
 * Yang diperiksa bukan cuma "baris penugasan terbentuk", melainkan bahwa
 * PROGRES ikut tersemai. Penugasan tanpa progres terlihat sempurna di layar
 * admin dan kosong sama sekali di layar karyawan — kegagalan yang selalu
 * dilaporkan dari sisi yang salah.
 */
class Alur020PenjadwalanTest extends TestCase
{
    use DatabaseTransactions;

    private function dunia(): UatDunia
    {
        $admin = UatAkun::admin();
        if (! $admin) {
            $this->markTestSkipped('Tidak ada user dengan keenam hak akses halaman dokumen.');
        }

        try {
            return UatDunia::mulai((int) $admin->Id_Users)
                ->buatDokumenTerbit()->buatPaket()
                ->tambahItem('Item Wajib', wajib: true)
                ->terbitkanPaket();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }
    }

    /** Beberapa kode karyawan yang benar-benar ada, dari saringan unit. */
    private function kodeDariUnit(int $berapa): array
    {
        $svc = app(SopPenjadwalanTargetService::class);
        $opsi = $svc->filterOptions();

        foreach (['divisi' => 'divisi', 'departemen' => 'dept'] as $kunciOpsi => $kunciSaringan) {
            $daftar = $opsi[$kunciOpsi] ?? [];
            $daftar = is_object($daftar) && method_exists($daftar, 'all') ? $daftar->all() : (array) $daftar;

            foreach ($daftar as $nilai) {
                if (! is_string($nilai) || trim($nilai) === '') {
                    continue;
                }
                $kode = $svc->resolveTargets([$kunciSaringan => [$nilai]]);
                if (count($kode) >= $berapa) {
                    return array_slice($kode, 0, $berapa);
                }
            }
        }

        $this->markTestSkipped("Tidak ada unit dengan minimal {$berapa} karyawan di database ini.");
    }

    private function detail(int $idPenjadwalan): \Illuminate\Support\Collection
    {
        return DB::table('N_SOP_Penjadwalan_Detail')
            ->where('Id_SOP_Penjadwalan', $idPenjadwalan)
            ->get();
    }

    #[Group('UAT-SOP-020')]
    public function test_penjadwalan_unit_membentuk_penugasan_beserta_tenggat_dan_progres(): void
    {
        $d = $this->dunia();
        $kode = $this->kodeDariUnit(2);
        $tenggat = now()->addDays(14)->toDateString();

        $hasil = app(SopPenjadwalanService::class)->assign($d->idPaket, $kode, [
            'nama_batch' => $d->nama('Batch Unit'),
            'sumber' => 'MANUAL',
            'tipe_tenggat' => 'DEADLINE',
            'tanggal_deadline' => $tenggat,
        ], $d->userIdUji());

        $this->assertSame(count($kode), (int) $hasil['assigned'],
            'Jumlah penugasan yang terbentuk berbeda dari jumlah sasaran.');

        $header = DB::table('N_SOP_Penjadwalan')
            ->where('Id_SOP_Penjadwalan', $hasil['penjadwalan_id'])->first();

        $this->assertSame('DEADLINE', $header->Tipe_Tenggat);
        $this->assertStringStartsWith($tenggat, (string) $header->Tanggal_Deadline,
            'Tenggat tidak tersimpan; kewajiban baca menjadi tanpa batas waktu.');

        $detail = $this->detail((int) $hasil['penjadwalan_id']);
        $this->assertCount(count($kode), $detail);

        /* Inti yang mudah terlewat: progres per item wajib harus ikut tersemai.
           Tanpa ini, admin melihat penugasan yang rapi sementara karyawan
           membuka SOP Saya dan menemukannya kosong. */
        $progres = DB::table('N_SOP_Progress')
            ->whereIn('Id_SOP_Penjadwalan_Detail', $detail->pluck('Id_SOP_Penjadwalan_Detail'))
            ->count();

        $this->assertGreaterThanOrEqual(count($kode), $progres,
            'Progres tidak tersemai untuk item wajib. Penugasan terlihat sempurna di sisi '
            .'admin dan kosong sama sekali di sisi karyawan.');
    }

    #[Group('UAT-SOP-021')]
    public function test_menugaskan_orang_yang_sama_dua_kali_tidak_menggandakan_penugasan(): void
    {
        $d = $this->dunia();
        $kode = $this->kodeDariUnit(1);
        $svc = app(SopPenjadwalanService::class);

        $pertama = $svc->assign($d->idPaket, $kode, ['nama_batch' => $d->nama('Batch A')], $d->userIdUji());
        $kedua = $svc->assign($d->idPaket, $kode, ['nama_batch' => $d->nama('Batch B')], $d->userIdUji());

        $this->assertSame(1, (int) $pertama['assigned']);
        $this->assertSame(0, (int) $kedua['assigned'],
            'Penugasan kedua atas orang yang sama ikut terbentuk.');
        $this->assertSame(1, (int) $kedua['skipped'],
            'Orang yang dilewati tidak dilaporkan, sehingga admin mengira penugasannya '
            .'gagal padahal justru sudah ada.');

        $aktif = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as p', 'p.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->where('p.Id_SOP_Paket', $d->idPaket)
            ->where('d.Kode_Karyawan', $kode[0])
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->count();

        $this->assertSame(1, $aktif,
            'Satu karyawan punya lebih dari satu penugasan aktif pada paket yang sama; '
            .'angka kepatuhan akan menghitungnya dua kali.');
    }

    #[Group('UAT-SOP-023')]
    public function test_karyawan_bisa_ditambahkan_ke_penjadwalan_yang_sudah_berjalan(): void
    {
        $d = $this->dunia();
        $kode = $this->kodeDariUnit(2);
        $svc = app(SopPenjadwalanService::class);

        $awal = $svc->assign($d->idPaket, [$kode[0]], ['nama_batch' => $d->nama('Batch Berjalan')], $d->userIdUji());
        $idPenjadwalan = (int) $awal['penjadwalan_id'];

        $tambah = DB::transaction(fn () => $svc->assignKeHeader(
            $idPenjadwalan, $d->idPaket, [$kode[1]], $d->userIdUji()
        ));

        $this->assertSame(1, (int) $tambah['assigned']);

        $detail = $this->detail($idPenjadwalan);
        $this->assertCount(2, $detail,
            'Karyawan yang ditambahkan tidak masuk ke penjadwalan yang sama; ia akan '
            .'terpisah dari batch dan luput dari pemantauannya.');

        $detailBaru = $detail->firstWhere('Kode_Karyawan', $kode[1])
            ?? $detail->first(fn ($x) => trim((string) $x->Kode_Karyawan) === trim($kode[1]));

        $this->assertNotNull($detailBaru);
        $this->assertGreaterThan(0,
            DB::table('N_SOP_Progress')
                ->where('Id_SOP_Penjadwalan_Detail', $detailBaru->Id_SOP_Penjadwalan_Detail)
                ->count(),
            'Karyawan yang ditambahkan belakangan tidak mendapat progres apa pun — '
            .'tugasnya ada di catatan admin tapi tak pernah muncul di layarnya.');
    }
}
