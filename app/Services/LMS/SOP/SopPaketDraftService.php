<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopAksesBerkas;
use App\Services\LMS\SOP\Support\SopGerbangPublikKuis;
use App\Services\LMS\SOP\Support\SopNomorVersi;
use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use App\Services\SYSTEM\Application\Database\PenyisipanBerbatasParameter;
use App\Services\SYSTEM\Application\Database\SkemaTersedia;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Vinkla\Hashids\Facades\Hashids;

/** Working copy terisolasi untuk revisi paket yang sudah PUBLISHED. */
class SopPaketDraftService
{
    private const HEADER = 'N_SOP_Paket_Draft';

    private const SECTION = 'N_SOP_Paket_Draft_Section';

    private const ITEM = 'N_SOP_Paket_Draft_Item';

    private const ITEM_VERSION = 'N_SOP_Paket_Draft_Item_Versi';

    /* Keempat tabel draft punya kolom Row_Version (rowversion = 8 byte biner mentah).
       SELECT * ikut menariknya, dan json_encode() menolaknya dengan "Malformed UTF-8
       characters" sehingga endpoint balas 500. Kolom itu tak pernah dibaca kode mana pun,
       jadi daftar kolom di bawah sengaja eksplisit tanpa Row_Version. */
    private const SECTION_COLUMNS = ['Id_SOP_Draft_Section', 'Id_SOP_Paket_Draft', 'Id_SOP_Section_Asal',
        'Nama_Section', 'Deskripsi', 'Urutan', 'Flag_Aktif', 'Created_By', 'Created_At', 'Updated_By', 'Updated_At'];

    private const ITEM_COLUMNS = ['Id_SOP_Draft_Item', 'Id_SOP_Paket_Draft', 'Id_SOP_Paket_Item_Asal',
        'Id_SOP_Draft_Section', 'Id_SOP_Item_Versi_Target', 'Tipe_Item', 'Judul_Item', 'Nomor_SOP_Resmi',
        'Deskripsi_Item', 'Konten_Teks', 'Mode_Akses', 'URL_Tautan', 'Label_Tautan', 'Jenis_Quiz',
        'Nilai_Minimum', 'Id_Master_Ujian', 'Nama_Ujian', 'Flag_Camera', 'Urutan', 'Flag_Wajib',
        'Flag_Highlight', 'Flag_Watermark', 'Flag_Aktif', 'Created_By', 'Created_At', 'Updated_By', 'Updated_At'];

    /**
     * Kolom identitas yang BUKAN milik overlay.
     *
     * Sejak Fase 6 sumber kebenarannya adalah N_SOP_Dokumen, dan penulis
     * tunggalnya SopDokumenService::sinkronIdentitasKeItem() — yang hanya
     * menyentuh N_SOP_Paket_Item. Baris overlay memotretnya sekali saat draft
     * dibuka lalu tak pernah disegarkan lagi, sehingga begitu nomor dokumen
     * bergeser (segmen Revisi ikut bergerak setiap versi berlaku berganti)
     * potretnya basi. Dibiarkan ikut dibandingkan dan ikut diterbitkan, ia
     * menghasilkan dua akibat yang saling menghidupi: diff melaporkan "Nomor
     * Dokumen berubah" selamanya, dan publish menulis nilai basi itu balik ke
     * item live — memundurkan cerminan yang baru saja disinkronkan.
     *
     * Formnya sendiri tak lagi punya field ini untuk tipe ber-versi, jadi tak
     * ada perubahan sah yang hilang karena dikeluarkan dari sini.
     */
    private const ITEM_IDENTITY_FIELDS = ['Judul_Item', 'Nomor_SOP_Resmi'];

    /**
     * Nilai baku yang DIPAKSAKAN saat item live disalin ke overlay.
     *
     * Kolom-kolom ini boleh NULL di `N_SOP_Paket_Item`, tetapi kembarannya di
     * tabel draft NOT NULL — menyalin apa adanya membuat pembuatan working copy
     * gagal, dan Builder paket terbit membalas 500. Karena itu NULL diisi baku
     * di sini.
     *
     * DAN KARENA ITU PULA IA HARUS DIPAKAI SAAT MEMBANDINGKAN. Selama baku ini
     * hanya diterapkan di sisi salinan, pembanding melihat `''` di sisi live
     * melawan `'T'` di sisi draft dan menyimpulkan "berubah" — pada SETIAP item,
     * SETIAP paket, tanpa satu pun admin menyentuh apa pun. Terukur 10 Sep 2026:
     * enam dari enam paket terbit melaporkan perubahan tertunda sebanyak jumlah
     * itemnya, seluruhnya dari `Flag_Camera` yang memang NULL di data live.
     *
     * Satu daftar untuk dua pemakaian; dua daftar akan menyimpang, dan yang
     * menyimpang di sini berarti "ada revisi" yang tak pernah bisa dibereskan.
     */
    private const BAKU_ITEM_SALIN = [
        'Flag_Camera' => 'T',
        'Flag_Wajib' => 'Y',
        'Flag_Highlight' => 'T',
        'Flag_Watermark' => 'Y',
    ];

    /**
     * Baris item dengan baku salinan sudah diterapkan — untuk DIBANDINGKAN,
     * bukan untuk ditulis.
     *
     * Bakunya persis meniru cara penyalinan menuliskannya: bendera memakai `?:`
     * (kosong maupun NULL sama-sama jatuh ke baku), sedangkan `Urutan` memakai
     * `??` sehingga nol yang sah tetap nol. Perbedaan dua operator itu bukan
     * gaya penulisan — menyamakannya akan mengubah arti salah satunya.
     */
    private function bakuItem(?object $row): ?object
    {
        if (! $row) {
            return $row;
        }

        $salin = clone $row;
        foreach (self::BAKU_ITEM_SALIN as $kolom => $baku) {
            $salin->{$kolom} = ($salin->{$kolom} ?? null) ?: $baku;
        }
        $salin->Urutan = $salin->Urutan ?? 0;

        return $salin;
    }

    private const ITEM_VERSION_COLUMNS = ['Id_SOP_Draft_Item_Versi', 'Id_SOP_Draft_Item', 'Id_SOP_Item_Versi_Asal',
        'Versi_Ke', 'Status', 'Id_Master_Konten', 'Id_Master_Ujian', 'Ringkasan_Perubahan', 'Tanggal_Berlaku',
        'Disahkan_Oleh', 'Jabatan_Pengesah',
        'Tanggal_Pengesahan', 'Nomor_Pengesahan', 'Created_By', 'Created_At', 'Updated_By', 'Updated_At'];

    /**
     * Overlay draft sudah bisa membawa tautan Dokumen SOP (DDL 33)?
     *
     * Sebelum skrip itu dijalankan, kolomnya tak ada dan seluruh jalur ini
     * harus berperilaku persis seperti sebelumnya — item baru di paket terbit
     * mengetik judul dan dokumennya dibuat saat promosi. Dijaga di sini, bukan
     * di pemanggil, supaya tak ada pemanggil yang lupa memeriksanya.
     */
    public function dukungDokumen(): bool
    {
        static $ada = null;

        return $ada ??= Schema::hasColumn(self::ITEM, 'Id_SOP_Dokumen');
    }

    private function enc(string $prefix, int $id): string
    {
        return $prefix.'_'.Hashids::connection('custom')->encode($id);
    }

    /** Daftar kolom bertakarir alias, dipakai menggantikan "alias.*" agar Row_Version tak ikut. */
    private function prefixed(array $columns, string $alias): array
    {
        return array_map(fn ($column) => $alias.'.'.$column, $columns);
    }

    /**
     * Token concurrency dari kolom rowversion, dalam bentuk hex "0x...".
     *
     * Nilai mentahnya 8 byte biner yang tidak bisa di-json_encode; dua CONVERT ini
     * mengubahnya jadi string ASCII yang aman dikirim ke browser dan dibandingkan
     * apa adanya. SQL Server menaikkan nilainya pada SETIAP update baris, jadi ia
     * penanda "baris sudah berubah" yang lebih andal daripada Updated_At.
     */
    private function rowVer(string $alias = ''): \Illuminate\Database\Query\Expression
    {
        $column = $alias ? $alias.'.Row_Version' : 'Row_Version';

        return DB::raw("CONVERT(VARCHAR(20), CONVERT(VARBINARY(8), {$column}), 1) as Row_Ver");
    }

    /** Baris sudah disentuh orang lain sejak klien membacanya. */
    private function rowVerMismatch(string $table, string $pkColumn, int $id, ?string $expected): bool
    {
        if ($expected === null || $expected === '') {
            return false;
        } // klien lama / payload tanpa token

        $current = DB::table($table)->where($pkColumn, $id)->value(
            DB::raw('CONVERT(VARCHAR(20), CONVERT(VARBINARY(8), Row_Version), 1)')
        );

        return $current !== null && strcasecmp((string) $current, $expected) !== 0;
    }

    public function available(): bool
    {
        /* Empat pertanyaan katalog yang jawabannya tak pernah berubah, dan
           available() dipanggil dari sembilan tempat di kelas ini. Tanpa memo,
           satu permintaan bisa membayar puluhan kueri sys.tables - masing-masing
           46.285 halaman dibaca. */
        return SkemaTersedia::semuaTabel(self::HEADER, self::SECTION, self::ITEM, self::ITEM_VERSION);
    }

    public function working(int $idPaket): ?object
    {
        if (! $this->available()) {
            return null;
        }

        return DB::table(self::HEADER)->where('Id_SOP_Paket', $idPaket)->where('Status', 'DRAFT')->first();
    }

