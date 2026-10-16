# Master Dokumen SOP — FASE 5: Register Ditumbuhkan Jadi Halaman Master Dokumen

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: `superpowers:subagent-driven-development` atau `superpowers:executing-plans`. Langkah memakai checkbox (`- [ ]`).

**Goal:** Register kontrol dokumen di Monitoring berhenti menjadi daftar **versi-per-item** dan menjadi daftar **dokumen** — satu baris per dokumen terkendali, dengan daftar paket yang memakainya dan riwayat versinya. Ini permukaan admin pertama dari Master Dokumen.

**Architecture:** **Ditumbuhkan, bukan ditambah.** Rancangan induk melarang layar dokumen kedua: dua layar berarti dua angka berbeda untuk hal yang sama, dan admin tak punya cara tahu mana yang benar. `TabDokumen.vue` yang sudah ada tetap tempatnya; isinya yang berubah sumbunya.

**Tech Stack:** Laravel 10, SQL Server (sqlsrv, tanpa RCSI), Inertia + Vue 3 (Options API), `DB::table()`, PHPUnit.

---

## Global Constraints

- Tidak ada migration Laravel; tidak ada Eloquent model/factory.
- Bahasa Indonesia untuk nama, komentar, pesan, dan judul commit.
- **Fase pertama yang menyentuh Vue.** Butuh `npm run dev` berjalan; verifikasi wajib termasuk kompilasi SFC, bukan hanya PHPUnit.
- **Setiap pembatasan dilaporkan** (plafon halaman, pemotongan daftar paket) — tak pernah senyap.
- Ikuti bahasa desain modul: acuan "senada dengan page lain" di modul SOP adalah pola `SopPenjadwalan.vue` (QuickStat + kepala kartu ber-border-bottom + lencana pil). `sop-glass-card` yang dipakai `TabDokumen.vue` sekarang adalah kokpit legacy — **jangan disebarkan ke markup baru**, tapi juga jangan menulis ulang seluruh kartunya di fase ini.

---

## Keputusan Cakupan yang Perlu Dinyatakan di Depan

**Fase 5 BACA-SAJA. Penyuntingan identitas dokumen ditunda ke Fase 6, dan itu bukan kemalasan.**

Rancangan induk menuliskan Fase 5 sebagai "kelola versi & pengesahan". Menyunting identitas dokumen (`Judul`, `Nomor_SOP_Resmi`, `Deskripsi`) hari ini akan **menciptakan perbedaan yang tak bisa dijelaskan UI**: item masih membawa `Judul_Item` dan `Nomor_SOP_Resmi`-nya sendiri, dan itulah yang dilihat karyawan di halaman SOP. Mengubah judul dokumen tanpa mengubah judul item menghasilkan dua nama untuk satu dokumen terkendali — persis penyakit yang proyek ini obati.

Di Fase 6 item berhenti membawa identitasnya sendiri (Builder memilih Dokumen), dan saat itulah penyuntingan punya satu tempat yang benar. Fase 5 memberi admin **penglihatan** lebih dulu: dokumen mana ada, dipakai paket mana, versinya apa saja, dan mana yang bermasalah kepatuhan.

---

## Temuan Riset

**1. `register()` mengembalikan baris per ITEM, bukan per dokumen.** Ia menyeleksi `i.Id_SOP_Paket_Item, i.Judul_Item, p.Nama_Paket, …` dan **tidak** menyertakan `Id_SOP_Dokumen`. Hari ini 1 dokumen = 1 item sehingga tampak setara; begitu Fase 6 menyalakan berbagi, satu dokumen yang dipakai 5 paket akan muncul **5 kali** sebagai 5 baris berbeda — kembali ke penyakit yang sama, sekarang di layar yang mengaku mengendalikannya.

**2. Register hanya memuat paket PUBLISHED** (`baseVersiAktif`: `p.Status = PUBLISHED`). Dokumen yang hanya dipakai paket DRAFT tak pernah terlihat. Itu perilaku lama yang **dipertahankan** di fase ini — mengubahnya akan menggeser semua angka ember sekaligus, dan baseline tak bisa membedakan mana yang disengaja.

**3. Pointer versi aktif kini menjadi satu-satunya penentu pasangan item↔versi** (perbaikan Fase 4). Itu membuat pengelompokan per dokumen aman: semua item perujuk menunjuk versi aktif yang sama.

**4. `TabDokumen.vue` memakai `sop-glass-card`** — kokpit legacy menurut bahasa desain modul. Markup baru mengikuti pola `SopPenjadwalan.vue`.

