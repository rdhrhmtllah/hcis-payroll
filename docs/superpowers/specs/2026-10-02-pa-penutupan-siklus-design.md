# PA — Ruang Kerja Penutupan Siklus (konsep A)

Tanggal: 2026-10-02 · Modul: KPI / Performance Appraisal / Cycle · Status: disetujui (konsep A)

## 1. Masalah

Pekerjaan admin sesudah pengisian ditutup tersebar dan berulang:

- Kunci kurva hanya ada di halaman laporan Distribusi; Balancing tidak ditautkan dari halaman PA
  mana pun; "Navigasi Tahapan" di Monitoring tidak mengenal kurva, Balancing, dan tutup penerbitan.
- Hitung Hasil (3 tempat), Terbitkan (3 tempat), dialog hasil (7 halaman) dengan kabel berbeda.
- Tombol tanpa efek: tutup penerbitan (siklus → PUBLISHED) tidak pernah tersambung; rilis ke
  leader massal tidak punya layar; publish/rilis per orang mati di `hasil.vue` dan `siklus.vue`.
- `hasil.vue` duplikat lama dari Monitoring; `kalibrasi.vue` yatim.

## 2. Tujuan & kriteria sukses

Satu admin bisa berjalan berurutan; beberapa admin bisa masuk langsung ke tahapnya masing-masing.

- Satu halaman per siklus dengan **stepper 5 tahap**; tiap tahap punya alamat (`?tahap=`).
- Setiap tahap menyebut status, **penghambat** (kalimat dari server, sama dengan gerbang yang akan
  menolak), dan **aktivitas terakhir** (apa, siapa, kapan).
- Tiap aksi tinggal di SATU tempat. Halaman laporan tidak punya aksi pengubah.
- Tidak ada tombol yang mati diam-diam.
- Tidak ada perubahan rumus/perhitungan: semua aksi memanggil service yang sudah ada.

## 3. Tahap

| # | Kode | Nama | Isi | Aksi |
|---|------|------|-----|------|
| 1 | `pengisian` | Pengisian | tab PENGISIAN lama (monitoring rater) | Buka/Tutup Pengisian |
| 2 | `verifikasi` | Hitung & Verifikasi | tab HASIL + KERANJANG lama (sub-tab) | Hitung Hasil massal, antrean, dialog hasil per orang |
| 3 | `kurva` | Kurva Normal | `PaKurvaPanel` (dipindah dari Distribusi) | Atur grup, Kunci/Buka kurva |
| 4 | `balancing` | Balancing | daftar sesi siklus | Buat sesi, buka board, Hitung ulang |
| 5 | `terbit` | Rilis & Terbit | ringkasan status hasil + rilis leader | Rilis ke leader (massal), Terbitkan, Tutup penerbitan |

"Ikhtisar" (tab RINGKASAN lama) tetap sebagai halaman awal, di luar stepper.

Status tahap (`keadaan`): `SELESAI`, `BERJALAN`, `MENUNGGU` (belum waktunya), `DILEWATI`
(kurva pada metode PITA; Balancing tanpa sesi). Turunan dari data, bukan disimpan:

- Pengisian: OPEN → BERJALAN; CLOSED/PUBLISHED → SELESAI; selainnya MENUNGGU.
- Verifikasi: hanya bila CLOSED/PUBLISHED. SELESAI bila belum_dihitung = 0 dan tertahan = 0.
- Kurva: PITA → DILEWATI; terkunci → SELESAI; CLOSED → BERJALAN; selainnya MENUNGGU.
- Balancing: ada sesi TERBUKA/DRAFT atau FINAL belum dihitung ulang → BERJALAN; ada sesi dan
  semuanya tuntas → SELESAI; tanpa sesi → DILEWATI (opsional); sebelum CLOSED → MENUNGGU.
- Terbit: PUBLISHED → SELESAI; CLOSED → BERJALAN; selainnya MENUNGGU.

`tahap_disarankan` = tahap pertama berstatus BERJALAN (Balancing DILEWATI tidak menghentikan).

## 4. Backend

`GET /api/v1/performance-appraisal/cycles/{cycleId}/penutupan` →
`PaPenutupanSiklusService::alur()` (hanya-baca). Bentuk:

