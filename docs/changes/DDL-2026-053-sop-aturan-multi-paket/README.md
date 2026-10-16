# DDL-2026-053 — Satu aturan otomasi menampung banyak paket

| | |
|---|---|
| Change ID | `DDL-2026-053-sop-aturan-multi-paket` |
| Jenis | DDL |
| Target development | `Web_HRIS` (host team311) |
| Status | `DEV_EXECUTED` — SQL Tracker & produksi masih `PENDING` |
| Checksum forward (sha256) | `f2055fcb95012d8f8fe95c31e2d127037bdf2ab3b59b8e312eb1d4b84c3d7a00` |
| Dieksekusi ke development | 2026-08-27 |

## Path

- Forward: `database/ddl/DDL-2026-053-sop-aturan-multi-paket/forward.sql`
- Drop (manual, **jangan dijalankan agen**): `database/ddl/DDL-2026-053-sop-aturan-multi-paket/drop.sql`
- Desain: `plans/sop/2026-08-27-otomasi-multi-paket-design.md`
- Rencana: `plans/sop/2026-08-27-otomasi-multi-paket-plan.md`

## Objek terdampak

| # | Objek | Perubahan | Tipe | Null |
|---|---|---|---|---|
| 1 | `N_SOP_Assignment_Rule_Paket` | tabel baru, 18 kolom | — | — |
| 1 | `UX_SOP_Assignment_Rule_Paket_Paket` | index unik terfilter `WHERE Flag_Aktif='Y'` | — | — |
| 1 | `IX_SOP_Assignment_Rule_Paket_Rule` | index `(Id_SOP_Assignment_Rule, Urutan)` | — | — |
| 2 | `N_SOP_Automation_Run` | + `Id_SOP_Paket` | `int` | YES |
| 2 | `N_SOP_Automation_Run` | + `Id_Run_Induk` | `int` | YES |
| 2 | `IX_SOP_Automation_Run_Induk` | index terfilter `WHERE Id_Run_Induk IS NOT NULL` | — | — |
| 3 | `UQ_N_SOP_Assignment_Rule_Paket` | **DILEPAS** — UNIQUE CONSTRAINT pada `N_SOP_Assignment_Rule.Id_SOP_Paket` | — | — |
| 3 | `N_SOP_Assignment_Rule` | `Id_SOP_Paket` NOT NULL → NULL | `int` | YES |
| 4 | `N_SYSTEM_Database_Change` | 1 baris pencatatan | — | — |

Kolom `#` adalah nomor bagian di `forward.sql`. Urutannya disengaja — lihat
"Urutan eksekusi" di bawah.

Tidak tersentuh: kolom `N_SOP_Assignment_Rule.Id_SOP_Paket` itu sendiri (tetap
ada), `FK_N_SOP_Rule_Paket`, `IX_N_SOP_Rule_Status`, dan seluruh baris data.

## Kenapa

`N_SOP_Assignment_Rule` mengikat satu aturan penugasan otomatis ke tepat satu
paket lewat kolom `Id_SOP_Paket`. Kebutuhan nyatanya berlawanan arah: satu
cohort — misalnya Divisi Finance yang dikerucutkan departemen, level, dan lokasi
kerja — wajib membaca lima paket SOP sekaligus.

Dengan bentuk lama, lima paket berarti lima aturan terpisah yang masing-masing
mengulang kriteria cohort yang sama persis. Kriterianya tersalin lima kali, dan
begitu cakupan Finance berubah, kelimanya harus disunting satu per satu tanpa
ada apa pun yang menjamin mereka tetap identik.

### Kenapa tabel jembatan, bukan daftar di `Draft_Config_JSON`

Config aturan sudah berbentuk JSON dan sudah punya normalizer, jadi menaruh
daftar paket di sana tidak akan menuntut DDL sama sekali. Yang menggagalkannya
adalah penjaganya.

Keputusan desain menetapkan bahwa satu paket hanya boleh diklaim satu aturan,
dan larangan itu adalah **satu-satunya** pengaman terhadap tumpang tindih —
mesin pencabutan sengaja dibiarkan tak tahu apa pun tentang aturan lain. Daftar
di dalam JSON berarti penjaga itu harus membaca dan mem-parse JSON seluruh
aturan setiap kali menyimpan, tanpa index yang bisa menjaganya, dan bisa dibobol
dua penyimpanan bersamaan.

`UX_SOP_Assignment_Rule_Paket_Paket` memindahkan penjaga itu ke mesin basis
data.

### Kenapa `UQ_N_SOP_Assignment_Rule_Paket` WAJIB dilepas

Constraint itu adalah penjaga "satu paket satu aturan" versi lama, di tingkat
basis data. Melepasnya bukan pilihan gaya — ia mematikan seluruh perubahan ini
kalau dibiarkan.

Mulai DDL ini, `Id_SOP_Paket` pada tabel aturan berhenti diisi, sehingga setiap
aturan baru bernilai `NULL` di sana. **SQL Server memperlakukan NULL sebagai
nilai yang bentrok dengan NULL pada UNIQUE CONSTRAINT: hanya satu baris NULL
yang diizinkan.** Selama constraint itu berdiri, tabel aturan hanya bisa memuat
satu baris di seluruh sistem — bukan satu per paket, melainkan satu, titik.
Terbukti langsung di development sebelum dilepas:

```
aturan ke-1 tanpa Id_SOP_Paket: DITERIMA
aturan ke-2 DITOLAK -> Violation of UNIQUE KEY constraint 'UQ_N_SOP_Assignment_Rule_Paket'
```

