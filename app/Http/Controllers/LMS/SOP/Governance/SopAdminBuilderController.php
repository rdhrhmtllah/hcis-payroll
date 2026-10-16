<?php

namespace App\Http\Controllers\LMS\SOP\Governance;

use App\Helpers\ResponseHelper;
use App\Helpers\SignatureHelper;
use App\Http\Controllers\Controller;
use App\Services\LMS\SOP\SopItemService;
use App\Services\LMS\SOP\SopItemVersionService;
use App\Services\LMS\SOP\SopPaketDraftService;
use App\Services\LMS\SOP\SopPaketService;
use App\Services\LMS\SOP\SopPaketVersionService;
use App\Services\LMS\SOP\SopReconciliationService;
use App\Services\LMS\SOP\SopSectionService;
use App\Services\LMS\SOP\Support\SopBuilderScope;
use App\Services\LMS\SOP\Support\SopDiff;
use App\Services\LMS\SOP\Support\SopHtml;
use App\Services\LMS\SOP\Support\SopLog;
use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use App\Services\LMS\SOP\Support\SopTipeItemAktif;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Vinkla\Hashids\Facades\Hashids;

/**
 * SOP — Admin Builder (paket + item).
 * Berkas/versi (upload GCS + render) menyusul di controller/endpoint terpisah
 * setelah pemetaan reuse selesai.
 */
class SopAdminBuilderController extends Controller
{
    public function __construct(
        private SopPaketService $paket,
        private SopItemService $item,
        private SopSectionService $section,
        private SopItemVersionService $versi,
        private SopPaketVersionService $paketVersi,
        private SopPaketDraftService $paketDraft,
        private SopReconciliationService $reconcile,
    ) {}

    private function decode($id): ?int
    {
        if (is_string($id) && preg_match('/^(?:dp|ds|di|dv)_(.+)$/', $id, $m)) {
            $id = $m[1];
        }
        $d = Hashids::connection('custom')->decode($id);

        return $d[0] ?? null;
    }

    private function isDraftToken($id, string $type): bool
    {
        return is_string($id) && str_starts_with($id, $type.'_');
    }

    private function activeItemIsPublished(int $idItem): bool
    {
        return DB::table('N_SOP_Paket_Item as i')->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->where('i.Id_SOP_Paket_Item', $idItem)->where('p.Status', SopStatus::PAKET_PUBLISHED)->exists();
    }

