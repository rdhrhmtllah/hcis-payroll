# Master Dokumen SOP — FASE 6: Builder Memilih Dokumen

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: `superpowers:subagent-driven-development` atau `superpowers:executing-plans`. Langkah memakai checkbox (`- [ ]`).

**Goal:** Item SOP berhenti membawa identitasnya sendiri. Builder memilih **Dokumen**, judul & nomor SOP ikut dokumen, dan menyalin item ke paket lain berarti **berbagi dokumen yang sama** — sehingga revisi menyebar ke semua paket, tujuan awal proyek ini.

**Architecture:** Fase pertama yang benar-benar mengubah alur admin. Mesinnya sudah berdiri sejak Fase 3–4 (fan-out + join lewat dokumen) dan sudah terbukti nol regresi selagi masih no-op; Fase 6 hanya **menyalakannya**.

**Tech Stack:** Laravel 10, SQL Server (tanpa RCSI), Inertia + Vue 3 (Options API), PHPUnit + Vitest.

---

## Keputusan Produk User (2026-08-10)

| # | Keputusan | Konsekuensi yang disengaja |
|---|---|---|
| 1 | **Identitas ikut dokumen sepenuhnya.** Field Judul & Nomor SOP hilang dari form item. | Satu dokumen = satu nama di mana pun. Judul yang berbeda antar paket menjadi seragam. |
| 2 | **Salin ke paket lain = berbagi dokumen otomatis.** | Tombol "Salin ke paket lain" berubah sifat: salinan kini terikat ke sumbernya sampai dilepas. |
| 3 | **Notifikasi digabung per dokumen.** | Satu notifikasi menyebut dokumen + daftar paket terdampak, bukan satu per paket. |

---

## Temuan Riset yang Menentukan

**1. Nol divergensi identitas — keputusan #1 aman pada data hari ini.** Seluruh 25 item ber-dokumen punya `Judul_Item` dan `Nomor_SOP_Resmi` yang **identik** dengan dokumennya (hasil backfill Fase 1). Memindahkan sumber tampilan ke dokumen karena itu netral perilaku, dan baseline bisa membuktikannya.

**2. CELAH DARI FASE 1 YANG BARU TERLIHAT SEKARANG.** Dua item ber-tipe versi **tidak punya dokumen**:

```
item #28 paket=4  KUIS 'TEST KUIS INI'  pointer=NULL  jml_versi=0  dibuat 2026-07-22
item #98 paket=24 KUIS 'Quiz'           pointer=NULL  jml_versi=0  dibuat 2026-08-05
```

Keduanya KUIS **tanpa satu pun baris versi**. Backfill Fase 1 berkunci pada *item yang punya versi*, jadi ia melewatkannya. `sop:dokumen-verify` juga tak melihatnya — kelima invariannya memeriksa **baris versi**, dan item tanpa versi tak punya baris untuk diperiksa. Selama identitas masih di item, ini tak berakibat apa-apa. Begitu Fase 6 memindahkan identitas ke dokumen, **kedua item ini kehilangan judulnya**.

Ini kelalaian pada pekerjaan Fase 1 saya, bukan temuan netral. Task 2 menutupnya, dan menambahkan invarian ke-6 supaya kelas kesalahan ini tak lagi tak terlihat.

**3. `cloneActiveTo()` harus berhenti membuat versi baru.** Dengan berbagi, item salinan **mewarisi seluruh riwayat versi dokumennya**; membuat baris `Versi_Ke = 1` yang baru akan ditolak unique index terfilter 26B dengan error 2601. Yang benar: item baru cukup menunjuk versi aktif dokumen tersebut.

**4. Notifikasi digabung berarti menahan notifikasi per-paket.** `reconcilePaket()` memanggil `notifDiperbarui()` sendiri (`SopReconciliationService:162`). Untuk menggabungkan, fan-out harus bisa meminta rekonsiliasi **tanpa** notifikasi, lalu mengirim satu notifikasi gabungan setelah semua paket selesai.

---

## Struktur Berkas

