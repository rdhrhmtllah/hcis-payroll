<?php

namespace App\Services\LMS\SOP\Support;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SopAssignmentRuleConfig
{
    public static function normalize(array $input): array
    {
        $groups = self::normalizeGroups(
            (array) ($input['groups'] ?? []),
            'groups',
            'Grup target',
            'akan menyasar SELURUH karyawan aktif',
        );

        if (! $groups) {
            throw ValidationException::withMessages(['groups' => 'Minimal satu grup target diperlukan.']);
        }

        /*
         * Pengecualian berbasis kriteria. Sebelum ini satu-satunya cara
         * mengecualikan adalah mengetik NIK satu per satu — "seluruh Finance
         * kecuali level Manager" harus dibongkar jadi daftar nama, dan daftar
         * itu langsung basi begitu ada yang naik jabatan.
         *
         * Boleh kosong, berbeda dengan groups: aturan tanpa pengecualian adalah
         * keadaan yang wajar.
         */
        $excludeGroups = self::normalizeGroups(
            (array) ($input['exclude_groups'] ?? []),
            'exclude_groups',
            'Grup pengecualian',
            'akan MENGECUALIKAN SELURUH karyawan aktif, sehingga aturan ini tak akan pernah menugaskan siapa pun',
        );

        return [
            'groups' => $groups,
            'exclude_groups' => $excludeGroups,
            'include_karyawan' => self::strings($input['include_karyawan'] ?? []),
            'exclude_karyawan' => self::strings($input['exclude_karyawan'] ?? []),
            'schedule' => self::normalizeSchedule((array) ($input['schedule'] ?? [])),
        ];
    }

    /**
     * Bentuk dan periksa sekumpulan grup kriteria.
     *
     * Dipakai grup target DAN grup pengecualian, dengan penjaga yang sama.
     * Grup tanpa satu pun saringan berarti "seluruh karyawan aktif" —
     * blankGroup() di layar persis berbentuk demikian, sehingga satu klik tak
     * sengaja bisa menyasar seluruh headcount. Pada grup pengecualian akibatnya
     * berlawanan tetapi sama fatalnya: aturan yang tampak aktif namun tak pernah
     * menugaskan siapa pun.
     *
     * Menuntut minimal satu kriteria memaksa cakupan itu dinyatakan dengan
     * sengaja, misalnya lewat tipe pekerja 'Karyawan' atau 'PHL'.
     *
     * @param  string  $kunci  awalan kunci galat, mis. 'groups' atau 'exclude_groups'
     * @param  string  $label  sebutan grup di pesan galat
     * @param  string  $akibat  kalimat akibat bila grup dibiarkan kosong
     */
    public static function normalizeGroups(array $raw, string $kunci, string $label, string $akibat): array
    {
        $groups = collect($raw)->map(function ($group) {
            $group = (array) $group;
            $workerType = $group['tipe'] ?? 'Semua';

            return [
                'divisi' => self::strings($group['divisi'] ?? []),
                'dept' => self::strings($group['dept'] ?? []),
                'level' => self::strings($group['level'] ?? []),
                'jabatan' => self::strings($group['jabatan'] ?? []),
                'lokasi' => self::strings($group['lokasi'] ?? []),
                'tipe' => in_array($workerType, ['Semua', 'Karyawan', 'PHL'], true)
                    ? $workerType : 'Semua',
            ];
        })->values()->all();

        foreach ($groups as $i => $group) {
            $adaSaringan = $group['divisi'] || $group['dept'] || $group['level']
                || $group['jabatan'] || $group['lokasi'] || $group['tipe'] !== 'Semua';

            if (! $adaSaringan) {
                throw ValidationException::withMessages([
                    "{$kunci}.{$i}" => $label.' ke-'.($i + 1).' belum memiliki kriteria apa pun, sehingga '
                        .$akibat.'. Pilih minimal satu saringan (divisi, departemen, level, jabatan, '
                        .'lokasi) atau tetapkan tipe pekerja.',
                ]);
            }
        }

        return $groups;
    }

