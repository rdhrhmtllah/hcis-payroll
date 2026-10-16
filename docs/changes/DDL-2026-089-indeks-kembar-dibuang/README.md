# DDL-2026-089 — Tujuh indeks kembar yang dibayar tiap tulis dan tak pernah dipakai baca

**Status:** REVIEW_REQUIRED — belum dieksekusi di mana pun.
**Skrip ini berisi `DROP INDEX` dan harus dijalankan manusia**, setelah BAGIAN 7
dipenuhi. Agen tidak boleh menjalankannya.

## Temuan

Tujuh indeks yang **kolom kunci, urutannya, kolom `INCLUDE`, dan definisi
saringannya sama persis** dengan indeks lain pada tabel yang sama. Indeks kembar
tidak memberi manfaat baca sedikit pun — pengoptimal hanya memakai salah satu —
tetapi setiap `INSERT`, `UPDATE`, dan `DELETE` tetap memelihara keduanya.

| tabel | baris | disimpan | seeks | dibuang | seeks |
|---|---:|---|---:|---|---:|
| `HRIS_KANDIDAT_Jawaban_Soal` | 11.236 | `PK__HRIS_KAN__5E8D9D0C` | 4.512 | `idx_jawaban_soal_pk` | 7 |
| `HRIS_KANDIDAT_Master_Soal` | 2.788 | `PK__HRIS_KAN__9F8D659A` | 194.029 | `idx_master_soal_pk` | 8 |
| `N_WEB_CAREERS_Lamaran_Tahap` | 2.179 | `UQ_…_Tahap_Urutan` | 6.496 | `IX_…_Tahap_Lamaran` | 1.294 |
| `HRIS_Rekrutmen_Karyawan` | 71 | `idx_rk_kode_calon` | 676 | `idx_rekrutmen_kode_calon` | 0 |
| `N_WEB_CAREERS_Sub_Divisi_Informations` | 32 | `UQ__N_WEB_CA__03FA2B1A` | 42 | `Idx_DeptInfo_Sub_Divisi` | 139 |
| `N_WEB_CAREERS_Division_Informations` | 22 | `UQ__N_WEB_CA__B2357D05` | 33 | `Idx_DivInfo_Divisi` | 584 |
| `N_ESIGN_Revoked_Certificates` | 0 | `UQ_Revoked_SerialNumber` | 0 | `IX_Revoked_SerialNumber` | 0 |

Beberapa yang dibuang memang punya seek. Itu wajar dan bukan bantahan: kalau dua
indeks identik, pengoptimal memilih salah satu sembarang. Sesudah yang kembar
dibuang, seek itu **pindah ke saudaranya**, bukan hilang.

`idx_jawaban_soal_pk` adalah contoh paling telanjang: **7 seek** berbanding
**9.298 pemeliharaan tulis** sepanjang jendela yang sama.

## Kenapa yang disimpan adalah yang unik

Pada enam dari tujuh pasang, salah satunya `PRIMARY KEY` atau `UNIQUE
CONSTRAINT`. Yang unik **wajib tinggal**: ia bukan sekadar indeks melainkan
penjamin keutuhan data, dan membuangnya membuka pintu baris kembar.

`HRIS_Rekrutmen_Karyawan` kekecualiannya — keduanya non-unik dan identik, jadi
yang disimpan adalah yang dipakai pengoptimal (`idx_rk_kode_calon`, 676 seek).

## Tujuh tuduhan yang sengaja ditarik

Pemeriksaan pertama menemukan **empat belas** pasang berkunci sama. Setengahnya
ternyata **bukan** kembar, dan tidak masuk skrip ini:

| pasangan | kenapa bukan kembar |
|---|---|
| `IX_JawabanAkhir_Token` vs `IX_JawAkhir_Token` | `INCLUDE` berbeda |
| `IX_KPI_Pengajuan_Det_Periode` vs `…_IdDetail_Periode` | `INCLUDE` berbeda (`Nilai_Target`) |
| `UX_HRIS_Absen_Snapshot_…` vs `IX_…_Read` | `INCLUDE` berbeda (17 kolom) |
| `UQ__KPI_Peng__3A5E7948` vs `IX_KPI_Pengajuan_NoTransaksi` | `INCLUDE` berbeda |
| `IX_N_SOP_Item_Versi_Dokumen` vs `UX_…_Urut_Hidup` | saringan `WHERE Flag_Aktif='Y'` |
| `UX_NWC_SkrVersi_Terbit` vs `UX_NWC_SkrVersi_Draf` | saringan `Status='PUBLISHED'` vs `'DRAFT'` |
| `PK__Cutoff_C__3214EC07` vs `IDX_Active_Config` | saringan `WHERE Effective_To IS NULL` |

**Indeks tersaring adalah pola yang dipakai sadar di repo ini.** Membuang salah
satunya karena kuncinya "kelihatan sama" akan menghapus penjamin keunikan yang
hanya berlaku pada baris aktif — persis kelas kesalahan yang paling mahal di
sini. Pemeriksaan `INCLUDE` dan `filter_definition` ditambahkan justru setelah
versi pertama pemeriksaan ini salah menuduh tiga pasangan pertama.

## Yang harus dilakukan di production lebih dulu

Nama indeks di production belum tentu sama dengan di development, dan indeks
**bernama sama pun bisa berkunci berbeda**. Menjalankan `DROP` berdasarkan daftar
dari development bisa membuang indeks yang di sana justru satu-satunya.

```bash
php artisan db:inventaris-skema --pemakaian --keluaran=prod.json   # di production
php artisan db:inventaris-skema --banding=dev.json,prod.json
```

Perhatikan khususnya bagian **"indeks bernama sama tetapi BERBEDA isinya"**.
BAGIAN 6 `forward.sql` juga memuat kueri hanya-baca yang mendeteksi kembar; ia
harus mengembalikan nol baris sesudah skrip dijalankan.

## Risiko

Rendah dan dapat dibatalkan penuh. Tidak ada `DROP TABLE`, `DROP COLUMN`, maupun
`TRUNCATE`; tidak ada satu baris data pun yang berubah. `drop.sql` membangun
kembali ketujuhnya persis — definisinya dibaca dari `sys.indexes` sebelum satu
pun `DROP` disusun, bukan ditulis tangan. Ketujuhnya non-unik, jadi pembangunan
ulang tidak bisa gagal karena data kembar.
