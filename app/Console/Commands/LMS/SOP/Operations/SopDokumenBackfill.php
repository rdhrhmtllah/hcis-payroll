<?php

namespace App\Console\Commands\LMS\SOP\Operations;

use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill Fase 1 Master Dokumen SOP — tiap item ber-versi menjadi SATU dokumen.
 *
 * SENGAJA 1:1, TIDAK menggabungkan duplikat. Satu berkas yang dipakai banyak
 * paket hari ini menyimpan pengesahan yang sudah MENYIMPANG (konten#80: dua
 * Tanggal_Kadaluarsa berbeda; konten#173: Nomor_Pengesahan, Tanggal_Berlaku, dan
 * Disahkan_Oleh semuanya berbeda). Mesin tak bisa memilih mana yang benar.
 * Penggabungan adalah keputusan admin yang tercatat — Fase 7.
 *
 * Item nonaktif (Flag_Aktif='T') IKUT di-backfill selama ia punya baris versi,
 * supaya tak ada versi yang jadi yatim dan riwayat progres lama tetap tertelusur.
 *
 * DUA LINTASAN, dan lintasan kedua itu yang membuatnya KONVERGEN — bukan sekadar
 * idempoten. Lintasan 1 hanya menyentuh item yang belum punya dokumen; lintasan 2
 * menyembuhkan baris versi yang itemnya SUDAH punya dokumen tapi versinya belum
 * ikut. Tanpa lintasan 2, alur revisi normal jadi ranjau: SopItemVersionService::create()
 * (dipanggil SopBerkasController::storeVersi & SopAdminBuilderController) menulis
 * baris versi TANPA Id_SOP_Dokumen, jadi begitu admin menambah v2 pada item mana
 * pun yang sudah di-backfill, invarian 2 sop:dokumen-verify langsung GAGAL — dan
 * lintasan 1 tak bisa memperbaikinya karena itemnya sudah tertaut. Gerbang Fase 2
 * akan merah permanen tanpa obat selain SQL manual.
 *
 * SELALU jalankan --dry-run lebih dulu.
 */
class SopDokumenBackfill extends Command
{
    protected $signature = 'sop:dokumen-backfill
        {--dry-run : Tampilkan rencana tanpa menulis apa pun}
        {--paket= : Batasi ke satu Id_SOP_Paket}';

    protected $description = 'Backfill 1:1 item ber-versi SOP menjadi entitas N_SOP_Dokumen';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        /* --paket=abc di-cast jadi 0 dan `when(0)` bernilai falsy, sehingga
           filternya diam-diam hilang dan command menyapu SELURUH paket —
           kebalikan persis dari yang diminta operator. Salah ketik pada
           command yang menulis data harus berhenti, bukan melebar. */
        $paketMentah = $this->option('paket');
        if ($paketMentah !== null && $paketMentah !== '') {
            if (! ctype_digit((string) $paketMentah) || (int) $paketMentah < 1) {
                $this->error("--paket harus bilangan bulat positif (Id_SOP_Paket), diberi: {$paketMentah}");

                return self::FAILURE;
            }
            $paket = (int) $paketMentah;
        } else {
            $paket = null;
        }

        $items = DB::table('N_SOP_Paket_Item as i')
            ->whereIn('i.Tipe_Item', SopTipeItem::VERSIONED)
            ->whereNull('i.Id_SOP_Dokumen')
            ->when($paket, fn ($q) => $q->where('i.Id_SOP_Paket', $paket))
            /* SYARAT "punya baris versi" DIBUANG (Fase 6).
               Semula lintasan ini hanya menyentuh item yang SUDAH punya baris
               versi. Akibatnya item ber-tipe versi yang belum punya versi sama
               sekali — di dev: KUIS #28 dan #98 — tak pernah dapat dokumen, dan
               kelima invarian sop:dokumen-verify juga tak melihatnya karena
               semuanya memeriksa BARIS VERSI.

               Selama identitas masih di item, itu tak berakibat apa-apa. Sejak
               Fase 6 memindahkan identitas ke dokumen, item semacam itu
               KEHILANGAN JUDULNYA. Setiap item ber-tipe versi karena itu wajib
               punya dokumen, ada versinya atau belum. */
            ->orderBy('i.Id_SOP_Paket_Item')
            ->get([
                'i.Id_SOP_Paket_Item', 'i.Id_SOP_Paket', 'i.Judul_Item', 'i.Nomor_SOP_Resmi',
                'i.Tipe_Item', 'i.Deskripsi_Item', 'i.Id_Item_Versi_Aktif', 'i.Created_By',
            ]);

        // Lintasan 2 (penyembuhan): baris versi yang itemnya SUDAH bertaut tapi
        // versinya belum. Dihitung di sini supaya dry-run melaporkannya juga.
        $versiLepas = $this->versiLepas($paket)->get(['v.Id_SOP_Item_Versi', 'i.Id_SOP_Dokumen']);