| Berkas | Tanggung jawab | Aksi |
|---|---|---|
| `app/Console/Commands/SopDokumenBackfill.php` | Lintasan ke-3: item ber-tipe versi tanpa versi ikut dapat dokumen | Ubah |
| `app/Console/Commands/SopDokumenVerify.php` | Invarian ke-6: item ber-tipe versi tanpa dokumen | Ubah |
| `app/Services/SOP/SopItemService.php` | `cloneRow()` menyalin `Id_SOP_Dokumen`; identitas tak lagi wajib dari input | Ubah |
| `app/Services/SOP/SopItemVersionService.php` | `cloneActiveTo()` berhenti membuat versi; hanya menunjuk versi aktif dokumen | Ubah |
| `app/Services/SOP/SopReconciliationService.php` | Opsi menahan notifikasi per-paket | Ubah |
| `app/Services/SOP/SopDokumenFanoutService.php` | Kirim satu notifikasi gabungan per dokumen | Ubah |
| `app/Http/Controllers/Sop/SopAdminBuilderController.php` | Endpoint pilih dokumen; validasi identitas pindah | Ubah |
| `resources/js/Pages/SOP/components/ui/SopDokumenPickerModal.vue` | Pemilih Dokumen di Builder | **Buat** |
| `resources/js/Pages/SOP/modules/admin/SopBuilder.vue` | Form item kehilangan Judul & Nomor untuk tipe ber-versi | Ubah |
| `tests/Feature/LMS/SOP/SopDokumenBerbagiTest.php` | Berbagi, identitas, notifikasi gabungan | **Buat** |

---

# Task 1 — Baseline Pra-Fase-6

- [ ] Tree bersih; `sop:dokumen-verify` exit 0
- [ ] `php artisan sop:dokumen-baseline --simpan=plans/sop/baseline/fase6-sebelum.json`, `--bandingkan=` → "Identik", `tercakup == jumlah`
- [ ] Commit

---

# Task 2 — Tutup Celah: Item Ber-tipe Versi Tanpa Versi

**Deliverable:** setiap item ber-tipe versi punya dokumen, dan `sop:dokumen-verify` bisa melihat kelas kesalahan ini.

- [ ] **Step 1: Test yang gagal**

```php
public function test_verify_melaporkan_item_ber_tipe_versi_tanpa_dokumen(): void
{
    $n = DB::table('N_SOP_Paket_Item')
        ->whereIn('Tipe_Item', SopTipeItem::VERSIONED)
        ->where('Flag_Aktif', SopStatus::AKTIF)
        ->whereNull('Id_SOP_Dokumen')->count();

    $this->assertSame(0, $n,
        "Ada {$n} item ber-tipe versi tanpa dokumen. Kelima invarian lama memeriksa BARIS "
        . 'VERSI, jadi item tanpa versi sama sekali tak terlihat oleh mereka — dan begitu '
        . 'Fase 6 memindahkan identitas ke dokumen, item itu kehilangan judulnya.');
}
```

