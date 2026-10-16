<?php

namespace App\Services\VISITOR\Workflow\Verification;

use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalRequestService;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Permit\Inspection\JadwalInspeksiService;
use App\Services\VISITOR\Permit\VehiclePermit\KategoriPermitPengajuan;
use App\Services\VISITOR\Permit\VehiclePermit\PlatNomorKendaraan;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitChecklistService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Koreksi data permit karyawan oleh verifikator yang memegang giliran
 * approval, selama permit masih PENDING. Persetujuan tahap sebelumnya tidak
 * di-reset; setiap field yang berubah dicatat satu baris di riwayat.
 */
class PermitKoreksiService
{
    public const T_KOREKSI = 'N_EMI_VISITOR_Transaksi_Permit_Koreksi';

    public const FIELD_KATEGORI = 'KATEGORI_PERMIT';

    public const FIELD_PLAT = 'PLAT_NOMOR';

    public const FIELD_MODEL = 'MODEL_KENDARAAN';

    public const FIELD_WARNA = 'WARNA';

    public const FIELD_TAHUN = 'TAHUN';

    public const FIELD_KETERANGAN = 'KETERANGAN';

    public function __construct(
        private ApprovalRequestService $approvalRequest,
        private VisitorPermitQueryService $permitQuery,
        private VisitorPermitChecklistService $checklist,
        private JadwalInspeksiService $jadwal,
        private VisitorEfektifService $efektif,
        private PlatNomorKendaraan $platNomor,
    ) {}

    public function bolehKoreksi(string $noTransaksi, string $kodeKaryawan): bool
    {
        try {
            $this->pastikanBolehDikoreksi($noTransaksi, $kodeKaryawan, kunci: false);

            return true;
        } catch (PermitKoreksiException) {
            return false;
        }
    }

    public function pratinjauChecklist(string $noTransaksi, string $kodeKaryawan, int $idKategori, int $idModel): array
    {
        $this->pastikanBolehDikoreksi($noTransaksi, $kodeKaryawan, kunci: false);
        $model = $this->modelBerlaku($idModel);

        return $this->checklist->pratinjauSelaras($noTransaksi, $idKategori, (int) $model->Id_Master_Tipe);
    }