---

## Struktur Berkas

| Berkas | Tanggung jawab | Aksi |
|---|---|---|
| `app/Services/SOP/SopDocumentControlService.php` | `register()` menyertakan `Id_SOP_Dokumen`; method baru `paketPemakai()` & `riwayatVersi()` | Ubah |
| `app/Http/Controllers/Sop/SopAdminMonitoringController.php` | Endpoint detail dokumen | Ubah |
| `routes/Sop/SopAdminWeb.php` | `GET /dokumen/{id}/detail` | Ubah |
| `resources/js/Pages/SOP/modules/admin/monitoring/TabDokumen.vue` | Baris per dokumen + lencana paket pemakai + pemicu drawer | Ubah |
| `resources/js/Pages/SOP/components/ui/SopDokumenDetailDrawer.vue` | Riwayat versi + daftar paket pemakai | **Buat** |
| `tests/Feature/LMS/SOP/SopMasterDokumenTest.php` | Pengelompokan per dokumen + detail | **Buat** |

---

# Task 1 — Baseline Pra-Fase-5

Sidik jari menyerap nilai relatif-hari-ini; ambil di hari yang sama dengan gerbang Task 5.

- [ ] `git status --porcelain` kosong, `php artisan sop:dokumen-verify` exit 0
- [ ] `php artisan sop:dokumen-baseline --simpan=plans/sop/baseline/fase5-sebelum.json`
- [ ] `--bandingkan=` melaporkan "Identik", `tercakup == jumlah`
- [ ] Commit `chore(sop): baseline pra-Fase-5`

---

# Task 2 — Register Membawa Identitas Dokumen

**Files:** `app/Services/SOP/SopDocumentControlService.php`, `tests/Feature/LMS/SOP/SopMasterDokumenTest.php`

**Interfaces:**
- `register()` baris kini juga memuat `Id_SOP_Dokumen` (boleh NULL untuk versi yatim)
- Baru: `paketPemakai(int $idDokumen): array` — `[{Id_SOP_Paket, Nama_Paket, Status}]`
- Baru: `riwayatVersi(int $idDokumen): array` — versi terbaru dulu, dengan kolom pengesahan

- [ ] **Step 1: Test yang gagal**

```php
public function test_baris_register_membawa_id_dokumen(): void
{
    [$rows] = app(SopDocumentControlService::class)->register([], 1, 50);
    if (!count($rows)) $this->markTestSkipped('Register kosong di data dev.');

    $this->assertTrue(property_exists($rows[0], 'Id_SOP_Dokumen'),
        'Tanpa Id_SOP_Dokumen, layar tak bisa mengelompokkan per dokumen — dan begitu Fase 6 '
        . 'menyalakan berbagi, satu dokumen muncul sekali per paket, kembali ke penyakit '
        . 'duplikasi yang justru diobati proyek ini.');
}

public function test_paket_pemakai_mencakup_semua_item_perujuk(): void
{
    // Simulasikan berbagi (pola sama dengan SopDokumenFanoutTest), lalu
    // pastikan paketPemakai() memuat KEDUA paket.
}

public function test_riwayat_versi_terbaru_dulu(): void
{
    // Dokumen dengan >1 versi: Versi_Ke menurun.
}
```

- [ ] **Step 2: Jalankan, pastikan GAGAL**
- [ ] **Step 3: Implementasi** — tambahkan `'i.Id_SOP_Dokumen'` ke daftar `->get([...])` `register()`, lalu dua method baru:

```php
    /**
     * Paket yang memakai dokumen ini. Dipakai halaman Master Dokumen untuk
     * menjawab pertanyaan yang selama ini tak bisa dijawab admin: "kalau saya
     * merevisi dokumen ini, siapa saja yang terdampak?"
     */
    public function paketPemakai(int $idDokumen): array
    {
        return DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->where('i.Id_SOP_Dokumen', $idDokumen)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('p.Flag_Aktif', SopStatus::AKTIF)
            ->distinct()
            ->orderBy('p.Nama_Paket')
            ->get(['p.Id_SOP_Paket', 'p.Nama_Paket', 'p.Status'])
            ->all();
    }

    /** Riwayat versi dokumen — terbaru dulu, dengan kolom pengesahan. */
    public function riwayatVersi(int $idDokumen): array
    {
        return DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Dokumen', $idDokumen)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderByDesc('Versi_Ke')
            ->get([
                'Id_SOP_Item_Versi', 'Versi_Ke', 'Status', 'Ringkasan_Perubahan',
                'Tanggal_Berlaku', 'Tanggal_Kadaluarsa', 'Tanggal_Review_Berikutnya',
                'Disahkan_Oleh', 'Jabatan_Pengesah', 'Nomor_Pengesahan', 'Tanggal_Pengesahan',
                'Tanggal_Publish',
            ])
            ->all();
    }
```

