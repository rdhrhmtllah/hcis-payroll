<?php

namespace App\Services\VISITOR\Permit\VehiclePermit;

use App\Services\VISITOR\Workflow\Approval\VisitorApprovalPolicyService;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Kuota pengajuan permit per karyawan.
 *
 * Satu jatah menutupi dua hal sekaligus: pengajuan yang masih diproses DAN
 * permit yang sudah hidup. Jatah kembali hanya ketika permitnya benar-benar
 * selesai — ditolak, kedaluwarsa, atau DICABUT.
 *
 * Pencabutan masuk daftar pembebas karena aksesnya memang sudah tidak ada.
 * Sebelumnya ia tetap memakan jatah, sehingga pemohon yang permitnya dicabut
 * membaca "2/2 terpakai" dan tidak bisa mengajukan pengganti — tanpa satu pun
 * pesan yang menjelaskan kenapa.
 *
 * Ini satu-satunya tempat kuota dihitung. Penjaga di `store()` dan angka yang
 * tampil di layar wajib memanggil kelas ini, bukan menghitung sendiri: dua
 * definisi yang berbeda tipis membuat UI berkata "2/2 terpakai" sementara
 * penjaganya menghitung 1.
 */
class VisitorPermitQuotaService
{
    /**
     * Status yang masih menahan jatah — sedang diproses, atau permitnya hidup.
     *
     * @var array<int,string>
     */
    public const STATUS_MEMAKAI_KUOTA = ['PENDING', 'NO_FLOW', 'VALIDITY_REQUIRED', 'APPROVED'];

    /**
     * Status yang sudah selesai dan mengembalikan jatah.
     *
     * @var array<int,string>
     */
    public const STATUS_MEMBEBASKAN_KUOTA = ['REJECTED', 'EXPIRED', 'REVOKED'];

    /**
     * Status yang berarti "belum diputus siapa pun".
     *
     * Sengaja BUKAN kebalikan dari STATUS_MEMBEBASKAN_KUOTA: 'APPROVED' tidak
     * ada di sini. Permit yang sudah disetujui memang masih memakai jatah
     * permit, tetapi ia tidak lagi menahan sesuatu yang menunggu keputusan -
     * dan pemohonnya berhak mengajukan berikutnya.
     *
     * @var array<int,string>
     */
    public const STATUS_MENUNGGU_KEPUTUSAN = ['PENDING', 'NO_FLOW', 'VALIDITY_REQUIRED'];

    public function __construct(
        protected VisitorApprovalPolicyService $visitorApprovalPolicy,
        protected VisitorPermitQueryService $permitQuery,
    ) {}

