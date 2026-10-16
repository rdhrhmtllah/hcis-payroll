<?php

namespace App\Console\Commands\LMS\SOP\Operations;

use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pemeriksa integritas Master Dokumen SOP — BACA-SAJA, tak pernah menulis.
 *
 * Dipakai sebagai gerbang: Fase 2 (memindahkan pembacaan ke dokumen) tak boleh
 * dimulai sebelum command ini keluar dengan kode 0.
 */
class SopDokumenVerify extends Command
{
    protected $signature = 'sop:dokumen-verify';

    protected $description = 'Periksa integritas tautan dokumen SOP (baca-saja)';

    public function handle(): int
    {
        $pelanggaran = 0;

        /* DIPERLUAS DI FASE 6: syarat "punya baris versi" dibuang.
           Semula invarian ini hanya melihat item yang SUDAH punya baris versi —
           sama seperti keempat invarian lain, yang semuanya berangkat dari
           baris versi. Akibatnya ada titik buta utuh: item ber-tipe versi yang
           belum punya versi sama sekali tak terlihat oleh SATU PUN invarian,
           dan backfill pun melewatkannya. Di dev ada dua (KUIS #28 dan #98),
           dan keduanya lolos "BERSIH" selama lima fase.

           Sejak Fase 6 identitas item dibaca dari dokumennya, jadi item tanpa
           dokumen kehilangan judulnya. Setiap item ber-tipe versi wajib punya
           dokumen — ada versinya atau belum. */
        $itemTanpaDokumen = DB::table('N_SOP_Paket_Item as i')
            ->whereIn('i.Tipe_Item', SopTipeItem::VERSIONED)
            ->whereNull('i.Id_SOP_Dokumen')
            ->count();
        $pelanggaran += $this->lapor('Item ber-tipe versi tanpa dokumen', $itemTanpaDokumen);

        $versiNyasar = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'v.Id_SOP_Paket_Item')
            ->whereNotNull('i.Id_SOP_Dokumen')
            ->where(fn ($q) => $q->whereNull('v.Id_SOP_Dokumen')
                ->orWhereColumn('v.Id_SOP_Dokumen', '<>', 'i.Id_SOP_Dokumen'))
            ->count();
        $pelanggaran += $this->lapor('Baris versi tak ikut dokumen itemnya', $versiNyasar);

        $tipeSalah = DB::table('N_SOP_Paket_Item')
            ->whereNotIn('Tipe_Item', SopTipeItem::VERSIONED)
            ->whereNotNull('Id_SOP_Dokumen')->count();
        $pelanggaran += $this->lapor('Tipe tanpa versi tapi punya dokumen', $tipeSalah);