        if ($items->isEmpty() && $versiLepas->isEmpty()) {
            $this->info('Tidak ada item ber-versi yang perlu di-backfill dan tak ada baris versi lepas — semua sudah tertaut.');

            return self::SUCCESS;
        }

        $awalan = $dryRun ? '[DRY-RUN] ' : '';
        $this->info($awalan."Akan membuat {$items->count()} dokumen (1 per item ber-versi).");
        $this->info($awalan."Akan menyembuhkan {$versiLepas->count()} baris versi yang itemnya sudah bertaut dokumen.");

        if ($items->isNotEmpty()) {
            $this->table(
                ['Item', 'Paket', 'Tipe', 'Judul'],
                $items->take(15)->map(fn ($i) => [
                    $i->Id_SOP_Paket_Item, $i->Id_SOP_Paket, $i->Tipe_Item,
                    mb_strimwidth((string) $i->Judul_Item, 0, 50, '…'),
                ])->all()
            );
            if ($items->count() > 15) {
                $this->line('… dan '.($items->count() - 15).' lainnya.');
            }
        }

        if ($dryRun) {
            $this->warn('Dry-run: tak ada yang ditulis. Jalankan tanpa --dry-run untuk menerapkan.');

            return self::SUCCESS;
        }

        $dibuat = 0;
        foreach ($items as $it) {
            DB::transaction(function () use ($it, &$dibuat) {
                $idDokumen = DB::table('N_SOP_Dokumen')->insertGetId([
                    'Nomor_SOP_Resmi' => $it->Nomor_SOP_Resmi,
                    // N_SOP_Dokumen.Judul NOT NULL (26A), sementara Judul_Item
                    // nullable. Fallback ini HANYA untuk judul kosong/NULL;
                    // untuk judul terisi, dokumen menyalinnya apa adanya — itu
                    // yang diuji test_identitas_dokumen_diambil_dari_itemnya,
                    // dan test_dokumen_selalu_punya_judul menjaga sisi NOT NULL-nya.
                    'Judul' => self::judulDokumen($it->Judul_Item),
                    'Tipe' => $it->Tipe_Item,
                    'Deskripsi' => $it->Deskripsi_Item,
                    'Id_Versi_Aktif' => $it->Id_Item_Versi_Aktif,
                    'Flag_Aktif' => SopStatus::AKTIF,
                    'Created_By' => $it->Created_By,
                    'Created_At' => now(),
                ], 'Id_SOP_Dokumen');

                DB::table('N_SOP_Paket_Item')
                    ->where('Id_SOP_Paket_Item', $it->Id_SOP_Paket_Item)
                    ->update(['Id_SOP_Dokumen' => $idDokumen]);

                DB::table('N_SOP_Item_Versi')
                    ->where('Id_SOP_Paket_Item', $it->Id_SOP_Paket_Item)
                    ->update(['Id_SOP_Dokumen' => $idDokumen]);

                $dibuat++;
            });
        }

        // Lintasan 2 — penyembuhan. Dikelompokkan per dokumen supaya jumlah
        // UPDATE-nya sebanyak dokumen terdampak, bukan sebanyak baris versi.
        $disembuhkan = 0;
        foreach ($versiLepas->groupBy('Id_SOP_Dokumen') as $idDokumen => $baris) {
            $ids = $baris->pluck('Id_SOP_Item_Versi')->all();
            $disembuhkan += DB::table('N_SOP_Item_Versi')
                ->whereIn('Id_SOP_Item_Versi', $ids)
                ->update(['Id_SOP_Dokumen' => (int) $idDokumen]);
        }

        $this->info("Selesai: {$dibuat} dokumen dibuat & ditautkan, {$disembuhkan} baris versi lepas disembuhkan.");
        $this->line('Jalankan `php artisan sop:dokumen-verify` untuk memastikan integritasnya.');

        return self::SUCCESS;
    }

    /**
     * Baris versi yang itemnya sudah punya dokumen tapi versinya belum ikut.
     * Sengaja TANPA saringan Tipe_Item: bila tipe item pernah berubah menjauh
     * dari tipe ber-versi, baris versinya tetap harus ikut dokumen item itu —
     * dan invarian 2 sop:dokumen-verify memang menagihnya tanpa saringan tipe.
     */
    private function versiLepas(?int $paket)
    {
        return DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'v.Id_SOP_Paket_Item')
            ->whereNotNull('i.Id_SOP_Dokumen')
            ->whereNull('v.Id_SOP_Dokumen')
            ->when($paket, fn ($q) => $q->where('i.Id_SOP_Paket', $paket))
            ->orderBy('v.Id_SOP_Item_Versi');
    }

    /** Judul dokumen = judul item; fallback hanya bila item tak berjudul (kolom NOT NULL). */
    public static function judulDokumen(?string $judulItem): string
    {
        return trim((string) $judulItem) !== '' ? $judulItem : 'Dokumen tanpa judul';
    }
}
