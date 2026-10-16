<?php

namespace App\Services\VISITOR\Permit\Inspection;

use App\Services\VISITOR\Guest\Visit\VisitorKehadiranQueryService;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Jadwal inspeksi kendaraan permit karyawan (DDL-2026-123).
 *
 * Satu-satunya penulis N_VISITOR_Inspeksi_Booking. Slot kosong TIDAK disimpan:
 * ia dihitung dari pola mingguan + pengecualian tanggal, dikurangi booking
 * TERJADWAL. Setiap penulisan booking wajib berjalan di dalam transaksi
 * pemanggil — kuota dijaga hitungan ber-UPDLOCK/HOLDLOCK yang hanya berarti
 * bila kuncinya bertahan sampai insert.
 */
class JadwalInspeksiService
{
    public function __construct(
        private VisitorEfektifService $efektif,
        private VisitorKehadiranQueryService $kehadiran,
    ) {}

    public const TERJADWAL = 'TERJADWAL';

    public const DIPINDAH = 'DIPINDAH';

    /**
     * Dipindah PETUGAS (Agenda Inspeksi), bukan pemohon. Status terpisah supaya
     * tidak ikut menghitung jatah MAKS_JADWAL_ULANG milik pemohon.
     */
    public const DIPINDAH_PETUGAS = 'DIPINDAH_PETUGAS';

    /** Lebar kolom N_VISITOR_Inspeksi_Booking.Alasan (varchar(250), DDL-2026-123). */
    public const MAKS_ALASAN = 250;

    public const TIDAK_HADIR = 'TIDAK_HADIR';

    public const SELESAI = 'SELESAI';

    public const DIBATALKAN = 'DIBATALKAN';

    /**
     * Slot terlewat tanpa dicatat siapa pun, lalu pemohon memilih jadwal baru.
     * BUKAN ketidakhadiran: tidak ada yang tahu siapa yang absen, jadi ia tidak
     * pernah ikut menghitung ambang MAKS_TIDAK_HADIR — tetapi ia memakan jatah
     * MAKS_JADWAL_ULANG, lihat jadwalUlangTerpakai().
     */
    public const KEDALUWARSA = 'KEDALUWARSA';

    public const TUTUP = 'TUTUP';

    public const TAMBAH = 'TAMBAH';

    /**
     * Bawaan bila config tidak terbaca. Pakai maksJadwalUlang(), bukan
     * konstanta ini: angkanya bisa disetel per lingkungan lewat
     * VISITOR_PERMIT_MAKS_JADWAL_ULANG, dan membaca konstanta langsung akan
     * menegakkan angka yang berbeda dari yang ditampilkan layar.
     */
    public const MAKS_JADWAL_ULANG = 2;

    /** Bawaan; pakai maksTidakHadir(). Lihat catatan di atas. */
    public const MAKS_TIDAK_HADIR = 2;

    /** Bawaan; pakai hariTenggangPutus(). Lihat catatan di atas. */
    public const HARI_TENGGANG_PUTUS = 1;

    /** Jadwal ulang mandiri paling banyak, per permit. */
    public static function maksJadwalUlang(): int
    {
        return max((int) config('services.visitor.permit.maks_jadwal_ulang', self::MAKS_JADWAL_ULANG), 1);
    }

    /**
     * Pengajuan masih bisa diputus sampai akhir hari ke-N sesudah hari
     * inspeksinya; lewat dari itu ia EXPIRED. 0 = hanya sampai akhir hari H.
     */
    public static function hariTenggangPutus(): int
    {
        return max((int) config('services.visitor.permit.hari_tenggang_putus', self::HARI_TENGGANG_PUTUS), 0);
    }

    /** Tidak hadir ke-N menggugurkan pengajuan, per permit. */
    public static function maksTidakHadir(): int
    {
        return max((int) config('services.visitor.permit.maks_tidak_hadir', self::MAKS_TIDAK_HADIR), 1);
    }

    /** Slot yang mulai kurang dari ini tidak ditawarkan dan tidak bisa dipesan. */
    public static function jedaMinimumMenit(): int
    {
        return max((int) config('services.visitor.permit.jeda_minimum_menit', self::JEDA_MINIMUM_MENIT), 0);
    }

    /**
     * Pos gerbang tempat tamu B2B diinspeksi. 0 = belum disetel.
     *
     * Tamu B2B tidak memilih slot, sehingga posnya tidak bisa diturunkan dari
     * apa pun pada barisnya sendiri — ia harus ditentukan di satu tempat.
     */
    public static function posTamuB2b(): int
    {
        return max((int) config('services.visitor.permit.pos_tamu_b2b', 0), 0);
    }

    /** Jendela pilihan slot ke depan. */
    public const HARI_KE_DEPAN = 14;

    /** Slot yang dimulai kurang dari ini tidak bisa dipesan lagi. */
    public const JEDA_MINIMUM_MENIT = 30;

    public const T_POS = 'N_VISITOR_Master_Pos_Inspeksi';

    public const T_SHIFT = 'N_VISITOR_Master_Shift_Inspeksi';

    public const T_HARI_SHIFT = 'N_VISITOR_Inspeksi_Hari_Shift';

    private const T_POLA = 'N_VISITOR_Inspeksi_Pola';

    private const T_PENGECUALIAN = 'N_VISITOR_Inspeksi_Pengecualian';

    private const T_BOOKING = 'N_VISITOR_Inspeksi_Booking';

    /** Keadaan satu slot; dipakai layar untuk membedakan "habis" dari "tak pernah dibuka". */
    public const SLOT_TERSEDIA = 'tersedia';

    public const SLOT_PENUH = 'penuh';

    public const SLOT_LEWAT = 'lewat';

