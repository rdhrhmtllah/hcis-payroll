<?php

namespace App\Console\Commands\LMS\SOP\Operations;

use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Audit dokumen SOP kembar — BACA-SAJA.
 *
 * Sengaja tidak menggabungkan apa pun. Dua dokumen berjudul sama menempel pada
 * item yang berbeda di paket yang berbeda, masing-masing dengan riwayat versi
 * dan catatan baca karyawannya sendiri. Menggabungkannya otomatis berarti
 * memilih riwayat mana yang dibuang — keputusan yang tak bisa dibatalkan dan
 * tak boleh diambil sebuah perintah.
 *
 * Yang diberikan perintah ini adalah yang dibutuhkan untuk MEMILIH: siapa
 * memakai apa, mana yang punya versi terbit, dan mana yang tak menempel pada
 * paket mana pun sehingga aman ditinggalkan.
 */
class SopDokumenKembar extends Command
{
    protected $signature = 'sop:dokumen-kembar
        {--nomor : Hanya kelompok yang nomor dokumen resminya sama — rujukan kepatuhan yang paling berbahaya bila ambigu}';

    protected $description = 'Daftar dokumen SOP kembar beserta pemakainya (baca-saja, tak mengubah apa pun)';

    public function handle(): int
    {
        $kelompok = $this->kelompokKembar((bool) $this->option('nomor'));

        if ($kelompok->isEmpty()) {
            $this->info('Tak ada dokumen kembar.');

            return self::SUCCESS;
        }

        $this->warn($kelompok->count().' kelompok dokumen kembar ditemukan.');
        $this->line('');

        foreach ($kelompok as $k) {
            $this->line('<fg=yellow>'.($k->Judul ?: '(tanpa judul)').'</>'
                .($k->Nomor_SOP_Resmi ? '  ['.$k->Nomor_SOP_Resmi.']' : '  [tanpa nomor]'));

            $this->table(
                ['Id', 'Versi', 'Terbit', 'Item', 'Paket pemakai', 'Dibuat'],
                $this->rincian((string) $k->Judul, $k->Nomor_SOP_Resmi),
            );
        }

        /* Saran yang bisa dikerjakan, bukan sekadar angka. Yang tak menempel
           paket mana pun adalah kandidat paling aman untuk ditinggalkan —
           tak ada karyawan yang catatannya ikut hilang. */
        $this->line('');
        $this->line('Baris ber-<fg=green>0 item</> aman ditinggalkan: tak ada paket maupun catatan baca yang menempel padanya.');
        $this->line('Baris yang dipakai paket TIDAK boleh digabung sembarangan — riwayat versi & catatan bacanya berbeda.');

        return self::SUCCESS;
    }

    /** Kelompok kembar: berdasarkan judul, atau nomor SOP resmi bila diminta. */
    private function kelompokKembar(bool $lewatNomor)
    {
        $q = DB::table('N_SOP_Dokumen')->where('Flag_Aktif', SopStatus::AKTIF);

        if ($lewatNomor) {
            // Nomor kosong bukan kembar — sebagian dokumen memang belum bernomor.
            $q->whereNotNull('Nomor_SOP_Resmi')->where('Nomor_SOP_Resmi', '<>', '');
        }

        return $q->select('Judul', 'Nomor_SOP_Resmi', DB::raw('COUNT(*) as n'))
            ->groupBy('Judul', 'Nomor_SOP_Resmi')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('Judul')
            ->get();
    }

    /** Rincian tiap anggota kelompok, lengkap dengan paket yang memakainya. */
    private function rincian(string $judul, ?string $nomor): array
    {
        $dokumen = DB::table('N_SOP_Dokumen')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Judul', $judul)
            ->when($nomor === null, fn ($q) => $q->whereNull('Nomor_SOP_Resmi'))
            ->when($nomor !== null, fn ($q) => $q->where('Nomor_SOP_Resmi', $nomor))
            ->orderBy('Id_SOP_Dokumen')
            ->get(['Id_SOP_Dokumen', 'Created_At']);

        $ids = $dokumen->pluck('Id_SOP_Dokumen')->all();

        $versi = DB::table('N_SOP_Item_Versi')->whereIn('Id_SOP_Dokumen', $ids)
            ->select('Id_SOP_Dokumen', DB::raw('COUNT(*) as n'),
                DB::raw("SUM(CASE WHEN Status = 'PUBLISHED' THEN 1 ELSE 0 END) as terbit"))
            ->groupBy('Id_SOP_Dokumen')->get()->keyBy('Id_SOP_Dokumen');

        $paket = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->whereIn('i.Id_SOP_Dokumen', $ids)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->select('i.Id_SOP_Dokumen', 'p.Nama_Paket')
            ->get()->groupBy('Id_SOP_Dokumen');

        return $dokumen->map(function ($d) use ($versi, $paket) {
            $v = $versi[$d->Id_SOP_Dokumen] ?? null;
            $p = $paket[$d->Id_SOP_Dokumen] ?? collect();

            return [
                $d->Id_SOP_Dokumen,
                (int) ($v->n ?? 0),
                (int) ($v->terbit ?? 0),
                $p->count(),
                $p->pluck('Nama_Paket')->unique()->implode(', ') ?: '—',
                substr((string) $d->Created_At, 0, 16),
            ];
        })->all();
    }
}