**Catatan penting:** `paketPemakai()` **tidak** menyaring `p.Status = PUBLISHED`, berbeda dengan `register()`. Itu disengaja: pertanyaan "siapa terdampak kalau dokumen ini direvisi" harus memuat paket DRAFT juga, karena paket itu akan terbit membawa dokumen tersebut. Perbedaan ini wajib terlihat di UI (lencana status), bukan disembunyikan.

- [ ] **Step 4: LULUS**, **Step 5: Commit**

---

# Task 3 — Endpoint Detail Dokumen

**Files:** `SopAdminMonitoringController.php`, `routes/Sop/SopAdminWeb.php`

- [ ] `GET /api/v1/sop/admin/monitoring/dokumen/{id}/detail` → `{ dokumen, paket_pemakai, riwayat_versi }`
- [ ] `{id}` adalah Hashid `Id_SOP_Dokumen`. Tolak id tak valid dengan 400, dokumen tak ada dengan 404.
- [ ] Test: struktur balasan + 404 untuk id asing. Ingat `ResponseHelper::success()` membungkus di kunci **`result`**, bukan `data`.
- [ ] Commit

---

# Task 4 — UI: Daftar Dokumen + Drawer Detail

**Files:** `TabDokumen.vue`, `SopDokumenDetailDrawer.vue` (baru)

- [ ] **Step 1: Kelompokkan baris per dokumen di sisi klien.** Baris ber-`Id_SOP_Dokumen` sama digabung jadi satu kartu; paket-paketnya jadi lencana pil. Baris ber-`Id_SOP_Dokumen` NULL (versi yatim) **tetap ditampilkan apa adanya** — jangan dibuang; di dev merekalah mayoritas baris rollup.

  Pengelompokan di klien, bukan di SQL, karena `register()` sudah berhalaman: mengelompokkan di SQL akan mengubah arti `total` dan menggeser semua angka ember sekaligus. Itu perubahan tersendiri yang layak diukur terpisah. **Konsekuensinya wajib dilaporkan di UI**: satu halaman berisi 25 *baris*, yang bisa menjadi kurang dari 25 *dokumen*.

- [ ] **Step 2: Drawer detail** — riwayat versi (Versi_Ke, status, tanggal berlaku/kadaluarsa, pengesah) + daftar paket pemakai dengan lencana status. Baca-saja; alasan ada di "Keputusan Cakupan".

- [ ] **Step 3: Verifikasi kompilasi SFC**

```bash
node -e "const {parse,compileScript}=require('@vue/compiler-sfc');const fs=require('fs');
for (const f of ['resources/js/Pages/SOP/modules/admin/monitoring/TabDokumen.vue','resources/js/Pages/SOP/components/ui/SopDokumenDetailDrawer.vue']) {
  const {descriptor,errors}=parse(fs.readFileSync(f,'utf8'),{filename:f});
  if (errors.length) { console.error(f, errors); process.exit(1); }
  compileScript(descriptor,{id:'x'}); console.log('OK', f);
}"
```

- [ ] **Step 4: Uji manual di browser** — buka Monitoring → tab Dokumen: chip ember masih benar, pencarian jalan, satu dokumen muncul sekali dengan lencana paketnya, drawer membuka riwayat versi.

- [ ] **Step 5: Commit**

---

# Task 5 — Gerbang

- [ ] `sop:dokumen-verify` exit 0
- [ ] `sop:dokumen-baseline --bandingkan=plans/sop/baseline/fase5-sebelum.json` → "Identik" (Fase 5 tak mengubah jalur baca yang di-hash; kalau BERUBAH, ada perubahan yang tak disengaja)
- [ ] `php artisan test --filter=Sop` → nol gagal
- [ ] `php artisan test` → 19 kegagalan tetap terkurung di keenam berkas pra-eksisting; **jalankan dua kali** (ada test flaky yang terbukti memberi 20 lalu 19)
- [ ] Vitest: `npx vitest run` → nol gagal
- [ ] Uji manual browser terlaksana

---

---

## Ledger Eksekusi (2026-08-10)

