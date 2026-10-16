<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopAssignmentRuleConfig;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Daftar paket yang dimiliki satu aturan penugasan otomatis.
 *
 * Sebelum DDL-2026-053 relasi ini adalah satu kolom pada N_SOP_Assignment_Rule,
 * sehingga satu cohort yang wajib membaca lima paket harus disusun sebagai lima
 * aturan berkriteria kembar. Sekarang relasinya tinggal di tabel jembatan.
 *
 * Penjaga "satu paket satu aturan" ditegakkan filtered unique index
 * UX_SOP_Assignment_Rule_Paket_Paket — bukan oleh pemeriksaan di kelas ini.
 * Pemeriksaan di sini ada untuk PESANNYA: index hanya memberi galat basis data
 * yang tak menyebut siapa pemilik paketnya, dan admin yang membacanya tak tahu
 * harus pergi ke mana.
 */
class SopAturanPaketService
{
    /**
     * Baris paket sebuah aturan, lengkap dengan nama paketnya, urut Urutan.
     *
     * @return array<int,object>
     */
    public function daftar(int $ruleId): array
    {
        return DB::table('N_SOP_Assignment_Rule_Paket as rp')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'rp.Id_SOP_Paket')
            ->where('rp.Id_SOP_Assignment_Rule', $ruleId)
            ->where('rp.Flag_Aktif', SopStatus::AKTIF)
            // Urutan bisa kembar bila daftar disunting berkali-kali; tanpa
            // pemecah sekunder, dua paket bisa bertukar tempat antar pembacaan
            // dan urutan apply jadi tak bisa diulang.
            ->orderBy('rp.Urutan')
            ->orderBy('rp.Id_SOP_Assignment_Rule_Paket')
            ->get(['rp.*', 'p.Nama_Paket', 'p.Mode_Distribusi'])
            ->all();
    }

    /**
     * Baris apa adanya, untuk perhitungan snapshot hash.
     *
     * @return array<int,object>
     */
    public function barisMentah(int $ruleId): array
    {
        return DB::table('N_SOP_Assignment_Rule_Paket')
            ->where('Id_SOP_Assignment_Rule', $ruleId)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->get()
            ->all();
    }

    /**
     * Paket mana saja yang sudah diklaim aturan LAIN.
     *
     * @param  array<int,int>  $idPaket
     * @return array<int,string> [Id_SOP_Paket => Nama_Aturan pemiliknya]
     */
    public function pemilikLain(array $idPaket, ?int $kecualiRuleId): array
    {
        if (! $idPaket) {
            return [];
        }

        return DB::table('N_SOP_Assignment_Rule_Paket as rp')
            ->join('N_SOP_Assignment_Rule as r', 'r.Id_SOP_Assignment_Rule', '=', 'rp.Id_SOP_Assignment_Rule')
            ->whereIn('rp.Id_SOP_Paket', $idPaket)
            ->where('rp.Flag_Aktif', SopStatus::AKTIF)
            ->where('r.Flag_Aktif', SopStatus::AKTIF)
            ->when($kecualiRuleId, fn ($q) => $q->where('rp.Id_SOP_Assignment_Rule', '<>', $kecualiRuleId))
            ->pluck('r.Nama_Aturan', 'rp.Id_SOP_Paket')
            ->all();
    }

    /**
     * Samakan isi tabel jembatan dengan daftar yang dikirim layar.
     *
     * Paket yang hilang dari daftar di-soft-delete (Flag_Aktif='T') dan TIDAK
     * menyentuh penugasan yang sudah tercatat: yang berhenti hanya penugasan
     * baru, sesuai janji yang sudah dipegang tombol Nonaktifkan. Soft-delete
     * itu pula yang melepaskan paket dari filtered unique index, sehingga
     * aturan lain langsung bisa mengklaimnya tanpa barisnya dihapus.
     *
     * @param  array<int,array{id_paket:int,urutan?:int,override?:bool,schedule?:?array}>  $paketInput
     */
    public function sinkron(int $ruleId, array $paketInput, ?int $userId): void
    {
        $normal = $this->normalkanInput($paketInput);
        $idBaru = array_column($normal, 'id_paket');

        $bentrok = $this->pemilikLain($idBaru, $ruleId);
        if ($bentrok) {
            $nama = DB::table('N_SOP_Master_Paket')
                ->whereIn('Id_SOP_Paket', array_keys($bentrok))
                ->pluck('Nama_Paket', 'Id_SOP_Paket')->all();

            $rincian = [];
            foreach ($bentrok as $idPaket => $namaAturan) {
                $rincian[] = ($nama[$idPaket] ?? "Paket #{$idPaket}")." sudah dimiliki aturan \"{$namaAturan}\"";
            }

            throw ValidationException::withMessages([
                'paket' => 'Paket berikut tidak bisa dipakai: '.implode('; ', $rincian).'.',
            ]);
        }

        DB::transaction(function () use ($ruleId, $normal, $idBaru, $userId) {
            // Pelepasan HARUS lebih dulu: paket yang dipindahkan urutannya atau
            // dibuang masih memegang slot di unique index sampai Flag_Aktif-nya
            // turun.
            DB::table('N_SOP_Assignment_Rule_Paket')
                ->where('Id_SOP_Assignment_Rule', $ruleId)
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->when($idBaru, fn ($q) => $q->whereNotIn('Id_SOP_Paket', $idBaru))
                ->update(['Flag_Aktif' => 'T', 'Updated_By' => $userId, 'Updated_At' => now()]);

            foreach ($normal as $baris) {
                $kolom = [
                    'Urutan' => $baris['urutan'],
                    'Flag_Override_Jadwal' => $baris['override'] ? 'Y' : 'T',
                    'Deadline_Mode' => $baris['override'] ? $baris['schedule']['deadline_mode'] : null,
                    'Deadline_Hari' => $baris['override'] ? $baris['schedule']['deadline_days'] : null,
                    'Tanggal_Deadline' => $baris['override'] ? $baris['schedule']['deadline_date'] : null,
                    'Flag_Reminder' => $baris['override'] ? ($baris['schedule']['reminder_enabled'] ? 'Y' : 'T') : null,
                    'Irama_Reminder_Hari' => $baris['override'] ? $baris['schedule']['reminder_interval_days'] : null,
                    'Updated_By' => $userId,
                    'Updated_At' => now(),
                ];

                $adaId = DB::table('N_SOP_Assignment_Rule_Paket')
                    ->where('Id_SOP_Assignment_Rule', $ruleId)
                    ->where('Id_SOP_Paket', $baris['id_paket'])
                    ->where('Flag_Aktif', SopStatus::AKTIF)
                    ->value('Id_SOP_Assignment_Rule_Paket');

                if ($adaId) {
                    DB::table('N_SOP_Assignment_Rule_Paket')
                        ->where('Id_SOP_Assignment_Rule_Paket', $adaId)
                        ->update($kolom);

                    continue;
                }

                DB::table('N_SOP_Assignment_Rule_Paket')->insert($kolom + [
                    'Id_SOP_Assignment_Rule' => $ruleId,
                    'Id_SOP_Paket' => $baris['id_paket'],
                    'Last_Apply_Status' => 'IDLE',
                    'Created_By' => $userId,
                    'Created_At' => now(),
                    'Flag_Aktif' => SopStatus::AKTIF,
                ]);
            }
        });
    }

    /**
     * Jadwal yang benar-benar dipakai paket ini.
     *
     * Override adalah PENGGANTIAN utuh, bukan tambahan di atas induk: paket
     * bermode NONE sah walau induknya bertenggat, dan artinya "paket ini tanpa
     * tenggat".
     */
    public function jadwalEfektif(object $barisPaket, array $scheduleInduk): array
    {
        if (($barisPaket->Flag_Override_Jadwal ?? 'T') !== 'Y') {
            return $scheduleInduk;
        }

        return SopAssignmentRuleConfig::normalizeSchedule([
            'deadline_mode' => $barisPaket->Deadline_Mode ?? 'NONE',
            'deadline_days' => $barisPaket->Deadline_Hari ?? null,
            'deadline_date' => $barisPaket->Tanggal_Deadline ?? null,
            'reminder_enabled' => ($barisPaket->Flag_Reminder ?? 'T') === 'Y',
            'reminder_interval_days' => $barisPaket->Irama_Reminder_Hari ?? null,
        ]);
    }

    public function tandaiStatus(int $rulePaketId, string $status, ?string $error = null): void
    {
        DB::table('N_SOP_Assignment_Rule_Paket')
            ->where('Id_SOP_Assignment_Rule_Paket', $rulePaketId)
            ->update([
                'Last_Apply_Status' => $status,
                'Last_Apply_Error' => $error !== null ? mb_substr($error, 0, 1000) : null,
                'Last_Applied_At' => $status === 'SUCCESS' ? now() : null,
                'Updated_At' => now(),
            ]);
    }

    /**
     * Tandai paket yang masih menggantung sebagai FAILED.
     *
     * Dipanggil hook failed() job dan jalur galat apply. Tanpa ini aturan
     * tampak NEEDS_ATTENTION tetapi tak satu paket pun menjelaskan sebabnya,
     * dan admin tak punya cara tahu paket mana yang sebenarnya berhenti.
     *
     * @return int jumlah baris yang disapu
     */
    public function tandaiGantung(int $ruleId, string $pesan): int
    {
        return DB::table('N_SOP_Assignment_Rule_Paket')
            ->where('Id_SOP_Assignment_Rule', $ruleId)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereIn('Last_Apply_Status', ['IDLE', 'RUNNING'])
            ->update([
                'Last_Apply_Status' => 'FAILED',
                'Last_Apply_Error' => mb_substr($pesan, 0, 1000),
                'Updated_At' => now(),
            ]);
    }

    /**
     * @param  array<int,array>  $paketInput
     * @return array<int,array{id_paket:int,urutan:int,override:bool,schedule:?array}>
     */
    private function normalkanInput(array $paketInput): array
    {
        $hasil = [];
        $terlihat = [];
        $urutan = 0;

        foreach ($paketInput as $baris) {
            $idPaket = (int) ($baris['id_paket'] ?? 0);
            // Paket kembar di satu daftar dibuang diam-diam, bukan ditolak:
            // layar sudah menyaringnya, dan menolak kiriman karena kembaran
            // hanya membuat admin menebak-nebak baris mana yang salah.
            if ($idPaket <= 0 || isset($terlihat[$idPaket])) {
                continue;
            }
            $terlihat[$idPaket] = true;
            $urutan++;
            $override = (bool) ($baris['override'] ?? false);

            $hasil[] = [
                'id_paket' => $idPaket,
                'urutan' => (int) ($baris['urutan'] ?? $urutan),
                'override' => $override,
                'schedule' => $override
                    ? SopAssignmentRuleConfig::normalizeSchedule((array) ($baris['schedule'] ?? []))
                    : null,
            ];
        }

        if (! $hasil) {
            return [];
        }

        $ada = DB::table('N_SOP_Master_Paket')
            ->whereIn('Id_SOP_Paket', array_column($hasil, 'id_paket'))
            ->where('Status', SopStatus::PAKET_PUBLISHED)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->pluck('Id_SOP_Paket')
            ->map(fn ($id) => (int) $id)
            ->all();

        $hilang = array_diff(array_column($hasil, 'id_paket'), $ada);
        if ($hilang) {
            throw ValidationException::withMessages([
                'paket' => 'Ada paket yang bukan paket published aktif: '.implode(', ', $hilang).'.',
            ]);
        }

        return $hasil;
    }
}
