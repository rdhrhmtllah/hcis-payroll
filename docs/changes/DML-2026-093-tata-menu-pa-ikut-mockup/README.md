# DML-2026-093 — Tata menu admin PA mengikuti alur mockup

| | |
|---|---|
| Change ID | `DML-2026-093-tata-menu-pa-ikut-mockup` |
| Jenis | DML |
| Artifact revision | **r1** |
| Artifact | `database/dml/DML-2026-093-tata-menu-pa-ikut-mockup/data-change.sql` |
| Bergantung pada | DML-2026-092 (tata ulang header PA sudah dijalankan) |
| Status | `TRACKER_PENDING` — dev DONE 3 September 2026, production PENDING |

> Ditulis menyusul pada 9 September 2026. Entri ledger-nya sudah menunjuk berkas ini
> sejak awal, tetapi berkasnya tidak pernah dibuat — ketahuan oleh
> `LedgerChangeUtuhTest::test_setiap_entri_menunjuk_berkas_yang_ada` yang datang dari
> master. Isinya diambil dari kepala skrip DML-nya dan catatan eksekusi di ledger,
> bukan dikarang ulang.

## Masalahnya

Mockup merancang **sebelas** halaman admin dalam **tiga** grup. Yang berjalan sebelum
perubahan ini hanya **sembilan butir dalam satu grup**, dan **tujuh** di antaranya — yang
mockup rancang sebagai halaman tersendiri — masih berupa dialog di dalam `siklus.vue`
yang berukuran 1.578 baris.

Itu sebab pengalaman adminnya buruk: sebelas pekerjaan berbeda dijejalkan ke satu layar,
dijalankan sekali-dua kali setahun, tanpa apa pun yang menunjukkan urutannya.

Endpoint datanya **sudah ada sejak lama dan sudah berkontrak**. Yang ditambahkan di sini
hanya halaman dan barisan menunya — bukan kemampuan baru.

## Susunan sesudah perubahan

| Grup | Butir |
|---|---|
| Performance Appraisal *(global, seluruh karyawan)* | Penilaian Saya |
| Ringkasan | Dashboard Admin **(baru)**, Monitoring Progress *(pindah + ganti nama)* |
| Konfigurasi Cycle | Assessment Cycle *(pindah + ganti nama)*, Assignment Penilai **(baru)**, Bobot Penilaian *(dari master Aturan Penilai)*, Pengaturan KPI *(dari master Bobot Pilar)* |
| Hasil | Hasil Performance *(pindah + ganti nama)*, Kalibrasi Grade Akhir **(baru)**, HAV Map (9-Box) **(baru)**, Distribusi Performance **(baru)**, Riwayat Penilaian **(baru)** |
| Master Data | Jenis Kompetensi, Kompetensi, Pita Nilai, Paket Soal *(seluruhnya pindah)* |

## Tiga keputusan yang perlu diketahui

**Empat grup, bukan tiga.** Mockup tidak menampilkan halaman master sama sekali, tetapi
keempatnya tetap dibutuhkan. Ditaruh di grup keempat paling bawah: alur mockup dihormati
utuh, dan yang jarang disentuh tidak mengotori tiga grup yang mockup rancang.

**Menu Leader dicabut.** Mockup hanya mengenal dua peran — Peserta dan Administrator.
Halaman dan endpoint-nya **tidak dihapus** dan tetap berfungsi lewat alamat langsung; yang
dicabut hanya baris menunya. Keputusan pemilik proyek, 3 September 2026.

**Akses ditiru, tidak didaftar manual.** Keenam halaman baru diberikan kepada setiap orang
yang hari ini memegang `PerformanceAppraisalPage`. Mendaftar manual berarti daftarnya
berselisih dengan kenyataan begitu ada orang ditambahkan.

## Cakupan maksimum sekali jalan

`N_HRIS_Menus` ≤ 6 disisipkan dan ≤ 11 diperbarui, `N_HRIS_Grup_Fitur_Map` ≤ 6,
`HRIS_Page_Access` ≤ 12, pendaftaran perubahan ≤ 1.

Skripnya menolak berjalan di luar `Web_HRIS` (`THROW 50410`) dan menolak berjalan sebelum
DML-2026-091 (`THROW 50411`).

## Sesudah menjalankan — WAJIB

Cache menu harus dibuang, kalau tidak menu barunya tidak muncul sampai cache-nya kedaluwarsa
sendiri:

```bash
php artisan tinker --execute="\App\Services\SYSTEM\Application\Shell\InertiaShellService::flushMenuCache();"
```

## Hasil di development (3 September 2026)

Diverifikasi lewat **efek**, bukan lewat jumlah baris yang tersentuh:

- **ADMIN H45** (user 4) melihat **16 butir dalam lima grup** — [Performance Appraisal]
  Penilaian Saya; [Ringkasan] Dashboard Admin, Monitoring Progress; [Konfigurasi Cycle]
  Assessment Cycle, Assignment Penilai, Bobot Penilaian, Pengaturan KPI; [Hasil] Hasil
  Performance, Kalibrasi Grade Akhir, HAV Map, Distribusi, Riwayat; [Master Data] empat master.
- **Karyawan biasa** (user 594) melihat **1 butir**.
- Uji FE: 28 berkas / 102 uji hijau.