```
{ siklus:{cycle_id,nama,status,metode_grade}, tahap_disarankan,
  tahap:[{kode,nama,keadaan,ringkas,penghambat:[],aktivitas:{aksi,oleh,pada}|null}],
  cacah:{penugasan_total,penugasan_selesai,hasil_*,dirilis_leader,sesi_terbuka,sesi_menunggu} }
```

Sumber: `N_PA_Cycle` (status, Updated/Published), `N_PA_Assignment` (Status_Penugasan),
`N_PA_Result` (status, Calculated/Published/Published_Leader), `N_PA_Cycle_Curve`
(Locked/Unlocked), `N_PA_Balancing_Sesi`. Nama pelaku dari `KPI_Users.Nama`. Penghambat
memakai pesan aturan yang sama dengan `PaBalancingPenjaga::pastikanTuntas`, kurva, dan gerbang.

Tidak ada DDL. Aksi memakai endpoint yang sudah ada (`rilis-leader`, `terbitkan`,
`tutup-penerbitan`, `balancing/*`, `kurva/*`).

## 5. Frontend

- `Cycle/pemantauan.vue` → judul "Penutupan Siklus". Tab bar diganti `PaPenutupanStepper`
  (Ikhtisar + 5 tahap). Tombol aksi header pindah ke tahapnya; header tinggal pemilih siklus
  dan Segarkan. URL `?tahap=` (kompatibel `?tab=` lama).
- Komponen baru di `Cycle/components/penutupan/`: `PaPenutupanStepper.vue`,
  `PaTahapKepala.vue` (status + penghambat + aktivitas), `PaTahapKurva.vue`,
  `PaTahapBalancing.vue`, `PaTahapTerbit.vue`.
- `PaPublicationDialog` `@close-publication` disambungkan (pemantauan). Rilis leader massal
  lewat `PaTahapTerbit` dengan dialog konfirmasi.
- `Hasil/distribusi.vue`: `PaKurvaPanel` diganti kartu ringkas hanya-baca + tautan ke
  `?tahap=kurva`.
- `hasil.vue`: dialog per orang tidak lagi menawarkan publish/rilis tanpa handler
  (`can-publish` false); halaman diarahkan ke tahap verifikasi. `siklus.vue`: handler rilis
  per orang disambungkan atau tombol dimatikan.
- `BalancingPage`/`BalancingSesiPage`: tautan kembali ke tahap Balancing.

## 6. Menu (DML, dev lalu SQL Tracker)

`DML-2026-16x-menu-pa-penutupan-siklus`: ubah Nama_Menu `PaPemantauanPage` → "Penutupan
Siklus"; sembunyikan `PaHasilPage` ("Hasil & Penerbitan") dan `PaKalibrasiPage` ("Balancing")
dari menu (halaman tetap bisa dibuka lewat tahap; hak akses tidak berubah).

## 7. Di luar cakupan

Kerapian nav switcher bersama di 8 halaman laporan, pemecahan `pemantauan.vue` lebih lanjut,
penghapusan berkas yatim (`kalibrasi.vue`, `PaHavMapDialog.vue`) — catatan tindak lanjut.

## 8. Uji

- PHPUnit `PaPenutupanSiklusServiceTest`: keadaan tiap tahap untuk OPEN, CLOSED belum hitung,
  tertahan, KURVA belum/sudah kunci, PITA, sesi terbuka/FINAL belum dihitung, PUBLISHED.
- Vitest: stepper (keadaan, URL), PaTahapTerbit (rilis massal, tutup penerbitan memanggil API),
  pemantauan `?tahap=` kompatibel `?tab=`.
- Suite Balancing + pemantauan + distribusi yang ada tetap hijau; `npm run build`.
- Cek data dev (#10244): angka ringkasan tahap = angka `ringkasanPenerbitan`/Balancing.

## 9. Rencana eksekusi

1. Backend service + route + test.
2. Komponen tahap + stepper, sambungkan ke pemantauan (tanpa memindah blok template besar).
3. Perbaikan tombol mati (tutup penerbitan, rilis massal, per orang).
4. Distribusi read-only, tautan Balancing, `hasil.vue`.
5. DML menu + dokumen perubahan + status.json; eksekusi dev.
6. Test, build, verifikasi data.
