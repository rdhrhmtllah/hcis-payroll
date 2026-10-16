# DDL-2026-063-visitor-efektif-flag-batal

## Ringkas

Mencatat kolom `Flag_Batal` pada `N_VISITOR_Permit_Efektif` dan
`N_VISITOR_Tamu_Efektif` — sudah ada di development, tidak pernah terdaftar
sebagai change apa pun — lalu **meluruskan isinya**, yang saat ini sebagian
besar terbalik.

| Nilai | Arti | Pasangan `Status_Efektif` |
|---|---|---|
| `T` | akses tidak dibatalkan | `AKTIF`, `EXPIRED` |
| `Y` | akses dibatalkan | `DIBATALKAN` |

## Kenapa muncul sekarang

Permintaan user 2026-09-01: pencabutan permit dari monitoring Visitor harus
ikut menandai `Flag_Batal = 'Y'`. Sampai sekarang tidak ada satu baris kode
pun yang menulis kolom itu.

## Temuan: isi kolomnya terbalik

Keadaan development sebelum change ini:

| Tabel | `Status_Efektif` | `Flag_Batal` | Baris | |
|---|---|---|---|---|
| `N_VISITOR_Permit_Efektif` | `AKTIF` | `T` | 1 | benar |
| | `AKTIF` | `Y` | 8 | **salah** |
| | `DIBATALKAN` | `T` | 3 | **salah** |
| | `DIBATALKAN` | `Y` | 2 | benar |
| `N_VISITOR_Tamu_Efektif` | `AKTIF` | `T` | 1 | benar |
| | `AKTIF` | `Y` | 76 | **salah** |

Delapan permit dan 76 kunjungan yang **masih aktif** bertanda "dibatalkan".
Kalau kolomnya mulai dibaca tanpa diluruskan lebih dulu, akses yang sah akan
terbaca sebagai dicabut — persis kebalikan dari yang diminta.

Asalnya tidak bisa dipastikan. Default kolomnya `('T')` dan tidak ada kode
yang menulis `Y`, jadi nilai `Y` masuk dari luar aplikasi. Polanya berkelompok
menurut `Id_Permit_Efektif` (baris 22–35 semuanya `Y`, baris 20–21 dan 55
`T`), yang lebih mirip satu `UPDATE` manual atas rentang baris daripada
akibat pemakaian normal.

## Konsekuensi bila tidak dijalankan

Change ini **wajib jalan sebelum kodenya rilis**. Mulai sekarang kode menulis
`Flag_Batal` di empat tempat; bila kolomnya belum ada di production, setiap
pencabutan dan setiap penyetujuan gagal dengan
`Invalid column name 'Flag_Batal'`.

## Keputusan desain

**Kolomnya redundan, dan itu disengaja.** `Flag_Batal = 'Y'` selalu setara
dengan `Status_Efektif = 'DIBATALKAN'` — bisa diturunkan, tidak menambah
informasi. Normalnya kolom seperti ini tidak dibuat. Kolomnya dipertahankan
karena sudah ada dan diminta secara eksplisit; kemungkinan besar untuk
konsumen luar yang menarik lewat `Flag_Sync`. Yang penting dijaga:
**`Status_Efektif` tetap sumber kebenaran.** Bila keduanya berselisih,
`Status_Efektif` yang benar, dan `data-change.sql` diturunkan dari situ.

**Ditulis eksplisit, tidak diserahkan ke default constraint.** Payload sync
menulis `'T'`, bukan mengandalkan `DEFAULT ('T')`. Dua alasan: baris yang
di-sync ulang ikut diluruskan, dan perilakunya tidak bergantung pada default
constraint yang belum tentu ada di setiap environment.

**Tamu ikut, bukan hanya permit.** Yang diminta user hanya permit, tetapi
pencabutan permit dan pencabutan tamu memakai satu penulis yang sama,
`VisitorEfektifService::revokeEfektifRow()`. Membatasi ke permit berarti
menambah percabangan per nama tabel di dalam penulis bersama — persis bentuk
"dua pintu" yang membuat aturan hanya berlaku di satu jalur. Kolomnya sudah
ada di kedua tabel dengan bentuk identik, jadi keduanya ditulis.

**`EXPIRED` tidak dianggap batal.** `ExpireVisitorEfektif` sengaja tidak
menyentuh `Flag_Batal`: kedaluwarsa bukan pembatalan. Barisnya tetap `'T'`.

## Catatan eksekusi

- Development: kedua kolom **sudah ada**. `forward.sql` idempoten, jadi
  menjalankannya di development adalah no-op terverifikasi.
- `data-change.sql` di development diperkirakan menyentuh **11 baris permit +
  76 baris tamu**. Cocokkan dengan hasil pratinjaunya sebelum `UPDATE`.
- Di production kedua tabel masih kosong per audit 2026-09-01, jadi
  `WITH VALUES` tidak memicu pengisian massal.
- Urutan: `forward.sql` lebih dulu, baru `data-change.sql`.

## Berkas

| Peran | Path |
|---|---|
| Forward DDL | `database/ddl/DDL-2026-063-visitor-efektif-flag-batal/forward.sql` |
| Drop (manual saja) | `database/ddl/DDL-2026-063-visitor-efektif-flag-batal/drop.sql` |
| DML pelurusan | `database/dml/DDL-2026-063-visitor-efektif-flag-batal/data-change.sql` |
| Dokumentasi | `docs/changes/DDL-2026-063-visitor-efektif-flag-batal/README.md` |

## Perubahan kode yang menyertai

Semuanya di `app/Services/VISITOR/Operations/Expiration/VisitorEfektifService.php`:

| Method | Perubahan |
|---|---|
| `revokeEfektifRow()` | menulis `Flag_Batal = 'Y'`. Dipakai `revokePermitEfektif()` dan `revokeTamuEfektif()`. |
| `revokePermitEfektifMany()` | menulis `Flag_Batal = 'Y'`. Pintu kedua: punya `UPDATE` sendiri, bukan lewat `revokeEfektifRow()`. |
| `syncPermitEfektif()` | payload menulis `Flag_Batal = 'T'`, kena insert dan update. |
| `syncTamuEfektif()` | sama. |
