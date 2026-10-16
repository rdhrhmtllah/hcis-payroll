<?php

namespace App\Services\VISITOR\Permit\Inspection;

use App\Services\SYSTEM\MasterData\KontakBantuan\KontakBantuanService;
use Illuminate\Support\Carbon;

/**
 * Kontak orang yang ditunggu di pos inspeksi, siap dihubungi admin SHE.
 *
 * Permintaan user 29 Sep 2026: SHE yang bertugas harus bisa langsung
 * menghubungi pemohon yang terlambat atau tidak datang ke jadwal inspeksi.
 *
 * - Permit karyawan (HIJAU/MERAH): pemohonnya sendiri yang membawa kendaraan.
 * - Permit tamu (COKLAT): tamunya (yang datang ke gerbang) lebih dulu, lalu
 *   karyawan PIC/pengajunya sebagai cadangan bila tamunya tak bisa dihubungi.
 *
 * Bentuk tiap kontak sama dengan kontak bantuan (KontakBantuanDaftar), jadi
 * kartunya dirender komponen yang sama. Tautan WhatsApp membawa pesan yang
 * sudah terisi (nama, plat, pos, jam) — admin tinggal menekan kirim.
 */
class KontakPemohonInspeksiService
{
    /**
     * @param  array{nama?: ?string, no_hp?: ?string}  $pemohon  pemohon permit karyawan
     * @param  array{nama?: ?string, no_hp?: ?string}|null  $tamu  tamu (permit COKLAT)
     * @param  array{nama?: ?string, no_hp?: ?string}|null  $pic  karyawan PIC/pengaju tamu
     * @param  array{plat?: ?string, pos?: ?string, mulai?: ?string}  $jadwal
     * @return list<array{nama: string, keterangan: string, no_hp: string, email: null, jam_layanan: null,
     *                    tautan_wa: ?string, tautan_telepon: ?string, tautan_email: null}>
     */
    public function susun(array $pemohon = [], ?array $tamu = null, ?array $pic = null, array $jadwal = []): array
    {
        $kontak = [];

        if ($tamu !== null) {
            $kontak[] = $this->satu($tamu, 'Tamu · yang datang ke pos', $jadwal);
            $kontak[] = $this->satu($pic ?? [], 'Karyawan PIC / pengaju', $jadwal, sebagaiPic: true, namaTamu: $tamu['nama'] ?? null);
        } else {
            $kontak[] = $this->satu($pemohon, 'Pemohon · pembawa kendaraan', $jadwal);
        }

        return array_values(array_filter($kontak));
    }

    /**
     * Kontak dari baris VisitorPermitQueryService::basePermitQuery() — untuk
     * modal review/detail permit. $jadwal = ringkasan JadwalInspeksiService
     * (pos, mulai) bila ada; permit tamu tidak memakai slot.
     */
    public function untukPermit(object $permit, ?array $jadwal = null): array
    {
        $rincianJadwal = [
            'plat' => $permit->No_Plat_Kendaraan ?? null,
            'pos' => $jadwal['pos'] ?? null,
            'mulai' => $jadwal['mulai'] ?? null,
        ];

        if (($permit->Permit_Untuk_Tamu ?? 'T') === 'Y') {
            return $this->susun(
                tamu: ['nama' => $permit->Nama_Pemohon_Tampil ?? null, 'no_hp' => $permit->No_HP_Tamu ?? null],
                pic: ['nama' => $permit->Nama_Pemohon ?? null, 'no_hp' => $permit->No_HP_Pemohon ?? null],
                jadwal: $rincianJadwal,
            );
        }

        return $this->susun(
            ['nama' => $permit->Nama_Pemohon ?? null, 'no_hp' => $permit->No_HP_Pemohon ?? null],
            jadwal: $rincianJadwal,
        );
    }

    /** Pesan pembuka WhatsApp: siapa, kendaraan apa, di mana, jam berapa. */
    public function pesan(string $nama, array $jadwal, bool $sebagaiPic = false, ?string $namaTamu = null): string
    {
        $plat = trim((string) ($jadwal['plat'] ?? ''));
        $pos = trim((string) ($jadwal['pos'] ?? ''));
        $waktu = $this->waktu($jadwal['mulai'] ?? null);

        $rincian = trim(implode(' ', array_filter([
            $plat !== '' && $plat !== '-' ? "kendaraan {$plat}" : 'kendaraan',
            $pos !== '' ? "di {$pos}" : '',
            $waktu !== '' ? $waktu : '',
        ])));

        if ($sebagaiPic) {
            $siapa = $namaTamu ? "tamu Anda, {$namaTamu}," : 'tamu Anda';

            return "Halo {$nama}, kami dari SHE. Jadwal inspeksi {$rincian} untuk {$siapa} belum terlihat di pos. "
                .'Mohon bantu konfirmasi apakah tamu sudah dalam perjalanan. Terima kasih.';
        }

        return "Halo {$nama}, kami dari SHE. Jadwal inspeksi {$rincian} belum terlihat di pos. "
            .'Apakah Anda sudah dalam perjalanan? Terima kasih.';
    }

    private function satu(array $orang, string $peran, array $jadwal, bool $sebagaiPic = false, ?string $namaTamu = null): ?array
    {
        $nama = trim((string) ($orang['nama'] ?? ''));
        $hp = trim((string) ($orang['no_hp'] ?? ''));
        if ($nama === '' || $hp === '' || $hp === '-') {
            return null;
        }

        $nomor = KontakBantuanService::nomorInternasional($hp);
        if (! $nomor) {
            return null;
        }

        return [
            'nama' => $nama,
            'keterangan' => $peran,
            'no_hp' => $hp,
            'email' => null,
            'jam_layanan' => null,
            'tautan_wa' => 'https://wa.me/'.$nomor.'?text='.rawurlencode($this->pesan($nama, $jadwal, $sebagaiPic, $namaTamu)),
            'tautan_telepon' => 'tel:+'.$nomor,
            'tautan_email' => null,
        ];
    }

    /** "hari ini pukul 08.00 WIB" / "Senin, 5 Okt pukul 08.00 WIB". */
    private function waktu(?string $mulai): string
    {
        if (! $mulai) {
            return '';
        }

        try {
            $t = Carbon::parse($mulai);
        } catch (\Throwable) {
            return '';
        }

        $jam = 'pukul '.$t->format('H.i').' WIB';
        if ($t->isToday()) {
            return 'hari ini '.$jam;
        }

        return $t->locale('id')->translatedFormat('l, j M').' '.$jam;
    }
}
