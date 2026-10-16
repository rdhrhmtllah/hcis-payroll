<?php

namespace App\Services\ESS\Attendance\Leave\Personal;

use App\Services\ESS\Attendance\Processing\AttendanceDirtyResolverService;
use App\Services\SYSTEM\Application\Transactions\TransactionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pembatalan transaksi izin/cuti/terlambat oleh admin (/izinAdmin).
 *
 * KONTRAK PEMBATALAN: header di-set `Status='Y'` + `Cancelled_At` diisi.
 *
 * `Status='Y'` saja sudah cukup untuk membuat transaksi lenyap dari SEMUA alur:
 * view sumber (N_HRIS_VW_SRC_*) dan SP absensi (HRIS_SP_GET_ABSEN_NEW_V3) sama-sama
 * memfilter `Status IS NULL`. Jadi tidak perlu menyentuh HRIS_Approval_Request —
 * antrian approver bersih dengan sendirinya, dan setelah dirty-mark, hari yang
 * bersangkutan otomatis dihitung ulang jadi hari kerja biasa.
 *
 * `Cancelled_At` adalah pembeda pembatalan admin dari `Status='Y'` warisan lama
 * (soft-delete karyawan / data legacy) yang tak punya alasan maupun pelaku.
 *
 * Beda dengan destroyIzinAdmin lama: transaksi yang SUDAH DISETUJUI pun boleh
 * dibatalkan — konsekuensinya saldo cuti yang terlanjur dipotong saat approval
 * final harus dikembalikan (lihat TransactionService::refundCutiTransaction).
 */
class IzinCancellationService
{
    /** Panjang minimum alasan pembatalan, sama dengan pembatalan KPI. */
    public const MIN_REASON = 10;

    /**
     * Peta prefix No_Transaksi -> tabel. `det` = tabel per-hari (TP menyimpan
     * tanggalnya langsung di detail, jadi det = detail).
     */
    private const TABEL = [
        'CT' => [
            'tipe' => 'CUTI',
            'header' => 'Transaksi_Cuti',
            'detail' => 'Transaksi_Cuti_Detail',
            'det' => 'Transaksi_Cuti_Det',
            'kolom_tanggal' => 'Tanggal_Cuti',
        ],
        'IS' => [
            'tipe' => 'IZIN_SAKIT',
            'header' => 'Transaksi_Sakit_Izin',
            'detail' => 'Transaksi_Sakit_Izin_Detail',
            'det' => 'Transaksi_Sakit_Izin_Det',
            'kolom_tanggal' => 'Tanggal_Sakit_Izin',
        ],
        'TP' => [
            'tipe' => 'TERLAMBAT',
            'header' => 'Transaksi_Terlambat_Pulang_Cepat',
            'detail' => 'Transaksi_Terlambat_Pulang_Cepat_Detail',
            'det' => 'Transaksi_Terlambat_Pulang_Cepat_Detail',
            'kolom_tanggal' => 'Tanggal_Masuk_Pulang',
        ],
    ];

    public function __construct(
        private TransactionService $transactionService,
        private AttendanceDirtyResolverService $attendanceDirtyResolver,
    ) {}

    /**
     * Ringkasan dampak pembatalan untuk modal konfirmasi admin.
     *
     * @return array{ok: bool, message?: string, preview?: array}
     */
    public function buildPreview(string $noTransaksi): array
    {
        $map = $this->tabelUntuk($noTransaksi);
        if (! $map) {
            return ['ok' => false, 'message' => 'Nomor transaksi tidak dikenali.'];
        }

        $header = DB::table($map['header'])->where('No_Transaksi', $noTransaksi)->first();
        if (! $header) {
            return ['ok' => false, 'message' => 'Transaksi tidak ditemukan.'];
        }

        $detail = DB::table($map['detail'])->where('No_Transaksi', $noTransaksi)->first();
        $tanggal = $this->tanggalTerdampak($noTransaksi, $map);
        $isApproved = $this->isApproved($noTransaksi, $map);
        $sudahDibatalkan = $header->Status === 'Y';

        $nama = DB::table('Karyawan')
            ->where('Kode_Karyawan', $detail->Kode_Karyawan ?? null)
            ->value('Nama');

        return [
            'ok' => true,
            'preview' => [
                'no_transaksi' => $noTransaksi,
                'tipe' => $map['tipe'],
                'sub_tipe' => $detail->Jenis ?? null,
                'nama_karyawan' => $nama,
                'alasan_asli' => $detail->Alasan ?? null,
                'tanggal' => $tanggal,
                'jumlah_hari' => count($tanggal),
                'is_approved' => $isApproved,
                'sudah_dibatalkan' => $sudahDibatalkan,
                'dibatalkan_oleh' => $header->Cancelled_By_Nama ?? null,
                'refund' => $this->previewRefund($noTransaksi, $map, $isApproved),
                'fingerprint' => $this->fingerprint($noTransaksi, $map),
            ],
        ];
    }

