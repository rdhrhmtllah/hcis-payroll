# DDL-2026-019 — Master entitas perusahaan dan kepemilikan entitas dokumen SOP

| Bidang | Nilai |
|---|---|
| Change ID | `DDL-2026-019-hris-master-entitas` |
| Jenis | DDL |
| Target development | `Web_HRIS` pada `team311.dyndns.info:1433` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |
| Forward | `database/ddl/DDL-2026-019-hris-master-entitas/forward.sql` |
| Drop (manual saja) | `database/ddl/DDL-2026-019-hris-master-entitas/drop.sql` |
| Checksum forward | `273892d8102958529d311934797916536ecae8b25c016f85de2d38df7cec8a52` |
| Desain | `plans/hris/2026-08-22-master-entitas-design.md` |
| Dokumentasi tabel | `docs/database/tables/hris/n-hris-master-entitas.md` |

## Kenapa

`Kode_Perusahaan` hadir di **141 tabel** pada basis data ini dan tidak pernah
punya tabel master; satu-satunya nilai yang hidup adalah `'001'`. Entitas
perusahaan sudah lama "ada" sebagai kolom di mana-mana, tapi tak ada yang bisa
menjawab entitas apa saja yang ada di grup ini.

Bagi dokumen SOP, akibatnya langsung: tidak ada cara menyatakan dokumen ini
milik badan hukum mana dan berlaku di badan hukum mana.

## Objek yang terpengaruh

```text
N_HRIS_Master_Entitas                  (tabel baru, 12 kolom)
N_SOP_Dokumen.Entitas_Pemilik_Id       (kolom baru, int NULL)
N_SOP_Dokumen.Entitas_Lingkup_Id       (kolom baru, int NULL)

UX_HRIS_Entitas_Kode                   (indeks unik)
IX_HRIS_Entitas_Urut                   (indeks)
IX_SOP_Dokumen_Entitas                 (indeks)
IX_SOP_Dokumen_Lingkup                 (indeks)

FK_HRIS_Entitas_Induk                  (foreign key ke tabel sendiri)
FK_SOP_Dokumen_Entitas_Pemilik         (foreign key)
FK_SOP_Dokumen_Entitas_Lingkup         (foreign key)
```

## Keputusan yang perlu dicatat

**Hirarki disimpan sebagai kolom `Induk_Entitas_Id`, bukan flag.** "EHG mencakup
keseluruhan" adalah relasi induk-anak, bukan sifat. Sebagai kolom, pertanyaan
"entitas apa saja yang tercakup EHG" dihitung dari data; sebagai flag
`Is_Holding`, daftar anaknya harus dihafal di kode dan tiap PT baru menuntut
perubahan kode.

**Dua kolom entitas di dokumen, bukan satu.** Pemilik dan ruang lingkup memang
bisa berbeda: dokumen yang dimiliki ENB bisa berlaku se-grup.

**Keduanya nullable.** 41 dokumen lahir sebelum kolomnya ada. Kewajiban mengisi
ditegakkan di pintu manusia (controller), bukan di skema.

**Tidak ada kolom `Kode_Perusahaan` di master ini.** Sempat diusulkan sebagai
jembatan ke kode yatim tersebut, lalu dibatalkan: `HRIS_Divisi` dan
`HRIS_Sub_Divisi` sama-sama bernilai `'001'`, jadi memetakan `'001'` ke satu
entitas akan diam-diam berarti "semua divisi milik entitas itu" — atribusi yang
ditolak eksplisit oleh pemilik keputusan.

**Tidak ada relasi apa pun ke divisi/sub-divisi.** Entitas dan departemen dua
sumbu yang berdiri sendiri.

**Warna disimpan, bukan dihitung.** `constants.js` sudah punya `kategoriTheme()`
yang menghash nama menjadi indeks palet — benar untuk kategori yang warnanya
arbitrer, salah untuk entitas yang warnanya fakta merek dari luar sistem.

## Hasil eksekusi development

Dijalankan 2026-08-22 terhadap `Web_HRIS`; dijalankan ulang untuk membuktikan
idempotensi: **10 batch OK tanpa efek**.

```text
N_HRIS_Master_Entitas    12 kolom, 4 baris setelah seed
N_SOP_Dokumen            2 kolom baru; 41 baris TIDAK berubah
Indeks                   4 dari 4 terbentuk
Foreign key              3 dari 3 terbentuk
```

## Pengujian

`tests/Feature/SYSTEM/MasterData/EntitasTest.php` adalah gerbang skemanya: satu-
satunya bukti otomatis bahwa DDL manual ini sudah dijalankan di basis data yang
sedang dipakai.

## Sisa pekerjaan pengguna

1. Review forward DDL.
2. Catat forward DDL yang sama persis ke SQL Tracker.
3. Jalankan dari SQL Tracker ke production.
4. Verifikasi production, lalu perbarui `database/ddl/status.json`.
