<?php

namespace Tests\Unit\VISITOR\Workflow\Reporting\Export;

use App\Services\VISITOR\Workflow\Reporting\Export\VisitorGuestDataset;
use App\Services\VISITOR\Workflow\Reporting\Export\VisitorPermitDataset;
use App\Services\VISITOR\Workflow\Reporting\Export\VisitorSystemTemplates;
use PHPUnit\Framework\TestCase;

class VisitorReportCatalogTest extends TestCase
{
    public function test_guest_and_permit_are_separate_datasets(): void
    {
        $guest = new VisitorGuestDataset;
        $permit = new VisitorPermitDataset;

        $this->assertSame('tamu', $guest->kunci());
        $this->assertSame('permit', $permit->kunci());
        $this->assertStringContainsString('transaksi kunjungan', $guest->grain());
        $this->assertStringContainsString('permit kendaraan', $permit->grain());
    }

    public function test_guest_sensitive_fields_are_explicitly_marked(): void
    {
        $dimensions = collect((new VisitorGuestDataset)->dimensi())->keyBy('key');

        $this->assertTrue($dimensions['no_hp']->sensitif);
        $this->assertTrue($dimensions['no_identitas']->sensitif);
        $this->assertFalse($dimensions['nama_tamu']->sensitif);
    }

    public function test_permit_does_not_publish_nik_or_phone(): void
    {
        $keys = array_map(fn ($dimension) => $dimension->key, (new VisitorPermitDataset)->dimensi());

        $this->assertNotContains('nik', $keys);
        $this->assertNotContains('no_hp', $keys);
    }

    /** Pembaruan VMS 29 Sep 2026 wajib bisa diekspor (permintaan user). */
    public function test_pembaruan_29_sep_tersedia_di_export(): void
    {
        $permit = collect((new VisitorPermitDataset)->dimensi())->keyBy('key');
        foreach (['nfc_inbio', 'no_hp_pemohon', 'estimasi_masuk_tamu', 'estimasi_keluar_tamu', 'pos_inspeksi',
            'jadwal_inspeksi', 'status_inspeksi', 'diinspeksi_oleh', 'diinspeksi_pada', 'disetujui_sebelum_jadwal',
            'foto_pemeriksaan', 'jumlah_foto_pemeriksaan'] as $kunci) {
            $this->assertTrue($permit->has($kunci), "Dimensi permit '{$kunci}' hilang dari export.");
        }
        $this->assertTrue($permit['no_hp_pemohon']->sensitif, 'Nomor HP pemohon wajib ditandai sensitif.');
        foreach (['tautan_foto_kendaraan', 'tautan_stnk', 'tautan_sim'] as $kunci) {
            $this->assertTrue($permit[$kunci]->sensitif, "Tautan '{$kunci}' wajib sensitif.");
            $this->assertSame('tautan:visitor_dokumen_permit', $permit[$kunci]->format);
        }

        $tamu = collect((new VisitorGuestDataset)->dimensi())->keyBy('key');
        foreach (['akses_mulai', 'akses_berakhir', 'unit_b2b', 'pos_inspeksi', 'status_inspeksi', 'diinspeksi_oleh',
            'model_kendaraan_inspeksi', 'warna_kendaraan_inspeksi', 'tahun_kendaraan_inspeksi', 'foto_pemeriksaan',
            'tautan_foto_kendaraan_tamu', 'tautan_permit_stnk'] as $kunci) {
            $this->assertTrue($tamu->has($kunci), "Dimensi tamu '{$kunci}' hilang dari export.");
        }
    }

    public function test_system_templates_are_partitioned_by_dataset(): void
    {
        $guest = VisitorSystemTemplates::untuk('tamu');
        $permit = VisitorSystemTemplates::untuk('permit');

        $this->assertCount(2, $guest);
        $this->assertCount(2, $permit);
        $this->assertSame(['tamu'], array_values(array_unique(array_column($guest, 'dataset'))));
        $this->assertSame(['permit'], array_values(array_unique(array_column($permit, 'dataset'))));
    }
}