Filtered unique index penggantinya tidak punya jebakan itu: baris di luar filter
tidak pernah dibandingkan satu sama lain.

### Kenapa kolom override tenggat ada di tabel jembatan

Setelan tenggat induk berlaku untuk semua paket, tetapi tiap paket boleh
menyalakan tenggatnya sendiri — SOP induk 7 hari, turunannya 30 hari. Nilai
override itu bagian dari definisi aturan, bukan hasil penerapannya, jadi ia
tinggal bersama baris yang mendefinisikannya.

Hasil penerapannya tetap mendarat di `N_SOP_Penjadwalan`, yang memang sudah
menyimpan `Tanggal_Deadline`, `Flag_Reminder`, dan `Irama_Reminder_Hari` di
tingkat header dan ber-`Id_SOP_Paket` NOT NULL. Satu aturan lima paket
menghasilkan lima header, masing-masing boleh berbeda jadwalnya — tak ada kolom
baru yang dibutuhkan untuk menyimpan hasil.

### Kenapa `Last_Apply_Status` per paket

Tanpanya, kegagalan hanya bisa dinyatakan di tingkat aturan. Kalau paket ketiga
dari lima gagal, admin hanya melihat "aturan gagal" tanpa tahu paket mana yang
berhenti — padahal empat lainnya sudah tersemai dengan benar.

### Kenapa `Id_SOP_Paket` tidak ikut di-drop

`AGENTS.md` melarang agent mengeksekusi ALTER yang membuang data. Kolom itu
hanya dijadikan nullable supaya INSERT baru tak lagi menuntutnya; tak ada kode
yang membacanya setelah perubahan ini. Statement drop-nya tersedia di `drop.sql`
untuk dijalankan manual bila diinginkan.

Pelepasan `UQ_N_SOP_Assignment_Rule_Paket` dijalankan agent atas **persetujuan
eksplisit pengguna pada 2026-08-27**, setelah ditunjukkan bahwa yang dibuang
adalah aturan keunikan beserta index penopangnya, bukan kolom maupun data.

## Urutan eksekusi

Empat bagian `forward.sql` berurutan dengan sengaja, supaya aman dijalankan ke
produksi tanpa saling tumpang tindih:

1. **Penjaga baru berdiri lebih dulu** — tabel jembatan beserta filtered unique
   index-nya.
2. **Kolom riwayat ditambahkan** — mandiri, tak bergantung pada bagian lain.
3. **Penjaga lama baru dilepas** — DROP CONSTRAINT, lalu `ALTER COLUMN` menjadi
   nullable. `ALTER COLUMN` harus sesudah DROP: SQL Server menolak mengubah
   kolom yang masih ditopang unique constraint.
4. **Pencatatan ke dashboard paling akhir** — perubahan hanya tercatat bila
   seluruh langkah di atasnya lolos.

Urutan ini tak pernah meninggalkan jeda ketika basis data berjalan tanpa penjaga
klaim paket sama sekali. `drop.sql` menempuh urutan kebalikannya dengan alasan
yang sama.

Seluruh statement idempoten (`IF EXISTS` / `IF NOT EXISTS`), jadi berkas ini
aman dijalankan ulang — termasuk pada basis data yang sudah menerima sebagiannya.

## Pekerjaan yang diselesaikan agent

- Menulis `forward.sql` dan `drop.sql`
- Menjalankan `forward.sql` ke development (`Web_HRIS`) dan memverifikasi
  hasilnya lewat `INFORMATION_SCHEMA`, `sys.objects`, dan `sys.indexes`
- Membuktikan di dalam transaksi yang dibatalkan bahwa: tiga aturan tanpa
  `Id_SOP_Paket` kini diterima; klaim kedua atas satu paket ditolak oleh
  `UX_SOP_Assignment_Rule_Paket_Paket`; dan klaim itu diterima kembali setelah
  pemilik lamanya melepas (filter `Flag_Aktif` bekerja)
- Memastikan `FK_N_SOP_Rule_Paket` dan `IX_N_SOP_Rule_Status` tetap utuh setelah
  constraint dilepas
- Mencatat perubahan di `N_SYSTEM_Database_Change` dan `database/ddl/status.json`

## Pekerjaan yang tersisa untuk pengguna

- Meninjau dan menyetujui perubahan
- Mengeksekusi forward DDL ke produksi lewat SQL Tracker
- Memverifikasi hasilnya di produksi
- Opsional: menjalankan bagian 4 `drop.sql` untuk membuang kolom
  `N_SOP_Assignment_Rule.Id_SOP_Paket` yang kini yatim

## Catatan untuk perubahan berikutnya

Constraint `UQ_N_SOP_Assignment_Rule_Paket` tidak terlihat sama sekali dari kode
PHP — penjaga yang sama sudah ditulis ulang sebagai pemeriksaan di
`SopAssignmentRuleService::saveDraft()`, sehingga membaca kode memberi kesan
tidak ada apa-apa di tingkat basis data. Ia juga tidak muncul saat memeriksa
`INFORMATION_SCHEMA.COLUMNS`.

Uji penjaga yang pertama kali dijalankan atas perubahan ini sempat "lulus"
karena alasan yang salah: insert **pertama**-nya yang ditolak, oleh constraint
yang sama sekali lain, dan pesan sukses uji tetap tercetak. Sebelum mengubah
tabel lama mana pun, periksa `sys.objects` dan `sys.indexes`-nya — bukan hanya
kolomnya — dan pastikan setiap langkah prasyarat sebuah uji penjaga benar-benar
berhasil sebelum menafsirkan penolakan yang menyusul.
