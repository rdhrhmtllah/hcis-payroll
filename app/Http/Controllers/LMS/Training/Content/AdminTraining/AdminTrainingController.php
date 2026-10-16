<?php

namespace App\Http\Controllers\LMS\Training\Content\AdminTraining;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Vinkla\Hashids\Facades\Hashids;

class AdminTrainingController extends Controller
{
    /* ───────────────────────────────────────────────
     *  INERTIA PAGES
     * ─────────────────────────────────────────────── */

    public function index()
    {
        return inertia('LMS/Training/Content/AdminTraining/AdminTrainingListPage');
    }

    public function create()
    {
        return inertia('LMS/Training/Content/AdminTraining/AdminTrainingBuilderPage');
    }

    public function edit($id)
    {
        return inertia('LMS/Training/Content/AdminTraining/AdminTrainingBuilderPage', [
            'trainingId' => $id, // ID is already hashed from URL
        ]);
    }

    /* ───────────────────────────────────────────────
     *  API — LIST
     * ─────────────────────────────────────────────── */

    public function getList(Request $request)
    {
        try {
            $perPage = (int) $request->input('limit', 10);
            $page = (int) $request->input('page', 1);
            $search = $request->input('q', '');
            $kategori = trim((string) $request->input('kategori', ''));
            $status = strtoupper(trim((string) $request->input('status', '')));
            $tipe = trim((string) $request->input('tipe', ''));
            $tanggalStart = $request->input('tanggal_start');
            $tanggalEnd = $request->input('tanggal_end');

            $query = DB::table('N_LMS_Materi as m')
                ->leftJoin('N_LMS_Training_Kategori as tk', 'm.Id_Training_Kategori', '=', 'tk.Id_Training_Kategori')
                ->leftJoin('N_LMS_Training_Tipe as tt', 'm.Id_Training_Tipe', '=', 'tt.Id_Training_Tipe')
                ->select([
                    'm.Id_Materi',
                    'm.Judul_Training',
                    'm.Deskripsi',
                    'm.Tanggal',
                    'm.Jam',
                    'm.Thumbnail_Training',
                    'm.Flag_Aktif',
                    'tk.Nama_Kategori',
                    'tk.Id_Training_Kategori',
                    'tt.Nama_Tipe',
                    'tt.Id_Training_Tipe',
                ]);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('m.Judul_Training', 'LIKE', "%{$search}%")->orWhere(
                        'tk.Nama_Kategori',
                        'LIKE',
                        "%{$search}%",
                    );
                });
            }

            if ($kategori !== '') {
                $decodedKategori = Hashids::connection('custom')->decode($kategori)[0] ?? null;
                $query->where(function ($q) use ($kategori, $decodedKategori) {
                    if (! empty($decodedKategori)) {
                        $q->where('m.Id_Training_Kategori', $decodedKategori)->orWhere(
                            'tk.Nama_Kategori',
                            'LIKE',
                            "%{$kategori}%",
                        );

                        return;
                    }
                    $q->where('tk.Nama_Kategori', 'LIKE', "%{$kategori}%");
                });
            }

            if (in_array($status, ['Y', 'N'], true)) {
                $query->where('m.Flag_Aktif', $status);
            }

            if ($tipe !== '') {
                $decodedTipe = Hashids::connection('custom')->decode($tipe)[0] ?? null;
                $query->where(function ($q) use ($tipe, $decodedTipe) {
                    if (! empty($decodedTipe)) {
                        $q->where('m.Id_Training_Tipe', $decodedTipe)->orWhere('tt.Nama_Tipe', 'LIKE', "%{$tipe}%");

                        return;
                    }
                    $q->where('tt.Nama_Tipe', 'LIKE', "%{$tipe}%");
                });
            }

            if (! empty($tanggalStart)) {
                $query->whereDate('m.Tanggal', '>=', $tanggalStart);
            }

            if (! empty($tanggalEnd)) {
                $query->whereDate('m.Tanggal', '<=', $tanggalEnd);
            }

            $total = $query->count();

            $baris = $query
                ->orderBy('m.Id_Materi', 'desc')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();

            /* Dua hitungan diambil SEKALI untuk seluruh halaman, bukan satu
               kueri masing-masing per baris. Halaman 8 materi dulu menghasilkan
               16 kueri hitung; biayanya naik lurus mengikuti ukuran halaman. */
            $idMateri = $baris->pluck('Id_Materi')->map(fn ($v) => (int) $v)->unique()->values()->all();
            $jumlahSection = $this->hitungPerMateri('N_LMS_Materi_Detail', 'Id_Master_Training', $idMateri);
            $jumlahItem = $this->hitungPerMateri('N_LMS_Materi_Det', 'Id_Materi', $idMateri);

            $items = $baris
                ->map(function ($row) use ($jumlahSection, $jumlahItem) {
                    $sectionCount = $jumlahSection[(int) $row->Id_Materi] ?? 0;
                    $itemCount = $jumlahItem[(int) $row->Id_Materi] ?? 0;

                    return [
                        'id' => Hashids::connection('custom')->encode($row->Id_Materi),
                        'judul' => $row->Judul_Training,
                        'deskripsi' => $row->Deskripsi,
                        'kategori' => $row->Nama_Kategori,
                        'id_kategori' => $row->Id_Training_Kategori
                            ? Hashids::connection('custom')->encode($row->Id_Training_Kategori)
                            : null,
                        'tipe' => $row->Nama_Tipe,
                        'id_tipe' => $row->Id_Training_Tipe
                            ? Hashids::connection('custom')->encode($row->Id_Training_Tipe)
                            : null,
                        'tanggal' => $row->Tanggal,
                        'thumbnail' => $row->Thumbnail_Training,
                        'thumbnail_url' => $this->resolveThumbnailUrl($row->Thumbnail_Training),
                        'flag_aktif' => $row->Flag_Aktif,
                        'section_count' => $sectionCount,
                        'item_count' => $itemCount,
                    ];
                });

            return ResponseHelper::successWithPaginationV2(
                $items,
                $page,
                $perPage,
                $total,
                'Daftar training berhasil dimuat',
            );
        } catch (\Exception $e) {
            Log::error('getList Admin Training Error: '.$e->getMessage());

            return ResponseHelper::error('Gagal memuat daftar training', 500);
        }
    }

    /* ───────────────────────────────────────────────
     *  API — MASTER DATA (dropdowns)
     * ─────────────────────────────────────────────── */

    public function getMasterData()
    {
        try {
            $kategori = DB::table('N_LMS_Training_Kategori')
                ->where('Flag_Aktif', 'Y')
                ->select('Id_Training_Kategori as id', 'Nama_Kategori as nama', 'Kode_Kategori as kode')
                ->get()
                ->map(
                    fn ($row) => [
                        'id' => Hashids::connection('custom')->encode($row->id),
                        'nama' => $row->nama,
                        'kode' => $row->kode,
                    ],
                );

            $tipe = DB::table('N_LMS_Training_Tipe')
                ->select('Id_Training_Tipe as id', 'Nama_Tipe as nama', 'Kode_Tipe as kode')
                ->get()
                ->map(
                    fn ($row) => [
                        'id' => Hashids::connection('custom')->encode($row->id),
                        'nama' => $row->nama,
                        'kode' => $row->kode,
                    ],
                );

            $aktivitas = DB::table('N_LMS_Master_Aktivitas')
                ->where('Flag_Aktif', 'Y')
                ->select(
                    'Id_Master_Aktivitas as id',
                    'Kode_Aktivitas as kode',
                    'Nama_Aktivitas as nama',
                    'Flag_Third_Party as flag_third_party',
                )
                ->get()
                ->map(
                    fn ($row) => [
                        'id' => Hashids::connection('custom')->encode($row->id),
                        'nama' => $row->nama,
                        'kode' => $row->kode,
                        'flag_third_party' => $row->flag_third_party,
                    ],
                );

            $konten = DB::table('N_LMS_Master_Konten as k')
                ->leftJoin('N_LMS_Kategori_Konten as kk', 'k.Id_Kategori_Konten', '=', 'kk.Id_Kategori_Konten')
                ->where('k.Flag_Aktif', 'Y')
                ->select(
                    'k.Id_Master_Konten as id',
                    'k.Judul_Konten as judul',
                    'k.Tipe_Konten as tipe',
                    'k.Flag_Third_Party as flag_third_party',
                    'kk.Nama_Kategori as kategori_konten',
                )
                ->get()
                ->map(
                    fn ($row) => [
                        'id' => Hashids::connection('custom')->encode($row->id),
                        'judul' => $row->judul,
                        'tipe' => $row->tipe,
                        'flag_third_party' => $row->flag_third_party,
                        'kategori_konten' => $row->kategori_konten,
                    ],
                );

            return ResponseHelper::success([
                'kategori' => $kategori,
                'tipe' => $tipe,
                'aktivitas' => $aktivitas,
                'konten' => $konten,
            ]);
        } catch (\Exception $e) {
            Log::error('getMasterData Admin Training Error: '.$e->getMessage());

            return ResponseHelper::error('Gagal memuat master data', 500);
        }
    }

    /* ───────────────────────────────────────────────
     *  API — GET SINGLE TRAINING (for edit)
     * ─────────────────────────────────────────────── */

    public function show($id)
    {
        try {
            $realId = Hashids::connection('custom')->decode($id)[0] ?? (int) $id;
            $materi = DB::table('N_LMS_Materi')->where('Id_Materi', $realId)->first();
            if (! $materi) {
                return ResponseHelper::error('Training tidak ditemukan', 404);
            }

            $materi->thumbnail_url = $this->resolveThumbnailUrl($materi->Thumbnail_Training);
            $materi->Outline_Session = $this->decodeMetaList($materi->Outline_Session ?? null);
            $materi->Metode = $this->decodeMetaList($materi->Metode ?? null);
            $materi->Durasi = $this->decodeMetaList($materi->Durasi ?? null);
            $materi->Sasaran_Peserta = $this->decodeMetaList($materi->Sasaran_Peserta ?? null);

            // Format ID for frontend
            $materi->Id_Materi = Hashids::connection('custom')->encode($materi->Id_Materi);
            if ($materi->Id_Training_Kategori) {
                $materi->Id_Training_Kategori = Hashids::connection('custom')->encode($materi->Id_Training_Kategori);
            }
            if ($materi->Id_Training_Tipe) {
                $materi->Id_Training_Tipe = Hashids::connection('custom')->encode($materi->Id_Training_Tipe);
            }

            $sections = DB::table('N_LMS_Materi_Detail')
                ->where('Id_Master_Training', $realId)
                ->orderBy('Urutan_Sub_Training')
                ->get()
                ->map(function ($sec) use ($realId) {
                    $items = DB::table('N_LMS_Materi_Det as d')
                        ->leftJoin('N_LMS_Master_Aktivitas as a', 'd.Id_Master_Aktivitas', '=', 'a.Id_Master_Aktivitas')
                        ->leftJoin('N_LMS_Master_Konten as k', 'd.Id_Master_Konten', '=', 'k.Id_Master_Konten')
                        ->where('d.Id_Materi', $realId)
                        ->where('d.Id_Materi_Detail', $sec->Id_Materi_Detail)
                        ->orderBy('d.Urutan_Materi')
                        ->select([
                            'd.Id_Materi_Det',
                            'd.Id_Master_Aktivitas',
                            'd.Id_Master_Konten',
                            'd.Id_Master_Rumus',
                            'd.Keterangan_Level',
                            'd.Urutan_Materi',
                            'd.Durasi',
                            'd.Flag_Download',
                            'a.Kode_Aktivitas',
                            'a.Nama_Aktivitas',
                            'k.Judul_Konten',
                            'k.Tipe_Konten',
                            'd.Flag_Aktif',
                        ])
                        ->get()
                        ->map(
                            fn ($item) => [
                                'Id_Materi_Det' => Hashids::connection('custom')->encode($item->Id_Materi_Det),
                                'Id_Master_Aktivitas' => $item->Id_Master_Aktivitas
                                    ? Hashids::connection('custom')->encode($item->Id_Master_Aktivitas)
                                    : null,
                                'Id_Master_Konten' => $item->Id_Master_Konten
                                    ? Hashids::connection('custom')->encode($item->Id_Master_Konten)
                                    : null,
                                'Id_Master_Rumus' => $item->Id_Master_Rumus
                                    ? Hashids::connection('custom')->encode($item->Id_Master_Rumus)
                                    : null,
                                'Keterangan_Level' => $item->Keterangan_Level,
                                'Urutan_Materi' => $item->Urutan_Materi,
                                'Durasi' => $item->Durasi,
                                'Flag_Download' => $item->Flag_Download,
                                'Kode_Aktivitas' => $item->Kode_Aktivitas,
                                'Nama_Aktivitas' => $item->Nama_Aktivitas,
                                'Judul_Konten' => $item->Judul_Konten,
                                'Tipe_Konten' => $item->Tipe_Konten,
                                'Flag_Aktif' => $item->Flag_Aktif,
                            ],
                        );

                    return [
                        'id' => Hashids::connection('custom')->encode($sec->Id_Materi_Detail),
                        'nama' => $sec->Nama_Sub,
                        'urutan' => $sec->Urutan_Sub_Training,
                        'flag_aktif' => $sec->Flag_Aktif ?? 'Y',
                        'items' => $items,
                    ];
                });

            return ResponseHelper::success([
                'materi' => $materi,
                'sections' => $sections,
            ]);
        } catch (\Exception $e) {
            Log::error('show Admin Training Error: '.$e->getMessage());

            return ResponseHelper::error('Gagal memuat data training', 500);
        }
    }

    /* ───────────────────────────────────────────────
     *  API — STORE (create new training)
     * ─────────────────────────────────────────────── */

    public function store(Request $request)
    {
        $payload = $request->all();

        // Decode IDs
        if (isset($payload['id_training_kategori'])) {
            $payload['id_training_kategori'] =
                Hashids::connection('custom')->decode($payload['id_training_kategori'])[0] ??
                $payload['id_training_kategori'];
        }
        if (isset($payload['id_training_tipe'])) {
            $payload['id_training_tipe'] =
                Hashids::connection('custom')->decode($payload['id_training_tipe'])[0] ?? $payload['id_training_tipe'];
        }

        if (isset($payload['sections']) && is_array($payload['sections'])) {
            foreach ($payload['sections'] as &$sec) {
                if (isset($sec['items']) && is_array($sec['items'])) {
                    foreach ($sec['items'] as &$item) {
                        if (isset($item['id_master_aktivitas'])) {
                            $item['id_master_aktivitas'] =
                                Hashids::connection('custom')->decode($item['id_master_aktivitas'])[0] ??
                                $item['id_master_aktivitas'];
                        }
                        if (isset($item['id_master_konten'])) {
                            $item['id_master_konten'] =
                                Hashids::connection('custom')->decode($item['id_master_konten'])[0] ??
                                $item['id_master_konten'];
                        }
                        if (isset($item['id_master_rumus'])) {
                            $item['id_master_rumus'] =
                                Hashids::connection('custom')->decode($item['id_master_rumus'])[0] ??
                                $item['id_master_rumus'];
                        }
                    }
                }
            }
        }

        $request->replace($payload);

        $data = $request->validate([
            'judul_training' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'id_training_kategori' => 'nullable|integer',
            'id_training_tipe' => 'nullable|integer',
            'thumbnail' => 'nullable|string',
            'flag_aktif' => 'nullable|string|max:1',
            'outline_session' => 'nullable|array',
            'outline_session.*' => 'nullable|string|max:500',
            'metode' => 'nullable|array',
            'metode.*' => 'nullable|string|max:255',
            'durasi' => 'nullable|array',
            'durasi.*' => 'nullable|string|max:255',
            'total_durasi' => 'nullable|integer|min:0',
            'sasaran_peserta' => 'nullable|array',
            'sasaran_peserta.*' => 'nullable|string|max:255',
            'standar_kelulusan' => 'nullable|integer|min:0|max:100',
            'sections' => 'required|array|min:1',
            'sections.*.nama_sub' => 'required|string|max:255',
            'sections.*.urutan' => 'required|integer',
            'sections.*.items' => 'required|array|min:1',
        ], [
            'sections.*.items.required' => 'Setiap section harus memiliki minimal 1 item.',
            'sections.*.items.array' => 'Items section harus berupa array.',
            'sections.*.items.min' => 'Setiap section harus memiliki minimal 1 item.',
        ]);

        $thumbnailPath = null;

        try {
            $thumbnailPath = $this->prepareThumbnailForStorage($data['thumbnail'] ?? null, $data['judul_training']);

            return DB::transaction(function () use ($data, $thumbnailPath) {
                $now = now();
                $userId = Auth::id() ?? 1;
                $jam = $now->format('H:i:s');
                $durasiList = $this->sanitizeMetaList($data['durasi'] ?? []);
                $encodedDurasi = $this->encodeMetaList($durasiList);
                $computedTotalDurasi = $this->calculateTotalDurasiFromDurasiList($durasiList);

                // 1. Create N_LMS_Master_Paket
                $paketId = DB::table('N_LMS_Master_Paket')->insertGetId([
                    'Nama_Paket' => $data['judul_training'],
                    'Deskripsi' => $data['deskripsi'] ?? '',
                    'Tanggal' => $now->format('Ymd'),
                    'Jam' => $jam,
                    'Flag_Aktif' => $data['flag_aktif'] ?? 'Y',
                ]);

                // 2. Create N_LMS_Materi
                $materiId = DB::table('N_LMS_Materi')->insertGetId([
                    'Id_Training_Kategori' => $data['id_training_kategori'],
                    'Id_Training_Tipe' => 1, // On Demand
                    'Judul_Training' => $data['judul_training'],
                    'Deskripsi' => $data['deskripsi'] ?? '',
                    'Outline_Session' => $this->encodeMetaList($data['outline_session'] ?? []),
                    'Metode' => $this->encodeMetaList($data['metode'] ?? []),
                    'Durasi' => $encodedDurasi,
                    'Total_Durasi' => $computedTotalDurasi,
                    'Sasaran_Peserta' => $this->encodeMetaList($data['sasaran_peserta'] ?? []),
                    'Standar_Kelulusan' => $data['standar_kelulusan'] ?? null,
                    'Tanggal' => $now,
                    'Jam' => $jam,
                    'Id_User' => $userId,
                    'Thumbnail_Training' => $thumbnailPath,
                    'Flag_Aktif' => $data['flag_aktif'] ?? 'Y',
                ]);

                // 3. Link Paket ↔ Materi
                DB::table('N_LMS_Master_Paket_Detail')->insert([
                    'Id_Master_Paket' => $paketId,
                    'Id_Master_Training' => $materiId,
                    'Urutan_Paket_Detail' => 1,
                ]);

                // 4. Insert sections and items
                foreach ($data['sections'] as $sec) {
                    $sectionId = DB::table('N_LMS_Materi_Detail')->insertGetId([
                        'Id_Master_Training' => $materiId,
                        'Nama_Sub' => $sec['nama_sub'],
                        'Urutan_Sub_Training' => $sec['urutan'],
                        'Tanggal' => $now,
                        'Jam' => $jam,
                        'Flag_Aktif' => 'Y',
                    ]);

                    if (! empty($sec['items'])) {
                        foreach ($sec['items'] as $item) {
                            DB::table('N_LMS_Materi_Det')->insert([
                                'Id_Materi' => $materiId,
                                'Id_Materi_Detail' => $sectionId,
                                'Id_Master_Aktivitas' => $item['id_master_aktivitas'] ?? null,
                                'Id_Master_Konten' => $item['id_master_konten'] ?? null,
                                'Id_Master_Rumus' => $item['id_master_rumus'] ?? null,
                                'Keterangan_Level' => $item['keterangan_level'] ?? null,
                                'Urutan_Materi' => $item['urutan_materi'] ?? 1,
                                'Durasi' => $item['durasi'] ?? null,
                                'Id_User' => $userId,
                                'Flag_Aktif' => $item['flag_aktif'] ?? 'Y',
                                'Flag_Download' => $item['flag_download'] ?? 'T',
                            ]);
                        }
                    }
                }

                return ResponseHelper::success(
                    [
                        'id' => Hashids::connection('custom')->encode($materiId),
                    ],
                    'Training berhasil dibuat',
                    201,
                );
            });
        } catch (\Exception $e) {
            if ($thumbnailPath && $this->isGcsPath($thumbnailPath) && Storage::disk('gcs')->exists($thumbnailPath)) {
                Storage::disk('gcs')->delete($thumbnailPath);
            }
            Log::error('Store Admin Training Error: '.$e->getMessage());

            return ResponseHelper::error('Gagal membuat training: '.$e->getMessage(), 500);
        }
    }

    /* ───────────────────────────────────────────────
     *  API — UPDATE
     * ─────────────────────────────────────────────── */

    public function update(Request $request, $id)
    {
        $realId = Hashids::connection('custom')->decode($id)[0] ?? (int) $id;
        $payload = $request->all();

        // Decode IDs
        if (isset($payload['id_training_kategori'])) {
            $payload['id_training_kategori'] =
                Hashids::connection('custom')->decode($payload['id_training_kategori'])[0] ??
                $payload['id_training_kategori'];
        }
        if (isset($payload['id_training_tipe'])) {
            $payload['id_training_tipe'] =
                Hashids::connection('custom')->decode($payload['id_training_tipe'])[0] ?? $payload['id_training_tipe'];
        }

        if (isset($payload['sections']) && is_array($payload['sections'])) {
            foreach ($payload['sections'] as &$sec) {
                if (isset($sec['id'])) {
                    $sec['id'] = Hashids::connection('custom')->decode($sec['id'])[0] ?? $sec['id'];
                }
                if (isset($sec['items']) && is_array($sec['items'])) {
                    foreach ($sec['items'] as &$item) {
                        if (isset($item['id'])) {
                            $item['id'] = Hashids::connection('custom')->decode($item['id'])[0] ?? $item['id'];
                        }
                        if (isset($item['id_master_aktivitas'])) {
                            $item['id_master_aktivitas'] =
                                Hashids::connection('custom')->decode($item['id_master_aktivitas'])[0] ??
                                $item['id_master_aktivitas'];
                        }
                        if (isset($item['id_master_konten'])) {
                            $item['id_master_konten'] =
                                Hashids::connection('custom')->decode($item['id_master_konten'])[0] ??
                                $item['id_master_konten'];
                        }
                        if (isset($item['id_master_rumus'])) {
                            $item['id_master_rumus'] =
                                Hashids::connection('custom')->decode($item['id_master_rumus'])[0] ??
                                $item['id_master_rumus'];
                        }
                    }
                }
            }
        }

        $request->replace($payload);

        $data = $request->validate([
            'judul_training' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'id_training_kategori' => 'nullable|integer',
            'id_training_tipe' => 'nullable|integer',
            'thumbnail' => 'nullable|string',
            'flag_aktif' => 'nullable|string|max:1',
            'outline_session' => 'nullable|array',
            'outline_session.*' => 'nullable|string|max:500',
            'metode' => 'nullable|array',
            'metode.*' => 'nullable|string|max:255',
            'durasi' => 'nullable|array',
            'durasi.*' => 'nullable|string|max:255',
            'total_durasi' => 'nullable|integer|min:0',
            'sasaran_peserta' => 'nullable|array',
            'sasaran_peserta.*' => 'nullable|string|max:255',
            'standar_kelulusan' => 'nullable|integer|min:0|max:100',
            'sections' => 'required|array|min:1',
            'sections.*.id' => 'nullable|integer',
            'sections.*.nama_sub' => 'required|string|max:255',
            'sections.*.urutan' => 'required|integer',
            'sections.*.items' => 'required|array|min:1',
        ], [
            'sections.*.items.required' => 'Setiap section harus memiliki minimal 1 item.',
            'sections.*.items.array' => 'Items section harus berupa array.',
            'sections.*.items.min' => 'Setiap section harus memiliki minimal 1 item.',
        ]);

        $existingMateri = DB::table('N_LMS_Materi')->where('Id_Materi', $realId)->first();
        if (! $existingMateri) {
            return ResponseHelper::error('Training tidak ditemukan', 404);
        }

        $oldThumbnailPath = $existingMateri->Thumbnail_Training;
        $thumbnailPath = $oldThumbnailPath;
        $uploadedNewThumbnail = false;

        try {
            if ($this->isBase64Image($data['thumbnail'] ?? null)) {
                $thumbnailPath = $this->uploadBase64ThumbnailToGcs($data['thumbnail'], $data['judul_training']);
                $uploadedNewThumbnail = true;
            } elseif (array_key_exists('thumbnail', $data)) {
                $thumbnailPath = $data['thumbnail'] ?: null;
            }

            $response = DB::transaction(function () use ($data, $realId, $thumbnailPath) {
                $now = now();
                $userId = Auth::id() ?? 1;
                $jam = $now->format('H:i:s');
                $durasiList = $this->sanitizeMetaList($data['durasi'] ?? []);
                $encodedDurasi = $this->encodeMetaList($durasiList);
                $computedTotalDurasi = $this->calculateTotalDurasiFromDurasiList($durasiList);

                // 1. Update N_LMS_Materi header
                DB::table('N_LMS_Materi')
                    ->where('Id_Materi', $realId)
                    ->update([
                        'Id_Training_Kategori' => $data['id_training_kategori'],
                        'Id_Training_Tipe' => $data['id_training_tipe'],
                        'Judul_Training' => $data['judul_training'],
                        'Deskripsi' => $data['deskripsi'] ?? '',
                        'Outline_Session' => $this->encodeMetaList($data['outline_session'] ?? []),
                        'Metode' => $this->encodeMetaList($data['metode'] ?? []),
                        'Durasi' => $encodedDurasi,
                        'Total_Durasi' => $computedTotalDurasi,
                        'Sasaran_Peserta' => $this->encodeMetaList($data['sasaran_peserta'] ?? []),
                        'Standar_Kelulusan' => $data['standar_kelulusan'] ?? null,
                        'Thumbnail_Training' => $thumbnailPath,
                        'Flag_Aktif' => $data['flag_aktif'] ?? 'Y',
                    ]);

                // Link Paket sync
                $paketDetail = DB::table('N_LMS_Master_Paket_Detail')->where('Id_Master_Training', $realId)->first();
                if ($paketDetail) {
                    DB::table('N_LMS_Master_Paket')
                        ->where('Id_Master_Paket', $paketDetail->Id_Master_Paket)
                        ->update(['Nama_Paket' => $data['judul_training']]);
                }

                // Hard-delete logic for removed sections/items
                $submittedSectionIds = collect($data['sections'])
                    ->filter(fn ($s) => ! empty($s['id']))
                    ->pluck('id')
                    ->toArray();
                $deletedSections = DB::table('N_LMS_Materi_Detail')
                    ->where('Id_Master_Training', $realId)
                    ->whereNotIn('Id_Materi_Detail', $submittedSectionIds)
                    ->get();
                $deletedSectionIds = $deletedSections->pluck('Id_Materi_Detail')->toArray();

                if (! empty($deletedSectionIds)) {
                    $deletedItemIds = DB::table('N_LMS_Materi_Det')
                        ->whereIn('Id_Materi_Detail', $deletedSectionIds)
                        ->pluck('Id_Materi_Det')
                        ->toArray();
                    if (! empty($deletedItemIds)) {
                        DB::table('N_LMS_Prasyarat')
                            ->whereIn('Id_Materi_Det', $deletedItemIds)
                            ->orWhereIn('Id_Materi_Det_Syarat', $deletedItemIds)
                            ->delete();
                    }
                    DB::table('N_LMS_Materi_Det')->whereIn('Id_Materi_Detail', $deletedSectionIds)->delete();
                    DB::table('N_LMS_Materi_Detail')->whereIn('Id_Materi_Detail', $deletedSectionIds)->delete();
                }

                // Upsert sections
                foreach ($data['sections'] as $sec) {
                    $sectionId = $sec['id'] ?? null;
                    $secData = [
                        'Nama_Sub' => $sec['nama_sub'],
                        'Urutan_Sub_Training' => $sec['urutan'],
                        'Tanggal' => $now,
                        'Jam' => $jam,
                        'Flag_Aktif' => $sec['flag_aktif'] ?? 'Y',
                    ];

                    if ($sectionId) {
                        DB::table('N_LMS_Materi_Detail')->where('Id_Materi_Detail', $sectionId)->update($secData);
                    } else {
                        $secData['Id_Master_Training'] = $realId;
                        $sectionId = DB::table('N_LMS_Materi_Detail')->insertGetId($secData);
                    }

                    // Sync items in section
                    $submittedItemIds = collect($sec['items'] ?? [])
                        ->filter(fn ($i) => ! empty($i['id']))
                        ->pluck('id')
                        ->toArray();
                    $deletedItemIdsInSection = DB::table('N_LMS_Materi_Det')
                        ->where('Id_Materi_Detail', $sectionId)
                        ->whereNotIn('Id_Materi_Det', $submittedItemIds)
                        ->pluck('Id_Materi_Det')
                        ->toArray();

                    if (! empty($deletedItemIdsInSection)) {
                        DB::table('N_LMS_Prasyarat')
                            ->whereIn('Id_Materi_Det', $deletedItemIdsInSection)
                            ->orWhereIn('Id_Materi_Det_Syarat', $deletedItemIdsInSection)
                            ->delete();
                        DB::table('N_LMS_Materi_Det')->whereIn('Id_Materi_Det', $deletedItemIdsInSection)->delete();
                    }

                    foreach ($sec['items'] ?? [] as $item) {
                        $itemData = [
                            'Id_Master_Aktivitas' => $item['id_master_aktivitas'] ?? null,
                            'Id_Master_Konten' => $item['id_master_konten'] ?? null,
                            'Id_Master_Rumus' => $item['id_master_rumus'] ?? null,
                            'Keterangan_Level' => $item['keterangan_level'] ?? null,
                            'Urutan_Materi' => $item['urutan_materi'] ?? 1,
                            'Durasi' => $item['durasi'] ?? null,
                            'Id_User' => $userId,
                            'Flag_Aktif' => $item['flag_aktif'] ?? 'Y',
                            'Flag_Download' => $item['flag_download'] ?? 'T',
                        ];

                        if (! empty($item['id'])) {
                            DB::table('N_LMS_Materi_Det')->where('Id_Materi_Det', $item['id'])->update($itemData);
                        } else {
                            $itemData['Id_Materi'] = $realId;
                            $itemData['Id_Materi_Detail'] = $sectionId;
                            DB::table('N_LMS_Materi_Det')->insert($itemData);
                        }
                    }
                }

                return ResponseHelper::success(null, 'Training berhasil diperbarui');
            });

            if (
                $uploadedNewThumbnail &&
                $oldThumbnailPath &&
                $oldThumbnailPath !== $thumbnailPath &&
                $this->isGcsPath($oldThumbnailPath)
            ) {
                Storage::disk('gcs')->delete($oldThumbnailPath);
            }

            return $response;
        } catch (\Exception $e) {
            if ($uploadedNewThumbnail && $thumbnailPath && $this->isGcsPath($thumbnailPath)) {
                Storage::disk('gcs')->delete($thumbnailPath);
            }
            Log::error('Update Admin Training Error: '.$e->getMessage());

            return ResponseHelper::error('Gagal memperbarui training: '.$e->getMessage(), 500);
        }
    }

    /* ───────────────────────────────────────────────
     *  API — DESTROY
     * ─────────────────────────────────────────────── */

    public function destroy(Request $request)
    {
        $id = $request->input('id');
        if (! $id) {
            return ResponseHelper::error('ID diperlukan', 422);
        }

        try {
            $realId = Hashids::connection('custom')->decode($id)[0] ?? (int) $id;
            DB::transaction(function () use ($realId) {
                $deletedItems = DB::table('N_LMS_Materi_Det')
                    ->where('Id_Materi', $realId)
                    ->pluck('Id_Materi_Det')
                    ->toArray();
                if (! empty($deletedItems)) {
                    DB::table('N_LMS_Prasyarat')
                        ->whereIn('Id_Materi_Det', $deletedItems)
                        ->orWhereIn('Id_Materi_Det_Syarat', $deletedItems)
                        ->delete();
                }
                DB::table('N_LMS_Materi_Det')->where('Id_Materi', $realId)->delete();
                DB::table('N_LMS_Materi_Detail')->where('Id_Master_Training', $realId)->delete();

                $paketDetail = DB::table('N_LMS_Master_Paket_Detail')->where('Id_Master_Training', $realId)->first();
                if ($paketDetail) {
                    DB::table('N_LMS_Master_Paket_Detail')->where('Id_Master_Training', $realId)->delete();
                    DB::table('N_LMS_Master_Paket')->where('Id_Master_Paket', $paketDetail->Id_Master_Paket)->delete();
                }
                DB::table('N_LMS_Materi')->where('Id_Materi', $realId)->delete();
            });

            return ResponseHelper::success(null, 'Training berhasil dihapus');
        } catch (\Exception $e) {
            Log::error('Destroy Admin Training Error: '.$e->getMessage());

            return ResponseHelper::error('Gagal menghapus: '.$e->getMessage(), 500);
        }
    }

    /* ───────────────────────────────────────────────
     *  API — PREVIEW KONTEN
     * ─────────────────────────────────────────────── */

    public function previewKonten($kontenId)
    {
        try {
            $realId = Hashids::connection('custom')->decode($kontenId)[0] ?? (int) $kontenId;
            $konten = DB::table('N_LMS_Master_Konten')->where('Id_Master_Konten', $realId)->first();

            if (! $konten) {
                return ResponseHelper::error('Konten tidak ditemukan', 404);
            }

            $previewData = ['type' => null, 'url' => null];

            // 1. Cek Media Video (Bunny Stream)
            $media = DB::table('N_LMS_Master_Konten_Media')->where('Id_Master_Konten', $realId)->first();
            if ($media && $media->Provider_Media_Id) {
                $libraryId = config('services.lms.bunny.library_id');
                $previewData = [
                    'type' => 'video',
                    'url' => "https://iframe.mediadelivery.net/embed/{$libraryId}/{$media->Provider_Media_Id}?autoplay=false&loop=false&muted=false&preload=true&responsive=true",
                ];
            } else {
                // 2. Cek Berkas Dokumen (PDF / file)
                $berkas = DB::table('N_LMS_Master_Konten_Berkas')->where('Id_Master_Konten', $realId)->first();
                if ($berkas && $berkas->Id_Master_Konten_Berkas) {
                    $previewData = [
                        'type' => 'pdf',
                        'url' => url(
                            '/api/v1/lms/materi/stream-pdf/'.
                                Hashids::connection('custom')->encode($berkas->Id_Master_Konten_Berkas),
                        ),
                    ];
                }
            }

            return ResponseHelper::success([
                'konten' => [
                    'id' => Hashids::connection('custom')->encode($konten->Id_Master_Konten),
                    'judul' => $konten->Judul_Konten,
                    'tipe' => $konten->Tipe_Konten,
                ],
                'preview' => $previewData,
            ]);
        } catch (\Exception $e) {
            Log::error('AdminTrainingController previewKonten Error: '.$e->getMessage());

            return ResponseHelper::error('Gagal memuat preview: '.$e->getMessage(), 500);
        }
    }

    private function prepareThumbnailForStorage(?string $thumbnail, string $judulTraining): ?string
    {
        if (! $thumbnail) {
            return null;
        }
        if ($this->isBase64Image($thumbnail)) {
            return $this->uploadBase64ThumbnailToGcs($thumbnail, $judulTraining);
        }

        return $thumbnail;
    }

    private function isBase64Image(?string $value): bool
    {
        return is_string($value) && preg_match('/^data:image\/[a-zA-Z0-9.+-]+;base64,/', $value) === 1;
    }

    private function uploadBase64ThumbnailToGcs(string $base64Image, string $judulTraining): string
    {
        if (! preg_match('/^data:image\/([a-zA-Z0-9.+-]+);base64,(.*)$/s', $base64Image, $matches)) {
            throw new \InvalidArgumentException('Format thumbnail tidak valid.');
        }

        $mimeSubtype = strtolower($matches[1]);
        $binary = base64_decode($matches[2], true);
        if ($binary === false) {
            throw new \InvalidArgumentException('Thumbnail gagal diproses.');
        }

        $extension = match ($mimeSubtype) {
            'jpeg', 'jpg' => 'jpg',
            'png' => 'png',
            'gif' => 'gif',
            'webp' => 'webp',
            'svg+xml' => 'svg',
            default => throw new \InvalidArgumentException('Format thumbnail belum didukung.'),
        };

        $folderPath = 'lms/training-thumbnails/'.now()->format('Y/m');
        $slug = Str::slug($judulTraining ?: 'training-thumbnail');
        $path = $folderPath.'/'.$slug.'-'.Str::uuid().'.'.$extension;

        Storage::disk('gcs')->put($path, $binary);

        return $path;
    }

    /**
     * Hitungan baris aktif per materi, satu kueri untuk seluruh daftar.
     *
     * Menggantikan `count()` yang dipanggil di dalam `map()`. Materi yang tidak
     * punya satu pun baris tidak muncul di hasil kelompok; pemanggil membacanya
     * sebagai 0 — jawaban yang sama dengan `count()` atas himpunan kosong.
     *
     * @param  array<int,int>  $idMateri
     * @return array<int,int> berkunci Id_Materi
     */
    private function hitungPerMateri(string $tabel, string $kolomKunci, array $idMateri): array
    {
        if ($idMateri === []) {
            return [];
        }

        return DB::table($tabel)
            ->whereIn($kolomKunci, $idMateri)
            ->where('Flag_Aktif', 'Y')
            ->groupBy($kolomKunci)
            ->selectRaw("{$kolomKunci} as kunci, COUNT(*) as jumlah")
            ->pluck('jumlah', 'kunci')
            ->mapWithKeys(fn ($n, $id) => [(int) $id => (int) $n])
            ->all();
    }

    private function resolveThumbnailUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (! $this->isGcsPath($path)) {
            return $path;
        }
        try {
            return Storage::disk('gcs')->temporaryUrl($path, now()->addMinutes(20));
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function isGcsPath(?string $path): bool
    {
        if (! $path) {
            return false;
        }

        return ! Str::startsWith($path, ['http://', 'https://', 'data:']);
    }

    private function encodeMetaList(?array $list): ?string
    {
        $sanitized = $this->sanitizeMetaList($list);

        if (empty($sanitized)) {
            return null;
        }

        return json_encode($sanitized, JSON_UNESCAPED_UNICODE);
    }

    private function sanitizeMetaList(?array $list): array
    {
        if (empty($list)) {
            return [];
        }

        return collect($list)->map(fn ($value) => trim((string) $value))->filter()->values()->all();
    }

    private function calculateTotalDurasiFromDurasiList(?array $list): ?int
    {
        $durasiList = $this->sanitizeMetaList($list);
        if (empty($durasiList)) {
            return null;
        }

        $total = 0;
        $hasValidDuration = false;

        foreach ($durasiList as $entry) {
            if (preg_match_all('/(\d+)\s*menit/i', $entry, $matches) && ! empty($matches[1])) {
                $total += (int) end($matches[1]);
                $hasValidDuration = true;

                continue;
            }

            if (preg_match('/^\s*(\d+)\s*$/', $entry, $numberOnly)) {
                $total += (int) $numberOnly[1];
                $hasValidDuration = true;
            }
        }

        return $hasValidDuration ? $total : null;
    }

    private function decodeMetaList(?string $value): array
    {
        if (! $value) {
            return [];
        }

        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return collect($decoded)->map(fn ($entry) => trim((string) $entry))->filter()->values()->all();
        }

        return collect(preg_split('/\r?\n|;/', $value) ?: [])
            ->map(fn ($entry) => trim((string) $entry))
            ->filter()
            ->values()
            ->all();
    }
}