    /**
     * Normalisasi satu blok jadwal.
     *
     * Publik karena dipakai DUA pemanggil: setelan induk aturan lewat
     * normalize(), dan setelan override per paket yang tinggal di tabel
     * jembatan N_SOP_Assignment_Rule_Paket. Satu aturan validasi untuk
     * keduanya — kalau dipisah, override bisa menerima tenggat yang ditolak
     * induknya, dan yang lolos justru yang paling jarang diperiksa orang.
     */
    public static function normalizeSchedule(array $schedule): array
    {
        $mode = strtoupper((string) ($schedule['deadline_mode'] ?? 'NONE'));
        if (! in_array($mode, ['NONE', 'RELATIVE', 'ABSOLUTE'], true)) {
            throw ValidationException::withMessages(['schedule.deadline_mode' => 'Mode tenggat tidak valid.']);
        }

        $days = $mode === 'RELATIVE' ? (int) ($schedule['deadline_days'] ?? 0) : null;
        if ($mode === 'RELATIVE' && ($days < 1 || $days > 3650)) {
            throw ValidationException::withMessages(['schedule.deadline_days' => 'Tenggat relatif harus 1-3650 hari.']);
        }

        $date = $mode === 'ABSOLUTE' ? ($schedule['deadline_date'] ?? null) : null;
        if ($mode === 'ABSOLUTE') {
            if (! $date) {
                throw ValidationException::withMessages(['schedule.deadline_date' => 'Tanggal tenggat absolut wajib diisi.']);
            }
            try {
                $date = Carbon::parse($date)->format('Y-m-d');
            } catch (\Throwable) {
                throw ValidationException::withMessages(['schedule.deadline_date' => 'Tanggal tenggat absolut tidak valid.']);
            }
        }

        $reminder = (bool) ($schedule['reminder_enabled'] ?? false);
        $interval = $reminder ? (int) ($schedule['reminder_interval_days'] ?? 0) : null;
        if ($reminder && ($interval < 1 || $interval > 365)) {
            throw ValidationException::withMessages(['schedule.reminder_interval_days' => 'Interval reminder harus 1-365 hari.']);
        }

        return [
            'deadline_mode' => $mode,
            'deadline_days' => $days,
            'deadline_date' => $date,
            'reminder_enabled' => $reminder,
            'reminder_interval_days' => $interval,
        ];
    }

    /**
     * Bentuk deterministik daftar paket sebuah aturan, untuk ikut dihitung ke
     * dalam hash.
     *
     * Diurutkan menurut Id_SOP_Paket, BUKAN urutan baris yang dikembalikan
     * basis data: tanpa ORDER BY yang eksplisit, dua pembacaan atas isi yang
     * sama bisa menghasilkan hash berbeda, dan lencana "draft berubah" menyala
     * tanpa ada yang berubah.
     *
     * @param  array<int,object>  $rows  baris N_SOP_Assignment_Rule_Paket
     */
    public static function snapshotPaket(array $rows): array
    {
        $snapshot = array_map(function ($row) {
            $override = ($row->Flag_Override_Jadwal ?? 'T') === 'Y';

            return [
                'id' => (int) $row->Id_SOP_Paket,
                'urutan' => (int) ($row->Urutan ?? 1),
                'override' => $override,
                'schedule' => $override ? [
                    'deadline_mode' => $row->Deadline_Mode ?? 'NONE',
                    'deadline_days' => isset($row->Deadline_Hari) ? (int) $row->Deadline_Hari : null,
                    'deadline_date' => $row->Tanggal_Deadline ?? null,
                    'reminder_enabled' => ($row->Flag_Reminder ?? 'T') === 'Y',
                    'reminder_interval_days' => isset($row->Irama_Reminder_Hari) ? (int) $row->Irama_Reminder_Hari : null,
                ] : null,
            ];
        }, $rows);

        usort($snapshot, fn ($a, $b) => $a['id'] <=> $b['id']);

        return $snapshot;
    }

    public static function encode(array $config): string
    {
        return json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Hash config berikut daftar paketnya.
     *
     * Daftar paket tinggal di tabel jembatan, di luar config JSON. Tanpa ikut
     * dihitung di sini, menambah atau membuang paket tak akan mengubah
     * Draft_Hash — dan lencana "draft berubah" di layar, yang membandingkan
     * Draft_Hash dengan Active_Hash, akan berbohong tepat pada perubahan yang
     * paling besar akibatnya.
     */
    public static function hash(array $config, array $snapshotPaket = []): string
    {
        return hash('sha256', self::encode($config).'|'.self::encode($snapshotPaket));
    }

    private static function strings(array $values): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($value) => trim((string) $value),
            $values,
        ), fn ($value) => $value !== '')));
    }
}