| Task | Commit | Catatan |
|---|---|---|
| 1 — Baseline pra-Fase-5 | `1b19…` | 916/916, "Identik" |
| 2 — Register bawa identitas dokumen | `dde030c7` | + `paketPemakai()` & `riwayatVersi()` |
| 3 — Endpoint detail dokumen | `5747f91b` | Baca-saja, disengaja |
| 4 — UI daftar + drawer | `cd6da70d` | Kedua SFC lolos parse + compileScript |

### Hasil gerbang

| Butir | Hasil |
|---|---|
| `sop:dokumen-verify` | exit 0 |
| Vitest | 126/126 lulus |
| Kompilasi SFC | `TabDokumen.vue` & `SopDokumenDetailDrawer.vue` lolos parse + compileScript |
| Suite SOP | 169 lulus, 2 skip, 0 gagal (dari 163) |
| Suite penuh | 19 gagal, 302 lulus (dari 296) |
| Keterkurungan 19 | Keenam berkas pra-eksisting sendirian menghasilkan 19, **dua jalan berturut konsisten** |
| Baseline | 1 perbedaan — **terbukti** hanya kolom baru (lihat bawah) |

### Baseline BERUBAH — dan itu benar, tapi harus dibuktikan

Perbandingan melaporkan **satu** perbedaan: `Isi register kontrol dokumen`. Itu diharapkan, karena Task 2 menambahkan kolom `Id_SOP_Dokumen` ke keluaran `register()` dan isi register ikut di-hash. Tapi "diharapkan" bukan "terbukti" — perbedaan yang diasumsikan tak berbahaya adalah cara paling rapi untuk melewatkan regresi.

Pembuktiannya: hitung ulang sidik register sekarang, lalu hitung sekali lagi **dengan kolom baru dibuang**.

```
jumlah baris   lama=28   kini=28
total          lama=28   kini=28
sidik baseline                  : e8052780c9acde89d8f6176d…
sidik sekarang (apa adanya)     : 9995240168949d7ca5180698…  BEDA
sidik sekarang TANPA kolom baru : e8052780c9acde89d8f6176d…  SAMA
```

Membuang kolom baru mereproduksi hash baseline **persis**, dengan jumlah baris identik di kedua sisi. Perbedaannya sepenuhnya berasal dari kolom yang sengaja ditambahkan — bukan dari baris yang bergeser, hilang, atau bertambah.

`fase5-sebelum.json` **sengaja tidak diperbarui**: ia bukti keadaan pra-perubahan, dan menimpanya akan menghapus satu-satunya pembanding yang membuat pembuktian di atas mungkin.

### Dua koreksi saat eksekusi

**1. `Id_SOP_Dokumen` semula tak di-encode.** Id lain di `register()` di-encode Hashid di akhir; kolom baru terlewat. Akibatnya dua sekaligus: id DB mentah bocor ke klien, dan endpoint detail (yang men-decode Hashid) tak akan bisa memuatnya. NULL dipertahankan NULL — meng-encode `null` menghasilkan token yang tampak sah padahal tidak menunjuk apa pun. Dikunci test.

**2. `sqlsrv` mengembalikan kolom numerik sebagai STRING.** Ditemukan lewat test endpoint yang gagal (`'1652'` vs `1652`). Ini penting untuk sisi Vue: pengelompokan baris memakai `String(...)` secara konsisten — mencampur int dan string akan memecah satu dokumen jadi dua kelompok tanpa gejala apa pun.

---

## Gerbang Menuju Fase 6

- [ ] Seluruh Task 5 hijau
- [ ] **Keputusan produk yang harus dijawab sebelum Fase 6 dirancang:**
  - Saat Builder memilih Dokumen, apakah `Judul_Item` dan `Nomor_SOP_Resmi` pada item **dihapus** (identitas sepenuhnya milik dokumen) atau tetap ada sebagai **judul tampil per paket**? Yang pertama lebih bersih secara kepatuhan; yang kedua mempertahankan keluwesan yang mungkin sudah dipakai admin.
  - Saat item disalin ke paket lain, apakah ia **berbagi** dokumen sumber (tujuan awal Anda) atau tetap dapat dokumen sendiri dengan opsi "tautkan ke dokumen yang sama"?
  - Volume notifikasi: satu revisi dokumen bersama bisa menghasilkan sampai 6 notifikasi untuk satu karyawan (58 dari 59 karyawan ada di >1 paket). Gabungkan, beri jeda, atau biarkan per paket? Lihat ledger Fase 4 Task 6.