    private function activeSectionIsPublished(int $idSection): bool
    {
        return DB::table('N_SOP_Paket_Section as s')->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 's.Id_SOP_Paket')
            ->where('s.Id_SOP_Section', $idSection)->where('p.Status', SopStatus::PAKET_PUBLISHED)->exists();
    }

    private function stalePublishedIdResponse()
    {
        return ResponseHelper::error('Data paket sudah terbit. Muat ulang Builder untuk bekerja pada draft revisi.', 409);
    }

    /**
     * Baris kalah adu Row_Ver: admin lain sudah mengubahnya sejak klien ini membacanya.
     * Sengaja 409 (bukan 422) supaya frontend bisa membedakan bentrok dari salah isi.
     */
    private function staleRowResponse(?string $judul = null)
    {
        $nama = $judul ? "\"{$judul}\"" : 'Data ini';

        return ResponseHelper::error(
            "{$nama} baru saja diubah admin lain. Perubahan Anda tidak disimpan agar tidak menimpa pekerjaan mereka. Muat ulang untuk melihat versi terbaru.",
            409
        );
    }

    /**
     * Jejak admin untuk mutasi Builder.
     *
     * Builder adalah tempat isi SOP benar-benar ditulis, tapi satu-satunya permukaan
     * SOP yang dulu tak punya jejak sama sekali — Penjadwalan, Kebijakan, Reconcile,
     * dan siklus versi semuanya sudah mencatat. SopLog bersifat best-effort, jadi
     * kegagalan menulis jejak tidak akan menggagalkan aksinya.
     */
    private function jejak(string $aksi, string $keterangan, ?int $idPaket = null, ?int $idItem = null, array $meta = []): void
    {
        SopLog::catat($aksi, $keterangan, $idPaket, $idItem, null, null, $meta + ['oleh_id_users' => Auth::id()]);
    }

    /**
     * Id paket dari id section — wajib terisi agar jejak muncul di riwayat per paket.
     *
     * N_SOP_Log dibaca lewat IX_N_SOP_Log_Paket(Id_SOP_Paket, Created_At); baris
     * ber-Id_SOP_Paket NULL tak akan pernah tampil di riwayat paket mana pun.
     */
    private function paketDariSection(int $id, bool $draft): ?int
    {
        $idPaket = $draft
            ? DB::table('N_SOP_Paket_Draft_Section as s')
                ->join('N_SOP_Paket_Draft as d', 'd.Id_SOP_Paket_Draft', '=', 's.Id_SOP_Paket_Draft')
                ->where('s.Id_SOP_Draft_Section', $id)->value('d.Id_SOP_Paket')
            : DB::table('N_SOP_Paket_Section')->where('Id_SOP_Section', $id)->value('Id_SOP_Paket');

        return $idPaket ? (int) $idPaket : null;
    }

    /** Id paket dari id item. Lihat catatan pada paketDariSection(). */
    private function paketDariItem(int $id, bool $draft): ?int
    {
        $idPaket = $draft
            ? DB::table('N_SOP_Paket_Draft_Item as i')
                ->join('N_SOP_Paket_Draft as d', 'd.Id_SOP_Paket_Draft', '=', 'i.Id_SOP_Paket_Draft')
                ->where('i.Id_SOP_Draft_Item', $id)->value('d.Id_SOP_Paket')
            : DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $id)->value('Id_SOP_Paket');

        return $idPaket ? (int) $idPaket : null;
    }

    /* ================= HALAMAN ================= */

    /** GET /sop/admin/builder */
    public function index()
    {
        /* Daftar tingkat prioritas ikut dari server, bukan dibaca layar dari
           konstanta JS: konstanta itu ditulis sebelum tingkat buatan admin ada,
           jadi tingkat baru akan tampil sebagai kode mentah tanpa lencana. */
        return inertia('LMS/SOP/Governance/Admin/SopBuilder', [
            'prioritas' => app(\App\Services\LMS\SOP\SopPrioritasService::class)->untukLayar(),
            'tipeItemAktif' => SopTipeItemAktif::aktif(),
            'departemenOpsi' => DB::table('N_HRIS_Master_Departemen')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Nama_Departemen')
                ->get(['Id_Departemen as Id', 'Nama_Departemen as label', 'Kode_Departemen as kode']),
            'jenisOpsi' => DB::table('N_SOP_Master_Jenis_Dokumen')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Level_Hirarki')
                ->orderBy('Nama_Jenis')
                ->get(['Id_SOP_Jenis_Dokumen as Id', 'Nama_Jenis as label', 'Kode_Jenis_Dokumen as kode', 'Level_Hirarki as level']),
            'apqcOpsi' => DB::table('N_SOP_Master_APQC')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Kode_Proses')
                ->get(['Id_SOP_APQC as Id', 'Nama_Proses as label', 'Kode_Proses as kode']),
        ]);
    }

    /* ================= PAKET ================= */

    /** GET /api/v1/sop/admin/builder/paket/current */
    public function listPaket(Request $request)
    {
        try {
            // Berplafon: tiap baris menandatangani URL sampul (~4 ms sekali), jadi
            // limit tanpa batas berarti detik-detik CPU per permintaan.
            $limit = min(100, max(5, (int) $request->query('limit', 10)));
            $page = (int) $request->query('page', 1);
            $filters = $this->listFilters($request);

            [$rows, $total] = $this->paket->paginate($filters, $page, $limit);

            if ($rows->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            return ResponseHelper::successWithPagination($rows, $page, $limit, $total, 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP builder listPaket gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /paket/counts — hitungan untuk badge chip status dan untuk rail.
     *
     * Satu endpoint, dua jawaban, karena layar selalu membutuhkan keduanya
     * bersamaan: setiap sentuhan saringan mengubah badge status DAN sebaran
     * rail sekaligus. Memisahkannya berarti dua permintaan yang selalu
     * berangkat berpasangan dan bisa mendarat tak sinkron.
     *
     * `sumbu` tak dikenal TIDAK dijawab dengan rail kosong — rail kosong
     * terbaca sebagai "memang tak ada paket". Ia ditolak 422.
     */
    public function countsPaket(Request $request)
    {
        try {
            $filters = $this->listFilters($request);
            $hasil = $this->paket->statusCounts($filters);

            $sumbu = (string) $request->query('sumbu', '');
            if ($sumbu !== '') {
                if (! $this->paket->sumbuGrupDikenal($sumbu)) {
                    return ResponseHelper::error('Sumbu pengelompokan tidak dikenal', 422);
                }
                $hasil['grup'] = $this->paket->grupCounts($filters, $sumbu);
                $hasil['sumbu'] = $sumbu;
            }

            $hasil['tree'] = $this->paket->treeCounts($filters);

            return ResponseHelper::success($hasil, 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP builder countsPaket gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * Nilai kueri sebagai daftar, menerima `k[]=a&k[]=b` maupun `k=a,b`.
     *
     * Bentuk CSV ikut diterima supaya tautan yang ditulis tangan (dan yang
     * sudah beredar di riwayat peramban orang) tidak diam-diam menyaring
     * dengan satu nilai gabungan yang tak pernah cocok dengan apa pun.
     */
    private function daftarQuery(Request $request, string $kunci): array
    {
        $nilai = $request->query($kunci);

        if ($nilai === null || $nilai === '') {
            return [];
        }
        if (! is_array($nilai)) {
            $nilai = explode(',', (string) $nilai);
        }

        return array_values(array_filter(
            array_map(fn ($v) => is_string($v) ? trim($v) : $v, $nilai),
            fn ($v) => $v !== '' && $v !== null,
        ));
    }

    /**
     * Rakit filter list paket dari request.
     *
     * Status, prioritas, dan distribusi BERDAFTAR — beberapa nilai sekaligus,
     * bukan satu.
     */
    private function listFilters(Request $request): array
    {
        return [
            'search' => $request->query('q'),
            'departemen' => $this->daftarQuery($request, 'departemen'),
            'jenis' => $this->daftarQuery($request, 'jenis'),
            'apqc' => $this->daftarQuery($request, 'apqc'),
            'status' => $this->daftarQuery($request, 'status'),
            'prioritas' => $this->daftarQuery($request, 'prioritas'),
            'distribusi' => $this->daftarQuery($request, 'distribusi'),
            'absensi' => $request->query('absensi'),
            'estimasi' => $request->query('estimasi'),
            'terbit' => $request->query('terbit'),
            'tgl_dari' => $request->query('tgl_dari'),
            'tgl_sampai' => $request->query('tgl_sampai'),
            'sort' => $request->query('sort', 'newest'),
        ];
    }

    /** GET /api/v1/sop/admin/builder/paket/{id} */
    public function showPaket($id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        try {
            $paket = $this->paket->find($decoded);
            if (! $paket) {
                return ResponseHelper::error('Paket tidak ditemukan', 404);
            }

            if ($paket->Status === SopStatus::PAKET_PUBLISHED && ($draft = $this->paketDraft->header($decoded, Auth::user()->Id_Users))) {
                foreach (['Kode_Paket', 'Nama_Paket', 'Kode_Karyawan_Owner', 'Deskripsi', 'Tujuan_Ruang_Lingkup', 'Prioritas',
                    'Estimasi_Waktu_Baca_Menit', 'Mode_Urutan_Item', 'Mode_Distribusi', 'Cover_Path', 'Cover_Position', 'Flag_Absensi'] as $field) {
                    $paket->{$field} = $draft->{$field};
                }
                $paket->Cover_Url = $this->paket->coverUrl($draft->Cover_Path ?? null);
                $paket->Is_Working_Draft = true;
            }

            $sections = ! empty($paket->Is_Working_Draft)
                ? $this->paketDraft->sections($decoded, Auth::user()->Id_Users)
                : $this->section->byPaket($decoded);
            $dukungDokumenDraft = $this->paketDraft->dukungDokumen();
            $items = ! empty($paket->Is_Working_Draft)
                ? $this->paketDraft->items($decoded, Auth::user()->Id_Users)
                : $this->item->byPaket($decoded);
            $paketVersi = $this->paketVersi->current($decoded);
            $riwayatVersiPaket = $this->paketVersi->history($decoded);
            $nextVersiPaket = $this->paketVersi->nextVersion($decoded);
            $pendingChanges = ! empty($paket->Is_Working_Draft)
                ? $this->paketDraft->pendingChanges($decoded, Auth::user()->Id_Users)
                : $this->paketVersi->pendingChanges($decoded);
            // Working draft dipakai bersama semua admin; beri tahu bila ada yang lain menyentuhnya.
            $draftActivity = ! empty($paket->Is_Working_Draft)
                ? $this->paketDraft->lastActivityByOthers($decoded, Auth::user()->Id_Users)
                : null;

            return ResponseHelper::success(
                $this->sanitizeJsonPayload(compact('paket', 'sections', 'items', 'paketVersi', 'riwayatVersiPaket', 'nextVersiPaket', 'pendingChanges', 'draftActivity', 'dukungDokumenDraft')),
                'Data Ditemukan'
            );
        } catch (\Exception $e) {
            Log::error('SOP builder showPaket gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    private function sanitizeJsonPayload($value)
    {
        if (is_string($value)) {
            return $this->sanitizeUtf8($value);
        }

        if ($value instanceof \Illuminate\Support\Collection) {
            return $value->map(fn ($item) => $this->sanitizeJsonPayload($item));
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->sanitizeJsonPayload($item);
            }

            return $value;
        }

        if (is_object($value)) {
            foreach ($value as $key => $item) {
                $value->{$key} = $this->sanitizeJsonPayload($item);
            }
        }

        return $value;
    }

    private function sanitizeUtf8(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252, ISO-8859-1, UTF-8');
        if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }

        return (string) @iconv('UTF-8', 'UTF-8//IGNORE', $value);
    }

    /** POST /api/v1/sop/admin/builder/paket/store */
    public function storePaket(Request $request)
    {
        $data = $this->validatePaket($request);

        if ($request->has('Cover')) {
            $data['Cover_Path'] = $this->resolveCover($request);
        }

        try {
            $newId = DB::transaction(fn () => $this->paket->create($data, Auth::user()->Id_Users));
        } catch (\Throwable $e) {
            Log::error('SOP builder storePaket gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }

        $this->jejak('PAKET_BUAT', "Paket Dokumen \"{$data['Nama_Paket']}\" dibuat.", $newId);

        return ResponseHelper::success(
            ['Id_SOP_Paket' => Hashids::connection('custom')->encode($newId)],
            'Paket berhasil dibuat!',
            201,
        );
    }

    /** PUT /api/v1/sop/admin/builder/paket/update/{id} */
    public function updatePaket(Request $request, $id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        $data = $this->validatePaket($request);

        // Cover: hanya tersentuh bila key 'Cover' dikirim. Simpan path lama untuk dihapus bila berganti.
        $oldCover = null;
        if ($request->has('Cover')) {
            $oldCover = $this->paket->coverPath($decoded);
            $data['Cover_Path'] = $this->resolveCover($request);
        }

        /* Nilai LAMA dibaca sebelum ditimpa. Tanpa ini jejaknya cuma berbunyi
           "identitas paket diubah" — benar, tapi tak menjawab satu pun
           pertanyaan yang membuat orang membuka riwayat. */
        $sebelum = (array) (DB::table('N_SOP_Master_Paket')
            ->where('Id_SOP_Paket', $decoded)->first() ?? []);

        try {
            $published = DB::transaction(function () use ($decoded, $data) {
                $published = DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $decoded)->value('Status') === SopStatus::PAKET_PUBLISHED;
                if ($published) {
                    if (! $this->paketDraft->updateHeader($decoded, $data, Auth::user()->Id_Users)) {
                        throw new \RuntimeException('Working draft paket tidak tersedia.');
                    }
                } else {
                    $this->paket->update($decoded, $data, Auth::user()->Id_Users);
                }

                return $published;
            });
        } catch (\Throwable $e) {
            Log::error('SOP builder updatePaket gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }

        // hapus objek GCS lama bila cover berganti (best-effort, DI LUAR try transaksi)
        if (! $published && $request->has('Cover') && $oldCover && $oldCover !== ($data['Cover_Path'] ?? null)) {
            try {
                Storage::disk('gcs')->delete($oldCover);
            } catch (\Throwable $e) {
                /* best-effort: berkas cover lama yang tertinggal tidak merusak apa pun,
                   sedangkan menggagalkan respons atas pembaruan yang SUDAH commit
                   mendorong admin mengulang dan membuat duplikat. */
                Log::warning('SOP cover lama gagal dihapus dari GCS', [
                    'path' => $oldCover,
                    'exception' => $e,
                ]);
            }
        }

        $beda = SopDiff::antara($sebelum, $data);
        $this->jejak('PAKET_UBAH',
            SopDiff::kalimat("Identitas paket \"{$data['Nama_Paket']}\"", $beda),
            $decoded, null, [
                'ke_working_draft' => $published,
                'perubahan' => $beda,
            ]);

        return ResponseHelper::success(null, 'Paket berhasil diupdate!');
    }

    /** PATCH /api/v1/sop/admin/builder/paket/status/{id}  body: { status } */
    public function updateStatusPaket(Request $request, $id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        $status = $request->input('status');
        $allowed = [SopStatus::PAKET_DRAFT, SopStatus::PAKET_PUBLISHED, SopStatus::PAKET_ARCHIVED];
        if (! in_array($status, $allowed, true)) {
            return ResponseHelper::error('Status tidak valid', 422);
        }

        $ringkasanPerubahan = null;
        $publishedRevision = $status === SopStatus::PAKET_PUBLISHED
            && DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $decoded)->value('Status') === SopStatus::PAKET_PUBLISHED
            && (bool) $this->paketDraft->working($decoded);
        /* DOKUMEN KEMBAR DI DALAM PAKET — diperiksa PALING AWAL.
           Penjaga saat item ditautkan menghentikan yang baru; yang SUDAH
           telanjur kembar (paket 924 di dev punya dua item menunjuk dokumen yang
           sama) baru merusak angka ketika paketnya terbit dan orang
           mengonfirmasi bacaannya — rollup berkunci (paket, versi) tanpa item,
           jadi keduanya menambah penghitung yang sama.

           Ditaruh sebelum pendingChanges() bukan demi kecepatan: penolakannya
           mutlak, dan menghitung dampak rilis untuk paket yang tak boleh terbit
           hanya menambah jalan bagi galat lain untuk muncul lebih dulu dan
           menutupi sebab sebenarnya. */
        if ($status === SopStatus::PAKET_PUBLISHED) {
            if ($pesan = $this->dokumenKembarDiPaket($decoded)) {
                return ResponseHelper::error($pesan, 422);
            }
        }

        if ($status === SopStatus::PAKET_PUBLISHED) {
            $ringkasanPerubahan = trim((string) $request->input('Ringkasan_Perubahan', ''));
            if ($ringkasanPerubahan === '') {
                return ResponseHelper::error('Ringkasan perubahan wajib diisi sebelum publish paket.', 422);
            }
            if (mb_strlen($ringkasanPerubahan) > 1000) {
                return ResponseHelper::error('Ringkasan perubahan maksimal 1000 karakter.', 422);
            }

            $changes = $publishedRevision
                ? $this->paketDraft->pendingChanges($decoded, Auth::user()->Id_Users)
                : $this->paketVersi->pendingChanges($decoded);
            if (! empty($changes['errors'])) {
                return ResponseHelper::error(implode(' ', $changes['errors']), 422);
            }
            $summary = $changes['summary'] ?? [];
            $ready = (int) ($summary['ready_item_versions'] ?? 0);
            $draft = (int) ($summary['draft_item_versions'] ?? 0);
            if (($changes['is_first_release'] ?? false) && $draft > 0) {
                /* Sebut dokumennya: "masih ada versi Draft" tanpa nama membuat
                   admin mencari sendiri item mana yang dimaksud. */
                $belum = $this->paketVersi->itemBelumSiapRilis($decoded);

                return ResponseHelper::error(
                    'Item berikut belum punya versi terbit: '.$belum->implode(', ')
                    .'. Terbitkan versinya di Master Dokumen terlebih dahulu.', 422);
            }
            if (! ($changes['is_first_release'] ?? false)
                && $ready === 0
                && (int) ($summary['items'] ?? 0) === 0
                && (int) ($summary['sections'] ?? 0) === 0
                && (int) ($summary['package'] ?? 0) === 0) {
                return ResponseHelper::error('Belum ada perubahan yang siap dirilis.', 422);
            }
        }

        // Gating publish: wajib ≥1 section & tak ada section kosong
        if ($status === SopStatus::PAKET_PUBLISHED && ! $publishedRevision) {
            $r = $this->section->readiness($decoded);
            if ($r['total'] < 1) {
                return ResponseHelper::error('Tambahkan minimal 1 section berisi item sebelum publish.', 422);
            }
            if ($r['empty'] > 0) {
                return ResponseHelper::error('Masih ada section kosong. Isi atau hapus dulu sebelum publish.', 422);
            }
        }

        try {
            $hasil = DB::transaction(function () use (
                $publishedRevision, $decoded, $ringkasanPerubahan, $request, $status
            ) {
                $result = $publishedRevision
                    ? $this->paketDraft->publish($decoded, Auth::user()->Id_Users, $ringkasanPerubahan,
                        /* Sifat menyeluruh — dipakai dokumen yang tak disebut satu per
                           satu, termasuk item yang baru lahir di publish ini. Apa pun
                           selain 'T' jatuh ke mayor: kalau ragu, membaca ulang itu
                           murah, melewatkan revisi penting tidak. */
                        $request->input('Wajib_Baca_Ulang') === 'T' ? 'T' : 'Y',
                        $this->sifatPerDokumen($request->input('Sifat_Per_Dokumen')))
                    : $this->paket->updateStatus($decoded, $status, Auth::user()->Id_Users, $ringkasanPerubahan);

                /* INTENT DI DALAM TRANSAKSI, dan itulah yang membuat outbox
                   bermakna: bila transaksinya batal intennya ikut batal, dan
                   bila commit pekerjaannya sudah durable — dapat dipulihkan
                   dispatcher walau proses ini mati sedetik kemudian. Isinya
                   satu INSERT, jadi ia tak menambah umur transaksi. */
                $taskId = $status === SopStatus::PAKET_PUBLISHED
                    ? $this->reconcileSetelahPublish($decoded, Auth::user()->Id_Users)
                    : null;

                return ['result' => $result, 'task_id' => $taskId];
            });
        } catch (\DomainException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('SOP builder updateStatusPaket gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }

        $taskId = $hasil['task_id'];

        $this->jejak('PAKET_STATUS', "Status paket diubah menjadi {$status}.", $decoded, null, [
            'status' => $status, 'revisi_terbit' => $publishedRevision,
            'ringkasan' => $ringkasanPerubahan ? mb_substr($ringkasanPerubahan, 0, 200) : null,
            'task_id' => $taskId,
        ]);

        /* Mempercepat saja, dan karena itu best-effort. Dispatcher terjadwal
           tetap akan memungut baris QUEUED yang tertinggal, sehingga kegagalan
           di sini tak boleh menggagalkan respons atas publish yang SUDAH
           commit — persis kekeliruan yang diperbaiki di Fase 1. */
        if ($taskId !== null) {
            try {
                app(\App\Services\SYSTEM\TaskProcessing\TaskOutboxDispatcher::class)
                    ->dispatchBatch('sop-reconcile:'.Str::uuid(), 5);
            } catch (\Throwable $e) {
                Log::warning('SOP: dispatch outbox rekonsiliasi gagal dipicu', [
                    'id_paket' => $decoded, 'exception' => $e,
                ]);
            }
        }

        return ResponseHelper::success(
            ($hasil['result'] ?? []) + ['task_id' => $taskId],
            $taskId !== null
                ? 'Paket diterbitkan. Kewajiban karyawan sedang disemai di latar — pantau di panel Aktivitas.'
                : "Status paket: {$status}",
            $taskId !== null ? 202 : 200,
        );
    }

    /**
     * Peta sifat revisi per dokumen dari permintaan: hashid => 'Y'|'T'.
     *
     * Masukan dari luar, jadi disaring keras. Kunci yang tak bisa didekode
     * DIBUANG, bukan dianggap dokumen lain — dan nilainya hanya diterima bila
     * persis 'T'; apa pun selain itu jatuh ke mayor. Dua-duanya condong ke arah
     * yang sama: salah tulis di sisi klien berakhir pada karyawan diminta
     * membaca ulang, bukan pada revisi yang lolos tanpa dibaca siapa pun.
     */
    private function sifatPerDokumen($masukan): array
    {
        if (! is_array($masukan)) {
            return [];
        }

        $peta = [];
        foreach ($masukan as $hash => $nilai) {
            $id = Hashids::connection('custom')->decode((string) $hash)[0] ?? null;
            if ($id === null) {
                continue;
            }

            $peta[(int) $id] = $nilai === 'T' ? 'T' : 'Y';
        }

        return $peta;
    }

    /**
     * Semai kewajiban karyawan setelah paket terbit.
     *
     * reconcilePaket() menyisir setiap penugasan aktif x setiap item wajib, sekitar
     * tiga pernyataan per pasangan. Terukur pada paket dengan 34 penugasan dan 3 item
     * wajib: 308 query dan 16,3 detik. Pada paket berpenugasan ribuan itu menjadi
     * belasan menit — dan admin menunggunya sambil permintaan publish menggantung,
     * padahal paketnya SUDAH ter-commit di baris sebelumnya.
     *
     * Di atas ambang, pekerjaan itu dipindahkan ke ProsesReconcileSop. Ambangnya
     * sengaja memakai setelan yang sama dengan penjadwalan (penjadwalan_async_ambang)
     * karena ukurannya sama: jumlah karyawan yang harus disentuh.
     *
     * Paket kecil tetap sinkron supaya perilakunya persis seperti sebelumnya —
     * kewajiban sudah tersemai begitu respons kembali.
     *
     * Hasil reconcile tidak pernah masuk respons publish (nilai baliknya memang
     * dibuang), jadi pemindahan ini tidak mengubah kontrak API sama sekali.
     */
    /**
     * Rekonsiliasi setelah paket terbit — kini INTENT, bukan pekerjaan.
     *
     * Dulu metode ini memilih sinkron-vs-antri lewat ambang, lalu menjalankan
     * reconcilePaket() di tempat bila di bawah ambang. Karena ia dipanggil dari
     * dalam transaksi publish, pilihan "sinkron" berarti menyisir setiap
     * penugasan x setiap item wajib SAMBIL menahan kunci paket — dan pemotongan
     * per-potongan milik SopReconciliationService lumpuh jadi savepoint.
     *
     * Sekarang yang ditulis hanya satu baris intent. Ambangnya dicabut dari
     * jalur ini: tak ada lagi dua jalur yang bisa menyimpang satu sama lain.
     * Settingnya sendiri tetap hidup untuk jalur penjadwalan.
     *
     * @return string|null Export_Id baris progres, untuk dipantau panel Aktivitas.
     */
    private function reconcileSetelahPublish(int $idPaket, ?int $userId): ?string
    {
        $paket = DB::table('N_SOP_Master_Paket')
            ->where('Id_SOP_Paket', $idPaket)
            ->first(['Nama_Paket', 'Versi_Paket_Aktif']);

        $versi = (int) ($paket->Versi_Paket_Aktif ?? 0);
        $taskSvc = app(\App\Services\LMS\SOP\SopReconcileTaskService::class);
        $taskId = $taskSvc->makeId();

        /* Ember menit pada kunci, alasan yang sama dengan jalur dokumen: retry
           atas satu permintaan tergabung, sedangkan penerbitan ulang yang
           benar-benar terjadi belakangan tetap mendapat intent sendiri. */
        $ember = now()->format('YmdHi');

        $outbox = app(\App\Services\SYSTEM\TaskProcessing\TaskOutboxService::class)->create([
            'Task_Type' => 'SOP_RECONCILE_PAKET',
            'Aggregate_Type' => 'N_SOP_Master_Paket',
            'Aggregate_Id' => $idPaket,
            'Idempotency_Key' => "SOP_RECONCILE_PAKET:{$idPaket}:{$versi}:{$ember}",
            'Queue_Name' => (string) config('queue.names.penjadwalan_training'),
            /* WAJIB lewat absoluteUrl(): APP_URL di lingkungan ini ditulis tanpa
               skema, dan URL tanpa skema ditolak Cloud Tasks secara SENYAP. */
            'Task_Url' => \App\Services\SYSTEM\TaskProcessing\CloudTaskService::absoluteUrl(
                '/api/v1/internal/task-outbox/execute'),
            'Payload' => [
                'id_paket' => $idPaket,
                'alasan' => 'PAKET_PUBLISH',
                'user_id' => $userId,
                'task_id' => $taskId,
            ],
            'Max_Attempts' => 3,
            'Created_By' => $userId,
            'Source_Module' => 'LMS',
            'Source_Feature' => 'SopPublishPaket',
        ]);

        /* Baris progres hanya bila intent-nya memang baru — kalau tidak, ia
           yatim dan panel menggantung di "queued" selamanya. */
        if ($outbox->wasRecentlyCreated) {
            $taskSvc->mulai(
                ['id_paket' => $idPaket, 'nama_paket' => $paket->Nama_Paket ?? 'Paket Dokumen'],
                1,
                Auth::user()?->karyawan?->Kode_Karyawan,
                $taskId,
            );

            return $taskId;
        }

        $lama = is_array($outbox->Payload) ? $outbox->Payload : (array) json_decode((string) $outbox->Payload, true);

        return isset($lama['task_id']) ? (string) $lama['task_id'] : null;
    }

    /**
     * GET /api/v1/sop/admin/builder/paket/{id}/riwayat
     *
     * Riwayat aktivitas paket dari N_SOP_Log, memakai IX_N_SOP_Log_Paket
     * (Id_SOP_Paket, Created_At). Melengkapi riwayat versi yang hanya mencatat
     * rilis; ini mencatat setiap suntingan di antaranya.
     *
     * Jejak baru terkumpul sejak pencatatan Builder dipasang — suntingan lama
     * tidak pernah tercatat dan tidak dapat di-backfill.
     */
    public function riwayatPaket(Request $request, $id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }
        if (! DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $decoded)->exists()) {
            return ResponseHelper::error('Paket tidak ditemukan', 404);
        }

        try {
            $limit = min(max((int) $request->input('limit', 40), 1), 200);
            $offset = max((int) $request->input('offset', 0), 0);

            $q = DB::table('N_SOP_Log as l')
                ->leftJoin('KPI_Users as u', 'u.Id_Users', '=', 'l.Oleh_Id_Users')
                ->leftJoin('Karyawan as k', 'k.UserID_Web', '=', 'u.Id_Users')
                /* Konteks item & versi. Tanpa keduanya, baris riwayat berbunyi
                   "versi item dipublikasikan" tanpa menyebut item yang mana —
                   pada paket berisi belasan item, itu jejak yang tak bisa
                   ditelusuri, dan jejak yang tak bisa ditelusuri bukan jejak. */
                ->leftJoin('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'l.Id_SOP_Paket_Item')
                ->leftJoin('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'l.Id_SOP_Item_Versi')
                ->where('l.Id_SOP_Paket', $decoded);

            if ($aksi = trim((string) $request->input('aksi', ''))) {
                $q->where('l.Aksi', $aksi);
            }

            $rows = (clone $q)
                ->orderByDesc('l.Created_At')
                ->orderByDesc('l.Id_SOP_Log')
                ->offset($offset)->limit($limit)
                ->get([
                    'l.Id_SOP_Log', 'l.Aksi', 'l.Keterangan', 'l.Created_At', 'l.Meta_JSON',
                    'k.Nama as Oleh_Nama', 'u.Username as Oleh_Username',
                    'i.Judul_Item', 'v.Versi_Ke',
                ])
                /* Diff dibongkar DI SERVER. Meta_JSON adalah kantong serba-guna
                   berisi id internal dan bendera teknis; menurunkannya utuh ke
                   browser berarti membocorkan bentuk dalam sekaligus menyerahkan
                   penafsirannya ke layar. Yang naik hanya daftar perubahan. */
                ->map(function ($r) {
                    $meta = json_decode((string) $r->Meta_JSON, true) ?: [];
                    unset($r->Meta_JSON);
                    $r->Perubahan = array_values(array_filter(
                        $meta['perubahan'] ?? [],
                        fn ($p) => is_array($p) && isset($p['field']),
                    ));

                    return $r;
                });

            /* Jumlah keseluruhan ikut dikirim supaya layar bisa mengatakan
               "40 dari 312" alih-alih diam-diam memotong. Pembatasan yang senyap
               adalah pembatasan yang menipu — dan pada layar audit, ia membuat
               orang menyimpulkan tak ada yang terjadi sesudah baris terakhir. */
            return ResponseHelper::success([
                'rows' => $this->sanitizeJsonPayload($rows),
                'total' => (clone $q)->count(),
                'aksi' => DB::table('N_SOP_Log')->where('Id_SOP_Paket', $decoded)
                    ->distinct()->orderBy('Aksi')->pluck('Aksi'),
            ], 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP builder riwayatPaket gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** DELETE /api/v1/sop/admin/builder/paket/delete/{id} */
    public function destroyPaket($id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        try {
            $nama = DB::transaction(function () use ($decoded) {
                $nama = DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $decoded)->value('Nama_Paket');
                $this->paket->softDelete($decoded, Auth::user()->Id_Users);

                return $nama;
            });
        } catch (\Throwable $e) {
            Log::error('SOP builder destroyPaket gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }

        $this->jejak('PAKET_HAPUS', "Paket Dokumen \"{$nama}\" dihapus.", $decoded);

        return ResponseHelper::success(null, 'Paket berhasil dihapus!');
    }

    /**
     * GET /item/{id}/dampak — apa yang ikut hilang kalau item ini dihapus.
     *
     * Konfirmasi hapus selama ini cuma berbunyi "Hapus item X?" — dan itu
     * menyembunyikan yang justru harus ditimbang. Catatan baca karyawan
     * menempel PADA ITEM: 559 baris di dev, seluruhnya di paket terbit.
     * Menghapus item berarti mencabut sesuatu yang sudah ditugaskan, dan angka
     * kepatuhan paketnya berubah tanpa penjelasan bagi siapa pun yang
     * membacanya belakangan.
     *
     * Ringan dengan sengaja: dipanggil tepat saat tombol ditekan, jadi ia tak
     * boleh jadi alasan konfirmasinya lambat muncul.
     */
    public function itemDampak($id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        /* Item working-draft belum punya catatan baca sama sekali — yang
           dibaca karyawan adalah item live. Dijawab nol, bukan ditolak:
           konfirmasi yang gagal memuat dampak lebih buruk daripada konfirmasi
           yang menyebut nol dengan jujur. */
        if ($this->isDraftToken($id, 'di')) {
            return ResponseHelper::success(
                ['progres' => 0, 'selesai' => 0, 'paket_terbit' => true, 'draft_overlay' => true],
                'Data Ditemukan');
        }

        $item = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->where('i.Id_SOP_Paket_Item', $decoded)
            ->first(['i.Id_SOP_Paket_Item', 'p.Status']);
        if (! $item) {
            return ResponseHelper::error('Item tidak ditemukan', 404);
        }

        $progres = DB::table('N_SOP_Progress')->where('Id_SOP_Paket_Item', $decoded);

        return ResponseHelper::success([
            'progres' => (clone $progres)->count(),
            'selesai' => (clone $progres)->whereIn('Status', [
                SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI,
            ])->count(),
            'paket_terbit' => $item->Status === SopStatus::PAKET_PUBLISHED,
            'draft_overlay' => false,
        ], 'Data Ditemukan');
    }

    /** POST /api/v1/sop/admin/builder/paket/{id}/reconcile — reseed manual */
    public function discardDraft($id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }
        if (DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $decoded)->value('Status') !== SopStatus::PAKET_PUBLISHED) {
            return ResponseHelper::error('Reset working draft hanya berlaku untuk paket yang sudah terbit.', 422);
        }
        if (! $this->paketDraft->discard($decoded, Auth::user()->Id_Users)) {
            return ResponseHelper::error('Working draft tidak ditemukan.', 404);
        }
        $this->jejak('DRAFT_BUANG', 'Working draft revisi dibuang; data aktif tidak berubah.', $decoded);

        return ResponseHelper::success(null, 'Draft revisi dibuang. Data aktif tidak berubah.');
    }

    /**
     * DELETE /api/v1/sop/admin/builder/paket/{id}/isi — kosongkan paket DRAFT.
     *
     * Pasangan discardDraft() untuk sisi yang belum terbit. Paket terbit sudah
     * lama punya jalan keluar dari revisi yang kacau (buang salinan kerjanya);
     * paket DRAFT justru tidak, padahal di sanalah orang paling banyak mencoba-
     * coba — dan endpoint di atas menolaknya dengan tegas.
     *
     * Bukan "undo". Yang dihapus penempatan item & section-nya saja; Dokumen SOP
     * beserta versinya tetap utuh karena dokumen milik bersama dan dikelola di
     * Master Dokumen. Lihat SopPaketService::kosongkan().
     */
    public function kosongkanPaket($id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        $paket = DB::table('N_SOP_Master_Paket')
            ->where('Id_SOP_Paket', $decoded)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->first(['Nama_Paket', 'Status']);
        if (! $paket) {
            return ResponseHelper::error('Paket tidak ditemukan', 404);
        }

        /* Paket terbit punya jalannya sendiri, dan jalan itu JAUH lebih aman:
           salinan kerjanya dibuang tanpa menyentuh apa yang sedang dibaca
           karyawan. Mengosongkan paket terbit lewat sini akan mencabut seluruh
           isinya dari orang yang sedang ditugaskan membacanya. */
        if ($paket->Status !== SopStatus::PAKET_DRAFT) {
            return ResponseHelper::error(
                'Paket ini sudah terbit, jadi isinya tak bisa dikosongkan begitu saja — '
                .'karyawan sedang ditugaskan membacanya. Untuk membatalkan perubahan yang '
                .'belum dirilis, pakai "Buang Draft Revisi".',
                422);
        }

        /* Penjaga yang hari ini memang tak pernah menyala — nol penjadwalan pada
           seluruh paket DRAFT — dan tetap ada karena gerbang "hanya paket
           terbit yang bisa dijadwalkan" hidup di DAFTAR PILIHAN penjadwalan
           (SopPenjadwalanService::paketPublished), bukan sebagai aturan pada
           penulisannya. Gerbang yang hanya menyaring layar bisa dilewati jalur
           lain; yang ini memastikan pengosongan tak pernah memutus catatan baca
           siapa pun. */
        $penugasan = DB::table('N_SOP_Penjadwalan')
            ->where('Id_SOP_Paket', $decoded)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->count();
        if ($penugasan > 0) {
            return ResponseHelper::error(
                'Paket ini sudah punya penugasan, jadi isinya tak bisa dikosongkan — '
                .'catatan baca karyawan menempel pada item-itemnya.',
                422);
        }

        try {
            $n = $this->paket->kosongkan($decoded, Auth::user()->Id_Users);
        } catch (\Exception $e) {
            Log::error('SOP builder kosongkanPaket gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }

        $this->jejak('PAKET_KOSONGKAN',
            "Isi paket dikosongkan: {$n['item']} item dan {$n['section']} section dinonaktifkan. "
            .'Dokumennya tidak dihapus.',
            $decoded, null, $n);

        return ResponseHelper::success($n,
            "Paket dikosongkan — {$n['item']} item dan {$n['section']} section dilepas. "
            .'Dokumennya tetap ada dan bisa dipakai lagi.');
    }

    public function reconcile($id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }
        try {
            $stat = $this->reconcile->reconcilePaket($decoded, 'MANUAL', Auth::user()->Id_Users);

            return ResponseHelper::success(
                $stat,
                "Reconcile: {$stat['seeded']} baris di-seed, {$stat['reopened']} dibuka kembali.",
            );
        } catch (\Exception $e) {
            Log::error('SOP builder reconcile gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /* ================= ITEM ================= */

    /** POST /api/v1/sop/admin/builder/paket/{idPaket}/item/store */
    public function storeItem(Request $request, $idPaket)
    {
        $decodedPaket = $this->decode($idPaket);
        if (! $decodedPaket) {
            return ResponseHelper::error('ID paket tidak valid', 400);
        }

        $data = $this->validateItem($request);
        if (! $this->item->tipeValid($data['Tipe_Item'])) {
            return ResponseHelper::error('Tipe item tidak valid', 422);
        }
        /* SAKELAR TIPE — pintu server, bukan cuma palet.
           Palet Builder memang berhenti merender tipe yang dimatikan, tapi layar
           yang berhenti menawarkan bukan penjaga: halaman yang sudah terbuka
           sebelum sakelarnya dimatikan masih memegang paletnya, dan permintaan
           buatan tak pernah melihat palet sama sekali. */
        if (! SopTipeItemAktif::nyala($data['Tipe_Item'])) {
            return ResponseHelper::error(SopTipeItemAktif::pesanMati($data['Tipe_Item']), 422);
        }
        $data = $this->terapkanDokumen($data);
        if ($data === null) {
            return ResponseHelper::error('Dokumen tidak valid atau tipenya tak cocok dengan tipe item.', 422);
        }
        // ABSENSI kini toggle paket, bukan item
        if ($data['Tipe_Item'] === 'ABSENSI') {
            return ResponseHelper::error('Absensi diatur lewat toggle paket, bukan item.', 422);
        }
        $data['Konten_Teks'] = SopHtml::sanitize($data['Konten_Teks'] ?? null); // anti-XSS server-side

        $published = DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $decodedPaket)->value('Status') === SopStatus::PAKET_PUBLISHED;

        /* Item ber-versi di paket TERBIT wajib bisa membawa tautan dokumennya.
           Jalur lama — judul + konten kandidat, dokumennya dibuat belakangan
           saat promosi lewat ensureForItem() — adalah pintu KEDUA tempat
           Dokumen SOP lahir, dan pintu yang paling tak terlihat: dokumennya
           muncul berhari-hari setelah admin menekan Simpan, lengkap dengan
           Nomor_SOP_Resmi salinan yang melewati penjaga nomor kembar.
           Ditutup terang-terangan, bukan dibiarkan gagal diam-diam. */
        if ($published && SopTipeItem::isVersioned($data['Tipe_Item']) && ! $this->paketDraft->dukungDokumen()) {
            return ResponseHelper::error(
                'Paket ini sudah terbit, dan tabel revisinya belum bisa menyimpan tautan Dokumen. '
                .'Jalankan DDL-2026-033 lebih dulu; sampai itu, item bertipe '.$data['Tipe_Item']
                .' hanya bisa ditambahkan pada paket yang belum terbit.',
                422);
        }

        if ($pesan = $this->pastikanIdentitasItem($data, $published)) {
            return ResponseHelper::error($pesan, 422);
        }

        /* Pintu server ikut ditutup, bukan hanya tab UI. Tanpa ini, permintaan
           API buatan masih bisa mencoba jalur legacy yang membuat dokumen lalu
           langsung menerbitkannya — melompati Pengajuan/Review/Pengesahan.

           Dibaca dari REQUEST, bukan dari $data: aturan validasinya sudah
           dicabut, jadi field itu tak pernah sampai ke $data lagi. Kalau
           penjaga ini ikut membacanya dari sana, ia berhenti menyala tanpa
           satu pun tanda — persis bentuk kegagalan yang paling mahal, karena
           pengirim lama akan dibalas "berhasil" untuk dokumen yang tak pernah
           dibuat. */
        if (filled($request->input('Dokumen_Baru'))) {
            return ResponseHelper::error(
                'Dokumen baru harus diajukan dan diselesaikan lewat Master Dokumen. '
                .'Setelah tahap Filing and Registered, pilih dokumennya di Builder.',
                422,
            );
        }

        /* Menautkan dokumen yang SUDAH ADA: pastikan ia memang punya isi.
           Jalur "buat dokumen baru" tak lewat sini — versi pertamanya dibuat
           dan diterbitkan sekaligus di buatDokumenBaru(). */
        if (! empty($data['Id_SOP_Dokumen'])) {
            if ($pesan = $this->pastikanDokumenSiap((int) $data['Id_SOP_Dokumen'])) {
                return ResponseHelper::error($pesan, 422);
            }
            if ($pesan = $this->pastikanDokumenBelumDipakaiDiPaket($decodedPaket, (int) $data['Id_SOP_Dokumen'])) {
                return ResponseHelper::error($pesan, 422);
            }
        }

        // Section WAJIB & harus milik paket ini
        $idSection = $this->decode($data['Id_SOP_Section'] ?? '');
        if (! $idSection || (! ($published
            ? $this->paketDraft->sectionBelongsTo($idSection, $decodedPaket, Auth::user()->Id_Users)
            : DB::table('N_SOP_Paket_Section')
                ->where('Id_SOP_Section', $idSection)
                ->where('Id_SOP_Paket', $decodedPaket)
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->exists()))) {
            return ResponseHelper::error('Section tidak valid', 422);
        }
        $data['Id_SOP_Section'] = $idSection;

        try {
            $hasil = DB::transaction(function () use ($published, $data, $idSection, $decodedPaket) {
                if ($published) {
                    $data['Id_SOP_Draft_Section'] = $idSection;
                    /* Dokumen sudah di-decode terapkanDokumen() jadi id mentah;
                       createItem menuliskannya bila kolomnya ada — dan gerbang di
                       atas memastikan kolomnya memang ada untuk tipe ber-versi. */
                    $draftVersion = ($data['Tipe_Item'] === 'KUIS' && ! empty($data['Id_Master_Ujian']))
                        ? ['Id_Master_Ujian' => $data['Id_Master_Ujian']]
                        : null;
                    $newId = $this->paketDraft->createItem($decodedPaket, $data, Auth::user()->Id_Users, $draftVersion);
                    if (! $newId) {
                        throw new \RuntimeException('Item draft tidak dapat dibuat.');
                    }

                    return ['draft' => true, 'id' => (int) $newId];
                }

                $newId = $this->item->create($decodedPaket, $data, Auth::user()->Id_Users);

                /* ITEM BER-VERSI MENUMPANG VERSI DOKUMENNYA — TIDAK MELAHIRKAN v1.
                   Dulu di sini dibuat versi milik-item (Id_SOP_Dokumen NULL) dari
                   konten Library, lalu langsung diterbitkan. Dua akibatnya buruk:
                   versinya tak terlihat oleh apa pun yang menelusuri lewat dokumen,
                   dan penjaga "tak boleh terbit tanpa pengesah" — yang menyaring
                   lewat join ke N_SOP_Dokumen — tak pernah menyentuhnya, sehingga
                   Builder bisa menerbitkan aturan kerja yang tak seorang pun
                   tanda tangani. Sejak Fase 6 sumbernya satu: dokumennya. */
                if (SopTipeItem::isVersioned($data['Tipe_Item'])) {
                    $this->item->tautkanDokumen($newId, (int) $data['Id_SOP_Dokumen'], Auth::user()->Id_Users);
                }

                return ['draft' => false, 'id' => (int) $newId];
            });
        } catch (\DomainException $e) {
            /* Pembuatan dokumen baru menolak isinya — konten tak cocok dengan
               tipe, atau versi pertamanya tak punya pengesah. Pesannya sudah
               ditulis untuk admin; tanpa cabang ini ia tertelan catch di bawah
               dan muncul sebagai "Terjadi Kesalahan Sistem" yang tak bisa
               ditindaklanjuti siapa pun. */
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('SOP builder storeItem gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }

        if ($hasil['draft']) {
            $this->jejak('ITEM_BUAT', "Item draft \"{$data['Judul_Item']}\" ditambahkan.", $decodedPaket, null, [
                'id_draft_item' => $hasil['id'], 'tipe' => $data['Tipe_Item'],
            ]);

            return ResponseHelper::success(
                ['Id_SOP_Paket_Item' => 'di_'.Hashids::connection('custom')->encode($hasil['id'])],
                'Item draft berhasil ditambahkan!',
                201,
            );
        }

        $this->jejak('ITEM_BUAT', "Item \"{$data['Judul_Item']}\" ditambahkan.", $decodedPaket, $hasil['id'], [
            'tipe' => $data['Tipe_Item'],
        ]);

        return ResponseHelper::success(
            ['Id_SOP_Paket_Item' => Hashids::connection('custom')->encode($hasil['id'])],
            'Item berhasil ditambahkan!',
            201,
        );
    }

    /** PUT /api/v1/sop/admin/builder/item/update/{id} — dukung ubah Tipe_Item (opsional). */
    public function updateItem(Request $request, $id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        $data = $this->validateItem($request, false);
        $data['Konten_Teks'] = SopHtml::sanitize($data['Konten_Teks'] ?? null); // anti-XSS server-side

        /* TIPE ITEM BER-DOKUMEN TAK BISA DIUBAH — dan ini bukan aturan baru.
           terapkanDokumen() di bawah sudah menolak setiap ketidakcocokan sejak
           Fase 6, tapi pesannya berbunyi "Dokumen SOP tidak valid", yang
           menyalahkan dokumennya dan tak menyebut sebab sebenarnya. Dijawab di
           sini supaya admin tahu apa yang harus dilakukan.

           Alasannya bukan kerapian: pembaca tiap tipe berbeda, dan 559 baris
           catatan baca karyawan menempel pada tipe yang sekarang. Aturan yang
           sama sudah lama berlaku di Master ("tipe dokumen tak bisa diubah
           selama masih dipakai paket"). */
        $tipeDiminta = $request->input('Tipe_Item');
        if ($tipeDiminta && ! empty($data['Id_SOP_Dokumen'])) {
            $idDok = $this->decode($data['Id_SOP_Dokumen']);
            $tipeDok = $idDok ? DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDok)->value('Tipe') : null;

            if ($tipeDok && $tipeDiminta !== $tipeDok) {
                return ResponseHelper::error(
                    "Tipe item mengikuti Dokumennya ({$tipeDok}) dan tak bisa diubah jadi {$tipeDiminta}. "
                    .'Pembaca tiap tipe berbeda, dan catatan baca karyawan menempel pada tipe yang sekarang. '
                    .'Kalau tipenya keliru, hapus item ini lalu tambahkan lagi dengan dokumen yang sesuai.',
                    422);
            }
        }

        $data = $this->terapkanDokumen($data);
        if ($data === null) {
            return ResponseHelper::error('Dokumen tidak valid atau tipenya tak cocok dengan tipe item.', 422);
        }

        if ($this->isDraftToken($id, 'di')) {
            $current = $this->paketDraft->draftItem($decoded);
            if (! $current) {
                return ResponseHelper::error('Item draft tidak ditemukan', 404);
            }

            $newTipe = $data['Tipe_Item'] ?? $current->Tipe_Item;
            if (! $this->item->tipeValid($newTipe) || $newTipe === 'ABSENSI') {
                return ResponseHelper::error('Tipe item tidak valid', 422);
            }
            /* Sakelar tipe hanya menutup PEMBUATAN. Item yang terlanjur bertipe
               mati tetap harus bisa disunting — kalau tidak, mematikan sebuah
               tipe akan membekukan setiap item lama bertipe itu, termasuk untuk
               perbaikan yang tak ada hubungannya dengan tipenya. Karena itu yang
               diperiksa PERPINDAHANNYA, bukan tipe yang sedang dipakai. */
            if ($newTipe !== $current->Tipe_Item && ! SopTipeItemAktif::nyala($newTipe)) {
                return ResponseHelper::error(SopTipeItemAktif::pesanMati($newTipe), 422);
            }
            /* BERPINDAH KE TIPE BER-VERSI = MEMBUAT DOKUMEN, dan itu pintu
               KETIGA yang ikut ditutup 27 Agu 2026. changeType() melahirkan versi
               pertama dari konten Library, lalu ensureForItem() membuatkan
               Dokumen SOP-nya sendiri — dengan Nomor_SOP_Resmi salinan yang
               melewati penjaga nomor kembar, dan tanpa satu pun tahap pengesahan.
               Yang benar: hapus item ini, buat dokumennya di Master, lalu
               tambahkan item baru yang menunjuknya. */
            if ($newTipe !== $current->Tipe_Item && SopTipeItem::isVersioned($newTipe)) {
                return ResponseHelper::error(
                    "Item tak bisa diubah menjadi tipe {$newTipe}. Tipe ber-versi selalu mengikuti Dokumen, "
                    .'dan dokumen lahir di Master Dokumen. Hapus item ini, lalu tambahkan item baru yang '
                    .'menunjuk dokumennya.',
                    422);
            }

            /* GANTI DOKUMEN TAK BISA LEWAT OVERLAY DRAFT — dan sebelumnya ini
               GAGAL DIAM-DIAM. N_SOP_Paket_Draft_Item tak punya kolom
               Id_SOP_Dokumen sama sekali (lihat ITEM_COLUMNS), jadi nilai yang
               dikirim form hanya lewat begitu saja: layar berkata "Item
               tersimpan", dan itemnya tetap menunjuk dokumen yang lama.

               Seluruh 559 baris catatan baca yang ada justru menempel pada item
               di paket TERBIT — persis jalur ini. Membiarkannya diam berarti
               admin mengira SOP yang wajib dibaca sudah berganti padahal tidak,
               dan tak ada apa pun yang memberitahunya. */
            /* Sudah berupa id nyata di sini — terapkanDokumen() di atas yang
               memecahkannya. Meng-decode() sekali lagi selalu menghasilkan
               kosong, dan penjaga di bawah tak pernah menyala sama sekali. */
            $dokDiminta = ! empty($data['Id_SOP_Dokumen']) ? (int) $data['Id_SOP_Dokumen'] : null;
            $dokSekarang = $current->Id_SOP_Paket_Item_Asal
                ? DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $current->Id_SOP_Paket_Item_Asal)
                    ->value('Id_SOP_Dokumen')
                : null;

            if ($dokDiminta && $dokSekarang && (int) $dokDiminta !== (int) $dokSekarang) {
                return ResponseHelper::error(
                    'Dokumen item ini tak bisa diganti pada paket yang sudah terbit — revisi paket '
                    .'menyimpan susunannya, bukan tautan dokumennya. Hapus item ini lalu tambahkan item '
                    .'baru dengan dokumen yang dimaksud, dan terbitkan revisi paketnya.',
                    422);
            }

            /* Sisanya — item yang LAHIR di overlay ini dan belum pernah terbit —
               boleh berpindah dokumen, dengan syarat yang sama seperti saat
               dibuat: dokumennya sudah punya versi berlaku, dan belum dipakai
               item lain di paket yang sama. */
            if ($dokDiminta && ! $dokSekarang) {
                if ($pesan = $this->pastikanDokumenSiap($dokDiminta)) {
                    return ResponseHelper::error($pesan, 422);
                }
                /* Kembarnya diperiksa terhadap item LIVE paket itu. Sesama item
                   overlay tak ikut di sini — id-nya beda semesta — dan memang
                   tak perlu: gerbang publish memanggil dokumenKembarDiPaket()
                   sesudah promosi, dan itu menolak lantang sambil menyebut
                   nama dokumennya. */
                $idPaketIni = $this->paketDariItem($decoded, true);
                if ($idPaketIni && ($pesan = $this->pastikanDokumenBelumDipakaiDiPaket($idPaketIni, $dokDiminta))) {
                    return ResponseHelper::error($pesan, 422);
                }
            }
            // Wajib ditulis balik: updateItem() menulis Tipe_Item tanpa fallback, sedangkan
            // validateItem($request, false) membuat field ini opsional (sometimes|required).
            $data['Tipe_Item'] = $newTipe;

            $wasFile = SopTipeItem::isFile($current->Tipe_Item);
            $nowFile = SopTipeItem::isFile($newTipe);
            $candidate = null;
            /* Pemeriksaan konten kandidat DICABUT SELURUHNYA. Keduanya —
               "non-berkas → berkas" dan "berkas → berkas lain" — menuntut tipe
               tujuan yang ber-versi, dan gerbang di atas sudah menolak itu
               sebelum sampai ke sini. Yang tersisa hanya perpindahan MENJAUH
               dari tipe ber-versi, yang tak butuh konten apa pun. */

            $oldUjian = $current->Id_Master_Ujian;
            $newUjian = $data['Id_Master_Ujian'] ?? null;
            if ($newTipe === 'KUIS' && $newUjian && (string) $oldUjian !== (string) $newUjian) {
                $candidate = [
                    'Id_Master_Ujian' => $newUjian,
                    'Ringkasan_Perubahan' => $request->input('Ringkasan_Perubahan') ?: ('Ganti ujian: '.($data['Nama_Ujian'] ?? $newUjian)),
                ];
            }

            try {
                $tersimpan = DB::transaction(function () use ($decoded, $data, $request, $wasFile, $nowFile, $current, $newTipe, $newUjian, $candidate) {
                    /* Row_Ver kalah = 409, BUKAN 422 (lihat staleRowResponse) — jadi
                       gerbang ini tidak boleh dilempar sebagai DomainException.
                       updateItem() belum menulis apa pun ketika Row_Ver tak cocok,
                       jadi closure cukup melaporkan kekalahannya dan pemanggil yang
                       memilih bentuk responsnya, dengan kode status yang sama. */
                    if (! $this->paketDraft->updateItem($decoded, $data, Auth::user()->Id_Users, $request->input('Row_Ver'))) {
                        return false;
                    }
                    if (($wasFile && ! $nowFile) || ($current->Tipe_Item === 'KUIS' && ($newTipe !== 'KUIS' || ! $newUjian))) {
                        $this->paketDraft->discardCandidateVersions($decoded, Auth::user()->Id_Users);
                        $this->paketDraft->clearVersionTarget($decoded, Auth::user()->Id_Users);
                    }
                    if ($candidate) {
                        $this->paketDraft->createVersion($decoded, $candidate, Auth::user()->Id_Users);
                    }

                    return true;
                });
            } catch (\Throwable $e) {
                Log::error('SOP builder updateItem gagal', ['exception' => $e]);

                return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
            }

            if (! $tersimpan) {
                return $this->staleRowResponse($current->Judul_Item);
            }

            $beda = SopDiff::antara((array) $current, $data);
            $this->jejak('ITEM_UBAH',
                SopDiff::kalimat("Item draft \"{$data['Judul_Item']}\"", $beda),
                $this->paketDariItem($decoded, true), null, [
                    'id_draft_item' => $decoded, 'tipe' => $newTipe, 'tipe_lama' => $current->Tipe_Item,
                    'perubahan' => $beda,
                ]);

            return ResponseHelper::success(null, 'Item draft berhasil diupdate!');
        }

        $current = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $decoded)->first();
        if (! $current) {
            return ResponseHelper::error('Item tidak ditemukan', 404);
        }
        if ($this->activeItemIsPublished($decoded)) {
            return $this->stalePublishedIdResponse();
        }

        $newTipe = $request->input('Tipe_Item');
        $tipeChanged = $newTipe && $newTipe !== $current->Tipe_Item;
        // Berpindah ke tipe yang dimatikan = pembuatan bentuk baru; lihat catatan
        // yang sama di cabang overlay draft di atas.
        if ($tipeChanged && ! SopTipeItemAktif::nyala($newTipe)) {
            return ResponseHelper::error(SopTipeItemAktif::pesanMati($newTipe), 422);
        }
        // Pintu ketiga pembuatan dokumen; lihat catatan yang sama di cabang overlay draft.
        if ($tipeChanged && SopTipeItem::isVersioned($newTipe)) {
            return ResponseHelper::error(
                "Item tak bisa diubah menjadi tipe {$newTipe}. Tipe ber-versi selalu mengikuti Dokumen, "
                .'dan dokumen lahir di Master Dokumen. Hapus item ini, lalu tambahkan item baru yang '
                .'menunjuk dokumennya.',
                422);
        }

        // ----- Update biasa (tanpa ubah tipe) -----
        if (! $tipeChanged) {
            try {
                DB::transaction(function () use ($decoded, $data, $current, $request) {
                    $this->item->update($decoded, $data, Auth::user()->Id_Users);

                    /* GANTI DOKUMEN = GANTI POINTER VERSI. update() hanya menulis
                       kolom dokumennya; tanpa baris ini Id_Item_Versi_Aktif tetap
                       menunjuk versi dokumen yang LAMA, sehingga karyawan disajikan
                       isi dokumen yang sudah tidak dipilih lagi — dan layarnya tetap
                       menampilkan judul dokumen yang baru. */
                    $dokumenBaru = $data['Id_SOP_Dokumen'] ?? null;
                    $gantiDokumen = $dokumenBaru && (int) $dokumenBaru !== (int) ($current->Id_SOP_Dokumen ?? 0);
                    if ($gantiDokumen) {
                        /* Diperiksa HANYA saat berganti. Item yang terlanjur
                           menunjuk dokumen kosong harus tetap bisa disunting —
                           layar ini satu-satunya tempat ia bisa dibetulkan.

                           Gerbang prasyarat: dilempar sebagai DomainException supaya
                           closure me-rollback, lalu dijawab 422 di bawah — kode status
                           yang sama persis dengan rollBack() manual yang digantikannya. */
                        if ($pesan = $this->pastikanDokumenSiap((int) $dokumenBaru)) {
                            throw new \DomainException($pesan);
                        }
                        // Dan tak boleh menabrak item lain di paket yang sama.
                        $pesan = $this->pastikanDokumenBelumDipakaiDiPaket(
                            (int) $current->Id_SOP_Paket, (int) $dokumenBaru, $decoded);
                        if ($pesan) {
                            throw new \DomainException($pesan);
                        }
                        $this->item->tautkanDokumen($decoded, (int) $dokumenBaru, Auth::user()->Id_Users);
                    }

                    /* KUIS: ganti ujian = versi baru (living). Backfill v1 utk KUIS lama tanpa versi.
                       DILEWATI saat dokumennya baru saja diganti: ujian item sudah
                       dicerminkan dari versi aktif dokumen yang baru, sehingga
                       perbandingan di bawah membandingkan ujian baru dengan pointer
                       dokumen LAMA dan melahirkan versi milik-item yang tak diminta
                       siapa pun. */
                    if (! $gantiDokumen && $current->Tipe_Item === 'KUIS') {
                        $newUjian = $data['Id_Master_Ujian'] ?? null;
                        $activeUjian = $current->Id_Item_Versi_Aktif
                            ? DB::table('N_SOP_Item_Versi')
                                ->where('Id_SOP_Item_Versi', $current->Id_Item_Versi_Aktif)
                                ->value('Id_Master_Ujian')
                            : null;
                        if (! empty($newUjian) && (string) $newUjian !== (string) $activeUjian) {
                            $this->versi->create(
                                $decoded,
                                [
                                    'Id_Master_Ujian' => $newUjian,
                                    'Nama_Ujian' => $data['Nama_Ujian'] ?? null,
                                    'Ringkasan_Perubahan' => $request->input('Ringkasan_Perubahan') ?: ('Ganti ujian: '.($data['Nama_Ujian'] ?? $newUjian)),
                                ],
                                Auth::user()->Id_Users,
                            );
                        }
                    }
                });
            } catch (\DomainException $e) {
                return ResponseHelper::error($e->getMessage(), 422);
            } catch (\Throwable $e) {
                Log::error('SOP builder updateItem gagal', ['exception' => $e]);

                return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
            }

            $beda = SopDiff::antara((array) $current, $data);
            $this->jejak('ITEM_UBAH',
                SopDiff::kalimat("Item \"{$data['Judul_Item']}\"", $beda),
                (int) $current->Id_SOP_Paket, $decoded,
                ['tipe' => $current->Tipe_Item, 'perubahan' => $beda]);

            return ResponseHelper::success(null, 'Item berhasil diupdate!');
        }

        // ----- Ubah tipe (integritas versi) -----
        if (! $this->item->tipeValid($newTipe)) {
            return ResponseHelper::error('Tipe item tidak valid', 422);
        }
        if ($newTipe === 'ABSENSI') {
            return ResponseHelper::error('Absensi diatur lewat toggle paket, bukan item.', 422);
        }

        /* Konten Library tak lagi diminta dari formulir item — semua tipe
           tujuan yang membutuhkannya ber-versi, dan perpindahan ke sana ditolak
           gerbang di atas. Argumennya dipertahankan karena tanda tangan
           changeType() masih melayani pemanggil lain. */
        $idKonten = null;

        /* PENGESAHAN TIDAK LAGI DIAMBIL DARI SINI.
           Sejak item ber-dokumen tak bisa berganti tipe, cabang ini hanya
           tersisa untuk item TANPA dokumen (PARAGRAF/TAUTAN) yang beralih jadi
           tipe berkas — dan versi yang lahir dari situ dokumennya dibuat
           otomatis oleh ensureForItem(). Membiarkan formulir item ikut menulis
           blok pengesahan menjadikannya penulis KEDUA untuk nilai yang sejak
           Fase 6 hanya boleh punya satu: dokumennya.

           Versinya lahir sebagai DRAFT tanpa pengesah, dan itu memang
           seharusnya — penjaga "tak boleh terbit tanpa pengesah" akan menahannya
           sampai admin mengisinya lewat panel versi. Diam-diam menerbitkannya
           dengan pengesahan yang diketik di formulir item persis jalan pintas
           yang melahirkan aturan kerja tanpa penanda tangan. */
        $versiExtra = [
            'Ringkasan_Perubahan' => $request->input('Ringkasan_Perubahan')
                ?: "Versi awal setelah tipe item diubah ke {$newTipe}.",
            'Tanggal_Berlaku' => $request->input('Tanggal_Berlaku'),
        ];

        try {
            DB::transaction(function () use ($decoded, $data, $newTipe, $idKonten, $versiExtra) {
                $this->item->update($decoded, $data, Auth::user()->Id_Users);
                $this->item->changeType($decoded, $newTipe, $idKonten, $versiExtra, Auth::user()->Id_Users, $this->versi);
            });
        } catch (\Throwable $e) {
            Log::error('SOP builder updateItem gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }

        $this->jejak('ITEM_TIPE', "Tipe item \"{$current->Judul_Item}\" diubah dari {$current->Tipe_Item} ke {$newTipe}.",
            (int) $current->Id_SOP_Paket, $decoded, ['tipe_lama' => $current->Tipe_Item, 'tipe' => $newTipe]);

        return ResponseHelper::success(null, 'Tipe item diperbarui!');
    }

    /** POST /api/v1/sop/admin/builder/paket/{id}/clone */
    public function clonePaket($id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        try {
            $newId = DB::transaction(fn () => $this->paket->clonePaket($decoded, Auth::user()->Id_Users, $this->item, $this->versi));
        } catch (\Throwable $e) {
            Log::error('SOP builder clonePaket gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }

        /* Gerbang "tidak ditemukan" menjawab 404, BUKAN 422 — jadi ia tidak boleh
           berubah jadi DomainException. clonePaket() mengembalikan null sebelum
           menulis satu baris pun, sehingga transaksinya memang kosong dan
           pemeriksaannya aman dilakukan di luar. */
        if (! $newId) {
            return ResponseHelper::error('Paket tidak ditemukan', 404);
        }

        $this->jejak('PAKET_KLON', 'Paket diduplikat sebagai draft baru.', $newId, null, ['id_paket_sumber' => $decoded]);

        return ResponseHelper::success(
            ['Id_SOP_Paket' => Hashids::connection('custom')->encode($newId)],
            'Paket diduplikat (draft).',
            201,
        );
    }

    /** POST /api/v1/sop/admin/builder/section/{id}/clone */
    public function cloneSection($id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }
        if (! $this->isDraftToken($id, 'ds') && $this->activeSectionIsPublished($decoded)) {
            return $this->stalePublishedIdResponse();
        }

        try {
            $newId = DB::transaction(fn () => $this->isDraftToken($id, 'ds')
                ? $this->paketDraft->cloneSection($decoded, Auth::user()->Id_Users)
                : $this->section->cloneById($decoded, Auth::user()->Id_Users, $this->item, $this->versi));
        } catch (\Throwable $e) {
            Log::error('SOP builder cloneSection gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }

        /* 404, bukan 422 — lihat catatan yang sama di clonePaket(). */
        if (! $newId) {
            return ResponseHelper::error('Section tidak ditemukan', 404);
        }

        // paketDariSection() adalah query SETELAH commit: dijalankan di luar try.
        $this->jejak('SECTION_KLON', 'Section diduplikat beserta itemnya.',
            $this->paketDariSection($decoded, $this->isDraftToken($id, 'ds')), null,
            ['id_section_sumber' => $decoded, 'id_section_baru' => $newId]);

        return ResponseHelper::success(
            ['Id_SOP_Section' => ($this->isDraftToken($id, 'ds') ? 'ds_' : '').Hashids::connection('custom')->encode($newId)],
            'Section diduplikat.',
            201,
        );
    }

    /** POST /api/v1/sop/admin/builder/item/{id}/clone */
    public function cloneItem($id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }
        if (! $this->isDraftToken($id, 'di') && $this->activeItemIsPublished($decoded)) {
            return $this->stalePublishedIdResponse();
        }

        try {
            $newId = DB::transaction(fn () => $this->isDraftToken($id, 'di')
                ? $this->paketDraft->cloneItem($decoded, Auth::user()->Id_Users)
                : $this->item->cloneById($decoded, Auth::user()->Id_Users, $this->versi));
        } catch (\Throwable $e) {
            Log::error('SOP builder cloneItem gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }

        /* 404, bukan 422 — lihat catatan yang sama di clonePaket(). */
        if (! $newId) {
            return ResponseHelper::error('Item tidak ditemukan', 404);
        }

        // paketDariItem() adalah query SETELAH commit: dijalankan di luar try.
        $this->jejak('ITEM_KLON', 'Item diduplikat.',
            $this->paketDariItem($decoded, $this->isDraftToken($id, 'di')), null,
            ['id_item_sumber' => $decoded, 'id_item_baru' => $newId]);

        return ResponseHelper::success(
            ['Id_SOP_Paket_Item' => ($this->isDraftToken($id, 'di') ? 'di_' : '').Hashids::connection('custom')->encode($newId)],
            'Item diduplikat.',
            201,
        );
    }

    /** GET /api/v1/sop/admin/builder/paket/{id}/section-options — ringan, khusus dropdown paket tujuan salin item. */
    /**
     * Pratinjau dampak fan-out sebelum admin menekan publish/rollback.
     *
     * KENAPA ADA. Sejak Fase 3, rollback tidak lagi senyap: ia menggeser
     * pointer pada semua item perujuk dokumen DAN membuka ulang kewajiban
     * karyawan di semua paket perujuk (keputusan produk 2026-08-09). Perubahan
     * sebesar itu tak boleh ditemukan admin setelah tombolnya ditekan.
     *
     * Menolak token draft-overlay dv_: PK tabel overlay beririsan dengan tabel
     * live, jadi endpoint yang lupa menolaknya akan menghitung dampak baris
     * yang sama sekali lain sambil membalas sukses.
     */
    /**
     * GET /paket/{id}/dampak-publish — apa yang terjadi bila paket ini terbit.
     *
     * Termasuk paket LAIN yang menyajikan dokumen yang sama. Sejak dokumen bisa
     * dipakai banyak paket, menerbitkan paket ini bisa menerbitkan revisi yang
     * juga disajikan paket lain — pointer mereka ikut bergeser dan karyawan
     * mereka ikut terdampak, tanpa admin pernah membuka paket itu.
     *
     * Ditampilkan SEBELUM tombol ditekan, bukan dilaporkan sesudahnya: akibat
     * yang baru diketahui setelah terjadi bukan akibat yang disetujui.
     */
    public function dampakPublishPaket($id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        try {
            $dampak = $this->paketDraft->dampakPublish($decoded);

            /* RILIS PERTAMA HARUS BISA DIBEDAKAN dari revisi susunan.
               Keduanya sama-sama "tanpa revisi dokumen", tapi akibatnya jauh
               berbeda: yang satu menugaskan SOP kepada karyawan untuk PERTAMA
               KALI, yang satu cuma memindahkan urutan. Tanpa penanda ini
               pratinjaunya menyebut keduanya "yang berubah hanya susunan paket"
               — kalimat yang pada rilis pertama justru terbalik. */
            $dampak['rilis_pertama'] = DB::table('N_SOP_Master_Paket')
                ->where('Id_SOP_Paket', $decoded)->value('Status') !== SopStatus::PAKET_PUBLISHED;
            $dampak['jumlah_item'] = DB::table('N_SOP_Paket_Item')
                ->where('Id_SOP_Paket', $decoded)->where('Flag_Aktif', SopStatus::AKTIF)->count();

            return ResponseHelper::success($dampak, 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP builder dampakPublishPaket gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    public function versiDampak($id)
    {
        if ($this->isDraftToken($id, 'dv')) {
            return ResponseHelper::error(
                'Versi pada revisi paket terbit belum punya dampak fan-out. Terbitkan revisinya lebih dulu.',
                422,
            );
        }

        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        $versi = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Item_Versi', $decoded)
            ->first(['Id_SOP_Item_Versi', 'Id_SOP_Dokumen', 'Versi_Ke', 'Status']);
        if (! $versi) {
            return ResponseHelper::error('Versi tidak ditemukan', 404);
        }

        $ambang = \App\Services\LMS\SOP\Support\SopSetting::int('penjadwalan_async_ambang', 30);

        /* Versi tanpa dokumen (versi yatim milik item yang tipenya diubah
           menjauh dari tipe ber-versi) tak punya dampak fan-out sama sekali —
           dilaporkan nol, bukan disembunyikan. */
        $dampak = $versi->Id_SOP_Dokumen !== null
            ? app(\App\Services\LMS\SOP\SopDokumenFanoutService::class)->hitungDampak((int) $versi->Id_SOP_Dokumen)
            : ['item' => 0, 'paket' => 0, 'penugasan' => 0];

        return ResponseHelper::success([
            'Versi_Ke' => $versi->Versi_Ke,
            'Status' => $versi->Status,
            'item' => $dampak['item'],
            'paket' => $dampak['paket'],
            'penugasan' => $dampak['penugasan'],
            'ambang' => $ambang,
            // Di atas ambang, rekonsiliasi masuk antrian — dan antrian hanya
            // berjalan bila worker hidup. Admin harus tahu sebelum menekan.
            'akan_antri' => $dampak['penugasan'] > $ambang,
        ], 'Data Ditemukan');
    }

    /**
     * GET /dokumen/options — daftar Dokumen SOP untuk pemilih di Builder.
     *
     * Menyertakan JUMLAH PAKET PEMAKAI, dan itu bukan hiasan: sejak Fase 6
     * memilih dokumen yang sudah dipakai paket lain berarti item ini ikut
     * bergerak setiap kali dokumen itu direvisi. Admin harus melihat angkanya
     * SEBELUM memilih, bukan menemukannya setelah revisi menyebar.
     */
    public function dokumenOptions(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $tipe = $request->query('tipe');
        $departemenId = $request->query('departemen_id');
        $jenisId = $request->query('jenis_id');
        $statusVersi = $request->query('status_versi');
        $sort = $request->query('sort', 'judul_asc');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(5, (int) $request->query('per_page', 10)));

        $depDecoded = $departemenId ? (is_numeric($departemenId) ? (int) $departemenId : $this->decode($departemenId)) : null;
        $jenisDecoded = $jenisId ? (is_numeric($jenisId) ? (int) $jenisId : $this->decode($jenisId)) : null;

        $query = DB::table('N_SOP_Dokumen as d')
            ->leftJoin('N_HRIS_Master_Departemen as dpt', 'dpt.Id_Departemen', '=', 'd.Departemen_Id')
            ->leftJoin('N_SOP_Master_Jenis_Dokumen as jd', 'jd.Id_SOP_Jenis_Dokumen', '=', 'd.Jenis_Dokumen_Id')
            ->leftJoin('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'd.Id_Versi_Aktif')
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            /* Tipe yang dimatikan tak ditawarkan: memasang item baru ADALAH
               pembuatan, jadi ia ikut tertutup. Dokumen lamanya tidak hilang
               ke mana-mana — item yang sudah terpasang tetap menyajikannya. */
            ->whereIn('d.Tipe', SopTipeItemAktif::aktif())
            /* Dokumen yang DINONAKTIFKAN tertutup dengan alasan yang sama
               persis, dan inilah satu-satunya tempat penonaktifan benar-benar
               menggigit (DDL-2026-055). */
            ->whereNull('d.Nonaktif_At')
            ->when($tipe, fn ($w) => $w->where('d.Tipe', $tipe))
            ->when($depDecoded, fn ($w) => $w->where('d.Departemen_Id', $depDecoded))
            ->when($jenisDecoded, fn ($w) => $w->where('d.Jenis_Dokumen_Id', $jenisDecoded))
            ->when($statusVersi === 'SIAP', fn ($w) => $w->whereNotNull('d.Id_Versi_Aktif'))
            ->when($statusVersi === 'TANPA_VERSI', fn ($w) => $w->whereNull('d.Id_Versi_Aktif'))
            ->when($q !== '', fn ($w) => $w->where(function ($s) use ($q) {
                $s->where('d.Judul', 'LIKE', "%{$q}%")
                    ->orWhere('d.Nomor_SOP_Resmi', 'LIKE', "%{$q}%");
            }));

        $total = (clone $query)->count('d.Id_SOP_Dokumen');

        switch ($sort) {
            case 'judul_desc':
                $query->orderBy('d.Judul', 'desc');
                break;
            case 'nomor_asc':
                $query->orderBy('d.Nomor_SOP_Resmi', 'asc')->orderBy('d.Judul', 'asc');
                break;
            case 'nomor_desc':
                $query->orderBy('d.Nomor_SOP_Resmi', 'desc')->orderBy('d.Judul', 'asc');
                break;
            case 'terbaru':
                $query->orderBy('d.Created_At', 'desc');
                break;
            case 'judul_asc':
            default:
                $query->orderBy('d.Judul', 'asc');
                break;
        }

        $rows = $query->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get([
                'd.Id_SOP_Dokumen',
                'd.Judul',
                'd.Nomor_SOP_Resmi',
                'd.Tipe',
                'd.Id_Versi_Aktif',
                'dpt.Nama_Departemen',
                'jd.Nama_Jenis',
                'v.Versi_Ke as Versi_String',
                'v.Status as Status_Versi',
                'v.Tanggal_Berlaku',
            ]);

        $ids = $rows->pluck('Id_SOP_Dokumen')->all();

        $pemakai = $ids === [] ? collect() : DB::table('N_SOP_Paket_Item')
            ->whereIn('Id_SOP_Dokumen', $ids)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->select('Id_SOP_Dokumen', DB::raw('COUNT(DISTINCT Id_SOP_Paket) as n'))
            ->groupBy('Id_SOP_Dokumen')
            ->pluck('n', 'Id_SOP_Dokumen');

        $departemenOptions = DB::table('N_HRIS_Master_Departemen')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Nama_Departemen')
            ->get(['Id_Departemen as value', 'Nama_Departemen as label', 'Kode_Departemen as kode'])
            ->map(function ($dp) {
                $dp->value = Hashids::connection('custom')->encode($dp->value);

                return $dp;
            });

        $jenisOptions = DB::table('N_SOP_Master_Jenis_Dokumen')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Level_Hirarki')
            ->orderBy('Nama_Jenis')
            ->get(['Id_SOP_Jenis_Dokumen as value', 'Nama_Jenis as label', 'Kode_Jenis_Dokumen as kode'])
            ->map(function ($jn) {
                $jn->value = Hashids::connection('custom')->encode($jn->value);

                return $jn;
            });

        return ResponseHelper::success([
            'rows' => $rows->map(function ($d) use ($pemakai) {
                $d->Jumlah_Paket_Pemakai = (int) ($pemakai[$d->Id_SOP_Dokumen] ?? 0);
                $d->Id_SOP_Dokumen = Hashids::connection('custom')->encode($d->Id_SOP_Dokumen);
                $d->Id_Versi_Aktif = $d->Id_Versi_Aktif !== null
                    ? Hashids::connection('custom')->encode($d->Id_Versi_Aktif) : null;

                return $d;
            })->all(),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_page' => (int) ceil($total / $perPage),
                'total_data' => $total,
            ],
            'options' => [
                'departemen' => $departemenOptions,
                'jenis' => $jenisOptions,
            ],
            'total' => $total,
        ], 'Data Ditemukan');
    }

    public function sectionOptionsPaket($id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }
        $paket = DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $decoded)
            ->where('Flag_Aktif', SopStatus::AKTIF)->first(['Id_SOP_Paket', 'Nama_Paket', 'Status']);
        if (! $paket) {
            return ResponseHelper::error('Paket tidak ditemukan', 404);
        }

        return ResponseHelper::success([
            'Nama_Paket' => $paket->Nama_Paket,
            'Status' => $paket->Status,
            'sections' => $this->section->byPaket($decoded),
        ], 'Data Ditemukan');
    }

    /**
     * POST /api/v1/sop/admin/builder/item/{id}/copy-to-paket
     * Salin satu item ke PAKET LAIN (beda dari paket asal), khusus paket tujuan
     * berstatus DRAFT — paket terbit punya alur draft/review sendiri
     * (SopPaketDraftService) yang sengaja TIDAK diintegrasikan di sini. Item
     * asal + seluruh riwayat progres karyawan di paket asal tidak disentuh sama
     * sekali (lihat SopItemService::copyToPaket()).
     */
    public function copyItemToPaket(Request $request, $id)
    {
        $decodedItem = $this->decode($id);
        if (! $decodedItem) {
            return ResponseHelper::error('ID tidak valid', 400);
        }
        /*
         * Token "di_…" adalah hashid PK N_SOP_Paket_Draft_Item (overlay revisi
         * paket terbit), BUKAN N_SOP_Paket_Item. Kedua tabel auto-increment
         * sendiri-sendiri dan PK-nya beririsan, jadi men-decode-nya lalu
         * mencarinya di tabel item live akan menemukan item lain yang sama
         * sekali tak berhubungan — dan menyalinnya tanpa satu pun galat.
         * Menyalin isi draft revisi butuh jalur SopPaketDraftService, di luar
         * cakupan fitur ini.
         */
        if ($this->isDraftToken($id, 'di')) {
            return ResponseHelper::error(
                'Item pada revisi paket terbit belum bisa disalin. Terbitkan atau buang draft revisinya lebih dulu.',
                422,
            );
        }

        $targetPaket = $this->decode($request->input('Id_SOP_Paket_Tujuan', ''));
        $targetSection = $this->decode($request->input('Id_SOP_Section_Tujuan', ''));
        if (! $targetPaket || ! $targetSection) {
            return ResponseHelper::error('Paket & section tujuan wajib dipilih', 422);
        }

        $srcPaket = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $decodedItem)
            ->where('Flag_Aktif', SopStatus::AKTIF)->value('Id_SOP_Paket');
        if (! $srcPaket) {
            return ResponseHelper::error('Item tidak ditemukan', 404);
        }
        if ((int) $srcPaket === $targetPaket) {
            return ResponseHelper::error('Paket tujuan harus berbeda dari paket asal. Gunakan Duplikat untuk menyalin di paket yang sama.', 422);
        }

        // WAJIB: target harus DRAFT — cegah bercampur dengan alur review paket terbit.
        $targetStatus = DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $targetPaket)
            ->where('Flag_Aktif', SopStatus::AKTIF)->value('Status');
        if ($targetStatus === null) {
            return ResponseHelper::error('Paket tujuan tidak ditemukan', 404);
        }
        if ($targetStatus !== SopStatus::PAKET_DRAFT) {
            return ResponseHelper::error('Paket tujuan harus berstatus Draft.', 422);
        }

        // WAJIB: section harus benar-benar milik paket tujuan (cegah baris yatim lintas paket).
        $sectionOk = DB::table('N_SOP_Paket_Section')->where('Id_SOP_Section', $targetSection)
            ->where('Id_SOP_Paket', $targetPaket)->where('Flag_Aktif', SopStatus::AKTIF)->exists();
        if (! $sectionOk) {
            return ResponseHelper::error('Section tujuan tidak valid', 422);
        }

        try {
            $newId = DB::transaction(function () use ($decodedItem, $targetPaket, $targetSection) {
                $newId = $this->item->copyToPaket($decodedItem, $targetPaket, $targetSection, Auth::user()->Id_Users, $this->versi);
                /* Gerbang prasyarat ber-status 422: dilempar sebagai DomainException
                   supaya closure me-rollback, lalu dijawab 422 di bawah — sama
                   persis dengan rollBack() manual yang digantikannya. */
                if (! $newId) {
                    throw new \DomainException('Item tidak dapat disalin (tipe tidak didukung atau tidak ditemukan)');
                }

                return $newId;
            });
        } catch (\DomainException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('SOP builder copyItemToPaket gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }

        $this->jejak('ITEM_SALIN_PAKET', 'Item disalin ke paket lain.', (int) $srcPaket, $decodedItem,
            ['id_paket_tujuan' => $targetPaket, 'id_item_baru' => $newId]);
        $this->jejak('ITEM_SALIN_PAKET_MASUK', 'Item diterima salinan dari paket lain.', $targetPaket, $newId,
            ['id_paket_asal' => (int) $srcPaket, 'id_item_asal' => $decodedItem]);

        return ResponseHelper::success([
            'Id_SOP_Paket_Item' => Hashids::connection('custom')->encode($newId),
            'Id_SOP_Paket_Tujuan' => Hashids::connection('custom')->encode($targetPaket),
        ], 'Item berhasil disalin ke paket lain.', 201);
    }

    /** POST /api/v1/sop/admin/builder/item/reorder  body: { items: [{id, id_section}, ...] } */
    public function reorderItem(Request $request)
    {
        $rows = $request->input('items', []);
        if (! is_array($rows)) {
            return ResponseHelper::error('Daftar item kosong/invalid', 422);
        }
        $draftOrder = ! empty($rows) && $this->isDraftToken($rows[0]['id'] ?? '', 'di');
        foreach ($rows as $row) {
            // id_section boleh kosong: item draft yang belum masuk section memang mengirim null.
            // Yang ditolak hanya nilai TERISI yang bukan token ds_ (indikasi ID aktif nyasar).
            $sectionToken = $row['id_section'] ?? null;
            if ($this->isDraftToken($row['id'] ?? '', 'di') !== $draftOrder
                || ($draftOrder && $sectionToken !== null && $sectionToken !== ''
                    && ! $this->isDraftToken($sectionToken, 'ds'))) {
                return ResponseHelper::error('Daftar urutan mencampur ID draft dan ID aktif.', 422);
            }
        }
        $ordered = [];
        foreach ($rows as $row) {
            $id = $this->decode($row['id'] ?? '');
            if (! $id) {
                continue;
            }
            $entry = ['id' => $id];
            if (array_key_exists('id_section', $row)) {
                $entry['section'] = $this->decode($row['id_section'] ?? '') ?: null;
            }
            $ordered[] = $entry;
        }
        if (empty($ordered)) {
            return ResponseHelper::error('Daftar item kosong/invalid', 422);
        }
        if (! $draftOrder && collect($ordered)->contains(fn ($row) => $this->activeItemIsPublished($row['id']))) {
            return $this->stalePublishedIdResponse();
        }

        // Item DAN section tujuannya wajib sepaket. Tanpa ini, payload buatan bisa
        // menempelkan item paket A ke section paket B dan membuatnya yatim permanen.
        $idPaket = SopBuilderScope::paketDariItemDanSection(
            array_column($ordered, 'id'),
            array_column($ordered, 'section'),
            $draftOrder
        );
        if ($idPaket === null) {
            return ResponseHelper::error('Daftar urutan memuat data dari paket berbeda atau id tidak dikenal.', 422);
        }

        try {
            DB::transaction(function () use ($draftOrder, $ordered) {
                if ($draftOrder) {
                    $this->paketDraft->reorderItems($ordered);
                } else {
                    $this->item->reorder($ordered);
                }
            });
        } catch (\Throwable $e) {
            Log::error('SOP builder reorderItem gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }

        $this->jejak('ITEM_URUT', count($ordered).' item diurutkan ulang.', $idPaket, null, ['jumlah' => count($ordered)]);

        return ResponseHelper::success(null, 'Urutan disimpan');
    }

    /** DELETE /api/v1/sop/admin/builder/item/delete/{id} */
    public function destroyItem($id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }
        if (! $this->isDraftToken($id, 'di') && $this->activeItemIsPublished($decoded)) {
            return $this->stalePublishedIdResponse();
        }

        $draftToken = $this->isDraftToken($id, 'di');
        // Diresolusi sebelum hapus: aman untuk soft delete maupun kalau nanti jadi hard delete.
        $idPaket = $this->paketDariItem($decoded, $draftToken);
        $judul = $draftToken
            ? DB::table('N_SOP_Paket_Draft_Item')->where('Id_SOP_Draft_Item', $decoded)->value('Judul_Item')
            : DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $decoded)->value('Judul_Item');

        try {
            DB::transaction(function () use ($draftToken, $decoded) {
                if ($draftToken) {
                    $this->paketDraft->deleteItem($decoded, Auth::user()->Id_Users);
                } else {
                    $this->item->softDelete($decoded, Auth::user()->Id_Users);
                }
            });
        } catch (\Throwable $e) {
            Log::error('SOP builder destroyItem gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }

        $this->jejak('ITEM_HAPUS', "Item \"{$judul}\" dihapus dari struktur.", $idPaket, null, [
            'id_item' => $decoded, 'di_working_draft' => $draftToken,
        ]);

        return ResponseHelper::success(null, 'Item berhasil dihapus!');
    }

    /* ================= SECTION ================= */

    /** POST /api/v1/sop/admin/builder/paket/{idPaket}/section/store */
    public function storeSection(Request $request, $idPaket)
    {
        $decoded = $this->decode($idPaket);
        if (! $decoded) {
            return ResponseHelper::error('ID paket tidak valid', 400);
        }

        $data = $request->validate([
            'Nama_Section' => 'required|string|max:200',
            'Deskripsi' => 'nullable|string|max:500',
        ]);

        try {
            $hasil = DB::transaction(function () use ($decoded, $data) {
                $published = DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $decoded)->value('Status') === SopStatus::PAKET_PUBLISHED;
                $id = $published
                    ? $this->paketDraft->createSection($decoded, $data, Auth::user()->Id_Users)
                    : $this->section->create($decoded, $data, Auth::user()->Id_Users);
                if (! $id) {
                    throw new \RuntimeException('Working draft paket tidak tersedia.');
                }

                return ['published' => $published, 'id' => $id];
            });
        } catch (\Throwable $e) {
            Log::error('SOP builder storeSection gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }

        $this->jejak('SECTION_BUAT', "Section \"{$data['Nama_Section']}\" dibuat.", $decoded, null, [
            'id_section' => $hasil['id'], 'ke_working_draft' => $hasil['published'],
        ]);

        return ResponseHelper::success(
            ['Id_SOP_Section' => ($hasil['published'] ? 'ds_' : '').Hashids::connection('custom')->encode($hasil['id'])],
            'Section dibuat!',
            201,
        );
    }

    /** PUT /api/v1/sop/admin/builder/section/update/{id} */
    public function updateSection(Request $request, $id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }
        if (! $this->isDraftToken($id, 'ds') && $this->activeSectionIsPublished($decoded)) {
            return $this->stalePublishedIdResponse();
        }

        $data = $request->validate([
            'Nama_Section' => 'required|string|max:200',
            'Deskripsi' => 'nullable|string|max:500',
        ]);

        /* Nilai lama dibaca dari tabel yang SESUAI TOKENNYA. Membacanya dari
           tabel live untuk token ds_ akan membandingkan section yang sama sekali
           lain — id kedua tabel beririsan, dan jejaknya akan menuduh perubahan
           yang tak pernah terjadi. */
        $draftToken = $this->isDraftToken($id, 'ds');
        $sebelum = (array) (DB::table($draftToken ? 'N_SOP_Paket_Draft_Section' : 'N_SOP_Paket_Section')
            ->where($draftToken ? 'Id_SOP_Draft_Section' : 'Id_SOP_Section', $decoded)
            ->first() ?? []);

        try {
            $tersimpan = DB::transaction(function () use ($draftToken, $decoded, $data, $request) {
                if ($draftToken) {
                    /* Row_Ver kalah = 409, bukan 422 — lihat catatan yang sama di
                       updateItem(). Karena itu bukan DomainException: closure hanya
                       melaporkan kekalahannya, dan updateSection() belum menulis
                       apa pun ketika Row_Ver tak cocok. */
                    if (! $this->paketDraft->updateSection($decoded, $data, Auth::user()->Id_Users, $request->input('Row_Ver'))) {
                        return false;
                    }
                } else {
                    $this->section->update($decoded, $data, Auth::user()->Id_Users);
                }

                return true;
            });
        } catch (\Throwable $e) {
            Log::error('SOP builder updateSection gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }

        if (! $tersimpan) {
            return $this->staleRowResponse($data['Nama_Section']);
        }

        $beda = SopDiff::antara($sebelum, $data);
        $this->jejak('SECTION_UBAH',
            SopDiff::kalimat("Section \"{$data['Nama_Section']}\"", $beda),
            $this->paketDariSection($decoded, $draftToken), null,
            ['id_section' => $decoded, 'perubahan' => $beda]);

        return ResponseHelper::success(null, 'Section disimpan');
    }

    /** POST /api/v1/sop/admin/builder/section/reorder  body: { ids: [hashid,...] } */
    public function reorderSection(Request $request)
    {
        $ids = $request->input('ids', []);
        // ids non-array (mis. ?ids=abc) membuat array_map() di bawah melempar TypeError
        // di luar try/catch → 500 HTML, bukan respons JSON.
        if (! is_array($ids)) {
            return ResponseHelper::error('Daftar section kosong/invalid', 422);
        }
        $draftOrder = ! empty($ids) && $this->isDraftToken($ids[0] ?? '', 'ds');
        if (collect($ids)->contains(fn ($id) => $this->isDraftToken($id, 'ds') !== $draftOrder)) {
            return ResponseHelper::error('Daftar urutan mencampur ID draft dan ID aktif.', 422);
        }
        $decoded = array_values(array_filter(array_map(fn ($h) => $this->decode($h), $ids)));
        if (empty($decoded)) {
            return ResponseHelper::error('Daftar section kosong/invalid', 422);
        }
        if (! $draftOrder && collect($decoded)->contains(fn ($sectionId) => $this->activeSectionIsPublished($sectionId))) {
            return $this->stalePublishedIdResponse();
        }

        // Seluruh section wajib sepaket; reorder lintas paket akan mengacak Urutan
        // di dua struktur sekaligus tanpa jejak yang bisa ditelusuri.
        $idPaket = SopBuilderScope::paketDariSection($decoded, $draftOrder);
        if ($idPaket === null) {
            return ResponseHelper::error('Daftar urutan memuat section dari paket berbeda atau id tidak dikenal.', 422);
        }

        try {
            DB::transaction(function () use ($draftOrder, $decoded) {
                if ($draftOrder) {
                    $this->paketDraft->reorderSections($decoded);
                } else {
                    $this->section->reorder($decoded);
                }
            });
        } catch (\Throwable $e) {
            Log::error('SOP builder reorderSection gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }

        $this->jejak('SECTION_URUT', count($decoded).' section diurutkan ulang.', $idPaket, null, ['jumlah' => count($decoded)]);

        return ResponseHelper::success(null, 'Urutan section disimpan');
    }

    /** DELETE /api/v1/sop/admin/builder/section/delete/{id} */
    public function destroySection($id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }
        if (! $this->isDraftToken($id, 'ds') && $this->activeSectionIsPublished($decoded)) {
            return $this->stalePublishedIdResponse();
        }

        $draftToken = $this->isDraftToken($id, 'ds');
        $idPaket = $this->paketDariSection($decoded, $draftToken);
        $nama = $draftToken
            ? DB::table('N_SOP_Paket_Draft_Section')->where('Id_SOP_Draft_Section', $decoded)->value('Nama_Section')
            : DB::table('N_SOP_Paket_Section')->where('Id_SOP_Section', $decoded)->value('Nama_Section');

        try {
            DB::transaction(function () use ($draftToken, $decoded) {
                $ok = $draftToken
                    ? $this->paketDraft->deleteSection($decoded, Auth::user()->Id_Users)
                    : $this->section->delete($decoded, Auth::user()->Id_Users);
                /* Gerbang prasyarat ber-status 422: DomainException di dalam closure
                   me-rollback, lalu dijawab 422 di bawah — kode status tak berubah. */
                if (! $ok) {
                    throw new \DomainException('Section masih berisi item — pindahkan/hapus itemnya dulu.');
                }
            });
        } catch (\DomainException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('SOP builder destroySection gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }

        $this->jejak('SECTION_HAPUS', "Section \"{$nama}\" dihapus dari struktur.", $idPaket, null, ['id_section' => $decoded]);

        return ResponseHelper::success(null, 'Section dihapus');
    }

    /* ================= OPTIONS ================= */

    /**
     * GET /api/v1/sop/admin/builder/options/prioritas — tingkat prioritas + warnanya.
     *
     * Warna & label ikut dikirim, bukan dibiarkan dicari layar dari konstanta
     * JS: konstanta itu ditulis sebelum tingkat barunya ada, jadi tingkat yang
     * ditambah admin akan tampil sebagai kode mentah tanpa lencana.
     */
    public function optionsPrioritas()
    {
        try {
            return ResponseHelper::success(app(\App\Services\LMS\SOP\SopPrioritasService::class)->untukLayar());
        } catch (\Exception $e) {
            Log::error('SOP builder optionsPrioritas gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /* ================= validasi ================= */

    private function validatePaket(Request $request): array
    {
        return $request->validate([
            'Nama_Paket' => 'required|string|max:200',
            'Kode_Paket' => 'nullable|string|max:50',
            'Kode_Karyawan_Owner' => 'nullable|string|max:20',
            // Batas disamakan dengan lebar kolom: SQL Server MELEMPAR error (bukan memotong)
            // saat kelebihan, sehingga tanpa max user hanya menerima 500 tanpa petunjuk field.
            'Deskripsi' => 'nullable|string|max:1000',            // N_SOP_Master_Paket nvarchar(1000)
            'Tujuan_Ruang_Lingkup' => 'nullable|string|max:2000', // nvarchar(2000)
            /* Daftar nilainya dari master, bukan ditulis di sini. Sebelum ini
               menambah satu tingkat berarti menyunting aturan validasi juga —
               dan yang lupa akan menyimpan tingkat yang sudah sah di master
               tapi ditolak di pintu dengan pesan "prioritas tidak valid". */
            'Prioritas' => 'nullable|in:'.implode(',', app(\App\Services\LMS\SOP\SopPrioritasService::class)->kode()),
            'Estimasi_Waktu_Baca_Menit' => 'nullable|integer|min:0',
            'Mode_Urutan_Item' => 'nullable|in:BEBAS,SEKUENSIAL',
            'Mode_Distribusi' => 'nullable|in:PUBLIK,TERBATAS', // siapa yang boleh melihat paket

            'Flag_Absensi' => 'nullable|boolean', // gerbang absensi paket (On/Off)
            'Cover' => 'nullable|string', // base64 data-uri ATAU path GCS lama ATAU '' (hapus)
            'Cover_Position' => 'nullable|string|max:20', // titik fokus cover ("50% 30%")
        ]);
    }

    /* validateKontenForTipe() DIHAPUS bersama pemanggilnya (27 Agu 2026).
     * Formulir item tak lagi pernah memegang konten Library: berkas selalu
     * datang dari versi aktif dokumennya, dan versi lahir di Master Dokumen.
     */

    /**
     * Resolusi cover paket → path GCS (null bila kosong).
     * - data:image/...;base64,... → unggah ke GCS, kembalikan path.
     * - string path lama (bukan data-uri) → dipertahankan apa adanya.
     * - '' / null → null (cover dihapus).
     * Reuse pola LMS uploadBase64ThumbnailToGcs.
     */
    private function resolveCover(Request $request): ?string
    {
        $cover = $request->input('Cover');
        if (! is_string($cover) || $cover === '') {
            return null;
        }

        if (! preg_match('/^data:image\/(\w+);base64,/', $cover, $m)) {
            return $cover; // sudah berupa path GCS
        }

        $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
        $binary = base64_decode(substr($cover, strpos($cover, ',') + 1)) ?: '';
        if ($binary === '') {
            return null;
        }

        $slug = Str::slug($request->input('Nama_Paket') ?: 'sop-cover');
        $path = 'sop/paket-covers/'.now()->format('Y/m').'/'.$slug.'-'.Str::uuid().'.'.$ext;
        Storage::disk('gcs')->put($path, $binary);

        return $path;
    }

    /**
     * Terapkan Dokumen SOP terpilih ke payload item — Fase 6.
     *
     * MENUTUP CELAH PENULIS KEDUA. Identitas item adalah CERMINAN dari
     * dokumennya (lihat SopDokumenService::sinkronIdentitasKeItem). Selama form
     * masih boleh mengirim Judul_Item sendiri, ada dua penulis untuk satu nilai
     * — dan judul yang diketik admin akan "kembali sendiri" pada sinkronisasi
     * berikutnya. Karena itu untuk item ber-dokumen, judul & nomor SOP yang
     * dikirim form DIABAIKAN dan diambil dari dokumennya.
     *
     * Mengembalikan null bila dokumen tak valid; pemanggil membalas 422.
     */
    private function terapkanDokumen(array $data): ?array
    {
        $token = $data['Id_SOP_Dokumen'] ?? null;
        if ($token === null || $token === '') {
            unset($data['Id_SOP_Dokumen']);

            return $data;
        }

        $id = $this->decode($token);
        if (! $id) {
            return null;
        }

        $dok = DB::table('N_SOP_Dokumen')
            ->where('Id_SOP_Dokumen', $id)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->first(['Id_SOP_Dokumen', 'Judul', 'Nomor_SOP_Resmi', 'Tipe']);
        if (! $dok) {
            return null;
        }

        // Tipe dokumen wajib cocok dengan tipe item: dokumen VIDEO tak boleh
        // dipasang pada item DOKUMEN, karena pembacanya berbeda sama sekali.
        if (isset($data['Tipe_Item']) && $data['Tipe_Item'] !== $dok->Tipe) {
            return null;
        }

        $data['Id_SOP_Dokumen'] = (int) $dok->Id_SOP_Dokumen;
        $data['Judul_Item'] = $dok->Judul;
        $data['Nomor_SOP_Resmi'] = $dok->Nomor_SOP_Resmi;

        return $data;
    }

    /**
     * Identitas item wajib punya SATU sumber — dan sumbernya beda per cabang.
     *
     * Paket DRAFT menulis ke tabel live, yang punya kolom Id_SOP_Dokumen: sejak
     * Fase 6 judul & nomor SOP adalah cerminan dokumennya, jadi yang wajib
     * adalah dokumennya dan judul dari form justru diabaikan.
     *
     * Paket TERBIT menulis ke overlay draft, dan N_SOP_Paket_Draft_Item TIDAK
     * punya kolom dokumen sama sekali — dokumennya baru dibuat saat promosi
     * (SopPaketDraftService::publish → ensureForItem). Di sana identitas masih
     * dibawa item, jadi judullah yang wajib.
     *
     * Mengembalikan pesan siap tampil, atau null bila sudah sah.
     */
    private function pastikanIdentitasItem(array $data, bool $published): ?string
    {
        $tipe = $data['Tipe_Item'] ?? '';
        $adaJudul = filled(trim((string) ($data['Judul_Item'] ?? '')));

        if (! SopTipeItem::isVersioned($tipe)) {
            return $adaJudul ? null : 'Judul item wajib diisi.';
        }

        /* Paket TERBIT maupun DRAFT menuntut hal yang sama sejak 27 Agu 2026:
           dokumennya sudah ada.

           Dua cabang lama dicabut sekaligus. "Judul saja boleh, dokumennya
           dibuat saat promosi" pergi bersama pintu keduanya — gerbang di
           storeItem() menolak lebih dulu bila overlaynya memang belum sanggup
           menyimpan tautan dokumen, dengan pesan yang menyebut DDL-nya. Dan
           "atau isi judulnya untuk membuat dokumen baru" pergi bersama
           buatDokumenBaru(): menawarkan jalan yang sudah tak ada membuat admin
           mengisi judul, ditolak, lalu tak tahu harus ke mana. */
        if (empty($data['Id_SOP_Dokumen'])) {
            return "Item bertipe {$tipe} wajib menunjuk Dokumen yang sudah punya versi berlaku. "
                .'Buat dan sahkan dokumennya di Master Dokumen dulu, lalu pilih di sini.';
        }

        return null;
    }

    /**
     * Satu dokumen hanya boleh muncul SEKALI dalam satu paket.
     *
     * Bukan soal kerapian — angka kepatuhannya yang rusak. Rollup berkunci
     * (Id_SOP_Paket, Id_SOP_Item_Versi) TANPA Id_SOP_Paket_Item:
     *
     *     ->where('Id_SOP_Paket', $idPaket)->where('Id_SOP_Item_Versi', $idVersi)
     *
     * jadi dua item yang menunjuk versi yang sama di satu paket berbagi SATU
     * baris angka. Konfirmasi baca keduanya menambah penghitung yang sama,
     * Jumlah_Dikonfirmasi bisa melampaui Total_Target, dan persennya melewati
     * 100. Keadaan ini sudah pernah ditandai di SopPaketDraftService sebagai
     * "pertanyaan yang belum terjawab"; jawabannya: dilarang.
     *
     * Bagi karyawan pun tak masuk akal — satu SOP yang sama diminta dibaca dua
     * kali dalam satu paket.
     *
     * @param  int|null  $kecualiItem  Item yang sedang disunting; dirinya sendiri bukan bentrok.
     */
    private function pastikanDokumenBelumDipakaiDiPaket(int $idPaket, int $idDokumen, ?int $kecualiItem = null): ?string
    {
        $judul = DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Paket', $idPaket)
            ->where('Id_SOP_Dokumen', $idDokumen)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->when($kecualiItem, fn ($q) => $q->where('Id_SOP_Paket_Item', '<>', $kecualiItem))
            ->value('Judul_Item');

        if ($judul === null) {
            return null;
        }

        return 'Dokumen ini sudah dipakai item "'.$judul.'" di paket yang sama. '
            .'Satu dokumen hanya boleh muncul sekali per paket — kalau muncul dua kali, '
            .'angka kepatuhannya dihitung dari satu baris yang sama dan bisa melewati 100%.';
    }

    /**
     * Dokumen yang muncul lebih dari sekali di sebuah paket — untuk gerbang publish.
     *
     * Menyebut NAMANYA, bukan sekadar "ada duplikat": paket bisa berisi puluhan
     * item, dan penolakan yang tak menunjuk mana yang bermasalah memaksa admin
     * memeriksa semuanya satu per satu.
     */
    private function dokumenKembarDiPaket(int $idPaket): ?string
    {
        $kembar = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'i.Id_SOP_Dokumen')
            ->where('i.Id_SOP_Paket', $idPaket)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->groupBy('i.Id_SOP_Dokumen', 'd.Judul')
            ->havingRaw('COUNT(*) > 1')
            ->select('d.Judul', DB::raw('COUNT(*) as n'))
            ->get();

        if ($kembar->isEmpty()) {
            return null;
        }

        $daftar = $kembar->map(fn ($k) => "\"{$k->Judul}\" ({$k->n}x)")->implode(', ');

        return 'Paket ini memuat dokumen yang sama lebih dari sekali: '.$daftar.'. '
            .'Hapus salah satunya sebelum publish — angka kepatuhan dihitung per dokumen, '
            .'bukan per item, jadi yang kembar akan saling menimpa dan persennya bisa melewati 100%.';
    }

    /**
     * Dokumen yang belum punya versi TERBIT tak boleh ditautkan ke item.
     *
     * Item seperti itu sah menurut skema, judulnya benar, kartunya terlihat
     * lengkap — dan karyawan yang membukanya tak menerima apa pun. Cacatnya
     * tak terdeteksi di mana pun sampai ada yang benar-benar mencoba membaca.
     *
     * Dipakai HANYA saat tautannya BARU atau BERUBAH, tak pernah pada setiap
     * simpan. Sudah ada item yang terlanjur menunjuk dokumen kosong; menolak
     * mereka pada penyuntingan biasa berarti mengunci admin dari satu-satunya
     * layar tempat mereka bisa dibetulkan.
     *
     * Mengembalikan pesan siap tampil, atau null bila dokumennya sudah siap.
     */
    private function pastikanDokumenSiap(int $idDokumen): ?string
    {
        /* PINTU KEDUA penonaktifan (DDL-2026-055). Pintu pertama adalah
           pemilih di dokumenOptions(), yang berhenti menawarkannya — tapi
           pemilih hanya menyembunyikan, tidak menolak. Permintaan yang membawa
           id dokumen langsung (tautan lama, formulir yang sudah terbuka
           sebelum penonaktifan, klien mana pun) tetap sampai ke sini, dan
           tanpa penjaga ini ia akan memasang dokumen yang sudah dipensiunkan
           ke paket baru dengan balasan sukses.

           Ditaruh di pastikanDokumenSiap(), bukan di ketiga pemanggilnya:
           penjaga yang hanya terpasang di sebagian pintu adalah cacat yang
           berulang di modul ini. */
        $dok = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDokumen)
            ->first(['Judul', 'Nonaktif_At']);

        if ($dok && $dok->Nonaktif_At !== null) {
            return 'Dokumen "'.($dok->Judul ?: 'ini').'" sudah dinonaktifkan, jadi tak bisa dipasang ke item baru. '
                .'Paket yang sudah memakainya tetap berjalan seperti biasa.';
        }

        $siap = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Dokumen', $idDokumen)
            ->where('Status', SopStatus::PAKET_PUBLISHED)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->exists();
        if ($siap) {
            return null;
        }

        $judul = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDokumen)->value('Judul');

        return 'Dokumen "'.($judul ?: 'ini').'" belum punya versi berlaku, jadi belum bisa dipakai item — '
            .'karyawan tak akan menerima isi apa pun. Lengkapi versinya di Master Dokumen lalu terbitkan.';
    }

    /* buatDokumenBaru() DIHAPUS (27 Agu 2026).
     *
     * Dulu ia membuat Dokumen SOP, menambah versi pertamanya, dan
     * menerbitkannya sekaligus dalam satu permintaan — supaya menambah item tak
     * perlu pindah halaman. Sejak versi punya alur nyata (Pengajuan → Review →
     * Pengesahan → Pemberkasan → Tinjauan Akhir), jalur satu-permintaan itu
     * bukan lagi kemudahan melainkan pintu samping: yang lahir lewatnya adalah
     * aturan kerja yang terbit tanpa melewati satu pun tahap persetujuan.
     *
     * Dokumen sekarang lahir HANYA di Master Dokumen. Builder merakit paket
     * dari dokumen yang sudah punya versi berlaku — dijaga pastikanDokumenSiap().
     */

    private function validateItem(Request $request, bool $withTipe = true): array
    {
        $rules = [
            // Batas mengikuti kolom TERSEMPIT antara tabel aktif dan tabel draft, karena isi
            // draft disalin apa adanya ke tabel aktif saat publish (dan sebaliknya saat ensure()).
            /* FASE 6 — identitas ikut dokumen. Untuk item ber-dokumen, judul
               tak lagi dikirim dari form: ia diambil dari N_SOP_Dokumen di
               terapkanDokumen(). Judul_Item tetap WAJIB untuk tipe tanpa
               dokumen (PARAGRAF/TAUTAN), yang memang tak punya dokumen. */
            /* Kewajibannya TIDAK ditegakkan di sini lagi. required_without
               menganggap satu-satunya pengganti judul adalah Id_SOP_Dokumen,
               padahal syaratnya berbeda per cabang: paket DRAFT menuntut
               dokumen (tabel live punya kolomnya), paket TERBIT menuntut judul
               (tabel draft overlay TIDAK punya kolom dokumen). Aturan yang
               tinggal di sini membalas pesan mentah Laravel — "field is
               required when id s o p dokumen is not present" — untuk keadaan
               yang bahkan bukan kesalahan admin. Lihat pastikanIdentitasItem(). */
            'Judul_Item' => 'nullable|string|max:255',   // aktif 300, draft 255 → pakai 255
            'Id_SOP_Dokumen' => 'nullable|string', // hashid Dokumen (pemilih Builder)
            /* Dokumen_Baru DICABUT. Builder tak lagi melahirkan Dokumen SOP:
               identitas, versi, dan pengesahannya milik Master Dokumen. Aturan
               yang ditinggalkan di sini bukan sekadar kode mati — ia menerima
               muatan yang tak akan dipakai siapa pun, dan penerimaan diam
               membuat pengirim lama mengira dokumennya terbuat. Penolakannya
               ditegakkan storeItem(), bukan di sini, supaya pesannya menyebut
               ke mana admin harus pergi. */
            'Id_SOP_Section' => 'nullable|string', // hashid; wajib divalidasi di storeItem
            'Id_Master_Konten' => 'nullable|string', // hashid konten library (item file)
            'Ringkasan_Perubahan' => 'nullable|string|max:1000', // aktif 2000, draft 1000
            'Tanggal_Berlaku' => 'nullable|date',
            'Disahkan_Oleh' => 'nullable|string|max:150',
            'Jabatan_Pengesah' => 'nullable|string|max:150',
            'Tanggal_Pengesahan' => 'nullable|date',
            'Nomor_Pengesahan' => 'nullable|string|max:100',
            'Nomor_SOP_Resmi' => 'nullable|string|max:60',
            'Deskripsi_Item' => 'nullable|string|max:1000', // N_SOP_Paket_Item nvarchar(1000)
            'Konten_Teks' => 'nullable|string',
            'Mode_Akses' => 'nullable|in:VIEW_ONLY,VIEW_UNDUH',
            'URL_Tautan' => 'nullable|string|max:1000',
            'Label_Tautan' => 'nullable|string|max:300',
            'Jenis_Quiz' => 'nullable|in:MICRO,PRETEST,POSTTEST',
            // Wajib bila item KUIS sudah menautkan ujian: tanpa ambang, mesin
            // penilaian tak punya dasar meluluskan siapa pun (lihat submitKuisResult).
            'Nilai_Minimum' => [
                'nullable', 'numeric', 'min:0', 'max:100',
                Rule::requiredIf(fn () => $request->input('Tipe_Item') === 'KUIS' && filled($request->input('Id_Master_Ujian'))),
            ],
            'Id_Master_Ujian' => 'nullable|string|max:200',
            'Nama_Ujian' => 'nullable|string|max:200',
            'Flag_Camera' => 'nullable|in:Y,T',
            'Flag_Wajib' => 'nullable|boolean',
            'Flag_Highlight' => 'nullable|boolean',
            'Flag_Watermark' => 'nullable|boolean',
        ];
        if ($withTipe) {
            $rules['Tipe_Item'] = 'required|string|max:12';
        } else {
            $rules['Tipe_Item'] = 'sometimes|required|string|max:12';
        }

        return $request->validate($rules);
    }

    /**
     * Proxy daftar ujian HC Learn untuk item KUIS (mirror LMS getDataKategoriUjian).
     * Outbound HMAC via SignatureHelper + kredensial HRIS dari config .env.
     * Balikan: [{ Id_Master_Ujian, Nama_Ujian }, ...]
     */
    public function optionsUjian()
    {
        $appId = Auth::user()->User_Key ?? null;
        $kodeKaryawan = Auth::user()->Kode_Users ?? null;

        // HC Learn mewajibkan identitas user (X-App-ID + X-Kode-Karyawan) — sama spt LMS.
        if (empty($appId) || empty($kodeKaryawan)) {
            return ResponseHelper::error(
                'Akun Anda belum tertaut integrasi HC Learn (User_Key/Kode_Users kosong). '.
                    'Gunakan akun yang sama seperti di Penjadwalan Training LMS.',
                403,
            );
        }

        $endpoint = 'api/v1/penjadwalan/training/options';
        $domain = rtrim((string) config('services.frans.hclearn_api.domain'), '/');
        $url = $domain.'/'.ltrim($endpoint, '/');

        try {
            $auth = SignatureHelper::get($endpoint, $appId, $kodeKaryawan);
            $response = Http::withHeaders($auth['headers'])->get($url);

            if (! $response->successful()) {
                $statusCode = $response->status();
                $apiData = $response->json();
                $message = is_array($apiData)
                    ? $apiData['message'] ?? ($apiData['result'] ?? 'Gagal mengambil daftar ujian dari HC Learn')
                    : 'Gagal mengambil daftar ujian dari HC Learn';

                // 401/403 di sini = akun HC Learn user tidak dikenal (app-signature valid, User_Key ditolak).
                if (in_array($statusCode, [401, 403], true)) {
                    $message .=
                        ' — pastikan akun Anda terintegrasi HC Learn (akun yang sama seperti di Penjadwalan Training LMS).';
                }

                Log::warning('SOP optionsUjian gagal', [
                    'status' => $statusCode,
                    'message' => $message,
                    'kode_karyawan' => $kodeKaryawan,
                    'body' => is_string($response->body()) ? substr($response->body(), 0, 500) : null,
                ]);

                return ResponseHelper::error($message, $statusCode);
            }

            $apiData = $response->json();
            $data = $apiData['result'] ?? ($apiData['data'] ?? $apiData);

            return ResponseHelper::success($data, 'Data Ditemukan !');
        } catch (\Exception $e) {
            Log::error('SOP builder optionsUjian gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Pada Sistem', 500);
        }
    }
}