    /** @return list<string> Nama_Field yang berubah */
    /**
     * @param  array<string,mixed>|null  $nilaiAwal  isi form saat dialog dibuka. Bila dikirim,
     *                                               field yang tidak disentuh verifikator diabaikan (koreksi rekan
     *                                               yang masuk lebih dulu tidak tertimpa form basi), dan field yang
     *                                               disentuh padahal sudah diubah rekan ditolak sebagai konflik.
     */
    public function koreksi(string $noTransaksi, string $kodeKaryawan, array $input, string $alasan, ?array $nilaiAwal = null): array
    {
        $alasan = trim($alasan);
        if ($alasan === '') {
            throw PermitKoreksiException::validasi('Alasan koreksi wajib diisi.');
        }

        return DB::transaction(function () use ($noTransaksi, $kodeKaryawan, $input, $alasan, $nilaiAwal) {
            $this->pastikanBolehDikoreksi($noTransaksi, $kodeKaryawan, kunci: true);

            $detail = DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->where('No_Transaksi', $noTransaksi)->first();

            if ($nilaiAwal !== null) {
                $input = $this->gabungDenganNilaiSekarang($input, $nilaiAwal, $this->nilaiSekarang($detail));
            }
            $modelLama = $detail->Id_Master_Model ? $this->namaModel((int) $detail->Id_Master_Model) : null;

            $kategori = KategoriPermitPengajuan::temukan((int) ($input['id_kategori_permit'] ?? 0))
                ?? throw PermitKoreksiException::validasi('Jenis permit tujuan tidak tersedia untuk pengajuan karyawan.');
            $plat = $this->platBerlaku((string) ($input['no_plat'] ?? ''));
            $model = $this->modelBerlaku((int) ($input['id_vehicle_model'] ?? 0));
            $warna = DB::table('N_EMI_VISITOR_Master_Vehicle_Color')
                ->where('Id_Master_Color', (int) ($input['id_vehicle_color'] ?? 0))->where('Flag_Aktif', 'Y')->first()
                ?? throw PermitKoreksiException::validasi('Warna kendaraan tidak valid atau sudah nonaktif.');
            $tahun = (int) ($input['tahun_kendaraan'] ?? 0);
            if ($tahun < 1900 || $tahun > (int) date('Y') + 1) {
                throw PermitKoreksiException::validasi('Tahun kendaraan tidak valid.');
            }
            $keterangan = trim((string) ($input['keterangan_pengajuan'] ?? ''));
            $keterangan = $keterangan === '' ? null : $keterangan;

            $platLama = [(string) $detail->Plat_Nomor_Prefix, (string) $detail->Plat_Nomor_Angka, (string) $detail->Plat_Nomor_Suffix];
            $platBaru = [$plat['prefix'], $plat['angka'], $plat['suffix']];
            $ketLama = trim((string) $detail->Keterangan_Pengajuan);
            $ketLama = $ketLama === '' ? null : $ketLama;

            $perubahan = [];
            $kolom = [];

            if ((int) $detail->Id_Master_Kategori_Permit !== (int) $kategori->Id_Kategori_Permit) {
                $perubahan[] = [self::FIELD_KATEGORI, $detail->Snapshot_Kategori_Permit, $kategori->Nama_Kategori];
                $kolom['Id_Master_Kategori_Permit'] = (int) $kategori->Id_Kategori_Permit;
                $kolom['Snapshot_Kategori_Permit'] = $kategori->Nama_Kategori;
            }
            if ($platLama !== $platBaru) {
                $perubahan[] = [self::FIELD_PLAT, implode(' ', array_filter($platLama)), implode(' ', $platBaru)];
                $kolom['Plat_Nomor_Prefix'] = $plat['prefix'];
                $kolom['Plat_Nomor_Angka'] = $plat['angka'];
                $kolom['Plat_Nomor_Suffix'] = $plat['suffix'];
            }
            if ((int) $detail->Id_Master_Model !== (int) $model->Id_Vehicle_Model) {
                $perubahan[] = [self::FIELD_MODEL, $modelLama?->label, $model->label];
                $kolom['Id_Master_Model'] = (int) $model->Id_Vehicle_Model;
            }
            if ((int) $detail->Id_Master_Warna !== (int) $warna->Id_Master_Color) {
                $namaWarnaLama = $detail->Id_Master_Warna
                    ? DB::table('N_EMI_VISITOR_Master_Vehicle_Color')->where('Id_Master_Color', $detail->Id_Master_Warna)->value('Nama_Warna')
                    : null;
                $perubahan[] = [self::FIELD_WARNA, $namaWarnaLama, $warna->Nama_Warna];
                $kolom['Id_Master_Warna'] = (int) $warna->Id_Master_Color;
            }
            if ((int) $detail->Tahun_Kendaraan !== $tahun) {
                $perubahan[] = [self::FIELD_TAHUN, $detail->Tahun_Kendaraan !== null ? (string) (int) $detail->Tahun_Kendaraan : null, (string) $tahun];
                $kolom['Tahun_Kendaraan'] = $tahun;
            }
            if ($ketLama !== $keterangan) {
                $perubahan[] = [self::FIELD_KETERANGAN, $ketLama, $keterangan];
                $kolom['Keterangan_Pengajuan'] = $keterangan;
            }

            if ($perubahan === []) {
                throw new PermitKoreksiException('Tidak ada data yang berubah.', 'VISITOR_PERMIT_KOREKSI_TANPA_PERUBAHAN', 422);
            }

            DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->where('No_Transaksi', $noTransaksi)->update($kolom);

            $tipeLama = $modelLama ? (int) $modelLama->Id_Master_Tipe : null;
            if (isset($kolom['Id_Master_Kategori_Permit']) || $tipeLama !== (int) $model->Id_Master_Tipe) {
                $this->checklist->selaraskanSnapshot(
                    $noTransaksi,
                    (int) $detail->Id_Permit_Detail,
                    (int) $kategori->Id_Kategori_Permit,
                    (int) $model->Id_Master_Tipe,
                );
            }

            $this->sinkronAksesInspeksi($noTransaksi);

            $kelompok = (string) Str::uuid();
            $sekarang = now();
            DB::table(self::T_KOREKSI)->insert(array_map(fn (array $p) => [
                'No_Transaksi' => $noTransaksi,
                'Kelompok_Koreksi' => $kelompok,
                'Nama_Field' => $p[0],
                'Nilai_Lama' => $p[1],
                'Nilai_Baru' => $p[2],
                'Alasan' => $alasan,
                'Created_At' => $sekarang,
                'Created_By' => $kodeKaryawan,
            ], $perubahan));

            DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $noTransaksi)
                ->update(['Updated_At' => $sekarang, 'Updated_By' => $kodeKaryawan]);

            return array_column($perubahan, 0);
        });
    }

    private const FIELD_INPUT = ['id_kategori_permit', 'no_plat', 'id_vehicle_model', 'id_vehicle_color', 'tahun_kendaraan', 'keterangan_pengajuan'];

    /** Nilai detail saat ini dalam kosakata input form. */
    private function nilaiSekarang(object $detail): array
    {
        return [
            'id_kategori_permit' => $detail->Id_Master_Kategori_Permit,
            'no_plat' => trim($detail->Plat_Nomor_Prefix.' '.$detail->Plat_Nomor_Angka.' '.$detail->Plat_Nomor_Suffix),
            'id_vehicle_model' => $detail->Id_Master_Model,
            'id_vehicle_color' => $detail->Id_Master_Warna,
            'tahun_kendaraan' => $detail->Tahun_Kendaraan,
            'keterangan_pengajuan' => $detail->Keterangan_Pengajuan,
        ];
    }

    private function normalField(string $field, mixed $nilai): string
    {
        if ($field === 'no_plat') {
            return implode(' ', array_filter($this->platNomor->normalkan((string) $nilai, null, null, null)));
        }
        if ($field === 'keterangan_pengajuan') {
            return trim((string) $nilai);
        }

        return (string) (int) $nilai;
    }

    private function gabungDenganNilaiSekarang(array $input, array $nilaiAwal, array $sekarang): array
    {
        foreach (self::FIELD_INPUT as $field) {
            $disentuh = $this->normalField($field, $input[$field] ?? null) !== $this->normalField($field, $nilaiAwal[$field] ?? null);
            $diubahRekan = $this->normalField($field, $sekarang[$field]) !== $this->normalField($field, $nilaiAwal[$field] ?? null);

            if (! $disentuh) {
                $input[$field] = $sekarang[$field];
            } elseif ($diubahRekan) {
                throw new PermitKoreksiException(
                    'Data permit baru saja dikoreksi verifikator lain. Tutup dialog lalu buka lagi untuk melihat data terbaru.',
                    'VISITOR_PERMIT_KOREKSI_KONFLIK',
                    409,
                );
            }
        }

        return $input;
    }

    public function riwayat(string $noTransaksi): array
    {
        return DB::table(self::T_KOREKSI.' as r')
            ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 'r.Created_By')
            ->where('r.No_Transaksi', $noTransaksi)
            // Id identity, bukan Created_At: dua koreksi di detik yang sama tetap berurutan benar.
            ->orderByDesc('r.Id_Permit_Koreksi')
            ->get(['r.Id_Permit_Koreksi', 'r.Kelompok_Koreksi', 'r.Nama_Field', 'r.Nilai_Lama', 'r.Nilai_Baru', 'r.Alasan', 'r.Created_At', 'r.Created_By', 'k.Nama'])
            ->groupBy('Kelompok_Koreksi')
            ->map(fn ($baris) => [
                'kelompok' => (string) $baris[0]->Kelompok_Koreksi,
                'waktu' => (string) $baris[0]->Created_At,
                'oleh' => (string) $baris[0]->Created_By,
                'nama' => (string) ($baris[0]->Nama ?: $baris[0]->Created_By),
                'alasan' => (string) $baris[0]->Alasan,
                'perubahan' => $baris->sortBy('Id_Permit_Koreksi')->map(fn ($r) => [
                    'field' => (string) $r->Nama_Field,
                    'lama' => $r->Nilai_Lama,
                    'baru' => $r->Nilai_Baru,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    private function pastikanBolehDikoreksi(string $noTransaksi, string $kodeKaryawan, bool $kunci): void
    {
        $header = DB::table('N_EMI_VISITOR_Transaksi_Permit')
            ->where('No_Transaksi', $noTransaksi)
            ->where('Flag_Aktif', 'Y')
            ->when($kunci, fn ($q) => $q->lockForUpdate())
            ->first();

        if (! $header) {
            throw new PermitKoreksiException('Data permit tidak ditemukan.', 'VISITOR_PERMIT_KOREKSI_TIDAK_DITEMUKAN', 404);
        }

        $sumber = strtoupper((string) ($header->Sumber_Pengajuan ?? 'HCIS'));
        if ($sumber !== 'HCIS' || ($header->No_Transaksi_Tamu ?? null) !== null) {
            throw new PermitKoreksiException('Hanya permit pengajuan karyawan yang bisa dikoreksi.', 'VISITOR_PERMIT_KOREKSI_STATUS_TIDAK_VALID', 409);
        }

        // Alias eksplisit, bukan ->value(DB::raw(...)): di SQL Server value() atas
        // ekspresi mentah mengembalikan kolom lain, dan permit PENDING terbaca terputus.
        $status = $this->permitQuery->basePermitQuery()
            ->where('h.No_Transaksi', $noTransaksi)
            ->select(DB::raw($this->permitQuery->permitStatusSql().' as Status_Permit'))
            ->first()?->Status_Permit;
        if ($status !== 'PENDING') {
            throw new PermitKoreksiException('Permit sudah tidak menunggu review, datanya tidak bisa dikoreksi.', 'VISITOR_PERMIT_KOREKSI_STATUS_TIDAK_VALID', 409);
        }

        $akses = $this->approvalRequest->validateApproverAccess($noTransaksi, $kodeKaryawan);
        if (! $akses['valid']) {
            throw new PermitKoreksiException($akses['reason'], 'VISITOR_PERMIT_KOREKSI_AKSES_DITOLAK', 403);
        }
    }

    private function platBerlaku(string $noPlat): array
    {
        $plat = $this->platNomor->normalkan($noPlat, null, null, null);
        if (! $plat['prefix'] || ! $plat['angka'] || ! $plat['suffix']) {
            throw PermitKoreksiException::validasi('Nomor plat kendaraan wajib diisi lengkap.');
        }
        if ($bagian = $this->platNomor->muat($plat, PlatNomorKendaraan::LEBAR_PERMIT)) {
            throw PermitKoreksiException::validasi("Bagian plat nomor '{$bagian}' melebihi ".PlatNomorKendaraan::LEBAR_PERMIT.' karakter.');
        }
        $prefixAda = DB::table('N_EMI_VISITOR_Master_Plat_Kendaraan')->where('Kode_Plat', $plat['prefix'])->where('Flag_Aktif', 'Y')->exists();
        if (! $prefixAda) {
            throw PermitKoreksiException::validasi("Kode wilayah plat '{$plat['prefix']}' tidak dikenal.");
        }

        return $plat;
    }

    /** Model aktif yang tipenya boleh untuk permit; ->label = "Nama Model (Nama Tipe)". */
    private function modelBerlaku(int $idModel): object
    {
        $model = $this->kueriModel($idModel)
            ->where('vm.Flag_Aktif', 'Y')
            ->where('vt.Flag_Aktif', 'Y')
            ->where(fn ($q) => $q->whereNull('vt.Akses')->orWhereIn('vt.Akses', ['PERMIT', 'ALL']))
            ->first();

        if (! $model) {
            throw PermitKoreksiException::validasi('Model kendaraan tidak valid atau sudah nonaktif.');
        }

        return $model;
    }

    private function namaModel(int $idModel): ?object
    {
        return $this->kueriModel($idModel)->first();
    }

    private function kueriModel(int $idModel): \Illuminate\Database\Query\Builder
    {
        return DB::table('N_EMI_VISITOR_Master_Vehicle_Model as vm')
            ->leftJoin('N_EMI_VISITOR_Master_Vehicle_Type as vt', 'vm.Id_Master_Tipe', '=', 'vt.Id_Vehicle_Type')
            ->where('vm.Id_Vehicle_Model', $idModel)
            ->select(
                'vm.Id_Vehicle_Model',
                'vm.Id_Master_Tipe',
                DB::raw("CONCAT(vm.Nama_Model_Kendaraan, ' (', ISNULL(vt.Nama_Tipe_Kendaraan, '-'), ')') as label"),
            );
    }

    /** Baris palang INSPEKSI yang masih AKTIF ikut membawa data terbaru; yang sudah berakhir dibiarkan. */
    private function sinkronAksesInspeksi(string $noTransaksi): void
    {
        $booking = $this->jadwal->aktif($noTransaksi);
        $aksesAktif = DB::table('N_VISITOR_Permit_Efektif')
            ->where('No_Transaksi', $noTransaksi)
            ->where('Jenis_Akses', VisitorEfektifService::JENIS_INSPEKSI)
            ->where('Status_Efektif', 'AKTIF')
            ->exists();

        if ($booking && $aksesAktif) {
            $this->efektif->terbitkanAksesInspeksi(
                $noTransaksi,
                Carbon::parse($booking->Waktu_Mulai),
                Carbon::parse($booking->Waktu_Selesai),
            );
        }
    }
}
