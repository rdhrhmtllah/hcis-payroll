<?php

namespace Tests\Feature\SYSTEM;

use Tests\TestCase;

/**
 * Ledger perubahan basis data harus menunjuk berkas yang benar-benar ada,
 * dengan checksum yang benar-benar cocok.
 *
 * ── Kenapa dijaga ─────────────────────────────────────────────────────────
 *
 * `forward_checksum_sha256` adalah satu-satunya cara mengetahui bahwa skrip
 * yang akan dijalankan di production adalah skrip yang direview. Checksum yang
 * basi lebih buruk daripada tidak ada checksum sama sekali: ia terlihat seperti
 * jaminan, padahal bukan.
 *
 * Cacat yang memunculkan penjaga ini nyata dan buatan sendiri. Skrip renumbering
 * pada PR #366 menghitung checksum LEBIH DULU, lalu pass berikutnya menulis ulang
 * berkas yang sama untuk mengganti sebutan nomor lama. Nilai yang tersimpan basi
 * sejak menit pertama, dan tidak ada satu pun yang menangkapnya sampai ledger itu
 * diperiksa ulang secara kebetulan.
 *
 * ── Kenapa ada daftar pengecualian ────────────────────────────────────────
 *
 * Sepuluh entri sudah menyimpang sebelum penjaga ini ditulis. Semuanya
 * `sql_tracker = PENDING`, artinya belum melewati garis beku TRACKER_RECORDED,
 * jadi penyimpangannya bukan pelanggaran integritas — kemungkinan besar hasil
 * amandemen di tempat yang memang diizinkan sebelum tracker mencatatnya.
 *
 * Daftar itu DIBEKUKAN, bukan diabaikan: entri baru yang menyimpang membuat uji
 * ini merah, dan entri lama yang diperbaiki juga membuatnya merah sampai
 * barisnya dihapus dari daftar. Utangnya tidak bisa bertambah diam-diam dan
 * tidak bisa lenyap dari catatan.
 *
 * Uji ini tidak menyentuh basis data.
 */
class LedgerChangeUtuhTest extends TestCase
{
    private const LEDGER = 'database/ddl/status.json';

    /**
     * Entri yang checksum-nya sudah menyimpang sebelum penjaga ini ada.
     * Semuanya sql_tracker = PENDING pada 2026-09-08. Jangan tambah baris di
     * sini: perbaiki checksum-nya.
     */
    private const DRIFT_LAMA = [
        'DDL-2026-006-add-provider-result-to-task-outbox',
        'DDL-2026-007-create-pengumpulan-data-tables',
        'DML-2026-008-register-pengumpulan-data-menu',
        'DDL-2026-009-create-report-template-tables',
        'DML-2026-010-register-report-template-change',
        'DML-2026-011-backfill-dashboard-changes',
        'DML-2026-012-register-sop-export-studio-menu',
        'DML-2026-013-bersihkan-pengesahan-non-dokumen',
        'DML-2026-015-bersihkan-riwayat-kepatuhan-yatim',
        'DDL-2026-022-hris-entitas-logo',
    ];

    /** @return list<array<string, mixed>> */
    private function entri(): array
    {
        $isi = (string) file_get_contents(base_path(self::LEDGER));
        $data = json_decode($isi, true);
        $this->assertIsArray($data, 'status.json bukan JSON yang sah');
        $this->assertArrayHasKey('changes', $data);

        return $data['changes'];
    }

    public function test_setiap_entri_menunjuk_berkas_yang_ada(): void
    {
        $hilang = [];
        foreach ($this->entri() as $c) {
            foreach (['artifact_path', 'documentation_path'] as $kunci) {
                $p = $c[$kunci] ?? null;
                if ($p === null || $p === '') {
                    continue;
                }
                if (! is_file(base_path($p))) {
                    $hilang[] = "{$c['change_id']}: {$kunci} = {$p}";
                }
            }
        }

        $this->assertSame([], $hilang,
            'Ledger menunjuk berkas yang tidak ada. Penyebab paling sering: direktori '
            .'dinamai ulang tanpa memperbarui artifact_path/documentation_path.');
    }

    public function test_checksum_forward_cocok_dengan_isi_berkasnya(): void
    {
        $menyimpang = [];
        foreach ($this->entri() as $c) {
            $p = $c['artifact_path'] ?? null;
            if ($p === null || ! is_file(base_path($p))) {
                continue;   // ketiadaan berkas sudah diuji terpisah
            }
            $nyata = hash_file('sha256', base_path($p));
            if ($nyata !== ($c['forward_checksum_sha256'] ?? null)) {
                $menyimpang[] = $c['change_id'];
            }
        }

        $baru = array_values(array_diff($menyimpang, self::DRIFT_LAMA));
        $this->assertSame([], $baru,
            'Checksum tidak cocok dengan isi berkasnya. Kalau berkasnya memang sengaja '
            .'diubah (dan belum TRACKER_RECORDED), hitung ulang checksum-nya — jangan '
            .'menambahkannya ke DRIFT_LAMA.');

        /*
         * Sisi ratchet: entri lama yang sudah diperbaiki harus keluar dari
         * daftar, supaya daftar ini tidak jadi tempat sampah abadi.
         */
        $sudahBeres = array_values(array_diff(self::DRIFT_LAMA, $menyimpang));
        $this->assertSame([], $sudahBeres,
            'Entri ini sudah tidak menyimpang lagi. Hapus barisnya dari DRIFT_LAMA.');
    }

    public function test_change_id_tidak_kembar_dan_cocok_dengan_nama_direktorinya(): void
    {
        $id = [];
        $salahDirektori = [];
        foreach ($this->entri() as $c) {
            $id[] = $c['change_id'];
            $p = $c['artifact_path'] ?? '';
            /*
             * Tabrakan nomor DDL-2026-068/DML-2026-077 dengan pekerjaan
             * Performance Appraisal terjadi karena nomor diambil dari ledger
             * berkas ini, yang tertinggal dari tabel N_SYSTEM_Database_Change.
             * Yang bisa dijaga tanpa basis data hanyalah konsistensi internal:
             * change_id harus sama dengan nama direktori artefaknya.
             */
            if ($p !== '' && ! str_contains($p, '/'.$c['change_id'].'/')) {
                $salahDirektori[] = "{$c['change_id']} -> {$p}";
            }
        }

        $kembar = array_keys(array_filter(array_count_values($id), fn ($n) => $n > 1));
        $this->assertSame([], $kembar, 'change_id kembar di dalam status.json.');
        $this->assertSame([], $salahDirektori,
            'change_id tidak cocok dengan direktori artefaknya.');
    }
}
