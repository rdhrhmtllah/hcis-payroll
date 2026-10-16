<?php

namespace App\Services\VISITOR\Permit\VehiclePermit;

use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VisitorPermitChecklistService
{
    /**
     * Salin pertanyaan inspeksi yang berlaku ke sebuah pengajuan permit.
     *
     * WAJIB dipanggil setiap pengajuan permit dibuat — permit karyawan, permit
     * mitra, maupun permit COKLAT turunan kunjungan tamu. saveAnswers() di bawah
     * meng-UPDATE baris ini, bukan menyisipkannya; pengajuan yang lahir tanpa
     * snapshot tidak menampilkan satu pun pertanyaan di meja verifikator, dan
     * jawaban yang dikirim ke sana tidak punya tempat untuk disimpan.
     *
     * Sengaja SNAPSHOT, bukan JOIN ke master: teks pertanyaan boleh berubah, dan
     * bukti inspeksi harus tetap memperlihatkan pertanyaan yang benar-benar
     * dijawab saat itu.
     *
     * $idTipeKendaraan boleh null — permit COKLAT belum tahu tipe kendaraannya
     * saat diajukan, karena justru verifikator yang mengisinya saat review.
     * Konsekuensinya pertanyaan yang MENYASAR satu tipe kendaraan tidak ikut
     * pada permit COKLAT; yang bertarget umum (Target_Id_Tipe_Kendaraan NULL)
     * tetap ikut seluruhnya.
     */
    public function buatSnapshot(
        string $noTransaksi,
        int $idDetailPermit,
        int $idKategoriPermit,
        ?int $idTipeKendaraan = null,
    ): int {
        $pertanyaan = $this->pertanyaanBerlaku($idKategoriPermit, $idTipeKendaraan)->values();

        $baris = $pertanyaan->map(static fn ($p) => [
            'Id_Detail_Permit' => $idDetailPermit,
            'No_Transaksi' => $noTransaksi,
            'Id_Master_Pertanyaan' => $p->Id_Approval_Question,
            'Snapshot_Pertanyaan' => $p->Teks_Pertanyaan,
        ])->all();

        if ($baris === []) {
            return 0;
        }

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')->insert($baris);

        return count($baris);
    }

    /**
     * Simpan seluruh jawaban dalam satu statement agar tidak ada partial update
     * dan waktu lock tidak bertambah satu round-trip untuk setiap pertanyaan.
     *
     * @param  array<int, array{question_id:int, jawaban:string, catatan?:string|null}>  $answers
     */
    public function saveAnswers(string $noTransaksi, array $answers): int
    {
        if ($answers === []) {
            return 0;
        }

        $questionIds = array_map(
            static fn (array $answer): int => (int) $answer['question_id'],
            $answers,
        );

        if (count($questionIds) !== count(array_unique($questionIds))) {
            throw new InvalidArgumentException('Pertanyaan checklist tidak boleh dikirim lebih dari satu kali.');
        }

        $rows = [];
        $bindings = [];

        foreach ($answers as $answer) {
            $rows[] = '(?, ?, ?)';
            $bindings[] = (int) $answer['question_id'];
            $bindings[] = (string) $answer['jawaban'];
            $bindings[] = $answer['catatan'] ?? null;
        }

        $bindings[] = $noTransaksi;

        $affected = DB::update(
            'UPDATE checklist
             SET checklist.Jawaban_Admin = source.Jawaban_Admin,
                 checklist.Catatan_Admin = source.Catatan_Admin
             FROM N_EMI_VISITOR_Transaksi_Permit_Checklist AS checklist
             INNER JOIN (VALUES '.implode(', ', $rows).')
                 AS source (Id_Master_Pertanyaan, Jawaban_Admin, Catatan_Admin)
                 ON source.Id_Master_Pertanyaan = checklist.Id_Master_Pertanyaan
             WHERE checklist.No_Transaksi = ?',
            $bindings,
        );

        if ($affected !== count($answers)) {
            throw new DomainException('Sebagian pertanyaan checklist tidak ditemukan pada transaksi permit.');
        }

        return $affected;
    }

    /**
     * Hitung dampak penggantian kategori/tipe kendaraan pada checklist permit
     * tanpa menulis apa pun — dasar peringatan di dialog koreksi verifikator.
     *
     * @return array{dipertahankan:int, dibuang: list<array{teks:string, terjawab:bool}>, ditambah: list<array{teks:string}>}
     */
    public function pratinjauSelaras(string $noTransaksi, int $idKategoriPermit, ?int $idTipeKendaraan): array
    {
        [$ada, $dibuang, $ditambah] = $this->selisihSnapshot($noTransaksi, $idKategoriPermit, $idTipeKendaraan);

        return [
            'dipertahankan' => $ada->count() - $dibuang->count(),
            'dibuang' => $dibuang->map(fn ($r) => [
                'teks' => (string) $r->Snapshot_Pertanyaan,
                'terjawab' => trim((string) $r->Jawaban_Admin) !== '',
            ])->all(),
            'ditambah' => $ditambah->map(fn ($p) => ['teks' => (string) $p->Teks_Pertanyaan])->all(),
        ];
    }

    /**
     * Sesuaikan snapshot dengan kategori/tipe kendaraan yang baru. Baris yang
     * pertanyaannya masih berlaku DIPERTAHANKAN beserta jawabannya — membangun
     * ulang dari nol akan menghapus pemeriksaan yang sudah dilakukan hanya
     * karena zonanya dikoreksi.
     *
     * @return array{dibuang:int, ditambah:int}
     */
    public function selaraskanSnapshot(string $noTransaksi, int $idDetailPermit, int $idKategoriPermit, ?int $idTipeKendaraan): array
    {
        [, $dibuang, $ditambah] = $this->selisihSnapshot($noTransaksi, $idKategoriPermit, $idTipeKendaraan);

        if ($dibuang->isNotEmpty()) {
            DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')
                ->whereIn('Id_Permit_Checklist', $dibuang->pluck('Id_Permit_Checklist')->all())
                ->delete();
        }

        if ($ditambah->isNotEmpty()) {
            DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')->insert($ditambah->map(fn ($p) => [
                'Id_Detail_Permit' => $idDetailPermit,
                'No_Transaksi' => $noTransaksi,
                'Id_Master_Pertanyaan' => (int) $p->Id_Approval_Question,
                'Snapshot_Pertanyaan' => $p->Teks_Pertanyaan,
            ])->all());
        }

        return ['dibuang' => $dibuang->count(), 'ditambah' => $ditambah->count()];
    }

    /** Pertanyaan master aktif yang berlaku untuk pasangan kategori + tipe kendaraan, berkunci id. */
    private function pertanyaanBerlaku(int $idKategoriPermit, ?int $idTipeKendaraan): Collection
    {
        return DB::table('N_EMI_VISITOR_Master_Approval_Question')
            ->where('Flag_Aktif', 'Y')
            ->where(function ($query) use ($idKategoriPermit) {
                $query->whereNull('Target_Id_Kategori_Permit')
                    ->orWhere('Target_Id_Kategori_Permit', $idKategoriPermit);
            })
            ->where(function ($query) use ($idTipeKendaraan) {
                $query->whereNull('Target_Id_Tipe_Kendaraan');

                if ($idTipeKendaraan) {
                    $query->orWhere('Target_Id_Tipe_Kendaraan', $idTipeKendaraan);
                }
            })
            ->orderBy('Id_Approval_Question')
            ->get()
            ->keyBy(fn ($p) => (int) $p->Id_Approval_Question);
    }

    /** @return array{0:Collection,1:Collection,2:Collection} baris yang ada, yang dibuang, pertanyaan yang ditambah */
    private function selisihSnapshot(string $noTransaksi, int $idKategoriPermit, ?int $idTipeKendaraan): array
    {
        $target = $this->pertanyaanBerlaku($idKategoriPermit, $idTipeKendaraan);
        $ada = DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')
            ->where('No_Transaksi', $noTransaksi)
            ->get(['Id_Permit_Checklist', 'Id_Master_Pertanyaan', 'Snapshot_Pertanyaan', 'Jawaban_Admin']);
        $idAda = $ada->map(fn ($r) => (int) $r->Id_Master_Pertanyaan)->all();

        $dibuang = $ada->reject(fn ($r) => $r->Id_Master_Pertanyaan !== null && $target->has((int) $r->Id_Master_Pertanyaan))->values();
        $ditambah = $target->reject(fn ($p, $id) => in_array((int) $id, $idAda, true))->values();

        return [$ada, $dibuang, $ditambah];
    }
}
