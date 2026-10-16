# Penyeragaman penamaan Performance Appraisal — 1 September 2026

Dikerjakan atas permintaan pemilik proyek, ketika **nol change masuk production** dan
seluruh 25 change masih `TRACKER_PENDING`. Sesudah production, ongkosnya berlipat.

> "sepertinya ada yang mesti kita perbaiki penamaan di table dan kolomnya … mumpung
> masih belum terlalu jauh"

## Prinsipnya

**Inggris untuk yang struktural** — waktu, pelaku, versi, jenis rekaman, field milik
sistem lain.
**Indonesia untuk nilai bisnis yang diucapkan tim** — Bobot, Nilai, Skor, Alasan, Kode,
Nama, Urutan, Jumlah.

Diuji ke keadaan sebelum perubahan, prinsip ini ternyata **sudah berlaku 90%**:
`Created_At`, `Updated_By`, `Cancelled_At`, `Locked_At`, `Activated_At` semuanya Inggris;
`Bobot_Persen`, `Nilai_KPI`, `Alasan_Perubahan` semuanya Indonesia. Yang diperbaiki
adalah penyimpangannya, bukan seluruh skema.

---

## A. Inggris menang — struktural

| Sebelum | Sesudah | Sebab |
|---|---|---|
| `Diterbitkan_At` / `_By` | `Published_At` / `_By` | `N_PA_Cycle` **sudah** memakai `Published_At/By` untuk hal yang sama |
| `Dikalkulasi_At` / `_By` | `Calculated_At` / `_By` | waktu & pelaku |
| `Diubah_Oleh` / `Diubah_At` | `Performed_By` / `Performed_At` | cara ketiga menyebut hal yang sama. `Performed_*` bukan `Updated_*`: riwayat kalibrasi hanya disisip |
| `Tenggat` | `Due_At` | tetangganya ikut: `Waktu_Mulai` → `Started_At`, `Waktu_Selesai` / `Waktu_Akhir` → `Finished_At` |
| `Pemeta_Versi` | `Mapper_Version` | urutan baku modul ini `Versi_*`, tetapi versi bersifat struktural |
| `Versi_Api` | `Api_Version` | |
| `Versi_Perhitungan` | `Calculation_Version` | tiga tabel |
| `Alasan_Batal` | `Cancel_Reason` | berpasangan dengan `Cancelled_At` / `Cancelled_By` |

## B. `Sumbu` dan `Kegunaan`

Keduanya sendirian berbahasa Indonesia di antara `Assignment_Status`, `Competency_Type`,
`Result_Note_Type`, `Rater_Policy_Status`, `Hav_Quadrant`.

`Sumbu` juga **tidak akurat**: isinya `FINAL`, `KPI`, `KOMPETENSI`. Dua terakhir memang
sumbu HAV Map; `FINAL` bukan sumbu apa pun. Ia sebenarnya menyatakan *skor yang mana*.

| Sebelum | Sesudah |
|---|---|
| tabel `N_PA_Score_Sumbu` | `N_PA_Score_Type` — `Kode_Type`, `Nama_Type` |
| tabel `N_PA_Score_Kegunaan` | `N_PA_Score_Usage` — `Kode_Usage`, `Nama_Usage` |
| kolom `Sumbu` / `Kegunaan` | `Score_Type` / `Score_Usage` |

## C. Indonesia menang — nilai bisnis

| Sebelum | Sesudah | Sebab |
|---|---|---|
| `Maksimal_Score` | `Skor_Maksimal` | konsep sama dengan `N_PA_Assignment_Score.Skor_Maksimal`, urutannya terbalik. **Sudah menimbulkan bug**: pemeta payload harus menulis `DB::raw('Maksimal_Score AS Skor_Maksimal')` untuk menjembataninya |
| `Bobot_Hclearn` | `Bobot_Sumber` | `Bobot` tetap; yang dibuang **nama vendornya**. Kalau HC Learn diganti, kolomnya berbohong |

## Yang DITOLAK pemilik proyek

- **Suffiks `_Salinan`** pada kolom tabel `_Snapshot` — sempat diusulkan dibuang karena
  mubazir. Diputuskan **tetap**; penamaan Snapshot dinilai sudah cocok.
- **`Final_Score` → `Nilai_Akhir`** — diputuskan **tetap** `Final_Score`. Nama yang bagus
  tidak diganti hanya demi keseragaman.

## Yang sengaja tidak disentuh

`ID_Level`, `ID_Divisi` — ejaan asli tabel HRIS lama yang di-join. ·
`Id_Ujian_Token`, `Id_Ujian_Nilai_Akhir` — nama field HC Learn, disalin agar bisa
ditelusuri ke kontrak. · `N_PA_Rater_Policy.Versi` — itu versi *aturan*, bukan versi
pemeta maupun perhitungan; tidak menyimpang. · `Kode_*`, `Nama_*`, `Flag_*`, `Urutan_*` —
konvensi seluruh repo.

---

## Cara dikerjakan

**Database** — `sp_rename`, non-destruktif, **tanpa satu baris data pun tersentuh**.
`sp_rename` menolak kolom yang diikat CHECK atau FK, jadi 14 CHECK dan 4 FK dilepas,
kolomnya diganti, lalu dipasang ulang `WITH CHECK` sehingga tetap *trusted*. Hasil akhir:
40 tabel, 83 CHECK, 75 FK, nol nama lama, nol yang tidak trusted.

**Kode dan dokumen** — 701 penggantian di 211 berkas, **dikurung ke berkas PA saja**.
`Waktu_Mulai`, `Waktu_Selesai`, `Waktu_Akhir`, `Tenggat`, dan `Sumbu` semuanya dipakai
modul lain — LMS, ESS, dan grafik Vue. Penggantian global akan merusak ketiganya.

**Artefak** — 12 change diamandemen di tempat (jalur `AGENTS.md`, bukan change korektif),
revisi dinaikkan, checksum dihitung ulang, baris riwayat ditulis di tiap README.

## Tiga cacat lama yang baru ketahuan

Syarat `AGENTS.md` "artefak yang diamandemen harus berjalan ulang sebagai no-op" menyalakan
tiga kegagalan yang **sudah ada sebelum penggantian nama** dan tak pernah terlihat:

| Change | Cacat | Sebab |
|---|---|---|
| DDL-073 | jumlah kolom item harus 13, FK harus 4 | DDL-083 r2 menambah 2 kolom dan 1 FK secara sah |
| DDL-075 | jumlah kolom assignment harus 19 | DDL-082 menambah 3 kolom secara sah |
| DML-078 | pita tersimpan harus berakhir di 100 | DML-079 sengaja membukanya ke 999.99 |

Ketiganya diperbaiki menjadi **memeriksa milik changenya sendiri**, bukan menghitung
seluruh isi tabel. Pelajarannya: penjaga yang menuntut jumlah persis akan menyala setiap
kali change berikutnya menambah sesuatu yang sah — dan karena tak seorang pun menjalankan
ulang artefak lama, kegagalan itu bisa diam berbulan-bulan.

## Bukti

`php artisan pa:demo-cycle` sesudah seluruh perubahan: dua peserta memperoleh nilai akhir
dan grade, enam tertahan dengan sebab yang benar — identik dengan sebelum penggantian nama.
Seluruh 12 artefak berjalan ulang sebagai no-op. `php artisan pa:audit` melaporkan L0
bersih kecuali dua butir manual (ERD) dan production.