    /**
     * Batalkan transaksi. All-or-nothing: kalau status berubah antara preview dan
     * eksekusi (approver bertindak barusan), tolak dan jangan ubah apa pun.
     *
     * @return array{ok: bool, code?: int, message?: string, result?: array}
     */
    public function execute(string $noTransaksi, string $reason, string $fingerprint, string $adminKode): array
    {
        $map = $this->tabelUntuk($noTransaksi);
        if (! $map) {
            return ['ok' => false, 'code' => 404, 'message' => 'Nomor transaksi tidak dikenali.'];
        }

        $adminNama = DB::table('Karyawan')->where('Kode_Karyawan', $adminKode)->value('Nama');
        $reason = trim(strip_tags($reason));

        // Pertahanan berlapis: controller sudah memvalidasi, tapi alasan adalah SATU-SATUNYA
        // jejak kenapa transaksi (bahkan yang sudah disetujui) dibatalkan. Jangan sampai
        // pemanggil lain menembusnya dengan alasan kosong.
        if (mb_strlen($reason) < self::MIN_REASON) {
            return [
                'ok' => false,
                'code' => 422,
                'message' => 'Alasan pembatalan minimal '.self::MIN_REASON.' karakter.',
            ];
        }

        try {
            DB::beginTransaction();

            $header = DB::table($map['header'])
                ->where('No_Transaksi', $noTransaksi)
                ->lockForUpdate()
                ->first();

            if (! $header) {
                DB::rollBack();

                return ['ok' => false, 'code' => 404, 'message' => 'Transaksi tidak ditemukan.'];
            }

            if ($header->Status === 'Y') {
                DB::rollBack();

                return ['ok' => false, 'code' => 409, 'message' => 'Transaksi ini sudah dibatalkan.'];
            }

            // Proteksi race: approver mungkin baru saja menyetujui/menolak sejak modal
            // dibuka. Dampak pembatalan (terutama refund saldo) beda total antara
            // pending dan approved, jadi jangan diteruskan diam-diam.
            if ($this->fingerprint($noTransaksi, $map) !== $fingerprint) {
                DB::rollBack();

                return [
                    'ok' => false,
                    'code' => 409,
                    'message' => 'Status transaksi sudah berubah sejak Anda membuka konfirmasi. Muat ulang lalu coba lagi.',
                ];
            }

            // Saldo hanya pernah dipotong kalau cuti sudah approval final.
            $refund = ['refunded' => 0.0, 'wallets' => 0, 'already' => false];
            if ($map['tipe'] === 'CUTI' && $this->isApproved($noTransaksi, $map)) {
                $refund = $this->transactionService->refundCutiTransaction(
                    $noTransaksi,
                    $adminKode,
                    "Pembatalan transaksi $noTransaksi oleh admin: $reason",
                );
            }

            DB::table($map['header'])
                ->where('No_Transaksi', $noTransaksi)
                ->update([
                    'Status' => 'Y',
                    'Flag_Cancelled_By_Admin' => 'Y',
                    'Cancel_Reason' => $reason,
                    'Cancelled_By' => $adminKode,
                    'Cancelled_By_Nama' => $adminNama,
                    'Cancelled_At' => now(),
                    'Updated_By' => Auth::user()?->Id_Users,
                    'Updated_At' => now(),
                ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::channel('izinLog')->error('Gagal membatalkan transaksi izin: '.$e->getMessage(), [
                'no_transaksi' => $noTransaksi,
                'admin' => $adminKode,
            ]);

            return ['ok' => false, 'code' => 500, 'message' => 'Terjadi kesalahan pada server: '.$e->getMessage()];
        }

        // SESUDAH commit: EXEC SP dirty-mark tak boleh menahan lock selama transaksi.
        $this->attendanceDirtyResolver->markFromTransaction(
            $noTransaksi,
            'IZIN_TRANSACTION_CANCELLED_ADMIN',
            'IZIN_CANCELLATION_SERVICE',
            1,
        );

        Log::channel('izinLog')->info('Transaksi izin dibatalkan admin', [
            'no_transaksi' => $noTransaksi,
            'tipe' => $map['tipe'],
            'admin' => $adminKode,
            'alasan' => $reason,
            'refund_hari' => $refund['refunded'],
        ]);

        return [
            'ok' => true,
            'result' => [
                'no_transaksi' => $noTransaksi,
                'dibatalkan_oleh' => $adminNama,
                'alasan_pembatalan' => $reason,
                'refund' => $refund,
            ],
        ];
    }

    /** @return array<string,string>|null */
    private function tabelUntuk(string $noTransaksi): ?array
    {
        return self::TABEL[strtoupper(substr($noTransaksi, 0, 2))] ?? null;
    }

    /**
     * Sudah lolos approval final? Ini yang menentukan apakah saldo pernah dipotong
     * dan apakah absensi sudah terlanjur tercatat sebagai cuti/izin.
     */
    private function isApproved(string $noTransaksi, array $map): bool
    {
        return DB::table($map['detail'])
            ->where('No_Transaksi', $noTransaksi)
            ->where('Flag_Approval', 'Y')
            ->exists();
    }

    /**
     * Sidik jari status transaksi. Berubah begitu approver bertindak (Flag_Approval)
     * atau transaksi tersentuh (Status/Updated_At).
     */
    private function fingerprint(string $noTransaksi, array $map): string
    {
        $header = DB::table($map['header'])->where('No_Transaksi', $noTransaksi)->first();

        $flags = DB::table($map['detail'])
            ->where('No_Transaksi', $noTransaksi)
            ->orderBy('No_Transaksi')
            ->pluck('Flag_Approval')
            ->implode(',');

        $approval = DB::table('HRIS_Approval_Request')
            ->where('No_Transaksi', $noTransaksi)
            ->orderBy('No_Transaksi')
            ->pluck('Flag_Approval')
            ->implode(',');

        return sha1(implode('|', [
            $noTransaksi,
            $header->Status ?? '',
            $header->Updated_At ?? '',
            $flags,
            $approval,
        ]));
    }

    /** Tanggal-tanggal yang absensinya akan dihitung ulang. @return array<int,string> */
    private function tanggalTerdampak(string $noTransaksi, array $map): array
    {
        return DB::table($map['det'])
            ->where('No_Transaksi', $noTransaksi)
            ->orderBy($map['kolom_tanggal'])
            ->pluck($map['kolom_tanggal'])
            ->map(fn ($t) => substr((string) $t, 0, 10))
            ->all();
    }

    /**
     * Berapa hari cuti yang akan dikembalikan, ke dompet mana. Dompet yang sudah
     * kedaluwarsa/ditutup DITANDAI (bukan dialihkan) — mengalihkan refund ke dompet
     * aktif akan menciptakan saldo dari udara dan memutus rantai ledger.
     */
    private function previewRefund(string $noTransaksi, array $map, bool $isApproved): ?array
    {
        if ($map['tipe'] !== 'CUTI' || ! $isApproved) {
            return null;
        }

        $debits = $this->transactionService->cutiDebitLedgerRows($noTransaksi);
        if ($debits->isEmpty()) {
            return null;
        }

        $sudahRefund = DB::table('N_HRIS_Log_Buku_Cuti')
            ->where('No_Transaksi_Ref', $noTransaksi)
            ->where('Kategori_Transaksi', TransactionService::KATEGORI_PEMBATALAN)
            ->exists();

        if ($sudahRefund) {
            return null;
        }

        $hariIni = now()->toDateString();
        $wallets = [];
        $total = 0.0;

        foreach ($debits as $d) {
            $jumlah = abs((float) $d->Jumlah);
            $dompet = DB::table('HRIS_Buku_Cuti')->where('Id_Buku_Cuti', $d->Id_Buku_Cuti)->first();

            $wallets[] = [
                'id_buku_cuti' => (int) $d->Id_Buku_Cuti,
                'jumlah' => $jumlah,
                'periode' => $dompet->Periode ?? null,
                'is_hutang' => (float) $d->Saldo_Sebelum < 0,
                'is_expired' => $dompet
                    ? ($dompet->Flag_Aktif !== 'Y' || ($dompet->tanggal_expired && substr((string) $dompet->tanggal_expired, 0, 10) < $hariIni))
                    : false,
            ];

            $total += $jumlah;
        }

        return [
            'total_hari' => $total,
            'wallets' => $wallets,
            'ada_expired' => collect($wallets)->contains('is_expired', true),
        ];
    }
}