    /**
     * Slot yang masih bisa dipesan pada rentang tanggal (inklusif).
     *
     * @return list<array{id_pos: int, pos: string, lokasi: ?string, id_shift: ?int, shift: ?string, mulai: string, selesai: string, kuota: int, terisi: int, sisa: int}>
     */
    public function slotTersedia(CarbonInterface $dari, CarbonInterface $sampai): array
    {
        return array_values(array_filter(
            $this->slotBerkeadaan($dari, $sampai),
            fn (array $slot) => $slot['keadaan'] === self::SLOT_TERSEDIA,
        ));
    }

    /**
     * Seluruh slot pada rentang, masing-masing dengan SEBAB ia bisa dipesan
     * atau tidak.
     *
     * Dipakai layar yang perlu memperlihatkan jadwal apa adanya: SHE yang
     * memeriksa jam mana yang sudah habis diserbu, dan pemohon yang ingin tahu
     * slotnya memang pernah ada. Menyaringnya lebih dulu membuat jadwal yang
     * penuh tak bisa dibedakan dari jadwal yang tak pernah dibuka — dua keadaan
     * yang menuntut tindakan berbeda.
     *
     * Tiga keadaan, dan urutannya berarti: slot yang jamnya sudah lewat tetap
     * 'lewat' walau kuotanya habis, sebab yang pertama menghalanginya adalah
     * waktu.
     *
     * @return list<array{id_pos: int, pos: string, lokasi: ?string, id_shift: ?int, shift: ?string, mulai: string, selesai: string, kuota: int, terisi: int, sisa: int, keadaan: string}>
     */
    public function slotBerkeadaan(CarbonInterface $dari, CarbonInterface $sampai): array
    {
        $batasAwal = now()->addMinutes(self::jedaMinimumMenit());

        return array_map(function (array $slot) use ($batasAwal) {
            $slot['keadaan'] = match (true) {
                Carbon::parse($slot['mulai'])->lt($batasAwal) => self::SLOT_LEWAT,
                $slot['sisa'] <= 0 => self::SLOT_PENUH,
                default => self::SLOT_TERSEDIA,
            };

            return $slot;
        }, $this->slotRentang($dari, $sampai));
    }