    /**
     * Berapa jatah yang sedang dipakai karyawan ini.
     */
    public function terpakai(string $kodeKaryawan): int
    {
        $kodeKaryawan = trim($kodeKaryawan);

        if ($kodeKaryawan === '') {
            return 0;
        }

        $isian = implode(',', array_fill(0, count(self::STATUS_MEMAKAI_KUOTA), '?'));

        return DB::table('N_EMI_VISITOR_Transaksi_Permit as h')
            ->leftJoin('N_EMI_VISITOR_Transaksi_Permit_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->where('h.Flag_Aktif', 'Y')
            ->where('h.Kode_Karyawan_Pemohon', $kodeKaryawan)
            // Izin sehari milik tamu TIDAK memakan jatah PIC-nya. Ia diajukan
            // atas nama PIC karena seseorang harus bertanggung jawab atas
            // kunjungannya, bukan karena PIC-nya yang mau lewat gerbang. Tanpa
            // pengecualian ini, karyawan yang mendaftarkan dua tamu membaca
            // "2/2 terpakai" pada permit kendaraannya sendiri — tanpa satu pun
            // keterangan kenapa.
            ->whereNull('h.No_Transaksi_Tamu')
            ->whereRaw($this->permitQuery->permitStatusSql()." IN ({$isian})", self::STATUS_MEMAKAI_KUOTA)
            // COUNT DISTINCT, bukan COUNT: join ke detail hari ini 1:1, tapi
            // kalau kelak satu pengajuan punya lebih dari satu baris detail,
            // jatah orangnya akan terhitung ganda tanpa gejala apa pun.
            ->distinct()
            ->count('h.No_Transaksi');
    }

    /**
     * Batas dari config permit. 0 berarti tanpa batas.
     */
    public function batas(): int
    {
        $batas = (int) ($this->visitorApprovalPolicy->config('permit')['max_pengajuan_aktif'] ?? 0);

        // Nilai negatif dari salah ketik .env tidak boleh menjelma jadi batas
        // yang mustahil dipenuhi; perlakukan sebagai tanpa batas.
        return max($batas, 0);
    }

    public function melampauiBatas(int $terpakai): bool
    {
        $batas = $this->batas();

        return $batas > 0 && $terpakai >= $batas;
    }

    /**
     * Serialisasi submit per pemohon. Wajib dipanggil setelah transaction dimulai
     * dan sebelum header permit di-insert.
     */
    /** Berapa pengajuan karyawan ini yang masih menunggu keputusan. */
    public function pendingTerpakai(string $kodeKaryawan): int
    {
        $kodeKaryawan = trim($kodeKaryawan);

        if ($kodeKaryawan === '') {
            return 0;
        }

        $isian = implode(',', array_fill(0, count(self::STATUS_MENUNGGU_KEPUTUSAN), '?'));

        return DB::table('N_EMI_VISITOR_Transaksi_Permit as h')
            ->leftJoin('N_EMI_VISITOR_Transaksi_Permit_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->where('h.Kode_Karyawan_Pemohon', $kodeKaryawan)
            ->where('h.Flag_Aktif', 'Y')
            // Izin turunan kunjungan tamu bukan pengajuan pemohon ini.
            ->whereNull('h.No_Transaksi_Tamu')
            ->whereRaw($this->permitQuery->permitStatusSql()." IN ({$isian})", self::STATUS_MENUNGGU_KEPUTUSAN)
            ->distinct()
            ->count('h.No_Transaksi');
    }

    public function batasPending(): int
    {
        return max((int) ($this->visitorApprovalPolicy->config('permit')['max_pengajuan_pending'] ?? 0), 0);
    }

    /**
     * Berapa PERMIT milik karyawan ini yang mendapat akses inspeksi hari ini.
     *
     * Dihitung per permit, bukan per baris booking: memindahkan jadwal membuat
     * baris booking baru tetapi TIDAK menerbitkan jendela gerbang tambahan -
     * jendelanya bergeser di baris proyeksi yang sama. Menghitung baris akan
     * menghukum orang yang menggeser jadwalnya, bukan orang yang mengulang
     * pengajuan.
     */
    public function aksesInspeksiHariIni(string $kodeKaryawan): int
    {
        $kodeKaryawan = trim($kodeKaryawan);

        if ($kodeKaryawan === '') {
            return 0;
        }

        return DB::table('N_VISITOR_Inspeksi_Booking as b')
            ->join('N_EMI_VISITOR_Transaksi_Permit as h', 'h.No_Transaksi', '=', 'b.No_Transaksi_Permit')
            ->where('h.Kode_Karyawan_Pemohon', $kodeKaryawan)
            ->whereNull('h.No_Transaksi_Tamu')
            ->whereDate('b.Created_At', now()->toDateString())
            ->distinct()
            ->count('b.No_Transaksi_Permit');
    }

    public function batasAksesInspeksiHarian(): int
    {
        return max((int) ($this->visitorApprovalPolicy->config('permit')['max_akses_inspeksi_harian'] ?? 0), 0);
    }

    public function assertAvailableWithinTransaction(string $kodeKaryawan): int
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Atomic permit quota guard requires an active transaction.');
        }

        $requesterExists = DB::table('Karyawan')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->lockForUpdate()
            ->exists();

        if (! $requesterExists) {
            throw new LogicException('Requester row is unavailable for permit quota locking.');
        }

        // Ketiganya diperiksa DI DALAM kunci yang sama. Memeriksa sebagian di
        // luar transaksi membuat dua submit bersamaan sama-sama lolos batas
        // yang kebetulan tinggal satu.
        $used = $this->terpakai($kodeKaryawan);
        $limit = $this->batas();

        if ($limit > 0 && $used >= $limit) {
            throw new VisitorPermitQuotaExceeded($used, $limit);
        }

        // Diperiksa SESUDAH jatah permit: bila keduanya penuh, yang lebih
        // berguna disebutkan lebih dulu - jatah permit punya jalan keluar yang
        // jelas (tunggu ditolak/kedaluwarsa), sedangkan pending hanya menunggu.
        $pending = $this->pendingTerpakai($kodeKaryawan);
        $batasPending = $this->batasPending();

        if ($batasPending > 0 && $pending >= $batasPending) {
            throw VisitorPermitQuotaExceeded::pending($pending, $batasPending);
        }

        $akses = $this->aksesInspeksiHariIni($kodeKaryawan);
        $batasAkses = $this->batasAksesInspeksiHarian();

        if ($batasAkses > 0 && $akses >= $batasAkses) {
            throw VisitorPermitQuotaExceeded::aksesHarian($akses, $batasAkses);
        }

        return $used;
    }
}