- [ ] **Step 2: Jalankan** — GAGAL dengan 2 (item #28, #98).
- [ ] **Step 3: Backfill lintasan ke-3.** Tambahkan lintasan tanpa syarat di `handle()`: item ber-tipe versi, `Flag_Aktif='Y'`, `Id_SOP_Dokumen IS NULL` → buat dokumen lewat `SopDokumenService::ensureForItem()`. Ikut dihitung di `--dry-run`; early-return hanya bila **ketiga** backlog kosong.
- [ ] **Step 4: Invarian ke-6 di `sop:dokumen-verify`** — hitung item ber-tipe versi tanpa dokumen, laporkan sebagai **pelanggaran** (bukan INFO), dengan penjelasan kenapa kelima invarian lama tak melihatnya.
- [ ] **Step 5:** `php artisan sop:dokumen-backfill` lalu `sop:dokumen-verify` → exit 0.
- [ ] **Step 6: Commit**

---

# Task 3 — Identitas Dibaca dari Dokumen

Netral perilaku karena divergensi nol (Temuan 1) — dan baseline yang membuktikannya.

- [ ] **Step 1: Test** — untuk item ber-dokumen, judul yang ditampilkan jalur baca berasal dari `N_SOP_Dokumen.Judul`. Ubah `Judul` dokumen di dalam transaksi test, pastikan jalur baca ikut berubah sementara `Judul_Item` tetap.
- [ ] **Step 2: Implementasi** — jalur baca menampilkan `COALESCE(d.Judul, i.Judul_Item)` dan `COALESCE(d.Nomor_SOP_Resmi, i.Nomor_SOP_Resmi)`. Item tanpa dokumen (PARAGRAF/TAUTAN/ABSENSI) mundur ke kolom itemnya — mereka memang tak punya dokumen dan tak boleh dipaksa.

  **Kolom item TIDAK dihapus dari tabel.** Ia jadi cadangan bagi tipe tanpa dokumen dan jejak bagi data lama. Penghapusan kolom adalah pekerjaan Fase 7 setelah penggabungan selesai, bukan sekarang.

- [ ] **Step 3:** Baseline → "Identik" (divergensi nol membuatnya harus identik; kalau BERUBAH, ada item yang identitasnya ternyata menyimpang dan itu wajib diselidiki sebelum lanjut).
- [ ] **Step 4: Commit**

---

# Task 4 — Salin ke Paket Lain = Berbagi Dokumen

- [ ] **Step 1: Test yang gagal** — salin item, lalu:
  - item baru punya `Id_SOP_Dokumen` **sama** dengan sumbernya
  - **tidak ada baris `N_SOP_Item_Versi` baru** yang lahir
  - `Id_Item_Versi_Aktif` item baru = versi aktif dokumen (atau NULL bila dokumen belum punya versi PUBLISHED)
  - menerbitkan revisi di paket sumber **menggeser pointer item salinan juga** (fan-out Fase 3 mengambil alih)

- [ ] **Step 2: `cloneRow()`** menyalin `Id_SOP_Dokumen` dari `$src`, dan **tidak** memanggil `ensureForItem()` lagi.
- [ ] **Step 3: `cloneActiveTo()`** berhenti membuat baris versi. Ia hanya menyalin pointer versi aktif bila versi itu `PUBLISHED`:

```php
        /* FASE 6 — BERBAGI. Item salinan mewarisi seluruh riwayat versi
           dokumennya; membuat baris Versi_Ke=1 yang baru akan ditolak unique
           index terfilter 26B (error 2601) karena dokumen itu sudah punya
           versi 1. Yang tersisa hanyalah menunjuk versi aktif dokumennya —
           dan hanya bila versi itu PUBLISHED, karena pointer versi aktif tak
           boleh menunjuk DRAFT (invarian kepatuhan). */
```

- [ ] **Step 4: UI** — modal salin memberi tahu bahwa item akan **berbagi dokumen**, dan revisi di paket mana pun menyebar ke semua. Perubahan sifat tombol wajib terlihat sebelum diklik, bukan sesudah.
- [ ] **Step 5: Commit**

---

# Task 5 — Builder Memilih Dokumen

- [ ] **Step 1: Endpoint** `GET /api/v1/sop/admin/builder/dokumen/options?q=&tipe=` — daftar dokumen aktif, dengan jumlah paket pemakainya supaya admin sadar dampak sebelum memilih.
- [ ] **Step 2: `SopDokumenPickerModal.vue`** — cari + pilih dokumen, atau "buat dokumen baru dari berkas Library" (jalur lama, kini membuat `N_SOP_Dokumen` lebih dulu).
- [ ] **Step 3: Form item** kehilangan field Judul & Nomor SOP untuk tipe ber-versi; digantikan kartu dokumen terpilih + tombol ganti.
- [ ] **Step 4:** Validasi server menolak simpan item ber-tipe versi tanpa `Id_SOP_Dokumen`.
- [ ] **Step 5:** Verifikasi SFC (`parse` + `compileScript`) + uji manual di browser.
- [ ] **Step 6: Commit**

---

# Task 6 — Notifikasi Digabung per Dokumen

- [ ] **Step 1: Test** — karyawan yang ditugaskan ke 2 paket berbagi dokumen menerima **satu** notifikasi saat dokumen direvisi, dan isinya menyebut kedua paket.
- [ ] **Step 2:** `reconcilePaket()` menerima opsi `tanpaNotifikasi` dan mengembalikan daftar `reopenedKodes`.
- [ ] **Step 3:** `SopDokumenFanoutService::rekonsiliasi()` memanggilnya dengan opsi itu, menggabungkan kode karyawan dari semua paket, lalu mengirim **satu** notifikasi per karyawan yang menyebut dokumen + daftar paket.
- [ ] **Step 4:** Jalur rekonsiliasi lain (Publish Paket, MANUAL) **tidak berubah** — mereka memang per paket.
- [ ] **Step 5: Commit**

---

# Task 7 — Gerbang

- [ ] `sop:dokumen-verify` exit 0 (kini termasuk invarian ke-6)
- [ ] `sop:dokumen-baseline --bandingkan=` → "Identik" di hari yang sama. **Fase 6 mengubah perilaku admin, bukan keluaran baca pada data 1:1** — selama belum ada dokumen yang benar-benar dibagi, keluarannya harus tetap sama. Perbedaan apa pun wajib dibuktikan asal-usulnya seperti pada Fase 5, bukan diasumsikan.
- [ ] `php artisan test --filter=Sop` → nol gagal
- [ ] `php artisan test` → 19 kegagalan tetap terkurung; jalankan keenam berkas **dua kali**
- [ ] `npx vitest run` → nol gagal
- [ ] Uji manual: buat item baru lewat pemilih dokumen; salin ke paket lain; terbitkan revisi di salah satu paket dan pastikan paket lain ikut bergeser; periksa notifikasi tunggal

---

---

## Ledger Eksekusi (2026-08-10)

| Task | Commit | Catatan |
|---|---|---|
| 1 — Baseline | `9d0b…` | 916/916, "Identik" |
| 2 — Titik buta item tanpa versi | `7d6bdf30` | **Kelalaian Fase 1**, lolos lima gerbang |
| 3 — Identitas ikut dokumen | `f1a936a3` | **Menyimpang dari rencana** — disebarkan, bukan di-join |
| 4 — Salin = berbagi | `c88b31e9` | Dua test lama dibalik kontraknya |
| 5a — Endpoint opsi dokumen | `4d4a768a` | |
| 5b — Item menerima dokumen | `2150c626` | Dua pagar di luar rencana |
| 5c — Builder UI | `fd8d27b5` | **Bug yang nyaris terkirim** |
| 6 — Notifikasi digabung | `369367b5` | Jalur terantri sengaja tetap per paket |

### Hasil gerbang

| Butir | Hasil |
|---|---|
| `sop:dokumen-verify` | exit 0 (invarian pertama kini diperluas) |
| Baseline nol regresi | "Identik" |
| Vitest | 126/126 |
| Kompilasi SFC | `ItemFields`, `SopDokumenPickerModal`, `SopBuilder` lolos |
| Suite SOP | 181 lulus, 2 skip, 0 gagal (dari 169) |
| Suite penuh | 19 gagal, 314 lulus (dari 302) |
| Keterkurungan 19 | Keenam berkas pra-eksisting sendirian menghasilkan 19, dua jalan konsisten |

### Kelalaian saya sendiri, ditemukan di sini

Kelima invarian `sop:dokumen-verify` **semuanya berangkat dari baris versi**, dan backfill Fase 1 juga berkunci pada item *yang punya versi*. Seluruh kelas jadi tak terlihat: item ber-tipe versi tanpa satu pun baris versi. Dua di antaranya nyata (KUIS #28, #98) dan **lolos laporan "BERSIH" di kelima gerbang Fase 1–5**. Baru berakibat di Fase 6, karena identitas pindah ke dokumen dan keduanya akan kehilangan judul.

### Penyimpangan terbesar dari rencana, dan alasannya

Rencana menyuruh tiap jalur baca memakai `COALESCE(d.Judul, i.Judul_Item)` — 10+ kueri di 8 service. Fase 4 sudah membuktikan pekerjaan jenis itu mudah meleset: lima situs, dua di antaranya tak ada di daftar rencana. Yang dipakai justru prinsip yang menyelamatkan migrasi ini sejak awal: `Id_Item_Versi_Aktif` dipertahankan sebagai **cerminan yang disinkronkan sistem**. Identitas mengikuti pola itu, dan **nol jalur baca disentuh**.

Konsekuensinya — kolom item jadi data turunan dengan satu penulis — baru aman setelah Task 5b mencabut kemampuan form menulisnya. Celah itu terbuka selama dua task dan ditutup dengan sengaja, bukan dilupakan.

### Bug yang nyaris terkirim

`byPaket()` meng-encode empat id tapi melewatkan `Id_SOP_Dokumen`. Rantainya: id mentah dikirim ke form → form mengembalikannya apa adanya → `terapkanDokumen()` men-decode sebagai Hashid → gagal → **422 pada setiap simpan item ber-dokumen**, di jalur paling ramai di Builder. Ketahuan lewat penelusuran rantai nilai, bukan lewat test yang gagal — tak ada test yang akan menangkapnya sebelum seseorang mencoba lewat UI.

### Prediksi saya yang meleset

Saya memperkirakan baseline akan BERUBAH karena Task 2 membuat dua dokumen baru. Ternyata "Identik": kedua KUIS itu tak punya baris versi, sedangkan sidik register dan bundle berangkat dari versi. Dokumen tanpa versi tak terlihat oleh keduanya.

---

## Gerbang Menuju Fase 7

- [ ] Seluruh Task 7 hijau
- [ ] **Fase 7 = alat "Gabungkan Dokumen"** untuk duplikat lama. Sekarang barulah ia punya arti: sebelum Fase 6, tak ada cara memakai dokumen bersama, jadi menggabungkan duplikat tak ada gunanya.
- [ ] Penghapusan kolom `Judul_Item`/`Nomor_SOP_Resmi` dari `N_SOP_Paket_Item` **hanya boleh** setelah penggabungan selesai dan tak ada lagi tipe yang bergantung padanya.