    /**
     * Seluruh slot (penuh atau tidak) pada rentang, dengan hitungan terisi.
     *
     * Pos inspeksi berasal dari master (DDL-2026-123 r2): pola dan slot TAMBAH
     * milik pos yang dinonaktifkan tidak membuka slot apa pun. Begitu pula
     * shift (r3): jadwal milik shift nonaktif berhenti membuka slot.
     *
     * Tiap hari memuat ketiga shift; sakelar per (hari, shift) dan per hari
     * penuh ada di N_VISITOR_Inspeksi_Hari_Shift (r4). Tidak ada baris = aktif.
     *
     * Jam selesai <= jam mulai berarti jadwal menyeberang tengah malam
     * (mis. Shift 3, 23:30–00:30); slotnya tetap milik HARI MULAInya.
     *
     * @return list<array{id_pos: int, pos: string, lokasi: ?string, id_shift: ?int, shift: ?string, mulai: string, selesai: string, kuota: int, terisi: int, sisa: int}>
     */
    public function slotRentang(CarbonInterface $dari, CarbonInterface $sampai): array
    {
        $awal = Carbon::parse($dari)->startOfDay();
        $akhir = Carbon::parse($sampai)->startOfDay();

        $posAktif = DB::table(self::T_POS)->where('Flag_Aktif', 'Y')->get()->keyBy('Id_Pos_Inspeksi');
        $shiftAktif = DB::table(self::T_SHIFT)->where('Flag_Aktif', 'Y')->get()->keyBy('Id_Shift_Inspeksi');
        $dimatikan = DB::table(self::T_HARI_SHIFT)
            ->where('Flag_Aktif', 'T')
            ->get()
            ->map(fn ($r) => $r->Hari_Minggu.'|'.($r->Id_Shift_Inspeksi ?? ''))
            ->flip();
        $pola = DB::table(self::T_POLA)->where('Flag_Aktif', 'Y')->get();
        $pengecualian = DB::table(self::T_PENGECUALIAN)
            ->where('Flag_Aktif', 'Y')
            ->whereBetween('Tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->get()
            ->groupBy(fn ($r) => substr((string) $r->Tanggal, 0, 10));

        $terisi = DB::table(self::T_BOOKING)
            ->where('Status', self::TERJADWAL)
            ->where('Waktu_Mulai', '>=', $awal)
            // +2 hari: jadwal yang menyeberang tengah malam pada hari terakhir
            // masih menaruh slotnya di dini hari berikutnya.
            ->where('Waktu_Mulai', '<', $akhir->copy()->addDays(2))
            ->groupBy('Id_Pos_Inspeksi', 'Waktu_Mulai')
            ->select('Id_Pos_Inspeksi', 'Waktu_Mulai', DB::raw('COUNT(*) as n'))
            ->get()
            ->mapWithKeys(fn ($r) => [$this->kunciSlot((int) $r->Id_Pos_Inspeksi, Carbon::parse($r->Waktu_Mulai)) => (int) $r->n]);

        $hasil = [];

        for ($hari = $awal->copy(); $hari->lte($akhir); $hari->addDay()) {
            $tanggal = $hari->toDateString();
            $khusus = $pengecualian->get($tanggal, collect());

            // Hari penuh dimatikan: seluruh shift hari itu tidak membuka slot.
            // Slot TAMBAH dari pengecualian tetap jalan — ia memang pengecualian.
            $hariMati = $dimatikan->has($hari->dayOfWeekIso.'|');

            $jendela = $pola
                ->where('Hari_Minggu', $hari->dayOfWeekIso)
                ->map(fn ($p) => (array) $p + ['dari_pola' => true])
                ->values()
                ->all();

            // Slot TAMBAH dibuka SHE untuk tanggal itu sendiri, jadi ia tidak
            // tunduk pada sakelar hari/shift mingguan.
            foreach ($khusus->where('Jenis', self::TAMBAH) as $tambah) {
                $jendela[] = (array) $tambah + ['dari_pola' => false];
            }

            $tutup = $khusus->where('Jenis', self::TUTUP);

            foreach ($jendela as $j) {
                $idPos = (int) $j['Id_Pos_Inspeksi'];
                $pos = $posAktif->get($idPos);
                $idShift = $j['Id_Shift_Inspeksi'] !== null ? (int) $j['Id_Shift_Inspeksi'] : null;
                $shift = $idShift !== null ? $shiftAktif->get($idShift) : null;
                $ditutup = $tutup->contains(fn ($t) => $t->Id_Pos_Inspeksi === null || (int) $t->Id_Pos_Inspeksi === $idPos);

                // Jadwal bershift hanya hidup selama shiftnya aktif; slot
                // tambahan tanpa shift (pengecualian) selalu ikut tanggalnya.
                $shiftMati = $j['dari_pola']
                    && ($hariMati || $dimatikan->has($hari->dayOfWeekIso.'|'.$idShift));

                if (! $pos || ($idShift !== null && ! $shift) || $shiftMati || $ditutup
                    || ! $j['Jam_Mulai'] || ! $j['Jam_Selesai'] || (int) $j['Durasi_Menit'] <= 0) {
                    continue;
                }

                $mulai = Carbon::parse($tanggal.' '.substr((string) $j['Jam_Mulai'], 0, 5));
                $batas = Carbon::parse($tanggal.' '.substr((string) $j['Jam_Selesai'], 0, 5));

                if ($batas->lte($mulai)) {
                    $batas->addDay();
                }
                $durasi = (int) $j['Durasi_Menit'];
                $kuota = max((int) $j['Kuota_Per_Slot'], 0);

                for ($s = $mulai->copy(); $s->copy()->addMinutes($durasi)->lte($batas); $s->addMinutes($durasi)) {
                    $isi = $terisi[$this->kunciSlot($idPos, $s)] ?? 0;
                    $hasil[$this->kunciSlot($idPos, $s)] = [
                        'id_pos' => $idPos,
                        'pos' => (string) $pos->Nama_Pos,
                        'lokasi' => $pos->Keterangan_Lokasi,
                        'id_shift' => $idShift,
                        'shift' => $shift?->Nama_Shift,
                        'mulai' => $s->format('Y-m-d H:i'),
                        'selesai' => $s->copy()->addMinutes($durasi)->format('Y-m-d H:i'),
                        'kuota' => $kuota,
                        'terisi' => $isi,
                        'sisa' => max($kuota - $isi, 0),
                    ];
                }
            }
        }

        usort($hasil, fn ($a, $b) => [$a['mulai'], $a['pos']] <=> [$b['mulai'], $b['pos']]);

        return array_values($hasil);
    }

    /**
     * Pesan satu slot untuk permit. WAJIB di dalam transaksi pemanggil.
     *
     * @throws \DomainException slot tidak ada, lewat, atau penuh
     */
    public function pesan(string $noPermit, int $idPos, string $mulai, string $oleh): object
    {
        $this->pastikanDalamTransaksi();

        // Satu-satunya jalan memesan dan memindah slot (pemohon maupun
        // petugas). Pengajuan yang batas approval-nya lewat tinggal arsip:
        // memesan lagi akan menyetel ulang batasnya dan menghidupkannya.
        if ($this->approvalKedaluwarsa($noPermit)) {
            throw new \DomainException('Batas waktu pengajuan ini sudah lewat. Silakan ajukan permit baru.');
        }

        $waktu = Carbon::parse($mulai);

        // Rentangnya mundur satu hari, bukan hanya tanggal slotnya sendiri.
        //
        // Jadwal yang menyeberang tengah malam (23:00-01:00 hari Senin) melahirkan
        // slot bertanggal SELASA, tetapi slot itu milik pola hari SENIN.
        // slotRentang(Selasa, Selasa) membangun slot dari pola hari Selasa saja,
        // sehingga slot dini hari yang barusan DITAWARKAN ke pemohon tidak
        // ditemukan lagi saat ia menekan pesan — dan yang muncul adalah "slot
        // tidak tersedia" untuk slot yang jelas-jelas ada di layarnya.
        //
        // Pencocokannya tetap persis pada (pos, waktu mulai), jadi melebarkan
        // rentang tidak pernah membuat slot lain ikut terpilih.
        $slot = collect($this->slotRentang($waktu->copy()->subDay(), $waktu))
            ->first(fn ($s) => $s['id_pos'] === $idPos && $s['mulai'] === $waktu->format('Y-m-d H:i'));

        if (! $slot) {
            throw new \DomainException('Slot inspeksi yang dipilih tidak tersedia.');
        }

        if ($waktu->lt(now()->addMinutes(self::jedaMinimumMenit()))) {
            throw new \DomainException('Slot inspeksi yang dipilih sudah lewat atau terlalu dekat.');
        }

        // Dihitung ulang DI DALAM kunci: dua pengaju tidak bisa sama-sama
        // mengambil kursi terakhir.
        $terisi = DB::table(self::T_BOOKING)
            ->where('Id_Pos_Inspeksi', $idPos)
            ->where('Waktu_Mulai', $waktu)
            ->where('Status', self::TERJADWAL)
            ->lockForUpdate()
            ->count();

        if ($terisi >= $slot['kuota']) {
            throw new \DomainException('Slot inspeksi yang dipilih sudah penuh. Pilih slot lain.');
        }

        $id = DB::table(self::T_BOOKING)->insertGetId([
            'No_Transaksi_Permit' => $noPermit,
            'Id_Pos_Inspeksi' => $idPos,
            'Id_Shift_Inspeksi' => $slot['id_shift'],
            // Salinan nama saat dipesan: riwayat tetap terbaca apa adanya
            // walau posnya kelak diganti nama.
            'Snapshot_Nama_Pos' => $slot['pos'],
            'Waktu_Mulai' => $waktu,
            'Waktu_Selesai' => Carbon::parse($slot['selesai']),
            'Status' => self::TERJADWAL,
            'Created_At' => now(),
            'Created_By' => $oleh,
        ]);

        $booking = DB::table(self::T_BOOKING)->where('Id_Inspeksi_Booking', $id)->first();

        // Akses palang sementara untuk menemui SHE, berlaku HANYA pada jam
        // slotnya. Diterbitkan di sini supaya satu-satunya jalan mendapatkannya
        // adalah benar-benar memesan slot — dan jadwal yang dipindah otomatis
        // menggeser jendelanya, karena pindah() bermuara ke pesan() juga.
        $this->efektif->terbitkanAksesInspeksi(
            $noPermit,
            Carbon::parse($booking->Waktu_Mulai),
            Carbon::parse($booking->Waktu_Selesai),
        );

        $this->setelBatasApproval($noPermit, Carbon::parse($booking->Waktu_Selesai));

        return $booking;
    }

    /**
     * Batas approval permit karyawan mengikuti jadwal inspeksinya: akhir hari
     * slot + hariTenggangPutus(), bukan approval_expire_limit sejak pengajuan.
     *
     * Permit karyawan diputus saat atau sesudah inspeksinya. Batas dari tanggal
     * pengajuan bisa jatuh SEBELUM slot (pemohon boleh memilih sampai
     * HARI_KE_DEPAN hari lagi) dan membuatnya mustahil disetujui (audit ulang
     * 28 Sep), atau jauh SESUDAH slot sehingga pengajuan yang jadwalnya lewat
     * menggantung terlalu lama. Karena itu batasnya DISETEL, maju maupun
     * mundur — jadwal yang dipindah ikut membawa batasnya.
     */
    private function setelBatasApproval(string $noPermit, CarbonInterface $selesaiSlot): void
    {
        // startOfSecond: kolom datetime SQL Server membulatkan .999999 ke
        // 00:00 hari berikutnya.
        $batas = Carbon::parse($selesaiSlot)->addDays(self::hariTenggangPutus())->endOfDay()->startOfSecond();

        DB::table('HRIS_Approval_Request')
            ->where('No_Transaksi', $noPermit)
            ->whereNull('Flag_Approval')
            ->whereNotNull('Expired_At')
            ->update(['Expired_At' => $batas, 'update_at' => now()]);
    }

    /**
     * Jatah jadwal ulang pemohon yang terpakai: pemindahan biasa DAN pemindahan
     * karena jadwal terlewat tanpa dicatat.
     *
     * Yang kedua dulu gratis. Karena setiap pemesanan menyetel ulang batas
     * approval ke H+N slot barunya, pemohon yang tak pernah datang — selama SHE
     * juga tak mencatatnya — bisa memindah terus dan permitnya tak pernah
     * kedaluwarsa (permintaan user 3 Okt 2026).
     */
    private static function jadwalUlangTerpakai(Collection $riwayat): int
    {
        return $riwayat->whereIn('Status', [self::DIPINDAH, self::KEDALUWARSA])->count();
    }

    /**
     * Jadwal ulang oleh pemohon: sampai H-1 (kecuali jadwalnya terlewat tanpa
     * dicatat) dan paling banyak MAKS_JADWAL_ULANG kali; setelah tidak hadir
     * selalu boleh (itu kewajiban, bukan kemurahan).
     */
    public function pindah(string $noPermit, int $idPos, string $mulai, string $oleh): object
    {
        return DB::transaction(function () use ($noPermit, $idPos, $mulai, $oleh) {
            // Dikunci DI SINI, bukan hanya diperiksa di controller: pemindahan yang
            // berlomba dengan pengguguran/keputusan di detik yang sama tidak boleh
            // memberi permit yang sudah diputus booking dan akses palang baru.
            $header = DB::table('N_EMI_VISITOR_Transaksi_Permit')
                ->where('No_Transaksi', $noPermit)
                ->where('Flag_Aktif', 'Y')
                ->lockForUpdate()
                ->first();

            if (! $header || $header->Flag_Approval !== null) {
                throw new \DomainException('Permit sudah diputus; jadwal inspeksinya tidak bisa diubah.');
            }

            $aktif = $this->aktif($noPermit, true);
            $riwayat = $this->riwayat($noPermit);

            if ($aktif) {
                // Slot yang jamnya sudah LEWAT dan tak seorang pun mencatat
                // hasilnya: pemohon tidak ikut terkunci oleh batas H-1, karena
                // sistem tidak tahu siapa yang absen — bisa pemohonnya, bisa SHE
                // yang belum sempat mencatat. Jatahnya tetap terpakai.
                $lewatJadwal = $this->lewatJadwal($aktif);

                if (! $lewatJadwal && Carbon::parse($aktif->Waktu_Mulai)->startOfDay()->lte(now()->startOfDay())) {
                    throw new \DomainException('Jadwal inspeksi hanya bisa dipindah paling lambat H-1.');
                }

                if (self::jadwalUlangTerpakai($riwayat) >= self::maksJadwalUlang()) {
                    throw new \DomainException('Jadwal inspeksi sudah dipindah '.self::maksJadwalUlang().' kali.');
                }

                DB::table(self::T_BOOKING)
                    ->where('Id_Inspeksi_Booking', $aktif->Id_Inspeksi_Booking)
                    ->update([
                        'Status' => $lewatJadwal ? self::KEDALUWARSA : self::DIPINDAH,
                        'Alasan' => $lewatJadwal ? 'Jadwal lewat tanpa tercatat; pemohon memilih jadwal baru.' : null,
                        'Updated_At' => now(),
                        'Updated_By' => $oleh,
                    ]);
            } elseif ($riwayat->first()?->Status !== self::TIDAK_HADIR) {
                throw new \DomainException('Permit ini tidak memiliki jadwal inspeksi yang bisa dipindah.');
            }

            return $this->pesan($noPermit, $idPos, $mulai, $oleh);
        });
    }

    /**
     * Pembatalan oleh pemohon: paling lambat H-1, sama seperti jadwal ulang.
     *
     * Yang ditutup hanya HARI jadwal inspeksi terakhirnya, apa pun status
     * bookingnya — hari itu SHE sudah menyiapkan pemeriksaannya. Jadwal yang
     * hari-nya sudah lewat tidak mengunci pemohon, sama seperti pindah().
     * Permit tanpa jadwal inspeksi tidak punya hari H.
     */
    public function bolehDibatalkanPemohon(string $noPermit): bool
    {
        $terakhir = $this->riwayat($noPermit)->first();

        return ! $terakhir || ! self::hariH($terakhir);
    }

    /** Langkah approval yang belum diputus sudah lewat Expired_At-nya. */
    public function approvalKedaluwarsa(string $noPermit): bool
    {
        return DB::table('HRIS_Approval_Request')
            ->where('No_Transaksi', $noPermit)
            ->whereNull('Flag_Approval')
            ->whereNotNull('Expired_At')
            ->where('Expired_At', '<', now())
            ->exists();
    }

    private static function hariH(object $booking): bool
    {
        return Carbon::parse($booking->Waktu_Mulai)->isSameDay(now());
    }

    /**
     * Jadwal ulang oleh petugas dari Agenda Inspeksi. Tanpa batas H-1 dan tanpa
     * memakan jatah pemohon, tetapi hanya selama permitnya belum diputus, dan
     * alasannya wajib — pemohon menerimanya di notifikasi.
     */
    public function pindahOlehPetugas(string $noPermit, int $idPos, string $mulai, string $oleh, string $alasan): object
    {
        $alasan = trim($alasan);
        if ($alasan === '') {
            throw new \DomainException('Alasan pemindahan jadwal wajib diisi.');
        }

        return DB::transaction(function () use ($noPermit, $idPos, $mulai, $oleh, $alasan) {
            $header = DB::table('N_EMI_VISITOR_Transaksi_Permit')
                ->where('No_Transaksi', $noPermit)
                ->where('Flag_Aktif', 'Y')
                ->lockForUpdate()
                ->first();

            if (! $header || $header->Flag_Approval !== null) {
                throw new \DomainException('Permit sudah diputus atau tidak ditemukan; jadwal inspeksinya tidak bisa dipindah.');
            }

            // Permit tamu (COKLAT) dan mitra tidak memakai slot inspeksi (audit M6).
            if (($header->No_Transaksi_Tamu ?? null) !== null
                || strtoupper((string) ($header->Sumber_Pengajuan ?? 'HCIS')) === 'API') {
                throw new \DomainException('Permit tamu dan mitra tidak memakai slot inspeksi; jadwalnya tidak bisa dipindah.');
            }

            $aktif = $this->aktif($noPermit, true);

            if ($aktif) {
                DB::table(self::T_BOOKING)
                    ->where('Id_Inspeksi_Booking', $aktif->Id_Inspeksi_Booking)
                    ->update([
                        'Status' => self::DIPINDAH_PETUGAS,
                        'Alasan' => mb_substr($alasan, 0, self::MAKS_ALASAN),
                        'Updated_At' => now(),
                        'Updated_By' => $oleh,
                    ]);
            } elseif ($this->riwayat($noPermit)->first()?->Status !== self::TIDAK_HADIR) {
                throw new \DomainException('Permit ini tidak memiliki jadwal inspeksi yang bisa dipindah.');
            }

            return $this->pesan($noPermit, $idPos, $mulai, $oleh);
        });
    }

    /**
     * SHE mencatat pemohon tidak hadir. Hanya setelah jam slotnya dimulai.
     *
     * @return array{gugur: bool, jumlah: int}
     */
    public function tidakHadir(string $noPermit, string $oleh): array
    {
        return DB::transaction(function () use ($noPermit, $oleh) {
            $aktif = $this->aktif($noPermit, true);

            if (! $aktif) {
                throw new \DomainException('Permit ini tidak memiliki jadwal inspeksi aktif.');
            }

            if (Carbon::parse($aktif->Waktu_Mulai)->gt(now())) {
                throw new \DomainException('Ketidakhadiran baru bisa dicatat setelah jam inspeksinya dimulai.');
            }

            DB::table(self::T_BOOKING)
                ->where('Id_Inspeksi_Booking', $aktif->Id_Inspeksi_Booking)
                ->update(['Status' => self::TIDAK_HADIR, 'Updated_At' => now(), 'Updated_By' => $oleh]);

            $jumlah = DB::table(self::T_BOOKING)
                ->where('No_Transaksi_Permit', $noPermit)
                ->where('Status', self::TIDAK_HADIR)
                ->count();

            $gugur = $jumlah >= self::maksTidakHadir();

            if ($gugur) {
                $this->gugurkanPermit($noPermit, $oleh, $jumlah);
            }

            return ['gugur' => $gugur, 'jumlah' => $jumlah];
        });
    }

    /**
     * Penjaga approve permit: persetujuan sekaligus menandai inspeksi SELESAI.
     * Permit tanpa booking sama sekali (pengajuan lama) tidak disentuh.
     * WAJIB di dalam transaksi approve.
     *
     * Boleh SEBELUM jam slotnya (permintaan user 29 Sep 2026): pemohon yang
     * datang lebih awal dan sudah diperiksa tidak harus menunggu jamnya tiba.
     * Kursi slotnya ikut lepas, karena kuota hanya menghitung TERJADWAL.
     * Layar review yang memperingatkan verifikator bahwa jadwalnya belum tiba.
     */
    public function tandaiSelesaiSaatApprove(string $noPermit, string $approver): void
    {
        $this->pastikanDalamTransaksi();

        $semua = $this->riwayat($noPermit);

        if ($semua->isEmpty() || $semua->contains('Status', self::SELESAI)) {
            return;
        }

        $aktif = $this->aktif($noPermit, true);

        if (! $aktif) {
            throw new \DomainException('Permit ini belum memiliki jadwal inspeksi aktif; pemohon wajib memilih slot baru.');
        }

        if ($this->lewatHariInspeksi($aktif)) {
            throw new \DomainException('Jadwal inspeksi permit ini sudah terlewat tanggal tanpa hasil pemeriksaan. Pemohon wajib memilih jadwal baru sebelum pengajuan dapat disetujui.');
        }

        DB::table(self::T_BOOKING)
            ->where('Id_Inspeksi_Booking', $aktif->Id_Inspeksi_Booking)
            ->update([
                'Status' => self::SELESAI,
                'Diinspeksi_Oleh' => $approver,
                'Diinspeksi_At' => now(),
                'Updated_At' => now(),
                'Updated_By' => $approver,
            ]);
    }

    /** Lepas slot aktif (penolakan / pembatalan pemohon). Aman bila tak ada. */
    /**
     * Hasil inspeksi kedatangan tamu B2B — dicatat, bukan dijadwalkan.
     *
     * Permit COKLAT tamu B2B sengaja tidak pernah memesan slot: jadwalnya
     * adalah jadwal KUNJUNGAN tamunya, dan tim SHE yang mendatangi pos gerbang
     * pada jam itu. Karena itu barisnya lahir LANGSUNG dalam keadaan final
     * (SELESAI / TIDAK_HADIR) dan tidak pernah melewati TERJADWAL.
     *
     * Dua akibat yang disengaja, dan keduanya bergantung pada hal yang sama —
     * kuota hanya menghitung baris TERJADWAL:
     *
     *  - Kedatangan tamu TIDAK memakan kursi slot milik permit karyawan. Tamu
     *    B2B wajib dilayani apa pun keadaannya, jadi ia tak boleh tertolak
     *    karena posnya "penuh", dan sebaliknya ia tak boleh menutup kursi yang
     *    memang dijual ke pemohon lain.
     *  - Indeks unik `UX_VISITOR_Inspeksi_Booking_Aktif` (satu TERJADWAL per
     *    permit) tidak pernah tersentuh, sehingga pencatatan ini tidak bisa
     *    bertabrakan dengan booking mana pun.
     *
     * Ketidakhadiran tamu juga TIDAK menggugurkan permitnya — berbeda dari
     * pemohon karyawan yang mangkir dari slot yang ia pilih sendiri. Yang tidak
     * datang di sini adalah tamunya, dan itu bukan kelalaian pemegang permit.
     */
    /** Masuk tercatat di palang (baris efektif) atau lewat catatan manual. */
    private function tamuSudahMasuk(string $noTransaksiTamu): bool
    {
        $kehadiran = app(\App\Services\VISITOR\Guest\Visit\VisitorKehadiranQueryService::class);

        return $kehadiran->join(
            DB::table('N_EMI_VISITOR_Transaksi_Tamu as h')
                ->join('N_EMI_VISITOR_Transaksi_Tamu_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
                ->where('h.No_Transaksi', $noTransaksiTamu)
        )
            ->whereRaw($kehadiran->masukSql().' IS NOT NULL')
            ->exists();
    }

    public function catatKedatanganTamuB2b(
        string $noPermit,
        string $status,
        CarbonInterface $mulai,
        CarbonInterface $selesai,
        string $oleh,
        ?string $catatan = null,
    ): object {
        if (! in_array($status, [self::SELESAI, self::TIDAK_HADIR], true)) {
            throw new \DomainException('Hasil inspeksi tamu hanya boleh SELESAI atau TIDAK_HADIR.');
        }

        $permit = DB::table('N_EMI_VISITOR_Transaksi_Permit')
            ->where('No_Transaksi', $noPermit)
            ->where('Flag_Aktif', 'Y')
            ->first();

        if (! $permit) {
            throw new \DomainException('Permit tamu B2B tidak ditemukan atau sudah tidak aktif.');
        }

        if ($permit->Flag_Approval === 'T') {
            throw new \DomainException('Permit tamu ini sudah ditolak; tidak ada inspeksi yang perlu dicatat.');
        }

        /*
         * SELESAI hanya setelah reviewnya tuntas.
         *
         * Urutan sebenarnya di lapangan: permit COKLAT diputus verifikator
         * lebih dulu — itulah yang memberi tamunya akses gerbang — baru tim SHE
         * memeriksa kendaraannya saat tamu tiba. Membolehkan "Selesai" pada
         * permit yang belum diputus menutup pekerjaan SHE di atas pengajuan
         * yang masih menggantung: agendanya bersih, meja verifikator tidak, dan
         * tak ada satu pun layar yang menunjukkan pasangan itu.
         *
         * TIDAK_HADIR sengaja TIDAK ikut aturan ini. Tamu yang tidak datang
         * justru harus bisa dicatat kapan pun, termasuk selagi permitnya
         * menunggu — itu satu-satunya cara menutup barisnya.
         */
        if ($status === self::SELESAI && $permit->Flag_Approval !== 'Y') {
            throw new \DomainException('Inspeksi baru bisa diselesaikan setelah permit tamu ini selesai direview.');
        }

        // Tamu yang sudah tercatat lewat palang jelas hadir — "tidak hadir"
        // hanya akan menimpa kehadiran yang nyata (30 Sep 2026).
        if ($status === self::TIDAK_HADIR && $permit->No_Transaksi_Tamu && $this->tamuSudahMasuk($permit->No_Transaksi_Tamu)) {
            throw new \DomainException('Tamu ini sudah tercatat masuk gerbang; tidak bisa ditandai tidak hadir.');
        }

        $idPos = self::posTamuB2b();

        if ($idPos < 1) {
            throw new \DomainException('Pos inspeksi untuk tamu B2B belum disetel (services.visitor.permit.pos_tamu_b2b).');
        }

        $pos = DB::table(self::T_POS)->where('Id_Pos_Inspeksi', $idPos)->first();

        if (! $pos || $pos->Flag_Aktif !== 'Y') {
            throw new \DomainException('Pos inspeksi tamu B2B yang disetel tidak ada atau sudah nonaktif.');
        }

        return DB::transaction(function () use ($noPermit, $status, $mulai, $selesai, $oleh, $catatan, $idPos, $pos) {
            $isi = [
                'Id_Pos_Inspeksi' => $idPos,
                'Snapshot_Nama_Pos' => $pos->Nama_Pos,
                'Waktu_Mulai' => Carbon::parse($mulai),
                'Waktu_Selesai' => Carbon::parse($selesai),
                'Status' => $status,
                'Alasan' => $catatan !== null ? mb_substr($catatan, 0, self::MAKS_ALASAN) : null,
                'Diinspeksi_Oleh' => $oleh,
                'Diinspeksi_At' => now(),
                'Updated_At' => now(),
                'Updated_By' => $oleh,
            ];

            /* Hasil yang sudah pernah dicatat DIPERBAIKI, bukan ditumpuk:
               petugas yang salah menekan "Tidak Hadir" harus bisa membetulkannya
               tanpa meninggalkan dua kenyataan pada satu kedatangan. */
            $ada = DB::table(self::T_BOOKING)
                ->where('No_Transaksi_Permit', $noPermit)
                ->whereIn('Status', [self::SELESAI, self::TIDAK_HADIR])
                ->lockForUpdate()
                ->orderByDesc('Id_Inspeksi_Booking')
                ->first();

            if ($ada) {
                DB::table(self::T_BOOKING)->where('Id_Inspeksi_Booking', $ada->Id_Inspeksi_Booking)->update($isi);
                $id = (int) $ada->Id_Inspeksi_Booking;
            } else {
                $id = (int) DB::table(self::T_BOOKING)->insertGetId($isi + [
                    'No_Transaksi_Permit' => $noPermit,
                    'Created_At' => now(),
                    'Created_By' => $oleh,
                ]);
            }

            return DB::table(self::T_BOOKING)->where('Id_Inspeksi_Booking', $id)->first();
        });
    }

    /**
     * Inspeksi kedatangan tamu B2B ditutup oleh KEPUTUSAN permitnya.
     *
     * Sejalan dengan permit karyawan, yang inspeksinya ditandai selesai saat
     * verifikator menyetujuinya (`tandaiSelesaiSaatApprove`). Agenda SHE karena
     * itu tidak punya tombol "Selesai" sendiri: satu-satunya jalan menutup
     * baris tamu adalah menuntaskan reviewnya, sehingga mustahil ada inspeksi
     * yang tercatat selesai di atas pengajuan yang masih menggantung.
     *
     * Mengembalikan null bila memang tidak ada yang bisa dicatat — posnya belum
     * disetel, atau permit ini bukan turunan kunjungan tamu. Approval TIDAK
     * boleh gagal karena setelan yang belum diisi.
     */
    public function tandaiSelesaiKedatanganTamu(string $noPermit, string $approver): ?object
    {
        if (self::posTamuB2b() < 1) {
            Log::warning('Pos inspeksi tamu B2B belum disetel; kedatangan tamu tidak dicatat.', [
                'no_transaksi' => $noPermit,
            ]);

            return null;
        }

        $jadwal = DB::table('N_EMI_VISITOR_Transaksi_Permit as p')
            ->join('N_EMI_VISITOR_Transaksi_Tamu as h', 'h.No_Transaksi', '=', 'p.No_Transaksi_Tamu')
            ->join('N_EMI_VISITOR_Transaksi_Tamu_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->where('p.No_Transaksi', $noPermit)
            ->where('p.Flag_Aktif', 'Y')
            ->selectRaw($this->kehadiran->estimasiMasukSql().' as Mulai, '.$this->kehadiran->estimasiKeluarSql().' as Selesai')
            ->first();

        if (! $jadwal) {
            return null;
        }

        return $this->catatKedatanganTamuB2b(
            $noPermit,
            self::SELESAI,
            Carbon::parse($jadwal->Mulai),
            Carbon::parse($jadwal->Selesai),
            $approver,
        );
    }

    public function batalkan(string $noPermit, ?string $alasan, string $oleh): void
    {
        DB::table(self::T_BOOKING)
            ->where('No_Transaksi_Permit', $noPermit)
            ->where('Status', self::TERJADWAL)
            ->update([
                'Status' => self::DIBATALKAN,
                'Alasan' => $alasan !== null ? mb_substr($alasan, 0, 250) : null,
                'Updated_At' => now(),
                'Updated_By' => $oleh,
            ]);
    }

    /**
     * Booking yang jam mulainya sudah lewat tetapi hasilnya belum dicatat.
     *
     * SENGAJA dihitung, bukan disimpan: tidak ada penjadwal yang mengubah
     * status di belakang layar, sehingga keputusan tetap milik manusia —
     * SHE mencatat hadir/tidak hadir, verifikator memutus permitnya.
     */
    public function lewatJadwal(object $booking): bool
    {
        return $booking->Status === self::TERJADWAL
            && Carbon::parse($booking->Waktu_Mulai)->lt(now());
    }

    /**
     * Booking yang tanggal inspeksinya sudah lewat (hari H sudah berakhir)
     * tetapi hasilnya belum pernah dicatat oleh SHE.
     */
    public function lewatHariInspeksi(object $booking): bool
    {
        return $booking->Status === self::TERJADWAL
            && Carbon::parse($booking->Waktu_Mulai)->endOfDay()->lt(now());
    }

    /**
     * Semua booking yang lewat jadwal dan belum dicatat, lintas tanggal.
     *
     * Agenda SHE bekerja per tanggal; tanpa daftar ini, yang terlewat kemarin
     * hilang dari pandangan justru ketika ia paling perlu ditindak.
     */
    public function belumDicatat(): Collection
    {
        return DB::table(self::T_BOOKING.' as b')
            ->join('N_EMI_VISITOR_Transaksi_Permit as h', 'h.No_Transaksi', '=', 'b.No_Transaksi_Permit')
            ->where('b.Status', self::TERJADWAL)
            ->where('b.Waktu_Mulai', '<', now())
            ->whereNull('h.Flag_Approval')
            ->where('h.Flag_Aktif', 'Y')
            // Yang batas approval-nya lewat tinggal arsip; penyapu membatalkan
            // bookingnya paling lambat lima menit kemudian.
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('HRIS_Approval_Request as ar')
                ->whereColumn('ar.No_Transaksi', 'h.No_Transaksi')
                ->whereNull('ar.Flag_Approval')
                ->whereNotNull('ar.Expired_At')
                ->where('ar.Expired_At', '<', now()))
            ->orderBy('b.Waktu_Mulai')
            ->get();
    }

    public function aktif(string $noPermit, bool $kunci = false): ?object
    {
        return DB::table(self::T_BOOKING)
            ->where('No_Transaksi_Permit', $noPermit)
            ->where('Status', self::TERJADWAL)
            ->when($kunci, fn ($q) => $q->lockForUpdate())
            ->first();
    }

    /** Riwayat booking permit, terbaru dulu. */
    public function riwayat(string $noPermit): Collection
    {
        return DB::table(self::T_BOOKING)
            ->where('No_Transaksi_Permit', $noPermit)
            ->orderByDesc('Id_Inspeksi_Booking')
            ->get();
    }

    /**
     * Ringkasan untuk layar pemohon & meja: jadwal aktif/terakhir dan sisa hak.
     *
     * @return array<string, mixed>|null
     */
    public function ringkasan(string $noPermit): ?array
    {
        $riwayat = $this->riwayat($noPermit);

        if ($riwayat->isEmpty()) {
            return null;
        }

        $terakhir = $riwayat->first();
        $dipindah = self::jadwalUlangTerpakai($riwayat);
        $tidakHadir = $riwayat->where('Status', self::TIDAK_HADIR)->count();
        $gugur = $tidakHadir >= self::maksTidakHadir();
        $mulai = Carbon::parse($terakhir->Waktu_Mulai);
        // Pengajuan kedaluwarsa tinggal arsip: tidak ada aksi yang ditawarkan.
        $kedaluwarsa = $this->approvalKedaluwarsa($noPermit);
        $lewatJadwal = ! $kedaluwarsa && $this->lewatJadwal($terakhir);

        return [
            'status' => $terakhir->Status,
            'id_pos' => (int) $terakhir->Id_Pos_Inspeksi,
            'shift' => $terakhir->Id_Shift_Inspeksi !== null
                ? DB::table(self::T_SHIFT)->where('Id_Shift_Inspeksi', $terakhir->Id_Shift_Inspeksi)->value('Nama_Shift')
                : null,
            'pos' => $terakhir->Snapshot_Nama_Pos,
            'lokasi' => DB::table(self::T_POS)->where('Id_Pos_Inspeksi', $terakhir->Id_Pos_Inspeksi)->value('Keterangan_Lokasi'),
            'mulai' => $mulai->format('Y-m-d H:i'),
            'selesai' => Carbon::parse($terakhir->Waktu_Selesai)->format('Y-m-d H:i'),
            'jadwal_ulang_terpakai' => $dipindah,
            // Terpisah dari jatah pemohon: pemindahan petugas tidak memakan MAKS_JADWAL_ULANG.
            'dipindah_petugas' => $riwayat->where('Status', self::DIPINDAH_PETUGAS)->count(),
            'tidak_hadir' => $tidakHadir,
            'gugur' => $gugur,
            // Jadwal terlewat tanpa catatan: keadaannya jujur di layar pemohon,
            // dan pintunya dibuka supaya ia tidak terkunci menunggu orang lain.
            'lewat_jadwal' => $lewatJadwal,
            'kedaluwarsa' => $kedaluwarsa,
            'boleh_pindah' => ! $kedaluwarsa && (($terakhir->Status === self::TERJADWAL
                    && $mulai->copy()->startOfDay()->gt(now()->startOfDay())
                    && $dipindah < self::maksJadwalUlang())
                || ($lewatJadwal && $dipindah < self::maksJadwalUlang())
                || ($terakhir->Status === self::TIDAK_HADIR && ! $gugur)),
            'wajib_pilih_ulang' => ! $kedaluwarsa && $terakhir->Status === self::TIDAK_HADIR && ! $gugur,
            'boleh_batal' => ! $kedaluwarsa && ! self::hariH($terakhir),
            'diinspeksi_oleh' => $terakhir->Diinspeksi_Oleh,
            'diinspeksi_at' => $terakhir->Diinspeksi_At,
        ];
    }

    /** Tutup pengajuan permit: langkah approval yang tersisa ditutup bersebab. */
    private function gugurkanPermit(string $noPermit, string $oleh, int $jumlah): void
    {
        $catatan = "Gugur otomatis: {$jumlah} kali tidak hadir inspeksi kendaraan.";

        DB::table('N_EMI_VISITOR_Transaksi_Permit')
            ->where('No_Transaksi', $noPermit)
            ->whereNull('Flag_Approval')
            ->update(['Flag_Approval' => 'T', 'Updated_At' => now(), 'Updated_By' => $oleh]);

        DB::table('HRIS_Approval_Request')
            ->where('No_Transaksi', $noPermit)
            ->whereNull('Flag_Approval')
            ->update([
                'Flag_Approval' => 'T',
                'Catatan' => $catatan,
                'Tanggal_Approval' => now(),
                'update_at' => now(),
            ]);

        // Akses palang sementara ke pos inspeksi ikut berakhir sekarang juga.
        $this->efektif->akhiriAksesInspeksi($noPermit);
    }

    private function kunciSlot(int $idPos, CarbonInterface $mulai): string
    {
        return $idPos.'|'.Carbon::parse($mulai)->format('Y-m-d H:i');
    }

    private function pastikanDalamTransaksi(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Penulisan booking inspeksi wajib di dalam transaksi pemanggil.');
        }
    }
}
