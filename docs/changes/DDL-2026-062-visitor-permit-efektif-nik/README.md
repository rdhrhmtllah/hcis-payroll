# DDL-2026-062-visitor-permit-efektif-nik

## Ringkas

Mencatat kolom `N_VISITOR_Permit_Efektif.NIK` yang **sudah ada di development**
tetapi tidak pernah terdaftar sebagai change apa pun, dan mengisi baris lama
yang terlanjur tersimpan tanpa nomor induk.

| Objek | Tipe | Sumber isi |
|---|---|---|
| `N_VISITOR_Permit_Efektif.NIK` | `varchar(20) NULL` | `Karyawan.No_Induk_Karyawan` |

`NIK` di sini berarti **nomor induk karyawan**, bukan NIK KTP. Penamaannya
menyesatkan tetapi dipertahankan karena kolomnya sudah ada di development;
mengganti nama akan memutus baris yang sudah tersimpan.

## Kenapa muncul sekarang

Dilaporkan user pada 2026-09-01: kolom `NIK` selalu kosong sesudah pengajuan
permit. Dugaan awal adalah kolom sumbernya salah.

Penelusuran menunjukkan sebab yang berbeda: **kolom itu tidak pernah ditulis
sama sekali.**

- Payload insert dan update di
  `app/Services/VISITOR/Operations/Expiration/VisitorEfektifService.php`
  tidak punya kunci `NIK`.
- Pencarian `NIK` di seluruh `app/Http/Controllers/VISITOR` dan
  `app/Services/VISITOR` tidak menghasilkan satu pun kecocokan.
- `sys.triggers` untuk tabel itu kosong, jadi tidak ada pengisi tersembunyi.
- `database/visitor/2026_07_30_visitor_efektif_projection.sql` — satu-satunya
  berkas pembuat tabelnya — tidak menyebut `NIK`. Kolomnya menempel di urutan
  paling akhir, sesudah `Synced_At`, tanda ia ditambahkan di luar alur DDL.

Dua dari 12 baris di development punya `NIK` terisi. Keduanya diisi manual di
luar aplikasi, dan salah satunya membuktikannya: `PMT0726-0002` menyimpan
`15160427` sedangkan `Karyawan` kode `C9` sekarang punya `No_Induk_Karyawan`
kosong. Nilai hasil kode mustahil berbeda dari sumbernya.

## Dampak bila tidak dijalankan

Berbeda dari DDL-2026-061, change ini **wajib jalan sebelum kodenya rilis**.
Perbaikan kode mulai menulis kolom `NIK` pada setiap penyetujuan permit. Bila
kolomnya belum ada di production, setiap penyetujuan akan gagal dengan
`Invalid column name 'NIK'`.

## Keputusan desain

**Nullable, bukan NOT NULL.** 63 dari 965 baris `Karyawan` punya
`No_Induk_Karyawan` kosong. `NOT NULL` akan menolak permit yang sah dari
karyawan yang nomor induknya belum diisi di master.

**Tetap `varchar(20)`, tidak dilebarkan.** Sumbernya `varchar(30)`, tetapi
nilai terpanjang di `Karyawan` per 2026-09-01 adalah 13 karakter, jadi tidak
ada pemotongan. Melebarkannya justru membuat development dan production
berbeda lagi.

**Baris yang sudah terisi tidak ditimpa oleh DML.** Dua baris manual di
development dibiarkan apa adanya; menimpanya menghapus satu-satunya bukti
nilai yang pernah ada di sana. Perhatikan bahwa perbaikan kode berperilaku
lain: `NIK` ikut dalam payload **update**, sehingga baris yang di-sync ulang
akan diselaraskan ke nilai master saat itu. Itu memang yang diinginkan — satu
sumber kebenaran — tetapi berarti nilai manual bisa hilang saat permit
bersangkutan disetujui atau di-sync ulang.

## Catatan eksekusi

- Development: kolomnya **sudah ada**. `forward.sql` idempoten, jadi
  menjalankannya di development adalah no-op terverifikasi.
- `data-change.sql` di development menyentuh **0 baris**, dan itu benar: 10
  baris yang `NIK`-nya kosong semuanya milik karyawan contoh (A3, A4, A5, B6,
  C9, ...) yang `No_Induk_Karyawan`-nya memang kosong di master. Angka nyata
  baru muncul di production.
- Urutan: `forward.sql` lebih dulu, baru `data-change.sql`.

## Kolom yatim satunya lagi

`N_VISITOR_Permit_Efektif.Flag_Batal` ditemukan bersamaan dengan `NIK`: sama-
sama ada di development, sama-sama tidak pernah tercatat sebagai change.
Awalnya ditinggal di luar cakupan karena tidak ada kode yang membacanya.
Statusnya berubah pada hari yang sama — user meminta pencabutan permit ikut
menandainya — dan sekarang ditangani sendiri di
**DDL-2026-063-visitor-efektif-flag-batal**.

Kedua change wajib jalan sebelum kodenya rilis, jadi sebaiknya dieksekusi
berurutan dalam satu kesempatan.

## Berkas

| Peran | Path |
|---|---|
| Forward DDL | `database/ddl/DDL-2026-062-visitor-permit-efektif-nik/forward.sql` |
| Drop (manual saja) | `database/ddl/DDL-2026-062-visitor-permit-efektif-nik/drop.sql` |
| DML pengisian | `database/dml/DDL-2026-062-visitor-permit-efektif-nik/data-change.sql` |
| Dokumentasi | `docs/changes/DDL-2026-062-visitor-permit-efektif-nik/README.md` |

## Perubahan kode yang menyertai

| Berkas | Perubahan |
|---|---|
| `app/Services/VISITOR/Permit/VehiclePermit/VisitorPermitQueryService.php` | `basePermitQuery()` memilih `k.No_Induk_Karyawan as NIK`. Tabel `Karyawan` sudah di-join sebagai `k`, jadi tidak ada join baru. |
| `app/Services/VISITOR/Operations/Expiration/VisitorEfektifService.php` | `syncPermitEfektif()` menulis `NIK` di payload insert **dan** update. Nilai kosong dinormalkan ke `NULL`. |