    /** Buat salinan kerja hanya untuk paket yang sudah terbit; draft paket baru tetap memakai tabel utama. */
    public function ensure(int $idPaket, int $userId): ?object
    {
        if (! $this->available()) {
            return null;
        }
        try {
            return DB::transaction(function () use ($idPaket, $userId) {
                if ($draft = DB::table(self::HEADER)->where('Id_SOP_Paket', $idPaket)->where('Status', 'DRAFT')->lockForUpdate()->first()) {
                    return $draft;
                }

                $paket = DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $idPaket)->lockForUpdate()->first();
                if (! $paket || $paket->Status !== SopStatus::PAKET_PUBLISHED) {
                    return null;
                }

                $idDraft = DB::table(self::HEADER)->insertGetId([
                    'Id_SOP_Paket' => $idPaket,
                    'Id_SOP_Paket_Versi_Basis' => $paket->Id_SOP_Paket_Versi_Aktif ?? null,
                    'Kode_Paket' => $paket->Kode_Paket,
                    'Nama_Paket' => $paket->Nama_Paket,
                    'Kode_Karyawan_Owner' => $paket->Kode_Karyawan_Owner,
                    'Deskripsi' => $paket->Deskripsi,
                    'Tujuan_Ruang_Lingkup' => $paket->Tujuan_Ruang_Lingkup,
                    'Prioritas' => $paket->Prioritas,
                    'Estimasi_Waktu_Baca_Menit' => $paket->Estimasi_Waktu_Baca_Menit,
                    'Mode_Urutan_Item' => $paket->Mode_Urutan_Item,
                    'Mode_Distribusi' => $paket->Mode_Distribusi,
                    'Cover_Path' => $paket->Cover_Path,
                    'Cover_Position' => $paket->Cover_Position,
                    'Flag_Absensi' => $paket->Flag_Absensi,
                    'Created_By' => $userId,
                ], 'Id_SOP_Paket_Draft');

                $mapSection = [];
                foreach (DB::table('N_SOP_Paket_Section')->where('Id_SOP_Paket', $idPaket)->where('Flag_Aktif', SopStatus::AKTIF)->orderBy('Urutan')->get() as $section) {
                    $mapSection[$section->Id_SOP_Section] = DB::table(self::SECTION)->insertGetId([
                        'Id_SOP_Paket_Draft' => $idDraft, 'Id_SOP_Section_Asal' => $section->Id_SOP_Section,
                        'Nama_Section' => $section->Nama_Section, 'Deskripsi' => $section->Deskripsi,
                        'Urutan' => $section->Urutan, 'Created_By' => $userId,
                    ], 'Id_SOP_Draft_Section');
                }

                /*
                 * Barisnya dikumpulkan lalu ditulis sekali. Sebelumnya satu
                 * INSERT per item paket, di dalam transaksi pembuatan draft;
                 * paket SOP bisa memuat puluhan item.
                 */
                $barisItem = [];

                foreach (DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket', $idPaket)->where('Flag_Aktif', SopStatus::AKTIF)->orderBy('Urutan')->get() as $item) {
                    $baku = $this->bakuItem($item);
                    $barisItem[] = [
                        'Id_SOP_Paket_Draft' => $idDraft, 'Id_SOP_Paket_Item_Asal' => $item->Id_SOP_Paket_Item,
                        'Id_SOP_Draft_Section' => $item->Id_SOP_Section ? ($mapSection[$item->Id_SOP_Section] ?? null) : null,
                        'Id_SOP_Item_Versi_Target' => $item->Id_Item_Versi_Aktif,
                        'Tipe_Item' => $item->Tipe_Item, 'Judul_Item' => $item->Judul_Item,
                        'Nomor_SOP_Resmi' => $item->Nomor_SOP_Resmi, 'Deskripsi_Item' => $item->Deskripsi_Item,
                        'Konten_Teks' => $item->Konten_Teks, 'Mode_Akses' => $item->Mode_Akses,
                        'URL_Tautan' => $item->URL_Tautan, 'Label_Tautan' => $item->Label_Tautan,
                        'Jenis_Quiz' => $item->Jenis_Quiz, 'Nilai_Minimum' => $item->Nilai_Minimum,
                        'Id_Master_Ujian' => $item->Id_Master_Ujian, 'Nama_Ujian' => $item->Nama_Ujian,
                        /* Baku NULL→nilai diambil dari BAKU_ITEM_SALIN lewat bakuItem(),
                           bukan diketik di sini. Daftar yang sama dipakai pembanding
                           perubahan; ditulis dua kali, keduanya pasti menyimpang — dan
                           menyimpang di sini berarti draft selalu tampak berubah. */
                        'Flag_Camera' => $baku->Flag_Camera, 'Urutan' => $baku->Urutan,
                        'Flag_Wajib' => $baku->Flag_Wajib, 'Flag_Highlight' => $baku->Flag_Highlight,
                        'Flag_Watermark' => $baku->Flag_Watermark,
                        'Created_By' => $userId, 'Created_At' => now(),
                    ];
                }

                PenyisipanBerbatasParameter::sisipkan(self::ITEM, $barisItem);

                return DB::table(self::HEADER)->where('Id_SOP_Paket_Draft', $idDraft)->first();
            });
        } catch (QueryException $e) {
            // Unique index working draft menangani race create dari dua editor.
            if ($draft = $this->working($idPaket)) {
                return $draft;
            }
            throw $e;
        }
    }

    /** Data editor dari working copy; ID yang dikirim adalah ID draft, bukan ID aktif. */
    public function editorData(int $idPaket, int $userId): ?array
    {
        $draft = $this->ensure($idPaket, $userId);
        if (! $draft) {
            return null;
        }

        $paket = DB::table(self::HEADER.' as d')
            ->where('d.Id_SOP_Paket_Draft', $draft->Id_SOP_Paket_Draft)
            ->select('d.*')
            ->first();
        if (! $paket) {
            return null;
        }

        $paket->Id_SOP_Paket = Hashids::connection('custom')->encode($paket->Id_SOP_Paket);
        $paket->Id_SOP_Paket_Draft = $this->enc('dp', (int) $paket->Id_SOP_Paket_Draft);
        $paket->Status = SopStatus::PAKET_PUBLISHED;
        $paket->Is_Working_Draft = true;

        $sections = DB::table(self::SECTION)
            ->where('Id_SOP_Paket_Draft', $draft->Id_SOP_Paket_Draft)
            ->where('Flag_Aktif', SopStatus::AKTIF)->orderBy('Urutan')->get()
            ->map(function ($row) {
                $row->Id_SOP_Section = $this->enc('ds', (int) $row->Id_SOP_Draft_Section);
                $row->Id_SOP_Draft_Section = $row->Id_SOP_Section;

                return $row;
            });

        $items = DB::table(self::ITEM)
            ->where('Id_SOP_Paket_Draft', $draft->Id_SOP_Paket_Draft)
            ->where('Flag_Aktif', SopStatus::AKTIF)->orderBy('Urutan')->get()
            ->map(function ($row) {
                $this->attachVersionMetadata($row);
                $row->Id_SOP_Paket_Item = $this->enc('di', (int) $row->Id_SOP_Draft_Item);
                $row->Id_SOP_Draft_Item = $row->Id_SOP_Paket_Item;
                $row->Id_SOP_Paket = Hashids::connection('custom')->encode($row->Id_SOP_Paket_Draft);
                $row->Id_SOP_Section = $row->Id_SOP_Draft_Section
                    ? $this->enc('ds', (int) $row->Id_SOP_Draft_Section) : null;
                $row->Id_Item_Versi_Aktif = $row->Id_SOP_Item_Versi_Target
                    ? Hashids::connection('custom')->encode($row->Id_SOP_Item_Versi_Target) : null;

                return $row;
            });

        return compact('paket', 'sections', 'items');
    }

    public function header(int $idPaket, int $userId): ?object
    {
        return $this->ensure($idPaket, $userId);
    }

    /**
     * Sentuhan terakhir pada working draft oleh admin SELAIN $userId.
     *
     * Working draft unik per paket (UX_N_SOP_Paket_Draft_Working), bukan per user —
     * jadi beberapa admin berbagi salinan kerja yang sama. Ini dipakai memberi tahu
     * bahwa draft ini bukan milik sendiri. Mengembalikan null bila hanya user ini
     * yang menyentuhnya, sehingga banner tidak muncul tanpa alasan.
     */
    public function lastActivityByOthers(int $idPaket, int $userId): ?array
    {
        $draft = $this->working($idPaket);
        if (! $draft) {
            return null;
        }

        $latest = ['at' => $draft->Updated_At ?? $draft->Created_At, 'by' => (int) ($draft->Updated_By ?? $draft->Created_By)];
        foreach ([self::SECTION, self::ITEM] as $table) {
            $row = DB::table($table)->where('Id_SOP_Paket_Draft', $draft->Id_SOP_Paket_Draft)
                ->orderByDesc(DB::raw('COALESCE(Updated_At, Created_At)'))
                ->first(['Updated_At', 'Created_At', 'Updated_By', 'Created_By']);
            if (! $row) {
                continue;
            }
            $at = $row->Updated_At ?? $row->Created_At;
            if ($at && (empty($latest['at']) || $at > $latest['at'])) {
                $latest = ['at' => $at, 'by' => (int) ($row->Updated_By ?? $row->Created_By)];
            }
        }

        if (! $latest['by'] || $latest['by'] === $userId) {
            return null;
        }

        return [
            'Nama' => DB::table('KPI_Users as u')->leftJoin('Karyawan as k', 'k.UserID_Web', '=', 'u.Id_Users')
                ->where('u.Id_Users', $latest['by'])->value('k.Nama'),
            'At' => $latest['at'],
        ];
    }

    public function updateHeader(int $idPaket, array $data, int $userId): bool
    {
        $draft = $this->ensure($idPaket, $userId);
        if (! $draft) {
            return false;
        }

        $payload = [
            'Kode_Paket' => $data['Kode_Paket'] ?? null,
            'Nama_Paket' => $data['Nama_Paket'],
            'Kode_Karyawan_Owner' => $data['Kode_Karyawan_Owner'] ?? null,
            'Deskripsi' => $data['Deskripsi'] ?? null,
            'Tujuan_Ruang_Lingkup' => $data['Tujuan_Ruang_Lingkup'] ?? null,
            'Prioritas' => $data['Prioritas'] ?? app(SopPrioritasService::class)->kodeBawaan(),
            'Estimasi_Waktu_Baca_Menit' => $data['Estimasi_Waktu_Baca_Menit'] ?? null,
            'Mode_Urutan_Item' => $data['Mode_Urutan_Item'] ?? 'BEBAS',
            'Mode_Distribusi' => $data['Mode_Distribusi'] ?? 'TERBATAS',
            'Flag_Absensi' => ($data['Flag_Absensi'] ?? false) ? SopStatus::AKTIF : SopStatus::INAKTIF,
            'Updated_By' => $userId,
            'Updated_At' => now(),
        ];
        foreach (['Cover_Path', 'Cover_Position'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }
        DB::table(self::HEADER)->where('Id_SOP_Paket_Draft', $draft->Id_SOP_Paket_Draft)->update($payload);

        return true;
    }

    public function discard(int $idPaket, int $userId): bool
    {
        if (! $this->available()) {
            return false;
        }

        return DB::table(self::HEADER)->where('Id_SOP_Paket', $idPaket)->where('Status', 'DRAFT')->update([
            'Status' => 'DISCARDED', 'Updated_By' => $userId, 'Updated_At' => now(),
        ]) > 0;
    }

    public function sections(int $idPaket, int $userId)
    {
        $draft = $this->ensure($idPaket, $userId);
        if (! $draft) {
            return collect();
        }

        return DB::table(self::SECTION)->where('Id_SOP_Paket_Draft', $draft->Id_SOP_Paket_Draft)
            ->where('Flag_Aktif', SopStatus::AKTIF)->orderBy('Urutan')
            ->get(array_merge(self::SECTION_COLUMNS, [$this->rowVer()]))->map(function ($row) {
                $row->Id_SOP_Section = $this->enc('ds', (int) $row->Id_SOP_Draft_Section);

                return $row;
            });
    }

    public function createSection(int $idPaket, array $data, int $userId): ?int
    {
        $draft = $this->ensure($idPaket, $userId);
        if (! $draft) {
            return null;
        }
        $last = (int) (DB::table(self::SECTION)->where('Id_SOP_Paket_Draft', $draft->Id_SOP_Paket_Draft)->max('Urutan') ?? 0);

        return DB::table(self::SECTION)->insertGetId([
            'Id_SOP_Paket_Draft' => $draft->Id_SOP_Paket_Draft, 'Nama_Section' => $data['Nama_Section'],
            'Deskripsi' => $data['Deskripsi'] ?? null, 'Urutan' => $last + 1, 'Created_By' => $userId,
        ], 'Id_SOP_Draft_Section');
    }

    public function isDraftSection(int $id): bool
    {
        return $this->available() && DB::table(self::SECTION)->where('Id_SOP_Draft_Section', $id)->exists();
    }

    public function sectionBelongsTo(int $idSection, int $idPaket, int $userId): bool
    {
        $draft = $this->ensure($idPaket, $userId);

        return $draft && DB::table(self::SECTION)->where('Id_SOP_Draft_Section', $idSection)
            ->where('Id_SOP_Paket_Draft', $draft->Id_SOP_Paket_Draft)->where('Flag_Aktif', SopStatus::AKTIF)->exists();
    }

    /** @return bool false bila baris sudah diubah admin lain sejak klien membacanya. */
    public function updateSection(int $id, array $data, int $userId, ?string $expectedRowVer = null): bool
    {
        if ($this->rowVerMismatch(self::SECTION, 'Id_SOP_Draft_Section', $id, $expectedRowVer)) {
            return false;
        }

        DB::table(self::SECTION)->where('Id_SOP_Draft_Section', $id)->update([
            'Nama_Section' => $data['Nama_Section'], 'Deskripsi' => $data['Deskripsi'] ?? null,
            'Updated_By' => $userId, 'Updated_At' => now(),
        ]);

        return true;
    }

    public function deleteSection(int $id, int $userId): bool
    {
        if (DB::table(self::ITEM)->where('Id_SOP_Draft_Section', $id)->where('Flag_Aktif', SopStatus::AKTIF)->exists()) {
            return false;
        }
        DB::table(self::SECTION)->where('Id_SOP_Draft_Section', $id)->update(['Flag_Aktif' => SopStatus::INAKTIF, 'Updated_By' => $userId, 'Updated_At' => now()]);

        return true;
    }

    public function reorderSections(array $ids): void
    {
        foreach ($ids as $i => $id) {
            DB::table(self::SECTION)->where('Id_SOP_Draft_Section', $id)->update(['Urutan' => $i + 1, 'Updated_At' => now()]);
        }
    }

    public function items(int $idPaket, int $userId)
    {
        $draft = $this->ensure($idPaket, $userId);
        if (! $draft) {
            return collect();
        }

        return DB::table(self::ITEM.' as i')
            ->join(self::HEADER.' as d', 'd.Id_SOP_Paket_Draft', '=', 'i.Id_SOP_Paket_Draft')
            /* Dokumen dibaca dari item LIVE-nya. N_SOP_Paket_Draft_Item tak
               punya kolom Id_SOP_Dokumen (lihat ITEM_COLUMNS), jadi tanpa join
               ini setiap item pada paket terbit tampil seolah BELUM bertaut
               dokumen — dan layarnya menawarkan "Pilih Dokumen SOP yang sudah
               ada" untuk item yang jelas-jelas sudah punya. Yang dipilih di sana
               tak bisa disimpan ke mana pun. */
            ->leftJoin('N_SOP_Paket_Item as live', 'live.Id_SOP_Paket_Item', '=', 'i.Id_SOP_Paket_Item_Asal')
            /* Identitas dibaca dari DOKUMENnya, bukan dari potret overlay.
               Potret itu diambil sekali saat draft dibuka dan tak pernah
               disegarkan — sedangkan nomor dokumen bergeser sendiri setiap versi
               berlaku berganti, sebab segmen Revisi ikut bergerak. Membacanya
               dari sini membuat layar Builder menyebut nomor yang sama dengan
               Master Dokumen, monitoring, dan ekspor. */
            ->leftJoin('N_SOP_Dokumen as dok', 'dok.Id_SOP_Dokumen', '=', DB::raw($this->dukungDokumen()
                ? 'COALESCE(i.Id_SOP_Dokumen, live.Id_SOP_Dokumen)'
                : 'live.Id_SOP_Dokumen'))
            ->where('i.Id_SOP_Paket_Draft', $draft->Id_SOP_Paket_Draft)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)->orderBy('i.Urutan')
            /* Dokumen dibaca dari item LIVE-nya, DAN dari barisnya sendiri bila
               kolomnya ada: item yang LAHIR di overlay belum punya item live,
               jadi tautannya hanya tersimpan di sini. COALESCE memilih yang
               terisi — keduanya tak pernah berbeda untuk satu baris. */
            ->get(array_merge(
                /* Judul & Nomor sengaja dikeluarkan dari daftar kolom overlay:
                   dua kolom bernama sama dalam satu SELECT membuat baris hasil
                   memakai yang TERAKHIR, dan mana yang terakhir bukan sesuatu
                   yang pantas dititipkan pada urutan argumen. */
                $this->prefixed(array_values(array_diff(self::ITEM_COLUMNS, self::ITEM_IDENTITY_FIELDS)), 'i'),
                [
                    'd.Id_SOP_Paket', $this->rowVer('i'),
                    'live.Id_Item_Versi_Aktif',
                    /* NULLIF: dokumen yang identitasnya belum terisi tak boleh
                       MENGOSONGKAN baris yang sudah punya nama — aturan yang
                       sama dengan segarkanNomorResmi(), yang juga tak pernah
                       menimpa nomor lama dengan null. Nyata, bukan teoretis:
                       15 dokumen di dev belum bernomor, dan sebagiannya memakai
                       penomoran ketik-tangan yang masih beredar di luar sistem.

                       Item LIVE dulu, baru overlay. Keduanya cuma jaring, tapi
                       urutannya penting: item live adalah cerminan yang ikut
                       disinkronkan, sedangkan overlay hanya potret sekali-jadi —
                       memilih potret lebih dulu berarti memilih yang lebih tua. */
                    DB::raw('COALESCE(NULLIF(dok.Judul, \'\'), live.Judul_Item, i.Judul_Item) as Judul_Item'),
                    DB::raw('COALESCE(NULLIF(dok.Nomor_SOP_Resmi, \'\'), live.Nomor_SOP_Resmi, i.Nomor_SOP_Resmi) as Nomor_SOP_Resmi'),
                ],
                [DB::raw($this->dukungDokumen()
                    ? 'COALESCE(i.Id_SOP_Dokumen, live.Id_SOP_Dokumen) as Id_SOP_Dokumen'
                    : 'live.Id_SOP_Dokumen as Id_SOP_Dokumen')]
            ))->map(function ($row) {
                $this->attachVersionMetadata($row);
                $row->Id_SOP_Paket_Item = $this->enc('di', (int) $row->Id_SOP_Draft_Item);
                $row->Id_SOP_Section = $row->Id_SOP_Draft_Section ? $this->enc('ds', (int) $row->Id_SOP_Draft_Section) : null;
                $row->Id_SOP_Paket = Hashids::connection('custom')->encode($row->Id_SOP_Paket);
                /* Dikodekan seperti id lain: form mengirimkannya balik apa
                   adanya, dan id mentah gagal decode di terapkanDokumen(). */
                if (! empty($row->Id_SOP_Dokumen)) {
                    $row->Id_SOP_Dokumen = Hashids::connection('custom')->encode((int) $row->Id_SOP_Dokumen);
                }
                if (! empty($row->Id_Item_Versi_Aktif)) {
                    $row->Id_Item_Versi_Aktif = Hashids::connection('custom')->encode((int) $row->Id_Item_Versi_Aktif);
                }

                /* PERNAH TERBIT atau BELUM — dua hal yang tampak sama di
                   layar tapi aturannya berlawanan. Item cerminan sudah punya
                   pembaca, jadi dokumennya terkunci; item yang lahir di
                   overlay ini belum pernah dilihat siapa pun, jadi pilihannya
                   masih boleh diperbaiki. Dikirim sebagai pernyataan, bukan
                   menyuruh layar menyimpulkannya sendiri dari id internal. */
                $row->Sudah_Pernah_Terbit = ! empty($row->Id_SOP_Paket_Item_Asal);

                return $row;
            });
    }

    public function isDraftItem(int $id): bool
    {
        return $this->available() && DB::table(self::ITEM)->where('Id_SOP_Draft_Item', $id)->exists();
    }

    private function attachVersionMetadata(object $row): void
    {
        $version = DB::table(self::ITEM_VERSION.' as v')
            ->leftJoin('N_LMS_Master_Konten as k', 'k.Id_Master_Konten', '=', 'v.Id_Master_Konten')
            ->where('v.Id_SOP_Draft_Item', $row->Id_SOP_Draft_Item)
            ->where('v.Status', '<>', 'DISCARDED')
            ->orderByRaw("CASE v.Status WHEN 'READY' THEN 0 WHEN 'DRAFT' THEN 1 ELSE 2 END")
            ->orderByDesc('v.Versi_Ke')
            ->first([
                'v.Versi_Ke',
                'v.Status as Versi_Status',
                'v.Id_Master_Konten',
                'k.Judul_Konten as Konten_Nama',
                'k.Tipe_Konten as Konten_Tipe',
            ]);

        if (! $version && ! empty($row->Id_SOP_Item_Versi_Target)) {
            $version = DB::table('N_SOP_Item_Versi as v')
                ->leftJoin('N_LMS_Master_Konten as k', 'k.Id_Master_Konten', '=', 'v.Id_Master_Konten')
                ->where('v.Id_SOP_Item_Versi', $row->Id_SOP_Item_Versi_Target)
                ->first([
                    'v.Versi_Ke',
                    'v.Status as Versi_Status',
                    'v.Id_Master_Konten',
                    'k.Judul_Konten as Konten_Nama',
                    'k.Tipe_Konten as Konten_Tipe',
                ]);
        }

        if ($version) {
            $row->Versi = $version->Versi_Ke;
            $row->Versi_Status = $version->Versi_Status;
            $row->Konten_Nama = $version->Konten_Nama;
            $row->Konten_Tipe = $version->Konten_Tipe;
            $row->Id_Master_Konten = $version->Id_Master_Konten
                ? Hashids::connection('custom')->encode($version->Id_Master_Konten)
                : null;
        }
    }

    public function createItem(int $idPaket, array $data, int $userId, ?array $versi = null): ?int
    {
        $draft = $this->ensure($idPaket, $userId);
        if (! $draft) {
            return null;
        }
        $section = (int) ($data['Id_SOP_Draft_Section'] ?? 0);
        if (! $section || ! DB::table(self::SECTION)->where('Id_SOP_Draft_Section', $section)->where('Id_SOP_Paket_Draft', $draft->Id_SOP_Paket_Draft)->where('Flag_Aktif', SopStatus::AKTIF)->exists()) {
            return null;
        }

        $urutan = (int) (DB::table(self::ITEM)->where('Id_SOP_Paket_Draft', $draft->Id_SOP_Paket_Draft)->max('Urutan') ?? 0) + 1;

        /* Tautan dokumen hanya ditulis bila kolomnya memang ada. Tanpa penjaga
           ini, environment yang belum menjalankan DDL 33 akan runtuh pada
           penambahan item — dan runtuhnya di jalur yang paling sering dipakai. */
        $dokumen = [];
        if ($this->dukungDokumen() && ! empty($data['Id_SOP_Dokumen'])) {
            $idDokumen = (int) $data['Id_SOP_Dokumen'];
            $dokumen['Id_SOP_Dokumen'] = $idDokumen;

            /* VERSI TARGETNYA IKUT DIPASANG — versi aktif dokumen itu.
               Gerbang publish menuntut tiap item ber-versi punya target ATAU
               kandidat "Siap Rilis" (lihat validasi "belum memiliki versi Siap
               Rilis"). Item yang menautkan dokumen yang sudah terbit tak punya
               keduanya: berkasnya sudah ada, tak ada revisi yang perlu
               disiapkan. Tanpa baris ini ia menahan seluruh rilis paket, dan
               admin diminta menyiapkan versi untuk sesuatu yang sudah siap.

               Hanya versi PUBLISHED yang dipasang — invarian pointer: target
               yang menunjuk DRAFT berarti menyajikan versi yang belum sah. */
            $dokumen['Id_SOP_Item_Versi_Target'] = DB::table('N_SOP_Dokumen as d')
                ->join('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'd.Id_Versi_Aktif')
                ->where('d.Id_SOP_Dokumen', $idDokumen)
                ->where('v.Status', SopStatus::PAKET_PUBLISHED)
                ->value('v.Id_SOP_Item_Versi');
        }

        $idItem = DB::table(self::ITEM)->insertGetId($dokumen + [
            'Id_SOP_Paket_Draft' => $draft->Id_SOP_Paket_Draft, 'Id_SOP_Draft_Section' => $section,
            'Tipe_Item' => $data['Tipe_Item'], 'Judul_Item' => $data['Judul_Item'],
            'Nomor_SOP_Resmi' => $data['Nomor_SOP_Resmi'] ?? null, 'Deskripsi_Item' => $data['Deskripsi_Item'] ?? null,
            'Konten_Teks' => $data['Konten_Teks'] ?? null, 'Mode_Akses' => $data['Mode_Akses'] ?? null,
            'URL_Tautan' => $data['URL_Tautan'] ?? null, 'Label_Tautan' => $data['Label_Tautan'] ?? null,
            'Jenis_Quiz' => $data['Jenis_Quiz'] ?? null, 'Nilai_Minimum' => $data['Nilai_Minimum'] ?? null,
            'Id_Master_Ujian' => $data['Id_Master_Ujian'] ?? null, 'Nama_Ujian' => $data['Nama_Ujian'] ?? null,
            'Flag_Camera' => ($data['Flag_Camera'] ?? null) === SopStatus::AKTIF ? SopStatus::AKTIF : SopStatus::INAKTIF,
            'Urutan' => $urutan, 'Flag_Wajib' => ($data['Flag_Wajib'] ?? true) ? SopStatus::AKTIF : SopStatus::INAKTIF,
            'Flag_Highlight' => ($data['Flag_Highlight'] ?? false) ? SopStatus::AKTIF : SopStatus::INAKTIF,
            'Flag_Watermark' => SopAksesBerkas::watermarkTersimpan($data['Flag_Watermark'] ?? null, $data['Mode_Akses'] ?? null),
            'Created_By' => $userId,
        ], 'Id_SOP_Draft_Item');

        if ($versi) {
            DB::table(self::ITEM_VERSION)->insert([
                'Id_SOP_Draft_Item' => $idItem, 'Versi_Ke' => 1, 'Status' => SopStatus::VERSI_DRAFT,
                'Id_Master_Konten' => $versi['Id_Master_Konten'] ?? null, 'Id_Master_Ujian' => $versi['Id_Master_Ujian'] ?? null,
                'Ringkasan_Perubahan' => $versi['Ringkasan_Perubahan'] ?? null, 'Created_By' => $userId,
            ]);
        }

        return $idItem;
    }

    /** @return bool false bila baris sudah diubah admin lain sejak klien membacanya. */
    public function updateItem(int $id, array $data, int $userId, ?string $expectedRowVer = null): bool
    {
        if ($this->rowVerMismatch(self::ITEM, 'Id_SOP_Draft_Item', $id, $expectedRowVer)) {
            return false;
        }

        /* PINDAH DOKUMEN HANYA UNTUK ITEM YANG LAHIR DI SINI. Item cerminan
           (Id_SOP_Paket_Item_Asal terisi) sudah ditolak lantang di controller —
           menukar dokumennya membuat catatan baca menunjuk SOP yang tak pernah
           dibaca. Item yang belum pernah terbit tak punya pembaca, jadi selama
           belum dipromosikan pilihannya masih boleh diperbaiki.

           Tanpa cabang ini tautannya HILANG DIAM-DIAM: Id_SOP_Dokumen tak ada di
           ITEM_COLUMNS, jadi nilainya lewat begitu saja dan layar tetap berkata
           "Item tersimpan" — cacat yang sama persis dengan yang ditutup DDL 33,
           cuma pindah ke pintu sebelah. */
        $dokumen = [];
        if ($this->dukungDokumen() && array_key_exists('Id_SOP_Dokumen', $data)) {
            $baris = DB::table(self::ITEM)->where('Id_SOP_Draft_Item', $id)
                ->first(['Id_SOP_Paket_Item_Asal']);

            if ($baris && ! $baris->Id_SOP_Paket_Item_Asal) {
                $idDokumen = $data['Id_SOP_Dokumen'] ? (int) $data['Id_SOP_Dokumen'] : null;
                $dokumen['Id_SOP_Dokumen'] = $idDokumen;
                // Versi targetnya ikut pindah — lihat alasannya di createItem().
                $dokumen['Id_SOP_Item_Versi_Target'] = $idDokumen
                    ? DB::table('N_SOP_Dokumen as d')
                        ->join('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'd.Id_Versi_Aktif')
                        ->where('d.Id_SOP_Dokumen', $idDokumen)
                        ->where('v.Status', SopStatus::PAKET_PUBLISHED)
                        ->value('v.Id_SOP_Item_Versi')
                    : null;
            }
        }

        DB::table(self::ITEM)->where('Id_SOP_Draft_Item', $id)->update($dokumen + [
            'Tipe_Item' => $data['Tipe_Item'], 'Judul_Item' => $data['Judul_Item'], 'Nomor_SOP_Resmi' => $data['Nomor_SOP_Resmi'] ?? null,
            'Deskripsi_Item' => $data['Deskripsi_Item'] ?? null, 'Konten_Teks' => $data['Konten_Teks'] ?? null,
            'Mode_Akses' => $data['Mode_Akses'] ?? null, 'URL_Tautan' => $data['URL_Tautan'] ?? null,
            'Label_Tautan' => $data['Label_Tautan'] ?? null, 'Jenis_Quiz' => $data['Jenis_Quiz'] ?? null,
            'Nilai_Minimum' => $data['Nilai_Minimum'] ?? null, 'Id_Master_Ujian' => $data['Id_Master_Ujian'] ?? null,
            'Nama_Ujian' => $data['Nama_Ujian'] ?? null,
            'Flag_Camera' => ($data['Flag_Camera'] ?? null) === SopStatus::AKTIF ? SopStatus::AKTIF : SopStatus::INAKTIF,
            'Flag_Wajib' => ($data['Flag_Wajib'] ?? true) ? SopStatus::AKTIF : SopStatus::INAKTIF,
            'Flag_Highlight' => ($data['Flag_Highlight'] ?? false) ? SopStatus::AKTIF : SopStatus::INAKTIF,
            'Flag_Watermark' => SopAksesBerkas::watermarkTersimpan($data['Flag_Watermark'] ?? null, $data['Mode_Akses'] ?? null),
            'Updated_By' => $userId, 'Updated_At' => now(),
        ]);

        return true;
    }

    public function deleteItem(int $id, int $userId): void
    {
        DB::table(self::ITEM)->where('Id_SOP_Draft_Item', $id)->update(['Flag_Aktif' => SopStatus::INAKTIF, 'Updated_By' => $userId, 'Updated_At' => now()]);
    }

    public function reorderItems(array $rows): void
    {
        foreach ($rows as $i => $row) {
            // Sejalan dengan SopItemService::reorder(): section hanya ditulis bila key-nya
            // memang dikirim. Menulis null tanpa syarat akan melepas seluruh item draft dari
            // section-nya begitu ada payload reorder parsial (mis. reorder per-section).
            $payload = ['Urutan' => $i + 1, 'Updated_At' => now()];
            if (array_key_exists('section', $row)) {
                $payload['Id_SOP_Draft_Section'] = $row['section'];
            }
            DB::table(self::ITEM)->where('Id_SOP_Draft_Item', $row['id'])->update($payload);
        }
    }

    public function draftItem(int $id): ?object
    {
        return $this->available() ? DB::table(self::ITEM)->where('Id_SOP_Draft_Item', $id)->where('Flag_Aktif', SopStatus::AKTIF)->first() : null;
    }

    /**
     * Dokumen terkendali yang ditunjuk satu baris overlay, atau null bila tak ada.
     *
     * Dua sumber, dan keduanya perlu: item yang LAHIR di overlay hanya menyimpan
     * tautannya di barisnya sendiri (dan hanya sejak DDL 33), sedangkan item
     * cerminan menyimpannya di item live-nya. Keduanya tak pernah berbeda untuk
     * satu baris — sama seperti COALESCE di items().
     */
    private function dokumenDraftItem(object $item): ?int
    {
        if ($this->dukungDokumen() && ! empty($item->Id_SOP_Dokumen)) {
            return (int) $item->Id_SOP_Dokumen;
        }

        if (empty($item->Id_SOP_Paket_Item_Asal)) {
            return null;
        }

        $live = DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Paket_Item', $item->Id_SOP_Paket_Item_Asal)
            ->value('Id_SOP_Dokumen');

        return $live ? (int) $live : null;
    }

    public function cloneItem(int $idDraftItem, int $userId, ?int $targetSection = null): ?int
    {
        $source = $this->draftItem($idDraftItem);
        if (! $source) {
            return null;
        }

        return $this->cloneItemRow($source, $targetSection ?: (int) $source->Id_SOP_Draft_Section, $userId);
    }

    public function cloneSection(int $idDraftSection, int $userId): ?int
    {
        $source = DB::table(self::SECTION)->where('Id_SOP_Draft_Section', $idDraftSection)
            ->where('Flag_Aktif', SopStatus::AKTIF)->first();
        if (! $source) {
            return null;
        }

        $newSection = DB::table(self::SECTION)->insertGetId([
            'Id_SOP_Paket_Draft' => $source->Id_SOP_Paket_Draft,
            'Nama_Section' => mb_substr((string) $source->Nama_Section, 0, 190).' (Salinan)',
            'Deskripsi' => $source->Deskripsi,
            'Urutan' => ((int) DB::table(self::SECTION)->where('Id_SOP_Paket_Draft', $source->Id_SOP_Paket_Draft)->max('Urutan')) + 1,
            'Created_By' => $userId,
        ], 'Id_SOP_Draft_Section');

        $items = DB::table(self::ITEM)->where('Id_SOP_Draft_Section', $idDraftSection)
            ->where('Flag_Aktif', SopStatus::AKTIF)->orderBy('Urutan')->get();
        foreach ($items as $item) {
            $this->cloneItemRow($item, $newSection, $userId);
        }

        return (int) $newSection;
    }

    private function cloneItemRow(object $source, int $targetSection, int $userId): int
    {
        $newId = DB::table(self::ITEM)->insertGetId([
            'Id_SOP_Paket_Draft' => $source->Id_SOP_Paket_Draft,
            'Id_SOP_Draft_Section' => $targetSection,
            'Id_SOP_Item_Versi_Target' => null,
            'Tipe_Item' => $source->Tipe_Item,
            'Judul_Item' => mb_substr((string) $source->Judul_Item, 0, 245).' (Salinan)',
            'Nomor_SOP_Resmi' => $source->Nomor_SOP_Resmi,
            'Deskripsi_Item' => $source->Deskripsi_Item, 'Konten_Teks' => $source->Konten_Teks,
            'Mode_Akses' => $source->Mode_Akses, 'URL_Tautan' => $source->URL_Tautan,
            'Label_Tautan' => $source->Label_Tautan, 'Jenis_Quiz' => $source->Jenis_Quiz,
            'Nilai_Minimum' => $source->Nilai_Minimum, 'Id_Master_Ujian' => $source->Id_Master_Ujian,
            'Nama_Ujian' => $source->Nama_Ujian, 'Flag_Camera' => $source->Flag_Camera,
            'Urutan' => ((int) DB::table(self::ITEM)->where('Id_SOP_Paket_Draft', $source->Id_SOP_Paket_Draft)->max('Urutan')) + 1,
            'Flag_Wajib' => $source->Flag_Wajib, 'Flag_Highlight' => $source->Flag_Highlight,
            'Flag_Watermark' => $source->Flag_Watermark,
            'Created_By' => $userId,
        ], 'Id_SOP_Draft_Item');

        if ($this->isVersionedItem($source)) {
            $version = DB::table(self::ITEM_VERSION)->where('Id_SOP_Draft_Item', $source->Id_SOP_Draft_Item)
                ->whereIn('Status', [SopStatus::VERSI_READY, SopStatus::VERSI_DRAFT])->orderByDesc('Versi_Ke')->first();
            if (! $version && $source->Id_SOP_Item_Versi_Target) {
                $version = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $source->Id_SOP_Item_Versi_Target)->first();
            }
            if ($version) {
                $this->createVersion($newId, (array) $version, $userId);
            }
        }

        return (int) $newId;
    }

    /**
     * Riwayat versi satu item overlay: versi kandidat miliknya sendiri ditambah
     * versi yang sudah sah milik dokumennya.
     *
     * @param  bool  $hanyaAktif  hanya versi yang BERLAKU (PUBLISHED), untuk panel ringkas.
     */
    public function versions(int $idDraftItem, bool $hanyaAktif = false)
    {
        $item = $this->draftItem($idDraftItem);
        if (! $item) {
            return collect();
        }

        /* SUMBU VERSI = DOKUMEN, bukan item — tertinggal di sini sejak Fase 2
           sementara jalur live (SopItemVersionService::byItem) sudah pindah.
           Sisa penyaringan lewat v.Id_SOP_Paket_Item membuat panel versi Builder
           kosong untuk SETIAP item, sebab tak satu pun baris N_SOP_Item_Versi
           masih mengisi kolom itu — layarnya lalu berkata "Belum Ada Versi
           Berlaku" pada dokumen yang versinya jelas terbit.

           Sumbu item dipertahankan sebagai jalan mundur untuk versi yatim
           (item yang tipenya diubah menjauh dari tipe ber-versi), persis seperti
           byItem(): memaksa mereka lewat dokumen akan menyembunyikan baris yang
           masih ada. */
        $idDokumen = $this->dokumenDraftItem($item);
        $asal = (int) ($item->Id_SOP_Paket_Item_Asal ?? 0);

        $activeVersions = collect();
        if ($idDokumen !== null || $asal) {
            $activeVersions = DB::table('N_SOP_Item_Versi as v')
                ->leftJoin('N_LMS_Master_Konten as k', 'k.Id_Master_Konten', '=', 'v.Id_Master_Konten')
                ->leftJoin('KPI_Users as ub', 'ub.Id_Users', '=', 'v.Created_By')
                ->leftJoin('Karyawan as kb', 'kb.UserID_Web', '=', 'ub.Id_Users')
                ->leftJoin('KPI_Users as uu', 'uu.Id_Users', '=', 'v.Updated_By')
                ->leftJoin('Karyawan as ku', 'ku.UserID_Web', '=', 'uu.Id_Users')
                ->when($idDokumen !== null,
                    fn ($q) => $q->where('v.Id_SOP_Dokumen', $idDokumen),
                    fn ($q) => $q->where('v.Id_SOP_Paket_Item', $asal))
                ->where('v.Flag_Aktif', SopStatus::AKTIF)
                ->whereIn('v.Status', $hanyaAktif
                    ? [SopStatus::VERSI_PUBLISHED]
                    : [SopStatus::VERSI_PUBLISHED, SopStatus::VERSI_OBSOLETE])
                ->get(['v.*', 'k.Judul_Konten as Konten_Nama', 'k.Tipe_Konten as Konten_Tipe',
                    DB::raw('COALESCE(kb.Nama, ub.Username) as Dibuat_Oleh_Nama'),
                    DB::raw('COALESCE(ku.Nama, uu.Username) as Diubah_Oleh_Nama')]);

            // Sama dengan byItem(): jenis berkas + form kosong, sebelum id di-hash.
            app(SopVersiLampiranService::class)->lengkapiIsi($activeVersions);

            $activeVersions = $activeVersions
                ->map(function ($row) use ($item) {
                    $idVersi = (int) $row->Id_SOP_Item_Versi;
                    $row->Id_SOP_Item_Versi = Hashids::connection('custom')->encode($row->Id_SOP_Item_Versi);
                    $row->Is_Target_Versi = (int) ($item->Id_SOP_Item_Versi_Target ?? 0) === $idVersi;

                    return $row;
                });
        }

        /* Panel ringkas menanyakan SATU hal: mana versi yang berlaku. Versi
           kandidat di overlay tak pernah berstatus PUBLISHED — ia DRAFT atau
           READY sampai Master Dokumen menerbitkannya — jadi membawanya ke sini
           hanya akan membuat baris pertama (yang dibaca layar sebagai "versi
           berlaku") ditempati versi yang belum sah. */
        if ($hanyaAktif) {
            return $activeVersions->sortByDesc(fn ($row) => (int) ($row->Versi_Ke ?? 0))->values();
        }

        /* Versi draft pun disebut pembuat & pengubahnya. Justru di sinilah
           pertanyaannya paling sering muncul: working draft satu paket dipakai
           BERSAMA beberapa admin, jadi "siapa yang mengubah versi ini" tak bisa
           dijawab dengan menebak. */
        $draftVersions = DB::table(self::ITEM_VERSION.' as v')
            ->leftJoin('N_LMS_Master_Konten as k', 'k.Id_Master_Konten', '=', 'v.Id_Master_Konten')
            ->leftJoin('KPI_Users as ub', 'ub.Id_Users', '=', 'v.Created_By')
            ->leftJoin('Karyawan as kb', 'kb.UserID_Web', '=', 'ub.Id_Users')
            ->leftJoin('KPI_Users as uu', 'uu.Id_Users', '=', 'v.Updated_By')
            ->leftJoin('Karyawan as ku', 'ku.UserID_Web', '=', 'uu.Id_Users')
            ->where('v.Id_SOP_Draft_Item', $idDraftItem)->where('v.Status', '<>', 'DISCARDED')->orderByDesc('v.Versi_Ke')
            ->get(array_merge($this->prefixed(self::ITEM_VERSION_COLUMNS, 'v'),
                ['k.Judul_Konten as Konten_Nama', 'k.Tipe_Konten as Konten_Tipe',
                    DB::raw('COALESCE(kb.Nama, ub.Username) as Dibuat_Oleh_Nama'),
                    DB::raw('COALESCE(ku.Nama, uu.Username) as Diubah_Oleh_Nama')]))
            ->map(function ($row) {
                $row->Id_SOP_Item_Versi = $this->enc('dv', (int) $row->Id_SOP_Draft_Item_Versi);

                return $row;
            });

        return $draftVersions
            ->merge($activeVersions)
            ->sortByDesc(fn ($row) => (int) ($row->Versi_Ke ?? 0))
            ->values();
    }

    public function createVersion(int $idDraftItem, array $data, int $userId): ?int
    {
        return DB::transaction(function () use ($idDraftItem, $data, $userId) {
            $item = DB::table(self::ITEM)->where('Id_SOP_Draft_Item', $idDraftItem)
                ->where('Flag_Aktif', SopStatus::AKTIF)->lockForUpdate()->first();
            if (! $item) {
                return null;
            }

            $draftMax = (int) (DB::table(self::ITEM_VERSION)->where('Id_SOP_Draft_Item', $idDraftItem)->max('Versi_Ke') ?? 0);
            $activeMax = $item->Id_SOP_Paket_Item_Asal
                ? (int) (DB::table('N_SOP_Item_Versi')->where('Id_SOP_Paket_Item', $item->Id_SOP_Paket_Item_Asal)->max('Versi_Ke') ?? 0)
                : 0;

            return DB::table(self::ITEM_VERSION)->insertGetId([
                'Id_SOP_Draft_Item' => $idDraftItem, 'Versi_Ke' => max($draftMax, $activeMax) + 1,
                'Status' => SopStatus::VERSI_DRAFT, 'Id_SOP_Item_Versi_Asal' => $item->Id_SOP_Item_Versi_Target ?: null,
                'Id_Master_Konten' => $data['Id_Master_Konten'] ?? null, 'Id_Master_Ujian' => $data['Id_Master_Ujian'] ?? null,
                'Ringkasan_Perubahan' => $data['Ringkasan_Perubahan'] ?? null, 'Tanggal_Berlaku' => $data['Tanggal_Berlaku'] ?? null,
                'Disahkan_Oleh' => $data['Disahkan_Oleh'] ?? null, 'Jabatan_Pengesah' => $data['Jabatan_Pengesah'] ?? null,
                'Tanggal_Pengesahan' => $data['Tanggal_Pengesahan'] ?? null, 'Nomor_Pengesahan' => $data['Nomor_Pengesahan'] ?? null,
                'Created_By' => $userId,
            ], 'Id_SOP_Draft_Item_Versi');
        });
    }

    public function isDraftVersion(int $id): bool
    {
        return $this->available() && DB::table(self::ITEM_VERSION)->where('Id_SOP_Draft_Item_Versi', $id)->exists();
    }

    public function draftVersion(int $id): ?object
    {
        return $this->available() ? DB::table(self::ITEM_VERSION)->where('Id_SOP_Draft_Item_Versi', $id)->first() : null;
    }

    public function setVersionReady(int $id, int $userId, bool $ready): bool
    {
        return DB::transaction(function () use ($id, $userId, $ready) {
            $row = DB::table(self::ITEM_VERSION)->where('Id_SOP_Draft_Item_Versi', $id)->lockForUpdate()->first();
            if (! $row || ! in_array($row->Status, [SopStatus::VERSI_DRAFT, SopStatus::VERSI_READY], true)) {
                return false;
            }
            if ($ready) {
                // Satu item hanya boleh mempunyai satu kandidat terpilih untuk satu rilis paket.
                DB::table(self::ITEM_VERSION)
                    ->where('Id_SOP_Draft_Item', $row->Id_SOP_Draft_Item)
                    ->where('Id_SOP_Draft_Item_Versi', '<>', $id)
                    ->where('Status', SopStatus::VERSI_READY)
                    ->update(['Status' => SopStatus::VERSI_DRAFT, 'Updated_By' => $userId, 'Updated_At' => now()]);
            }
            DB::table(self::ITEM_VERSION)->where('Id_SOP_Draft_Item_Versi', $id)->update([
                'Status' => $ready ? SopStatus::VERSI_READY : SopStatus::VERSI_DRAFT,
                'Updated_By' => $userId,
                'Updated_At' => now(),
            ]);

            return true;
        });
    }

    public function deleteVersion(int $id, int $userId): bool
    {
        $row = DB::table(self::ITEM_VERSION)->where('Id_SOP_Draft_Item_Versi', $id)->first();
        if (! $row || $row->Status !== SopStatus::VERSI_DRAFT) {
            return false;
        }
        DB::table(self::ITEM_VERSION)->where('Id_SOP_Draft_Item_Versi', $id)->update(['Status' => 'DISCARDED', 'Updated_By' => $userId, 'Updated_At' => now()]);

        return true;
    }

    public function discardCandidateVersions(int $idDraftItem, int $userId): void
    {
        DB::table(self::ITEM_VERSION)->where('Id_SOP_Draft_Item', $idDraftItem)
            ->whereIn('Status', [SopStatus::VERSI_DRAFT, SopStatus::VERSI_READY])
            ->update(['Status' => 'DISCARDED', 'Updated_By' => $userId, 'Updated_At' => now()]);
    }

    public function clearVersionTarget(int $idDraftItem, int $userId): void
    {
        DB::table(self::ITEM)->where('Id_SOP_Draft_Item', $idDraftItem)->update([
            'Id_SOP_Item_Versi_Target' => null, 'Updated_By' => $userId, 'Updated_At' => now(),
        ]);
    }

    /** Ringkasan perubahan working copy terhadap data aktif. Hanya READY yang dihitung sebagai kandidat versi. */
    public function pendingChanges(int $idPaket, int $userId): array
    {
        $draft = $this->ensure($idPaket, $userId);
        if (! $draft) {
            return ['total' => 0, 'is_first_release' => false, 'summary' => [], 'details' => [], 'errors' => []];
        }

        $packageFields = ['Kode_Paket', 'Nama_Paket', 'Kode_Karyawan_Owner', 'Deskripsi',
            'Tujuan_Ruang_Lingkup', 'Prioritas', 'Estimasi_Waktu_Baca_Menit', 'Mode_Urutan_Item',
            'Mode_Distribusi', 'Cover_Path', 'Cover_Position', 'Flag_Absensi'];
        $activeHeader = DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $idPaket)->first();
        $metadata = $this->rowsDiffer($activeHeader, $draft, $packageFields) ? 1 : 0;

        $activeSections = DB::table('N_SOP_Paket_Section')->where('Id_SOP_Paket', $idPaket)
            ->where('Flag_Aktif', SopStatus::AKTIF)->orderBy('Urutan')->get();
        $draftSections = DB::table(self::SECTION)->where('Id_SOP_Paket_Draft', $draft->Id_SOP_Paket_Draft)
            ->where('Flag_Aktif', SopStatus::AKTIF)->orderBy('Urutan')->get();
        $sectionChanges = $this->collectionDiffCount($activeSections, $draftSections,
            'Id_SOP_Section', 'Id_SOP_Section_Asal', ['Nama_Section', 'Deskripsi', 'Urutan']);

        $activeItems = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket', $idPaket)
            ->where('Flag_Aktif', SopStatus::AKTIF)->orderBy('Urutan')->get();
        $draftItems = DB::table(self::ITEM)->where('Id_SOP_Paket_Draft', $draft->Id_SOP_Paket_Draft)
            ->where('Flag_Aktif', SopStatus::AKTIF)->orderBy('Urutan')->get();
        $itemFields = ['Tipe_Item', 'Judul_Item', 'Nomor_SOP_Resmi', 'Deskripsi_Item', 'Konten_Teks', 'Mode_Akses',
            'URL_Tautan', 'Label_Tautan', 'Jenis_Quiz', 'Nilai_Minimum', 'Id_Master_Ujian', 'Nama_Ujian',
            'Flag_Camera', 'Urutan', 'Flag_Wajib', 'Flag_Highlight', 'Flag_Watermark'];
        $itemChanges = $this->itemDiffCount($activeItems, $draftItems, $draftSections, $itemFields);
        $details = [
            'package' => $this->packageChangeDetails($activeHeader, $draft, $packageFields),
            'sections' => $this->sectionChangeDetails($activeSections, $draftSections),
            'items' => $this->itemChangeDetails($activeItems, $draftItems, $draftSections, $itemFields),
            'versions' => $this->versionChangeDetails($draft),
        ];

        $ready = DB::table(self::ITEM_VERSION.' as v')->join(self::ITEM.' as i', 'i.Id_SOP_Draft_Item', '=', 'v.Id_SOP_Draft_Item')
            ->where('i.Id_SOP_Paket_Draft', $draft->Id_SOP_Paket_Draft)->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('v.Status', SopStatus::VERSI_READY)->count();
        $draftVersions = DB::table(self::ITEM_VERSION.' as v')->join(self::ITEM.' as i', 'i.Id_SOP_Draft_Item', '=', 'v.Id_SOP_Draft_Item')
            ->where('i.Id_SOP_Paket_Draft', $draft->Id_SOP_Paket_Draft)->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('v.Status', SopStatus::VERSI_DRAFT)->count();

        $errors = $this->validationErrors($draft);
        $summary = [
            'package' => $metadata,
            'sections' => $sectionChanges,
            'items' => $itemChanges,
            'metadata' => $metadata,
            'section_updates' => $sectionChanges,
            'draft_item_versions' => (int) $draftVersions,
            'ready_item_versions' => (int) $ready,
        ];
        $total = $metadata + $sectionChanges + $itemChanges + (int) $ready;

        return [
            'total' => $total,
            'is_first_release' => false,
            'can_publish' => $total > 0 && empty($errors),
            'summary' => $summary,
            'details' => $details,
            'errors' => $errors,
        ];
    }

    /** Terapkan satu working copy dan semua kandidat READY ke tabel aktif dalam satu transaksi. */
    /**
     * @param  string  $sifatRevisi  'Y' = mayor (wajib baca ulang, default),
     *                               'T' = minor (penyelesaian karyawan dibawa).
     *                               Berlaku untuk versi item yang TIDAK disebut
     *                               dalam $sifatPerDokumen.
     * @param  array<int,string>  $sifatPerDokumen  Id_SOP_Dokumen => 'Y'|'T'.
     *                                              Satu publish paket bisa menaikkan beberapa
     *                                              dokumen sekaligus, dan sifatnya tak selalu sama:
     *                                              satu dokumen bisa cuma salah ketik sementara yang
     *                                              lain benar-benar berubah isinya. Satu saklar untuk
     *                                              semuanya memaksa admin memilih yang paling ketat
     *                                              — dan karena kalau ragu orang memilih mayor,
     *                                              hasilnya sama saja dengan tak punya pilihan.
     *
     *                            Dokumen yang tak disebut jatuh ke $sifatRevisi,
     *                            dan $sifatRevisi sendiri berbaku mayor. Arahnya
     *                            disengaja: membaca ulang itu murah, melewatkan
     *                            revisi penting tidak.
     */
    public function publish(
        int $idPaket,
        int $userId,
        string $ringkasan,
        string $sifatRevisi = 'Y',
        array $sifatPerDokumen = [],
    ): array {
        $hasil = DB::transaction(function () use ($idPaket, $userId, $ringkasan, $sifatRevisi, $sifatPerDokumen) {
            $paket = DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $idPaket)->lockForUpdate()->first();
            $draft = DB::table(self::HEADER)->where('Id_SOP_Paket', $idPaket)->where('Status', 'DRAFT')->lockForUpdate()->first();
            if (! $paket || ! $draft) {
                throw new \RuntimeException('Working draft paket tidak ditemukan.');
            }
            if ((int) ($draft->Id_SOP_Paket_Versi_Basis ?? 0) !== (int) ($paket->Id_SOP_Paket_Versi_Aktif ?? 0)) {
                throw new \DomainException('Versi aktif paket telah berubah. Muat ulang Builder sebelum publish.');
            }

            $changes = $this->pendingChanges($idPaket, $userId);
            if (! empty($changes['errors'])) {
                throw new \DomainException(implode(' ', $changes['errors']));
            }
            if (($changes['total'] ?? 0) < 1) {
                throw new \DomainException('Belum ada perubahan yang siap dirilis.');
            }

            DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $idPaket)->update([
                'Kode_Paket' => $draft->Kode_Paket, 'Nama_Paket' => $draft->Nama_Paket,
                'Kode_Karyawan_Owner' => $draft->Kode_Karyawan_Owner,
                'Deskripsi' => $draft->Deskripsi, 'Tujuan_Ruang_Lingkup' => $draft->Tujuan_Ruang_Lingkup,
                'Prioritas' => $draft->Prioritas, 'Estimasi_Waktu_Baca_Menit' => $draft->Estimasi_Waktu_Baca_Menit,
                'Mode_Urutan_Item' => $draft->Mode_Urutan_Item, 'Mode_Distribusi' => $draft->Mode_Distribusi,
                'Cover_Path' => $draft->Cover_Path, 'Cover_Position' => $draft->Cover_Position,
                'Flag_Absensi' => $draft->Flag_Absensi, 'Updated_By' => $userId, 'Updated_At' => now(),
            ]);

            $sectionMap = $this->applySections($draft, $idPaket, $userId);
            $this->applyItems($draft, $idPaket, $sectionMap, $userId, $sifatRevisi, $sifatPerDokumen);

            $release = app(SopPaketVersionService::class)->release($idPaket, 'PAKET_PUBLISH', $userId, null, $ringkasan, false);
            if (! $release || empty($release['created'])) {
                throw new \RuntimeException('Snapshot versi paket baru tidak berhasil dibuat.');
            }

            // Working copy tetap hidup agar kandidat DRAFT yang belum siap tidak kehilangan identitas.
            DB::table(self::HEADER)->where('Id_SOP_Paket_Draft', $draft->Id_SOP_Paket_Draft)->update([
                'Id_SOP_Paket_Versi_Basis' => $release['id'], 'Updated_By' => $userId, 'Updated_At' => now(),
            ]);

            return ['version' => $release];
        });

        /* DI LUAR TRANSAKSI: diff untuk LAYAR, bukan untuk keputusan.
           pendingChanges() dipanggil dua kali pada publish. Yang pertama (di
           dalam, sebelum mutasi) adalah validasi dan wajib berada di bawah
           kunci yang sama dengan mutasinya. Yang kedua hanya mengisi payload
           respons — dan menjalankannya di dalam berarti seluruh pekerjaan diff
           berjalan sementara kunci N_SOP_Master_Paket dan header draft masih
           dipegang. Dengan RCSI mati di Web_HRIS, setiap pembaca paket itu
           antre di belakangnya tanpa alasan.

           Bentuk nilai balik sengaja tidak berubah — pemanggil tetap membaca
           ['version'] dan ['pending']. */
        $hasil['pending'] = $this->pendingChanges($idPaket, $userId);

        return $hasil;
    }

    private function applySections(object $draft, int $idPaket, int $userId): array
    {
        $rows = DB::table(self::SECTION)->where('Id_SOP_Paket_Draft', $draft->Id_SOP_Paket_Draft)->get();
        $kept = [];
        $map = [];
        foreach ($rows->where('Flag_Aktif', SopStatus::AKTIF)->sortBy('Urutan') as $row) {
            $payload = ['Nama_Section' => $row->Nama_Section, 'Deskripsi' => $row->Deskripsi, 'Urutan' => $row->Urutan,
                'Flag_Aktif' => SopStatus::AKTIF, 'Updated_By' => $userId, 'Updated_At' => now()];
            $activeId = $row->Id_SOP_Section_Asal;
            if ($activeId) {
                DB::table('N_SOP_Paket_Section')->where('Id_SOP_Section', $activeId)->where('Id_SOP_Paket', $idPaket)->update($payload);
            } else {
                $activeId = DB::table('N_SOP_Paket_Section')->insertGetId(array_merge($payload, [
                    'Id_SOP_Paket' => $idPaket, 'Created_By' => $userId, 'Created_At' => now(),
                ]), 'Id_SOP_Section');
                DB::table(self::SECTION)->where('Id_SOP_Draft_Section', $row->Id_SOP_Draft_Section)->update(['Id_SOP_Section_Asal' => $activeId]);
            }
            $kept[] = (int) $activeId;
            $map[(int) $row->Id_SOP_Draft_Section] = (int) $activeId;
        }
        DB::table('N_SOP_Paket_Section')->where('Id_SOP_Paket', $idPaket)->whereNotIn('Id_SOP_Section', $kept ?: [0])
            ->update(['Flag_Aktif' => SopStatus::INAKTIF, 'Updated_By' => $userId, 'Updated_At' => now()]);

        return $map;
    }

    private function applyItems(
        object $draft,
        int $idPaket,
        array $sectionMap,
        int $userId,
        string $sifatRevisi = 'Y',
        array $sifatPerDokumen = [],
    ): void {
        $rows = DB::table(self::ITEM)->where('Id_SOP_Paket_Draft', $draft->Id_SOP_Paket_Draft)->get();
        $kept = [];
        foreach ($rows->where('Flag_Aktif', SopStatus::AKTIF)->sortBy('Urutan') as $row) {
            $payload = [
                'Id_SOP_Section' => $sectionMap[(int) $row->Id_SOP_Draft_Section] ?? null,
                'Tipe_Item' => $row->Tipe_Item, 'Judul_Item' => $row->Judul_Item, 'Nomor_SOP_Resmi' => $row->Nomor_SOP_Resmi,
                'Deskripsi_Item' => $row->Deskripsi_Item, 'Konten_Teks' => $row->Konten_Teks, 'Mode_Akses' => $row->Mode_Akses,
                'URL_Tautan' => $row->URL_Tautan, 'Label_Tautan' => $row->Label_Tautan, 'Jenis_Quiz' => $row->Jenis_Quiz,
                'Nilai_Minimum' => $row->Nilai_Minimum, 'Id_Master_Ujian' => $row->Id_Master_Ujian, 'Nama_Ujian' => $row->Nama_Ujian,
                'Flag_Camera' => $row->Flag_Camera, 'Urutan' => $row->Urutan, 'Flag_Wajib' => $row->Flag_Wajib,
                'Flag_Highlight' => $row->Flag_Highlight, 'Flag_Watermark' => $row->Flag_Watermark,
                'Flag_Aktif' => SopStatus::AKTIF,
                'Updated_By' => $userId, 'Updated_At' => now(),
            ];
            $activeId = $row->Id_SOP_Paket_Item_Asal;
            if ($activeId) {
                /* IDENTITAS TAK IKUT TERBIT untuk item yang sudah bertaut
                   dokumen. Judul & nomornya adalah cerminan dengan satu penulis
                   — sinkronIdentitasKeItem() dari dokumennya — sedangkan nilai
                   di payload ini potret lama milik overlay. Menuliskannya
                   kembali memundurkan cerminan yang sudah benar, dan diff
                   berikutnya melaporkannya lagi sebagai perubahan: lingkaran
                   yang tak punya pintu keluar, sebab formnya sendiri sudah tak
                   punya field untuk membetulkannya.

                   Item TANPA dokumen tak lewat sini: bagi mereka overlay masih
                   satu-satunya penulis identitas. */
                $update = $payload;
                if (DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $activeId)
                    ->whereNotNull('Id_SOP_Dokumen')->exists()) {
                    foreach (self::ITEM_IDENTITY_FIELDS as $field) {
                        unset($update[$field]);
                    }
                }
                DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $activeId)->where('Id_SOP_Paket', $idPaket)->update($update);
            } else {
                $activeId = DB::table('N_SOP_Paket_Item')->insertGetId(array_merge($payload, [
                    'Id_SOP_Paket' => $idPaket, 'Created_By' => $userId, 'Created_At' => now(),
                ]), 'Id_SOP_Paket_Item');
                DB::table(self::ITEM)->where('Id_SOP_Draft_Item', $row->Id_SOP_Draft_Item)->update(['Id_SOP_Paket_Item_Asal' => $activeId]);
            }

            /* ITEM YANG LAHIR DI OVERLAY DENGAN DOKUMEN PILIHAN.
               Sebelum DDL 33, satu-satunya nasib item baru di paket terbit
               adalah mendapat dokumen BARU saat promosi (ensureForItem di
               bawah) — sehingga alur pembetulan yang benar, "hapus item ber-SOP
               salah lalu tambahkan item ber-SOP benar", berhenti di langkah
               kedua: SOP yang benar tak pernah bisa dipilih.

               Ditautkan lewat tautkanDokumen() yang SAMA dengan jalur salin dan
               jalur Builder — ia yang mengisi pointer versi aktif (hanya bila
               PUBLISHED) dan mencerminkan identitasnya. */
            if ($this->dukungDokumen() && ! empty($row->Id_SOP_Dokumen)
                && DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $activeId)
                    ->whereNull('Id_SOP_Dokumen')->exists()) {
                app(SopItemService::class)->tautkanDokumen(
                    (int) $activeId, (int) $row->Id_SOP_Dokumen, $userId);
            }

            $ready = DB::table(self::ITEM_VERSION)->where('Id_SOP_Draft_Item', $row->Id_SOP_Draft_Item)
                ->where('Status', SopStatus::VERSI_READY)->orderByDesc('Versi_Ke')->lockForUpdate()->first();
            // Target draft sudah menyalin Id_Item_Versi_Aktif apa adanya saat ensure(), dan
            // controller mengosongkannya lewat clearVersionTarget() ketika tipe item berubah.
            // Menyaring ulang dengan isVersionedItem() di sini menghapus pointer milik item
            // yang tipenya tak dianggap versioned (mis. PARAGRAF berkonten versi) — data loss.
            /* PUBLISH PAKET TAK BOLEH MEMUNDURKAN REVISI DOKUMEN.
               Target di atas adalah SALINAN yang diambil saat draft dibuka.
               Bila Master menerbitkan revisi dokumen sesudah itu, menulis
               salinan lama kembali akan memundurkan karyawan ke versi yang
               sudah dicabut — dan pointer aktif jadi menunjuk baris OBSOLETE,
               melanggar invarian yang ditegakkan seluruh modul ini.

               Keputusan produk 2026-08-10: revisi dokumen menang. Aturan
               "publish paket dulu" tetap berlaku untuk milik paket — susunan
               item, urutan, penugasan — bukan untuk revisi dokumen, yang
               memang berlaku serentak di semua paket pemakainya. */
            $pointer = $this->pointerTakMundur($activeId, $row->Id_SOP_Item_Versi_Target);
            if ($ready) {
                /* Item live hasil promosi draft lahir tanpa Id_SOP_Dokumen:
                   payload-nya berasal dari tabel draft, yang tak punya kolom
                   itu. Tanpa baris ini, setiap penerbitan revisi paket
                   meluruhkan backfill Fase 1 satu item pada satu waktu. */
                $idDokumen = app(\App\Services\LMS\SOP\SopDokumenService::class)->ensureForItem($activeId, $userId);

                /* SUMBU PENOMORAN IKUT DOKUMEN, bukan item — tertinggal sejak
                   Fase 2. Sumbu item menghitung ulang dari nol untuk tiap item,
                   sehingga dokumen yang sudah di v2 lewat Master akan ditulisi
                   v2 lagi dari sini, dan unique index terfilter 26B
                   (Id_SOP_Dokumen, Versi_Ke) menolaknya dengan galat 2601 —
                   publish paket GAGAL, bukan salah diam-diam. */
                /* Sumbu DOKUMEN lewat penulis tunggal SopNomorVersi — saringan
                   yang sama dengan indeks unik terfilter DDL-2026-062. Menghitung
                   MAX sendiri di sini berarti pintu ini menawarkan nomor yang
                   basis data tolak begitu ada versi batal di riwayatnya. */
                $activeVersion = $idDokumen !== null
                    ? SopNomorVersi::berikutnya($idDokumen)
                    : ((int) DB::table('N_SOP_Item_Versi')->where('Id_SOP_Paket_Item', $activeId)->max('Versi_Ke')) + 1;

                /* Baris lahir sebagai DRAFT lalu diterbitkan lewat jalur resmi
                   di bawah. Meng-obsolete-kan versi lama di sini secara mentah
                   hanya berkunci ITEM, sehingga paket lain yang memakai dokumen
                   yang sama tetap menganggap versi lamanya sah. */
                $pointer = DB::table('N_SOP_Item_Versi')->insertGetId([
                    'Id_SOP_Paket_Item' => $activeId, 'Id_SOP_Dokumen' => $idDokumen,
                    'Versi_Ke' => $activeVersion, 'Status' => SopStatus::PAKET_DRAFT,
                    'Id_Master_Konten' => $ready->Id_Master_Konten, 'Id_Master_Ujian' => $ready->Id_Master_Ujian,
                    /* Nama ujian diambil dari ITEM, bukan dari baris versi draft:
                       N_SOP_Draft_Versi memang tak punya kolom itu, sedangkan
                       item draft menyimpannya sejak dipilih. Tanpa dititipkan di
                       sini, versi terbitan Builder sampai ke Master SOP tanpa
                       nama dan cuma bisa disebut "ujian tertaut". */
                    'Nama_Ujian' => $row->Nama_Ujian ?? null,
                    'Ringkasan_Perubahan' => $ready->Ringkasan_Perubahan, 'Tanggal_Berlaku' => $ready->Tanggal_Berlaku,
                    'Disahkan_Oleh' => $ready->Disahkan_Oleh, 'Jabatan_Pengesah' => $ready->Jabatan_Pengesah,
                    'Tanggal_Pengesahan' => $ready->Tanggal_Pengesahan, 'Nomor_Pengesahan' => $ready->Nomor_Pengesahan,
                    'Created_By' => $userId, 'Created_At' => now(), 'Flag_Aktif' => SopStatus::AKTIF,
                ], 'Id_SOP_Item_Versi');

                /* Penerbitan lewat jalur yang sama dengan Master, bukan salinan
                   mentahnya. terbitkanVersi() meng-obsolete-kan versi lama
                   BERKUNCI DOKUMEN, menggeser pointer pada SEMUA item perujuk,
                   mematikan integrasi ujian versi lama, dan memperbarui
                   Id_Versi_Aktif dokumennya. Yang terakhir itu tak pernah
                   dilakukan jalur ini sebelumnya, sehingga dokumen menyebut
                   versi lama masih berlaku sementara itemnya sudah pindah.

                   Item tanpa dokumen (tipe tak ber-versi) tak punya jalur itu;
                   untuk mereka penerbitan tetap dilakukan setempat. */
                /* Sifat per DOKUMEN, jatuh ke pilihan menyeluruh bila dokumen ini
                   tak disebut — termasuk untuk item yang baru lahir di publish
                   ini, yang dokumennya memang belum ada saat pratinjau dihitung.
                   Nilai selain 'T' diperlakukan mayor: masukan yang tak dikenali
                   tak boleh berujung pada pelonggaran. */
                $sifatItem = ($sifatPerDokumen[$idDokumen] ?? $sifatRevisi) === 'T' ? 'T' : 'Y';

                $terbit = $idDokumen !== null
                    /* $idPaket sebagai kecualiPaket: paket INI direkonsiliasi
                       lewat alurnya sendiri (reconcileSetelahPublish), dan
                       merekonsiliasinya dua kali berarti karyawan yang sama
                       disisir dua kali untuk satu peristiwa. */
                    && app(\App\Services\LMS\SOP\SopDokumenService::class)
                        ->terbitkanVersi((int) $pointer, $userId, $sifatItem, $idPaket);

                if (! $terbit) {
                    DB::table('N_SOP_Item_Versi')->where('Id_SOP_Paket_Item', $activeId)
                        ->where('Id_SOP_Item_Versi', '<>', $pointer)
                        ->where('Status', SopStatus::VERSI_PUBLISHED)
                        ->update(['Status' => SopStatus::VERSI_OBSOLETE, 'Updated_By' => $userId, 'Updated_At' => now()]);
                    DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $pointer)->update([
                        'Status' => SopStatus::VERSI_PUBLISHED, 'Tanggal_Publish' => now(),
                        'Kode_Karyawan_Publisher' => Auth::user()?->karyawan?->Kode_Karyawan,
                        'Updated_By' => $userId, 'Updated_At' => now(),
                    ]);
                }

                DB::table(self::ITEM_VERSION)->where('Id_SOP_Draft_Item_Versi', $ready->Id_SOP_Draft_Item_Versi)
                    ->update(['Status' => 'DISCARDED', 'Updated_By' => $userId, 'Updated_At' => now()]);
                DB::table(self::ITEM_VERSION)->where('Id_SOP_Draft_Item', $row->Id_SOP_Draft_Item)
                    ->where('Status', SopStatus::VERSI_READY)
                    ->update(['Status' => SopStatus::VERSI_DRAFT, 'Updated_By' => $userId, 'Updated_At' => now()]);
                DB::table(self::ITEM)->where('Id_SOP_Draft_Item', $row->Id_SOP_Draft_Item)->update(['Id_SOP_Item_Versi_Target' => $pointer]);
            }
            DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $activeId)->update(['Id_Item_Versi_Aktif' => $pointer]);
            $kept[] = (int) $activeId;
        }
        DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket', $idPaket)->whereNotIn('Id_SOP_Paket_Item', $kept ?: [0])
            ->update(['Flag_Aktif' => SopStatus::INAKTIF, 'Updated_By' => $userId, 'Updated_At' => now()]);
    }

    /**
     * Pointer versi untuk item yang TIDAK membawa versi baru dari draftnya.
     *
     * Urutan kebenarannya, dari yang paling berhak:
     *   1. Versi aktif DOKUMEN — ditetapkan saat revisi diterbitkan, dan
     *      berlaku untuk semua paket pemakainya sekaligus
     *   2. Salinan yang dibawa draft, SELAMA masih berstatus PUBLISHED
     *   3. Salinan itu apa adanya — supaya kegagalan menyelesaikan tak pernah
     *      berubah menjadi penghapusan pointer yang sudah ada
     *
     * Yang dicegah: draft yang dibuka sebelum revisi, lalu diterbitkan
     * sesudahnya, menulis balik versi lama tanpa siapa pun memintanya.
     */
    private function pointerTakMundur(int $idItem, $target)
    {
        $dok = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'i.Id_SOP_Dokumen')
            ->where('i.Id_SOP_Paket_Item', $idItem)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->value('d.Id_Versi_Aktif');

        if ($dok) {
            return (int) $dok;
        }

        if ($target) {
            $status = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $target)->value('Status');
            if ($status === SopStatus::VERSI_PUBLISHED) {
                return $target;
            }
        }

        return $target;
    }

    /**
     * Apa yang akan terjadi bila paket ini diterbitkan — termasuk ke paket LAIN.
     *
     * KENAPA PAKET LAIN. Sejak dokumen bisa dipakai banyak paket, menerbitkan
     * paket ini bisa menerbitkan revisi dokumen yang juga disajikan paket lain.
     * Pointer mereka ikut bergeser, dan karyawan mereka ikut terdampak — tanpa
     * admin pernah membuka paket itu. Menerbitkan sambil mengira hanya
     * menyentuh paket sendiri adalah dugaan yang salah dengan akibat nyata.
     *
     * Versi_Sekarang dibaca dari pointer LIVE tiap item, bukan dari dokumen:
     * itulah yang benar-benar disajikan paket masing-masing hari ini.
     */
    public function dampakPublish(int $idPaket): array
    {
        $draft = DB::table(self::HEADER)->where('Id_SOP_Paket', $idPaket)
            ->where('Status', 'DRAFT')->first(['Id_SOP_Paket_Draft']);
        if (! $draft) {
            return ['versi' => [], 'paket_lain' => 0, 'karyawan_lain' => 0];
        }

        /* Hanya versi SIAP RILIS yang akan benar-benar terbit. Draft yang belum
           ditandai siap tak ikut, dan menyebutnya di pratinjau akan menjanjikan
           sesuatu yang tak terjadi. */
        $ready = DB::table(self::ITEM_VERSION.' as dv')
            ->join(self::ITEM.' as di', 'di.Id_SOP_Draft_Item', '=', 'dv.Id_SOP_Draft_Item')
            /* Disaring lewat ITEM-nya: tabel versi draft tak punya kolom
               Id_SOP_Paket_Draft — ia menempel pada draft hanya lewat
               itemnya. */
            ->where('di.Id_SOP_Paket_Draft', $draft->Id_SOP_Paket_Draft)
            ->where('dv.Status', SopStatus::VERSI_READY)
            ->where('di.Flag_Aktif', SopStatus::AKTIF)
            ->get(['di.Id_SOP_Draft_Item', 'di.Id_SOP_Paket_Item_Asal', 'di.Judul_Item', 'di.Tipe_Item']);

        if ($ready->isEmpty()) {
            return ['versi' => [], 'paket_lain' => 0, 'karyawan_lain' => 0];
        }

        $idItemAsal = $ready->pluck('Id_SOP_Paket_Item_Asal')->filter()->all();

        /* Dokumen + versi yang SEDANG disajikan, per item live. Item baru (tanpa
           asal) belum punya dokumen, jadi belum menyentuh paket mana pun. */
        $live = $idItemAsal === [] ? collect() : DB::table('N_SOP_Paket_Item as i')
            ->leftJoin('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'i.Id_Item_Versi_Aktif')
            ->whereIn('i.Id_SOP_Paket_Item', $idItemAsal)
            ->get(['i.Id_SOP_Paket_Item', 'i.Id_SOP_Dokumen', 'v.Versi_Ke'])
            ->keyBy('Id_SOP_Paket_Item');

        $idDokumen = $live->pluck('Id_SOP_Dokumen')->filter()->unique()->all();

        // Nomor berikut per dokumen — sumbu penomoran sejak Fase 2.
        $maksVersi = $idDokumen === [] ? collect() : DB::table('N_SOP_Item_Versi')
            ->whereIn('Id_SOP_Dokumen', $idDokumen)
            ->select('Id_SOP_Dokumen', DB::raw('MAX(Versi_Ke) as maks'))
            ->groupBy('Id_SOP_Dokumen')->pluck('maks', 'Id_SOP_Dokumen');

        /* Paket LAIN yang menyajikan dokumen yang sama, berikut jangkauannya.
           Paket ini sendiri dikecualikan: ia bukan "paket lain", dan
           memasukkannya membuat angka totalnya menghitung ganda. */
        $paketLain = $idDokumen === [] ? collect() : DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->whereIn('i.Id_SOP_Dokumen', $idDokumen)
            ->where('i.Id_SOP_Paket', '<>', $idPaket)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->select('i.Id_SOP_Dokumen', 'p.Id_SOP_Paket', 'p.Nama_Paket')
            ->distinct()->get()->groupBy('Id_SOP_Dokumen');

        $semuaPaketLain = $paketLain->flatten(1)->pluck('Id_SOP_Paket')->unique();

        $orang = $semuaPaketLain->isEmpty() ? collect() : DB::table('N_SOP_Penjadwalan as pj')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan', '=', 'pj.Id_SOP_Penjadwalan')
            ->whereIn('pj.Id_SOP_Paket', $semuaPaketLain->all())
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->groupBy('pj.Id_SOP_Paket')
            ->selectRaw('pj.Id_SOP_Paket, COUNT(DISTINCT d.Kode_Karyawan) as n')
            ->pluck('n', 'Id_SOP_Paket');

        $versi = $ready->map(function ($r) use ($live, $maksVersi, $paketLain, $orang) {
            $l = $r->Id_SOP_Paket_Item_Asal ? ($live[$r->Id_SOP_Paket_Item_Asal] ?? null) : null;
            $dok = $l->Id_SOP_Dokumen ?? null;

            return [
                /* Identitas baris, supaya pilihan sifat revisi bisa ditautkan ke
                   dokumen yang dimaksud. Item BARU tak punya dokumen sampai ia
                   diterbitkan — dan memang tak perlu: kewajibannya baru apa pun
                   sifat revisinya, jadi barisnya tak menawarkan pilihan. */
                /* Hashids polos, BUKAN enc() milik kelas ini — enc() menempelkan
                   awalan token overlay draft (di_/dv_), dan id dokumen ini
                   menunjuk tabel live. Menyamarkannya sebagai token draft persis
                   jebakan yang sudah pernah menggigit modul ini. */
                'Id_SOP_Dokumen' => $dok !== null
                    ? Hashids::connection('custom')->encode((int) $dok)
                    : null,
                'Judul_Item' => $r->Judul_Item,
                'Tipe_Item' => $r->Tipe_Item,
                'Versi_Sekarang' => $l && $l->Versi_Ke !== null ? (int) $l->Versi_Ke : null,
                'Versi_Baru' => $dok !== null && isset($maksVersi[$dok]) ? ((int) $maksVersi[$dok]) + 1 : 0,
                'Paket_Lain' => collect($dok !== null ? ($paketLain[$dok] ?? []) : [])
                    ->map(fn ($p) => [
                        'Nama_Paket' => $p->Nama_Paket,
                        'Jumlah_Karyawan' => (int) ($orang[$p->Id_SOP_Paket] ?? 0),
                    ])->values()->all(),
            ];
        })->values()->all();

        return [
            'versi' => $versi,
            'paket_lain' => $semuaPaketLain->count(),
            'karyawan_lain' => (int) $orang->sum(),
        ];
    }

    private function validationErrors(object $draft): array
    {
        $sections = DB::table(self::SECTION)->where('Id_SOP_Paket_Draft', $draft->Id_SOP_Paket_Draft)
            ->where('Flag_Aktif', SopStatus::AKTIF)->get();
        if ($sections->isEmpty()) {
            return ['Tambahkan minimal satu section sebelum publish.'];
        }

        $items = DB::table(self::ITEM)->where('Id_SOP_Paket_Draft', $draft->Id_SOP_Paket_Draft)
            ->where('Flag_Aktif', SopStatus::AKTIF)->get();
        $errors = [];
        foreach ($sections as $section) {
            if (! $items->contains('Id_SOP_Draft_Section', $section->Id_SOP_Draft_Section)) {
                $errors[] = "Section '{$section->Nama_Section}' masih kosong.";
            }
        }
        /*
         * Keanggotaan "punya versi Siap Rilis" ditanyakan SEKALI untuk seluruh
         * item, bukan satu exists() per item. Jumlah itemnya sebesar isi paket
         * SOP, dan pemeriksaan ini berjalan tiap kali draft divalidasi.
         */
        $punyaVersiSiap = DB::table(self::ITEM_VERSION)
            ->whereIn('Id_SOP_Draft_Item', $items->pluck('Id_SOP_Draft_Item')->all())
            ->where('Status', SopStatus::VERSI_READY)
            ->distinct()
            ->pluck('Id_SOP_Draft_Item')
            ->flip();

        foreach ($items as $item) {
            if (! $this->isVersionedItem($item)) {
                continue;
            }
            $hasReady = $punyaVersiSiap->has($item->Id_SOP_Draft_Item);
            if (! $item->Id_SOP_Item_Versi_Target && ! $hasReady) {
                $errors[] = "Item '{$item->Judul_Item}' belum memiliki versi Siap Rilis.";
            }
        }

        /* PAKET PUBLIK TAK BOLEH MEMBAWA KUIS (keputusan 10 Sep 2026).
           Dibaca dari DRAFT, bukan dari paket hidup: distribusi dan daftar item
           yang akan berlaku sesudah publish adalah milik draft ini, dan
           publish() memang menyalin `$draft->Mode_Distribusi` ke paket. Menilai
           paket hidup di sini berarti memeriksa keadaan yang justru sedang
           diganti — lolos pada draft yang membalik distribusinya menjadi
           publik, dan menolak pada draft yang justru sedang membereskannya.

           Penilainya milik SopGerbangPublikKuis, sama dengan pintu publish
           satunya di SopPaketService. */
        $judulKuis = $items
            ->where('Tipe_Item', SopTipeItem::KUIS)
            ->pluck('Judul_Item')
            ->values()
            ->all();

        if (SopGerbangPublikKuis::dilanggar($draft->Mode_Distribusi ?? null, $judulKuis)) {
            $errors[] = SopGerbangPublikKuis::pesan($judulKuis);
        }

        return $errors;
    }

    private function packageChangeDetails(?object $active, object $draft, array $fields): array
    {
        if (! $active) {
            return [['type' => 'package', 'action' => 'added', 'title' => $draft->Nama_Paket ?? 'Paket Dokumen baru', 'fields' => []]];
        }

        $changed = [];
        foreach ($fields as $field) {
            if ((string) ($active->{$field} ?? '') !== (string) ($draft->{$field} ?? '')) {
                $changed[] = $this->fieldLabel($field);
            }
        }

        return empty($changed) ? [] : [[
            'type' => 'package',
            'action' => 'changed',
            'title' => $draft->Nama_Paket ?? $active->Nama_Paket ?? 'Identitas paket',
            'fields' => $changed,
        ]];
    }

    private function sectionChangeDetails($activeSections, $draftSections): array
    {
        $activeById = $activeSections->keyBy('Id_SOP_Section');
        $draftSourceIds = [];
        $details = [];

        // Entri membawa 'id' bertoken sama dengan baris struktur (ds_/di_), supaya panel
        // Struktur bisa memetakan badge ke barisnya tanpa mencocokkan judul — judul tidak
        // unik dan dua section bernama sama akan saling tertukar badge.
        // Entri 'removed' sengaja TANPA id: barisnya hanya ada di tabel aktif dan tak punya
        // padanan di daftar struktur; ia hanya dipakai sebagai hitungan.
        foreach ($draftSections as $section) {
            $source = $section->Id_SOP_Section_Asal;
            if (! $source || ! $activeById->has($source)) {
                $details[] = ['type' => 'section', 'action' => 'added', 'id' => $this->enc('ds', (int) $section->Id_SOP_Draft_Section), 'title' => $section->Nama_Section, 'fields' => []];

                continue;
            }

            $draftSourceIds[] = (int) $source;
            $active = $activeById->get($source);
            $changed = [];
            foreach (['Nama_Section', 'Deskripsi', 'Urutan'] as $field) {
                if ((string) ($active->{$field} ?? '') !== (string) ($section->{$field} ?? '')) {
                    $changed[] = $this->fieldLabel($field);
                }
            }
            if ($changed) {
                $details[] = ['type' => 'section', 'action' => 'changed', 'id' => $this->enc('ds', (int) $section->Id_SOP_Draft_Section), 'title' => $section->Nama_Section, 'fields' => $changed];
            }
        }

        foreach ($activeSections as $section) {
            if (! in_array((int) $section->Id_SOP_Section, $draftSourceIds, true)) {
                $details[] = ['type' => 'section', 'action' => 'removed', 'title' => $section->Nama_Section, 'fields' => []];
            }
        }

        return $details;
    }

    private function itemChangeDetails($activeItems, $draftItems, $draftSections, array $fields): array
    {
        $activeById = $activeItems->keyBy('Id_SOP_Paket_Item');
        $sectionSources = $draftSections->keyBy('Id_SOP_Draft_Section');
        $draftSourceIds = [];
        $details = [];

        foreach ($draftItems as $item) {
            $source = $item->Id_SOP_Paket_Item_Asal;
            if (! $source || ! $activeById->has($source)) {
                $details[] = ['type' => 'item', 'action' => 'added', 'id' => $this->enc('di', (int) $item->Id_SOP_Draft_Item), 'title' => $item->Judul_Item, 'subtitle' => $item->Tipe_Item, 'fields' => []];

                continue;
            }

            $draftSourceIds[] = (int) $source;
            /* Sama seperti itemDiffCount(): baku salinan diterapkan lebih dulu,
               kalau tidak daftar perubahan menyebut "Kamera" pada setiap item
               yang tak pernah disentuh siapa pun. */
            $active = $this->bakuItem($activeById->get($source));
            $changed = [];
            $draftSectionSource = $item->Id_SOP_Draft_Section ? ($sectionSources->get($item->Id_SOP_Draft_Section)->Id_SOP_Section_Asal ?? null) : null;
            if ((int) ($active->Id_SOP_Section ?? 0) !== (int) ($draftSectionSource ?? 0)) {
                $changed[] = 'Section';
            }
            foreach ($this->itemFieldsFor($fields, $active, $item) as $field) {
                if ((string) ($active->{$field} ?? '') !== (string) ($item->{$field} ?? '')) {
                    $changed[] = $this->fieldLabel($field);
                }
            }
            if ($changed) {
                $details[] = [
                    'type' => 'item',
                    'action' => 'changed',
                    'id' => $this->enc('di', (int) $item->Id_SOP_Draft_Item),
                    'title' => $this->judulTampil($active, $item),
                    'subtitle' => $item->Tipe_Item,
                    'fields' => array_values(array_unique($changed)),
                ];
            }
        }

        foreach ($activeItems as $item) {
            if (! in_array((int) $item->Id_SOP_Paket_Item, $draftSourceIds, true)) {
                $details[] = ['type' => 'item', 'action' => 'removed', 'title' => $item->Judul_Item, 'subtitle' => $item->Tipe_Item, 'fields' => []];
            }
        }

        return $details;
    }

    private function versionChangeDetails(object $draft): array
    {
        return DB::table(self::ITEM_VERSION.' as v')
            ->join(self::ITEM.' as i', 'i.Id_SOP_Draft_Item', '=', 'v.Id_SOP_Draft_Item')
            ->leftJoin('N_LMS_Master_Konten as k', 'k.Id_Master_Konten', '=', 'v.Id_Master_Konten')
            ->where('i.Id_SOP_Paket_Draft', $draft->Id_SOP_Paket_Draft)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->whereIn('v.Status', [SopStatus::VERSI_DRAFT, SopStatus::VERSI_READY])
            ->orderBy('i.Urutan')
            ->orderByDesc('v.Versi_Ke')
            ->get(['i.Id_SOP_Draft_Item', 'i.Judul_Item', 'i.Tipe_Item', 'v.Versi_Ke', 'v.Status', 'v.Ringkasan_Perubahan', 'k.Judul_Konten as Konten_Nama'])
            ->map(fn ($row) => [
                'type' => 'version',
                'action' => $row->Status === SopStatus::VERSI_READY ? 'ready' : 'draft',
                'id' => $this->enc('di', (int) $row->Id_SOP_Draft_Item),
                'title' => $row->Judul_Item,
                'subtitle' => trim('v'.$row->Versi_Ke.' '.($row->Konten_Nama ? '· '.$row->Konten_Nama : '')),
                'fields' => array_values(array_filter([$row->Ringkasan_Perubahan])),
            ])
            ->all();
    }

    private function fieldLabel(string $field): string
    {
        return [
            'Kode_Paket' => 'Kode paket',
            'Nama_Paket' => 'Nama paket',
            'Kode_Karyawan_Owner' => 'Owner',
            'Deskripsi' => 'Deskripsi',
            'Tujuan_Ruang_Lingkup' => 'Tujuan/ruang lingkup',
            'Prioritas' => 'Prioritas',
            'Estimasi_Waktu_Baca_Menit' => 'Estimasi baca',
            'Mode_Urutan_Item' => 'Urutan item',
            'Mode_Distribusi' => 'Distribusi',
            'Cover_Path' => 'Cover',
            'Cover_Position' => 'Posisi cover',
            'Flag_Absensi' => 'Absensi',
            'Nama_Section' => 'Nama section',
            'Urutan' => 'Urutan',
            'Tipe_Item' => 'Tipe item',
            'Judul_Item' => 'Judul',
            'Nomor_SOP_Resmi' => 'Nomor Dokumen',
            'Deskripsi_Item' => 'Deskripsi item',
            'Konten_Teks' => 'Konten teks',
            'Mode_Akses' => 'Hak akses',
            'URL_Tautan' => 'URL tautan',
            'Label_Tautan' => 'Label tautan',
            'Jenis_Quiz' => 'Jenis kuis',
            'Nilai_Minimum' => 'Nilai minimum',
            'Id_Master_Ujian' => 'Ujian',
            'Nama_Ujian' => 'Nama ujian',
            'Flag_Camera' => 'Kamera',
            'Flag_Wajib' => 'Wajib',
            'Flag_Highlight' => 'Sorotan',
            'Flag_Watermark' => 'Watermark',
        ][$field] ?? $field;
    }

    private function isVersionedItem(object $item): bool
    {
        return in_array($item->Tipe_Item, ['DOKUMEN', 'FORM', 'VIDEO'], true)
            || ($item->Tipe_Item === 'KUIS' && ! empty($item->Id_Master_Ujian));
    }

    /**
     * Baris ini bertaut Dokumen SOP?
     *
     * Dibaca dari kedua sisi karena tautannya bisa tersimpan di salah satunya
     * saja: item cerminan menyimpannya di item live, item yang lahir di overlay
     * di barisnya sendiri.
     */
    private function berdokumen(?object $active, ?object $draft): bool
    {
        return ! empty($active->Id_SOP_Dokumen ?? null) || ! empty($draft->Id_SOP_Dokumen ?? null);
    }

    /** Field yang boleh dibandingkan untuk SATU baris item. */
    private function itemFieldsFor(array $fields, ?object $active, ?object $draft): array
    {
        return $this->berdokumen($active, $draft)
            ? array_values(array_diff($fields, self::ITEM_IDENTITY_FIELDS))
            : $fields;
    }

    /**
     * Judul yang layak ditampilkan di daftar perubahan.
     *
     * Untuk item ber-dokumen, potret di overlay bisa basi; cerminan di item live
     * selalu yang terbaru karena dialah yang disinkronkan dari dokumennya.
     */
    private function judulTampil(?object $active, ?object $draft): ?string
    {
        return $this->berdokumen($active, $draft)
            ? (($active->Judul_Item ?? null) ?: ($draft->Judul_Item ?? null))
            : ($draft->Judul_Item ?? null);
    }

    private function rowsDiffer(?object $a, ?object $b, array $fields): bool
    {
        if (! $a || ! $b) {
            return true;
        }
        foreach ($fields as $field) {
            if ((string) ($a->{$field} ?? '') !== (string) ($b->{$field} ?? '')) {
                return true;
            }
        }

        return false;
    }

    private function collectionDiffCount($active, $draft, string $activeKey, string $sourceKey, array $fields): int
    {
        $byId = $active->keyBy($activeKey);
        $seen = [];
        $count = 0;
        foreach ($draft as $row) {
            $source = $row->{$sourceKey};
            if (! $source || ! $byId->has($source)) {
                $count++;

                continue;
            }
            $seen[] = (int) $source;
            if ($this->rowsDiffer($byId->get($source), $row, $fields)) {
                $count++;
            }
        }

        return $count + $byId->keys()->map(fn ($v) => (int) $v)->diff($seen)->count();
    }

    private function itemDiffCount($active, $draft, $draftSections, array $fields): int
    {
        $byId = $active->keyBy('Id_SOP_Paket_Item');
        $sectionSources = $draftSections->keyBy('Id_SOP_Draft_Section');
        $seen = [];
        $count = 0;
        foreach ($draft as $row) {
            $source = $row->Id_SOP_Paket_Item_Asal;
            if (! $source || ! $byId->has($source)) {
                $count++;

                continue;
            }
            $seen[] = (int) $source;
            /* Baku salinan diterapkan pada sisi LIVE sebelum dibandingkan —
               sisi draft sudah membawanya sejak ensure(). Tanpa ini, kolom
               yang NULL di live (Flag_Camera pada seluruh data hari ini)
               selalu terbaca berbeda. */
            $activeRow = $this->bakuItem($byId->get($source));
            $draftSectionSource = $row->Id_SOP_Draft_Section ? ($sectionSources->get($row->Id_SOP_Draft_Section)->Id_SOP_Section_Asal ?? null) : null;
            if ((int) ($activeRow->Id_SOP_Section ?? 0) !== (int) ($draftSectionSource ?? 0)
                || $this->rowsDiffer($activeRow, $row, $this->itemFieldsFor($fields, $activeRow, $row))) {
                $count++;
            }
        }

        return $count + $byId->keys()->map(fn ($v) => (int) $v)->diff($seen)->count();
    }
}
