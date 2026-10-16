<?php

namespace App\Services\VISITOR\Workflow\Approval;

use App\Helpers\FormatTanggalHelper;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalService;
use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Permit\Inspection\JadwalInspeksiService;
use App\Services\VISITOR\Permit\VehiclePermit\PermitCoklatTamuService;
use App\Services\VISITOR\Permit\VehiclePermit\PermitValidityPeriodCalculator;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitChecklistService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VisitorApprovalDecisionService
{
    public function __construct(
        protected ApprovalService $approvalService,
        protected VisitorPermitQueryService $permitQuery,
        protected VisitorEfektifService $visitorEfektif,
        protected PermitValidityPeriodCalculator $validityCalculator,
        protected VisitorPermitChecklistService $permitChecklist,
        protected PermitCoklatTamuService $permitCoklatTamu,
        protected JadwalInspeksiService $jadwalInspeksi,
    ) {}

    public function approvePermit(string $noTransaksi, string $approverKode, array $data, bool $checklistRequired): bool
    {
        return DB::transaction(function () use ($noTransaksi, $approverKode, $data, $checklistRequired) {
            $header = DB::table('N_EMI_VISITOR_Transaksi_Permit')
                ->where('No_Transaksi', $noTransaksi)
                ->where('Flag_Aktif', 'Y')
                ->lockForUpdate()
                ->first();

            if (! $header || $header->Flag_Approval !== null) {
                throw new \DomainException('Permit tidak tersedia untuk approval.');
            }

            $this->tolakBilaApprovalKedaluwarsa($noTransaksi);

            // Permit tamu — permit mitra maupun permit turunan kunjungan tamu.
            // Keduanya datang TANPA data kendaraan dan TANPA masa berlaku yang
            // pasti: justru verifikator yang mengisi keduanya saat memeriksa
            // kendaraan di pos. Durasi berbasis master bulanan tidak berlaku di
            // sini; yang dipakai jendela waktu yang ia tetapkan sendiri.
            $permitTamu = strtoupper((string) ($header->Sumber_Pengajuan ?? 'HCIS')) === 'API'
                || ($header->No_Transaksi_Tamu ?? null) !== null;

            // Pengaju tidak menyetujui pengajuannya sendiri (audit 27 Sep, M3,
            // keputusan user opsi b). Permit tamu dikecualikan: pemohon yang
            // tercatat di sana adalah PIC, bukan penerima izinnya.
            if (! $permitTamu) {
                $this->tolakBilaMilikSendiri($header->Kode_Karyawan_Pemohon ?? null, $approverKode);
            }

            // Data kendaraan dan jendela berlaku hasil inspeksi disimpan SEBELUM
            // checklist divalidasi dan sebelum langkah approval dicatat: kalau ia
            // disimpan belakangan, approval yang gagal di tengah meninggalkan
            // jawaban checklist tanpa data yang menyertainya.
            if ($permitTamu) {
                $this->simpanInspeksiKendaraan($noTransaksi, $data);
                $this->simpanJendelaPilihanVerifikator($noTransaksi, $data);
            }

            // Checklist fisik berlaku untuk SEMUA permit, termasuk COKLAT:
            // pemeriksaan kelayakan kendaraan tidak berubah hanya karena
            // pengemudinya tamu. Pengajuan tanpa kendaraan tidak punya satu pun
            // baris checklist, jadi keduanya menjadi no-op dengan sendirinya.
            $this->permitChecklist->saveAnswers($noTransaksi, $data['answers'] ?? []);
            $this->permitQuery->validatePermitChecklist($noTransaksi, $checklistRequired);

            // Permit karyawan ber-jadwal inspeksi (DDL-2026-123) hanya boleh
            // disetujui setelah jam inspeksinya tiba; persetujuan ini sekaligus
            // mencatat inspeksinya SELESAI. Tanpa penjaga ini permit kembali
            // bisa disetujui dari meja tanpa kendaraannya pernah dilihat.
            if (! $permitTamu) {
                $this->jadwalInspeksi->tandaiSelesaiSaatApprove($noTransaksi, $approverKode);
            }

            // Foto bukti pemeriksaan permit COKLAT wajib ada SEBELUM langkah
            // approval mana pun dicatat (permintaan user 29 Sep 2026).
            if ($permitTamu) {
                $this->validasiLampiranPermitTamu($noTransaksi);
            }

            $this->approvalService->approve($noTransaksi, $approverKode, $data['catatan'] ?? null);

            $isFinal = $this->permitQuery->isApprovalComplete($noTransaksi);
            if (! $isFinal) {
                return false;
            }

            if ($permitTamu) {
                $this->validasiDataKendaraanTamu($noTransaksi);
                $this->tetapkanMasaBerlakuPermitTamu($noTransaksi);
            } else {
                $duration = $this->resolveDuration($data['id_durasi_permit'] ?? null);
                [$start, $expiry] = $this->validityCalculator->calculate(
                    FormatTanggalHelper::getCurrentTime(),
                    (int) $duration->Jumlah_Angka,
                );

                DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')
                    ->where('No_Transaksi', $noTransaksi)
                    ->update([
                        'Id_Master_Durasi' => $duration->Id_Durasi_Permit,
                        'Snapshot_Nama_Durasi' => $duration->Nama_Durasi,
                        'Tanggal_Mulai_Berlaku' => $start,
                        'Tanggal_Kadaluarsa' => $expiry,
                    ]);
            }

            $updated = DB::table('N_EMI_VISITOR_Transaksi_Permit')
                ->where('No_Transaksi', $noTransaksi)
                ->where('Flag_Aktif', 'Y')
                ->update([
                    'Flag_Approval' => 'Y',
                    'Approved_At' => FormatTanggalHelper::getCurrentTime(),
                    'Approved_By' => $approverKode,
                    'Updated_At' => FormatTanggalHelper::getCurrentTime(),
                    'Updated_By' => $approverKode,
                ]);

            if ($updated !== 1) {
                throw new \RuntimeException('Header permit gagal difinalisasi secara konsisten.');
            }

            $this->visitorEfektif->syncPermitEfektif($noTransaksi);

            /*
             * Permit COKLAT turunan kunjungan tamu: keputusan inilah yang
             * menutup inspeksinya.
             *
             * Tamu B2B tidak memesan slot — SHE mendatangi pos gerbang saat
             * tamunya tiba — sehingga tak ada baris booking yang bisa ditandai
             * `tandaiSelesaiSaatApprove()`. Barisnya dibuat di sini, SESUDAH
             * header berstatus 'Y', supaya penjaga "selesai hanya setelah
             * direview" di dalamnya terpenuhi oleh kenyataan, bukan dilewati.
             */
            if (($header->No_Transaksi_Tamu ?? null) !== null) {
                $this->jadwalInspeksi->tandaiSelesaiKedatanganTamu($noTransaksi, $approverKode);
            }

            return true;
        });
    }

    public function rejectPermit(string $noTransaksi, string $rejectorKode, string $alasan): void
    {
        DB::transaction(function () use ($noTransaksi, $rejectorKode, $alasan) {
            $header = DB::table('N_EMI_VISITOR_Transaksi_Permit')
                ->where('No_Transaksi', $noTransaksi)
                ->where('Flag_Aktif', 'Y')
                ->lockForUpdate()
                ->first();

            if (! $header || $header->Flag_Approval !== null) {
                throw new \DomainException('Permit tidak tersedia untuk rejection.');
            }

            $this->tolakBilaApprovalKedaluwarsa($noTransaksi);

            $this->approvalService->reject($noTransaksi, $rejectorKode, $alasan, true);
            // Slot inspeksinya dilepas untuk pengaju lain.
            $this->jadwalInspeksi->batalkan($noTransaksi, 'Permit ditolak: '.$alasan, $rejectorKode);
            $this->visitorEfektif->akhiriAksesInspeksi($noTransaksi);
            DB::table('N_EMI_VISITOR_Transaksi_Permit')
                ->where('No_Transaksi', $noTransaksi)
                ->whereNull('Flag_Approval')
                ->update([
                    'Flag_Approval' => 'T',
                    'Updated_At' => FormatTanggalHelper::getCurrentTime(),
                    'Updated_By' => $rejectorKode,
                ]);
        });
    }

    public function approveTamu(
        string $noTransaksi,
        string $approverKode,
        ?string $catatan = null,
    ): bool {
        return DB::transaction(function () use ($noTransaksi, $approverKode, $catatan) {
            $header = DB::table('N_EMI_VISITOR_Transaksi_Tamu')
                ->where('No_Transaksi', $noTransaksi)
                ->where('Flag_Aktif', 'Y')
                ->lockForUpdate()
                ->first();

            if (! $header || $header->Flag_Approval !== null) {
                throw new \DomainException('Pengajuan tamu tidak tersedia untuk approval.');
            }

            $this->tolakBilaApprovalKedaluwarsa($noTransaksi);

            $this->tolakBilaMilikSendiri($header->Kode_Karyawan_PIC ?? null, $approverKode);

            $this->approvalService->approve($noTransaksi, $approverKode, $catatan);
            $isFinal = $this->permitQuery->isApprovalComplete($noTransaksi);

            if ($isFinal) {
                $updated = DB::table('N_EMI_VISITOR_Transaksi_Tamu')
                    ->where('No_Transaksi', $noTransaksi)
                    ->where('Flag_Aktif', 'Y')
                    ->update(['Flag_Approval' => 'Y', 'Status_Kunjungan' => StatusKunjunganTamu::SCHEDULED]);

                if ($updated !== 1) {
                    throw new \RuntimeException('Header tamu gagal difinalisasi secara konsisten.');
                }

                $this->visitorEfektif->syncTamuEfektif($noTransaksi);
                // Kunjungan yang disetujui MENGAJUKAN permit coklatnya — tidak
                // menerbitkannya. Izin gerbangnya baru hidup setelah permit itu
                // sendiri disetujui, lewat approvePermit() di atas.
                $this->permitCoklatTamu->sinkron($noTransaksi);
            }

            return $isFinal;
        });
    }

    public function rejectTamu(string $noTransaksi, string $rejectorKode, string $alasan): void
    {
        DB::transaction(function () use ($noTransaksi, $rejectorKode, $alasan) {
            $header = DB::table('N_EMI_VISITOR_Transaksi_Tamu')
                ->where('No_Transaksi', $noTransaksi)
                ->where('Flag_Aktif', 'Y')
                ->lockForUpdate()
                ->first();

            if (! $header || $header->Flag_Approval !== null) {
                throw new \DomainException('Pengajuan tamu tidak tersedia untuk rejection.');
            }

            $this->tolakBilaApprovalKedaluwarsa($noTransaksi);

            $this->approvalService->reject($noTransaksi, $rejectorKode, $alasan, true);
            DB::table('N_EMI_VISITOR_Transaksi_Tamu')
                ->where('No_Transaksi', $noTransaksi)
                ->whereNull('Flag_Approval')
                ->update(['Flag_Approval' => 'T', 'Status_Kunjungan' => StatusKunjunganTamu::REJECTED]);
        });
    }

    public const ALASAN_APPROVAL_KEDALUWARSA = 'Ditutup otomatis: batas waktu approval terlewati tanpa keputusan.';

    /**
     * Tutup kunjungan yang approval-nya sudah lewat Expired_At tanpa keputusan.
     *
     * ApprovalService menolak memutus langkah yang kedaluwarsa, sehingga
     * kunjungan seperti ini tertahan PENDING_APPROVAL selamanya: tak bisa
     * disetujui, ditolak, maupun hilang dari antrean (audit M11). Ditandai
     * REJECTED dengan catatan di langkah approval — tempat `alasan` dibaca
     * layar dan API mitra.
     *
     * @return list<array{no_transaksi:string, tipe_tamu:?string}> kunjungan yang ditutup
     */
    public function tutupTamuApprovalKedaluwarsa(): array
    {
        $baris = DB::table('N_EMI_VISITOR_Transaksi_Tamu as t')
            ->leftJoin('N_EMI_VISITOR_Transaksi_Tamu_Detail as d', 'd.No_Transaksi', '=', 't.No_Transaksi')
            ->where('t.Flag_Aktif', 'Y')
            ->whereNull('t.Flag_Approval')
            ->where('t.Status_Kunjungan', StatusKunjunganTamu::PENDING_APPROVAL)
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('HRIS_Approval_Request as ar')
                ->whereColumn('ar.No_Transaksi', 't.No_Transaksi')
                ->whereNull('ar.Flag_Approval')
                ->whereNotNull('ar.Expired_At')
                ->where('ar.Expired_At', '<', now()))
            ->select('t.No_Transaksi', 'd.Tipe_Tamu')
            ->get();

        $ditutup = [];
        foreach ($baris as $b) {
            $berubah = DB::transaction(function () use ($b) {
                $n = DB::table('N_EMI_VISITOR_Transaksi_Tamu')
                    ->where('No_Transaksi', $b->No_Transaksi)
                    ->where('Flag_Aktif', 'Y')
                    ->whereNull('Flag_Approval')
                    ->where('Status_Kunjungan', StatusKunjunganTamu::PENDING_APPROVAL)
                    ->update(['Flag_Approval' => 'T', 'Status_Kunjungan' => StatusKunjunganTamu::REJECTED]);

                if ($n === 1) {
                    DB::table('HRIS_Approval_Request')
                        ->where('No_Transaksi', $b->No_Transaksi)
                        ->whereNull('Flag_Approval')
                        ->update([
                            'Flag_Approval' => 'T',
                            'Catatan' => self::ALASAN_APPROVAL_KEDALUWARSA,
                            'Tanggal_Approval' => now(),
                            'update_at' => now(),
                        ]);
                }

                return $n === 1;
            });

            if ($berubah) {
                $ditutup[] = ['no_transaksi' => (string) $b->No_Transaksi, 'tipe_tamu' => $b->Tipe_Tamu];
            }
        }

        return $ditutup;
    }

    public const EXPIRED_OLEH_SISTEM = 'Sistem (Auto-Expire)';

    /** Kedaluwarsa yang lebih tua dari ini tidak dikabarkan lagi; lihat di bawah. */
    public const HARI_KABAR_KEDALUWARSA = 3;

    /**
     * Tandai permit karyawan yang batas approval-nya lewat tanpa keputusan.
     *
     * BEDA dari tamu: permitnya TIDAK ditutup jadi ditolak. Statusnya tetap
     * terbaca EXPIRED (dihitung dari Expired_At) dan ia tinggal arsip. Yang
     * dikerjakan di sini hanya mengisi Expired_by pada langkah approval-nya,
     * sebagai penanda "pemohon sudah dikabari" — tanpa penanda, penyapu lima
     * menitan akan mengabarkan hal yang sama berulang-ulang.
     *
     * Hanya yang kedaluwarsa dalam HARI_KABAR_KEDALUWARSA hari terakhir: saat
     * pertama dipasang, pengajuan lama yang sudah lama mati tidak dikabarkan
     * serentak, dan tidak disentuh sama sekali.
     *
     * @return list<string> nomor permit yang baru ditandai
     */
    public function tandaiPermitApprovalKedaluwarsa(): array
    {
        $nomor = DB::table('N_EMI_VISITOR_Transaksi_Permit as h')
            ->where('h.Flag_Aktif', 'Y')
            ->whereNull('h.Flag_Approval')
            ->whereNull('h.No_Transaksi_Tamu')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('HRIS_Approval_Request as ar')
                ->whereColumn('ar.No_Transaksi', 'h.No_Transaksi')
                ->whereNull('ar.Flag_Approval')
                ->whereNull('ar.Expired_by')
                ->where('ar.Expired_At', '<', now())
                ->where('ar.Expired_At', '>=', now()->subDays(self::HARI_KABAR_KEDALUWARSA)))
            ->pluck('h.No_Transaksi');

        $ditandai = [];
        foreach ($nomor as $no) {
            // Bersyarat Expired_by IS NULL: dua penyapu yang berlomba hanya
            // satu yang mendapat baris berubah, jadi kabarnya terkirim sekali.
            $n = DB::table('HRIS_Approval_Request')
                ->where('No_Transaksi', $no)
                ->whereNull('Flag_Approval')
                ->whereNull('Expired_by')
                ->where('Expired_At', '<', now())
                ->update(['Expired_by' => self::EXPIRED_OLEH_SISTEM, 'update_at' => now()]);

            if ($n > 0) {
                $ditandai[] = (string) $no;
            }
        }

        return $ditandai;
    }

    public function setLegacyPermitValidity(string $noTransaksi, string $actorKode, int $durationId): void
    {
        DB::transaction(function () use ($noTransaksi, $actorKode, $durationId) {
            $header = DB::table('N_EMI_VISITOR_Transaksi_Permit')
                ->where('No_Transaksi', $noTransaksi)
                ->where('Flag_Aktif', 'Y')
                ->where('Flag_Approval', 'Y')
                ->lockForUpdate()
                ->first();

            $detail = DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')
                ->where('No_Transaksi', $noTransaksi)
                ->lockForUpdate()
                ->first();

            if (! $header || ! $detail) {
                throw new \DomainException('Permit approved tidak ditemukan.');
            }

            if ($detail->Id_Master_Durasi || $detail->Tanggal_Mulai_Berlaku || $detail->Tanggal_Kadaluarsa) {
                throw new \DomainException('Masa berlaku permit sudah pernah ditetapkan.');
            }

            $duration = $this->resolveDuration($durationId);
            [$start, $expiry] = $this->validityCalculator->calculate(
                FormatTanggalHelper::getCurrentTime(),
                (int) $duration->Jumlah_Angka,
            );

            DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')
                ->where('Id_Permit_Detail', $detail->Id_Permit_Detail)
                ->update([
                    'Id_Master_Durasi' => $duration->Id_Durasi_Permit,
                    'Snapshot_Nama_Durasi' => $duration->Nama_Durasi,
                    'Tanggal_Mulai_Berlaku' => $start,
                    'Tanggal_Kadaluarsa' => $expiry,
                ]);

            DB::table('N_EMI_VISITOR_Transaksi_Permit')
                ->where('No_Transaksi', $noTransaksi)
                ->update([
                    'Updated_At' => FormatTanggalHelper::getCurrentTime(),
                    'Updated_By' => $actorKode,
                ]);

            $this->visitorEfektif->syncPermitEfektif($noTransaksi);
        });
    }

    /**
     * Simpan hasil inspeksi kendaraan yang dicatat verifikator.
     *
     * Nilai yang TIDAK dikirim sengaja tidak menimpa apa pun: approval bertahap
     * berarti metode ini dijalankan sekali per langkah, dan approver kedua yang
     * mengirim formulir kosong tidak boleh menghapus hasil inspeksi approver
     * pertama.
     *
     * @param  array<string,mixed>  $data
     */
    private function simpanInspeksiKendaraan(string $noTransaksi, array $data): void
    {
        $isian = [];

        foreach ([
            'id_master_model' => 'Id_Master_Model',
            'id_master_warna' => 'Id_Master_Warna',
            'tahun_kendaraan' => 'Tahun_Kendaraan',
        ] as $kunci => $kolom) {
            if (($data[$kunci] ?? null) !== null && $data[$kunci] !== '') {
                $isian[$kolom] = (int) $data[$kunci];
            }
        }

        if ($isian === []) {
            return;
        }

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')
            ->where('No_Transaksi', $noTransaksi)
            ->update($isian);
    }

    /** Lampiran yang wajib ada sebelum permit tamu berkendaraan disetujui. */
    public const LAMPIRAN_WAJIB_PERMIT_TAMU = [
        'FOTO_KENDARAAN' => 'Foto Kendaraan',
        'STNK' => 'STNK',
        'SIM' => 'SIM',
    ];

    /**
     * Foto bukti pemeriksaan permit COKLAT wajib lengkap dan tidak boleh
     * dilewati (permintaan user 29 Sep 2026).
     *
     * Diperiksa di SERVER, bukan hanya di tombol Setujui: pemanggilan API
     * langsung atau layar lama melewati tombol sepenuhnya. Berlaku di setiap
     * langkah approval — foto diambil di pos saat pemeriksaan, dan approver
     * berikutnya melihat lampiran yang sudah tersimpan. Tamu pejalan kaki
     * (tanpa kendaraan, aturan wajibInspeksiKendaraan yang sama dengan data
     * kendaraan) tidak membawa apa pun untuk difoto.
     */
    private function validasiLampiranPermitTamu(string $noTransaksi): void
    {
        if (! $this->permitQuery->wajibInspeksiKendaraan($noTransaksi)) {
            return;
        }

        $ada = DB::table('N_EMI_VISITOR_Transaksi_Permit_Dokumen')
            ->where('No_Transaksi', $noTransaksi)
            ->whereIn('Jenis_Dokumen', array_keys(self::LAMPIRAN_WAJIB_PERMIT_TAMU))
            ->whereNotNull('Path_File_URL')
            ->where('Path_File_URL', '<>', '')
            ->distinct()
            ->pluck('Jenis_Dokumen')
            ->map(fn ($j) => strtoupper((string) $j))
            ->all();

        $kurang = array_values(array_diff_key(self::LAMPIRAN_WAJIB_PERMIT_TAMU, array_flip($ada)));
        if ($kurang) {
            throw new \InvalidArgumentException(
                'Lampiran foto pemeriksaan wajib diunggah sebelum menyetujui permit tamu: '.implode(', ', $kurang).'.',
            );
        }
    }

    /**
     * Permit COKLAT tidak boleh terbit tanpa bukti inspeksinya.
     *
     * Penjaga ini di SERVER, bukan hanya tombol yang di-disable: jalur approval
     * legacy dan pemanggilan langsung ke service melewati layar sepenuhnya, dan
     * kegagalannya berupa izin akses fisik yang terbit tanpa seorang pun pernah
     * melihat kendaraannya.
     */
    private function validasiDataKendaraanTamu(string $noTransaksi): void
    {
        if (! $this->permitQuery->wajibInspeksiKendaraan($noTransaksi)) {
            return;
        }

        $detail = DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')
            ->where('No_Transaksi', $noTransaksi)
            ->first(['Id_Master_Model', 'Id_Master_Warna', 'Tahun_Kendaraan']);

        if (! $detail || ! $detail->Id_Master_Model || ! $detail->Id_Master_Warna || ! $detail->Tahun_Kendaraan) {
            throw new \DomainException(
                'Lengkapi hasil inspeksi kendaraan (merek/model, tahun pembuatan, dan warna) '
                    .'sebelum menyetujui permit ini.',
            );
        }
    }

    /**
     * Simpan jendela berlaku yang DIPILIH VERIFIKATOR untuk permit tamu.
     *
     * Sebelumnya jendelanya diturunkan otomatis dari tanggal kunjungan dan
     * dipaksa sehari. Itu keliru dua arah sekaligus: permit mitra lewat
     * /permit-tamu tidak membawa tanggal apa pun (kolomnya ditulis NULL), jadi
     * penjaga sehari membuatnya MUSTAHIL disetujui siapa pun; sementara tamu
     * yang menginap atau bekerja lintas hari tidak punya cara menyatakannya.
     *
     * Kosong sama sekali = langkah approval yang tidak menyentuh jendelanya.
     * Approval bertahap mengirim formulir yang sama pada setiap langkah, dan
     * menolak approver pertama yang tidak mengisinya berarti memblokir alur
     * tanpa sebab. Yang menuntut keberadaannya adalah penjaga di langkah FINAL.
     *
     * Hanya kolom JAM yang ditulis di sini. Kolom tanggal turunannya ditetapkan
     * sekali di langkah final — lihat tetapkanMasaBerlakuPermitTamu() — supaya
     * jendela yang masuk lewat pintu lain tidak melewatkan penurunan itu.
     */
    private function simpanJendelaPilihanVerifikator(string $noTransaksi, array $data): void
    {
        $mulaiMentah = trim((string) ($data['waktu_mulai_berlaku'] ?? ''));
        $akhirMentah = trim((string) ($data['waktu_berakhir'] ?? ''));

        if ($mulaiMentah === '' && $akhirMentah === '') {
            return;
        }

        if ($mulaiMentah === '' || $akhirMentah === '') {
            throw new \InvalidArgumentException(
                'Isi waktu mulai DAN waktu berakhir masa berlaku permit; satu ujung saja tidak cukup.',
            );
        }

        $mulai = Carbon::parse($mulaiMentah);
        $akhir = Carbon::parse($akhirMentah);

        $this->pastikanUrutanMasaBerlaku($mulai, $akhir);
        $this->pastikanTanggalMulaiTidakMundur($mulai);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')
            ->where('No_Transaksi', $noTransaksi)
            ->update([
                'Waktu_Mulai_Berlaku' => $mulai,
                'Waktu_Berakhir' => $akhir,
            ]);
    }

    /**
     * Tetapkan masa berlaku final permit tamu dari jendela jam yang tersimpan.
     *
     * Dibaca ulang dari basis data, bukan dari payload: jalur approval lain dan
     * pemanggilan langsung ke service melewati formulir sepenuhnya, dan
     * kegagalannya berupa izin akses fisik tanpa batas waktu — yang tidak akan
     * pernah dicabut penjadwal kedaluwarsa karena tidak ada yang dibandingkan.
     *
     * Penurunan kolom tanggal terjadi DI SINI, sekali, untuk jendela dari pintu
     * mana pun. Aplikasi palang yang belum mengadopsi kolom jam hanya membaca
     * Tanggal_*; membiarkannya kosong membuat permit yang sah tidak terlihat di
     * gerbang tanpa satu pun galat.
     */
    private function tetapkanMasaBerlakuPermitTamu(string $noTransaksi): void
    {
        $detail = DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')
            ->where('No_Transaksi', $noTransaksi)
            ->first(['Waktu_Mulai_Berlaku', 'Waktu_Berakhir']);

        if (! $detail || ! $detail->Waktu_Mulai_Berlaku || ! $detail->Waktu_Berakhir) {
            throw new \DomainException(
                'Tentukan masa berlaku permit ini — tanggal dan jam mulai sampai tanggal dan jam '
                    .'berakhir — sebelum menyetujuinya.',
            );
        }

        $mulai = Carbon::parse($detail->Waktu_Mulai_Berlaku);
        $akhir = Carbon::parse($detail->Waktu_Berakhir);

        $this->pastikanUrutanMasaBerlaku($mulai, $akhir);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')
            ->where('No_Transaksi', $noTransaksi)
            ->update([
                'Tanggal_Mulai_Berlaku' => $mulai->toDateString(),
                'Tanggal_Kadaluarsa' => $this->tanggalTerakhirTersentuh($akhir)->toDateString(),
                // Durasi master hanya mengenal hitungan bulan; jendela yang
                // ditetapkan verifikator tidak punya barisnya di sana.
                'Id_Master_Durasi' => null,
                'Snapshot_Nama_Durasi' => $this->namaDurasiDariRentang($mulai, $akhir),
            ]);
    }

    private function pastikanUrutanMasaBerlaku(Carbon $mulai, Carbon $akhir): void
    {
        if ($akhir->lessThanOrEqualTo($mulai)) {
            throw new \InvalidArgumentException('Waktu berakhir harus setelah waktu mulai.');
        }
    }

    /**
     * Larangan memundurkan TANGGAL mulai, berlaku atas PILIHAN verifikator saja.
     *
     * Panjang jendelanya bebas — tamu proyek yang bekerja seminggu tetap satu
     * permit, bukan tujuh. Yang dilarang hanya menerbitkan izin yang berlaku
     * surut: akses fisik untuk hari yang sudah lewat tidak menjaga apa pun, ia
     * hanya melegalkan kunjungan yang terlanjur terjadi tanpa pemeriksaan.
     *
     * Aturan ini SENGAJA tidak dipasang pada nilai yang sudah tersimpan. Jendela
     * bisa lahir jauh sebelum verifikator membuka mejanya — permit turunan
     * kunjungan membawa tanggal kunjungannya sejak diajukan — dan menolaknya di
     * langkah final berarti pengajuan yang menunggu terlalu lama berubah menjadi
     * mustahil disetujui, persis kebuntuan yang baru saja dibongkar di sini.
     *
     * Jamnya sendiri boleh mundur di dalam hari ini. Verifikator yang menyetujui
     * pukul 14:00 tetap boleh menuliskan berlaku sejak pukul 08:00 — tamunya
     * memang sudah menunggu di pos sejak pagi, dan memaksanya menulis 14:00
     * membuat catatan gerbang berbohong tentang jam kedatangan.
     */
    private function pastikanTanggalMulaiTidakMundur(Carbon $mulai): void
    {
        $hariIni = Carbon::parse(FormatTanggalHelper::getCurrentTime())->startOfDay();

        if ($mulai->copy()->startOfDay()->lessThan($hariIni)) {
            throw new \InvalidArgumentException(
                'Masa berlaku tidak boleh dimulai pada tanggal yang sudah lewat. '
                    .'Tetapkan ulang jendela berlakunya sebelum menyetujui.',
            );
        }
    }

    /**
     * Tanggal terakhir yang benar-benar tersentuh jendela berlaku.
     *
     * Jendelanya setengah-terbuka: izin sehari milik tamu berakhir pada pukul
     * 00:00 hari BERIKUTNYA, dan menghitung tanggal itu sebagai hari yang
     * tersentuh membuat izin sehari terbaca dua hari — di label durasinya
     * maupun di kolom kedaluwarsa yang dibaca gerbang.
     */
    private function tanggalTerakhirTersentuh(Carbon $akhir): Carbon
    {
        return $akhir->copy()->subSecond()->startOfDay();
    }

    private function namaDurasiDariRentang(Carbon $mulai, Carbon $akhir): string
    {
        // Dihitung dari TANGGAL yang tersentuh, bukan dari selisih jam: izin
        // pukul 22:00 sampai 02:00 menyentuh dua tanggal, dan satpam di gerbang
        // membaca tanggal, bukan lama waktu.
        $hari = $mulai->copy()->startOfDay()->diffInDays($this->tanggalTerakhirTersentuh($akhir)) + 1;

        return max(1, (int) $hari).' Hari';
    }

    private function resolveDuration(?int $durationId): object
    {
        if (! $durationId) {
            throw new \InvalidArgumentException('Approver final wajib memilih durasi permit.');
        }

        $duration = DB::table('N_EMI_VISITOR_Master_Durasi_Permit')
            ->where('Id_Durasi_Permit', $durationId)
            ->where('Flag_Aktif', 'Y')
            ->first();

        if (! $duration || strtoupper((string) $duration->Satuan_Waktu) !== 'MONTH' || (int) $duration->Jumlah_Angka <= 0) {
            throw new \InvalidArgumentException('Master durasi permit tidak valid atau tidak aktif.');
        }

        return $duration;
    }

    /**
     * ApprovalService menolak langkah yang lewat Expired_At dengan \Exception
     * polos, yang sampai ke pengguna sebagai 500 "gagal disetujui". Diperiksa
     * lebih dulu di sini supaya jawabannya 409 dengan sebab yang jelas.
     */
    private function tolakBilaApprovalKedaluwarsa(string $noTransaksi): void
    {
        $kedaluwarsa = DB::table('HRIS_Approval_Request')
            ->where('No_Transaksi', $noTransaksi)
            ->whereNull('Flag_Approval')
            ->whereNotNull('Expired_At')
            ->where('Expired_At', '<', now())
            ->exists();

        if ($kedaluwarsa) {
            throw new \DomainException('Batas waktu approval pengajuan ini sudah lewat, sehingga tidak bisa diputus lagi. Pemohon perlu mengajukan ulang.');
        }
    }

    private function tolakBilaMilikSendiri(?string $pengaju, string $approverKode): void
    {
        $pengaju = trim((string) $pengaju);

        if ($pengaju !== '' && strcasecmp($pengaju, trim($approverKode)) === 0) {
            throw new \DomainException('Anda tidak dapat menyetujui pengajuan Anda sendiri. Minta verifikator lain untuk memutusnya.');
        }
    }
}