        $pointerAsing = DB::table('N_SOP_Dokumen as d')
            ->leftJoin('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'd.Id_Versi_Aktif')
            ->whereNotNull('d.Id_Versi_Aktif')
            ->where(fn ($q) => $q->whereNull('v.Id_SOP_Item_Versi')   // pointer menggantung (id tak ada)
                ->orWhereNull('v.Id_SOP_Dokumen')                      // versi belum ikut dokumen mana pun
                ->orWhereColumn('v.Id_SOP_Dokumen', '<>', 'd.Id_SOP_Dokumen')) // versi milik dokumen lain
            ->count();
        $pelanggaran += $this->lapor('Pointer versi aktif menggantung/tanpa dokumen/milik dokumen lain', $pointerAsing);

        /* ITEM MUSTAHIL DISELESAIKAN.
           Item ber-versi tanpa versi berlaku, di paket yang SUDAH TERBIT:
           karyawan menerimanya sebagai kewajiban yang tak berisi apa-apa dan
           tak akan pernah bisa dituntaskan, sehingga kepatuhan mereka tertahan
           permanen. Ditemukan pada 11 Agustus 2026: satu item wajib di paket
           terbit sejak 3 Agustus, lima karyawan terdampak, dan tak satu pun
           layar melaporkannya.

           Dibatasi paket TERBIT dengan sengaja. Di paket DRAFT keadaan ini
           normal dan biasa — item baru memang belum punya versi — dan
           memerahkan gerbang untuknya akan membuat gerbang ini merah setiap
           kali admin menyusun paket baru, lalu berhenti dipercaya. */
        $itemMustahil = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('p.Status', SopStatus::PAKET_PUBLISHED)
            ->whereIn('i.Tipe_Item', SopTipeItem::VERSIONED)
            ->whereNull('i.Id_Item_Versi_Aktif')
            ->count();
        $pelanggaran += $this->lapor('Item ber-versi tanpa versi berlaku di paket TERBIT', $itemMustahil);

        /* DILONGGARKAN JADI INFO saat halaman Master Dokumen lahir.
           Dulu dokumen SELALU tercipta bersama itemnya, sehingga dokumen tanpa
           item pasti cacat. Sejak deklarasi SOP menjadi langkah PERTAMA alur
           (admin mendeklarasikan di Master Dokumen, baru memakainya di paket),
           dokumen belum-dipakai adalah keadaan yang SAH dan biasa.

           Membiarkannya tetap pelanggaran berarti gerbang ini merah setiap kali
           admin mendeklarasikan SOP baru — dan gerbang yang rutin merah tanpa
           sebab nyata adalah gerbang yang berhenti dipercaya, lalu diabaikan
           justru saat ia benar-benar menangkap sesuatu.

           Angkanya tetap dilaporkan, dan halaman Master menandai dokumen
           "belum dipakai" supaya yang benar-benar terlantar tetap terlihat. */
        $yatim = DB::table('N_SOP_Dokumen as d')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('N_SOP_Paket_Item as i')
                ->whereColumn('i.Id_SOP_Dokumen', 'd.Id_SOP_Dokumen'))
            ->count();
        $this->line("  <fg=cyan>INFO</> Dokumen belum dipakai paket mana pun: {$yatim}");

        /*
         * BARIS INFORMASI — BUKAN invarian. Sengaja tak menambah $pelanggaran
         * dan tak mengubah exit code.
         *
         * Kelima invarian di atas semuanya bertumpu pada item: yang diperiksa
         * adalah baris versi yang ITEMNYA punya dokumen. Baris versi yang
         * itemnya juga tak punya dokumen — mis. item yang Tipe_Item-nya kemudian
         * diubah menjauh dari tipe ber-versi, atau versi yatim sungguhan — tak
         * terlihat oleh satu pun di antaranya, dan itu SAH di Fase 1.
         *
         * Angkanya tetap wajib dilaporkan karena Fase 2 me-rekey
         * UX_N_SOP_Item_Versi_Urut ke (Id_SOP_Dokumen, Versi_Ke), dan SQL Server
         * menganggap NULL SETARA di unique index: baris (NULL, 1) yang kedua
         * akan ditolak dengan error 2601. Selama angka ini > 0, Fase 2 HARUS
         * memilih index TERFILTER (WHERE Id_SOP_Dokumen IS NOT NULL) secara
         * sadar — bukan menemukannya saat DDL gagal.
         */
        $versiTanpaDokumen = DB::table('N_SOP_Item_Versi')->whereNull('Id_SOP_Dokumen')->count();
        $this->line("  <fg=cyan>INFO</> Baris versi tanpa dokumen (di luar kelima invarian): {$versiTanpaDokumen}");
        if ($versiTanpaDokumen > 0) {
            $this->line('       Fase 2 wajib memakai unique index TERFILTER (WHERE Id_SOP_Dokumen IS NOT NULL) —'
                .' SQL Server menganggap NULL setara, jadi index polos akan gagal (2601).');
        }

        if ($pelanggaran === 0) {
            $this->info('Integritas dokumen SOP: BERSIH.');

            return self::SUCCESS;
        }

        $this->error("Ditemukan {$pelanggaran} jenis pelanggaran. JANGAN lanjut ke fase berikutnya sebelum bersih.");

        return self::FAILURE;
    }

    private function lapor(string $label, int $jumlah): int
    {
        if ($jumlah === 0) {
            $this->line("  <fg=green>OK</>   {$label}: 0");

            return 0;
        }
        $this->line("  <fg=red>GAGAL</> {$label}: {$jumlah}");

        return 1;
    }
}
