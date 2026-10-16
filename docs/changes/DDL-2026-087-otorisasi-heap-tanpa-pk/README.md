# DDL-2026-087 — Dua tabel otorisasi berbentuk heap tanpa primary key

**Status:** REVIEW_REQUIRED — belum dieksekusi di mana pun.

## Temuan

| tabel | baris | bentuk | indeks |
|---|---|---|---|
| `N_HRIS_Role_Menu_Access` | 2.005 | **HEAP** | **nol** |
| `HRIS_Page_Access` | 3.026 | **HEAP** | **nol** |

Terukur di `sys.dm_db_index_usage_stats` untuk `N_HRIS_Role_Menu_Access`:

```
user_seeks = 0    user_scans = 18    user_lookups = 0
```

Nol seek, dan itu bukan kebetulan: tanpa indeks, SQL Server tidak punya cara
lain selain memindai seluruh tabel — termasuk untuk mencari **satu** baris.

## Kenapa unique index-nya penting, bukan sekadar rapi

`SystemAuthController::togglePermission` melakukan cek-lalu-tulis:

```php
$existing = DB::table('N_HRIS_Role_Menu_Access')
    ->where('Id_Page_Access', $validated['id_page_access'])
    ->where('Id_Aksi', $validated['id_aksi'])
    ->where('Flag_Aktif', 'Y')
    ->first();

if ($existing) { …->update(…); } else { …->insert(…); }
```

Tanpa unique index, dua permintaan bersamaan untuk pasangan yang sama sama-sama
melihat "belum ada" dan sama-sama `INSERT`. Hasilnya **dua baris izin aktif**
untuk `(halaman, aksi)` yang sama — dan mana yang menang saat dibaca bergantung
pada urutan pemindaian.

Bandingkan dengan tabel sekerabatnya, `N_HRIS_Role_Resource_Access`, yang
**sudah** punya `UQ_NHRIS_RRA_RoleContextTypeCode` di keempat kolom kuncinya.
Pola kodenya sama persis — yang satu dijaga basis data, yang satu tidak.

## Kenapa indeksnya tersaring (`WHERE Flag_Aktif = 'Y'`)

Kodenya hanya menganggap baris ber-`Flag_Aktif='Y'` sebagai "sudah ada". Unique
index penuh akan menolak `INSERT` ketika ada baris **nonaktif** berpasangan sama
— padahal menurut kodenya itu sah. Indeks tersaring menegakkan persis invarian
yang dipakai kodenya, tidak lebih.

Diperiksa pada data sekarang:

| pemeriksaan | hasil |
|---|---|
| `Id_Role_Menu_Access` unik | **2.005 dari 2.005**, nol NULL |
| `(Id_Page_Access, Id_Aksi)` kembar — seluruh tabel | **0** |
| `(Id_Page_Access, Id_Aksi)` kembar — hanya aktif | **0** |
| `Id_Page_Access` unik di `HRIS_Page_Access` | **3.026 dari 3.026**, nol NULL |

Jadi pembuatannya tidak akan gagal di development.

## Kenapa `HRIS_Page_Access` hanya mendapat PK

Berbeda dengan `N_HRIS_Role_Menu_Access`, belum ada pola cek-lalu-tulis yang
terbukti pada tabel ini. Menambahkan batasan yang tidak dituntut kode adalah
cara membuat kegagalan yang tak seorang pun siap menanganinya, jadi ia hanya
mendapat primary key.

## Apa yang BELUM diketahui

Semua angka dari basis data **pengembangan**. Struktur biasanya sama lintas
lingkungan; **jumlah baris dan ada-tidaknya duplikat tidak**.

**BAGIAN 4 (`SELECT` saja) wajib dijalankan di production lebih dulu.** Bila di
sana `rma_kembar_aktif > 0`, BAGIAN 2 akan gagal dan datanya harus dibereskan
dengan DML tersendiri sebelum skrip ini dijalankan.

## Konteks yang lebih luas

Basis data ini punya **8 tabel berisi >100 baris tanpa primary key**:

```
HRIS_Page_Access             3.026
N_HRIS_Role_Menu_Access      2.005
Loadtest_HP_Backup             965      (sisa uji beban)
N_HRIS_Transaksi_Shift_Det     268
Transaksi_Cuti_Det             184
HRIS_Shift_Kerja_detail2       144
HRIS_Karyawan_Team             108
N_HRIS_Grup_Fitur_Map          103
```

Perubahan ini **hanya menyentuh dua yang pertama** — keduanya tabel otorisasi,
dan salah satunya punya cek-lalu-tulis yang tidak terjaga. Sisanya perlu
dinilai satu per satu: sebagian mungkin memang tabel detail yang wajar tanpa
kunci sendiri, dan `Loadtest_HP_Backup` tampaknya sisa uji beban yang lebih
tepat dibereskan lewat DML, bukan diberi indeks.

## Berkas

- `database/ddl/DDL-2026-087-otorisasi-heap-tanpa-pk/forward.sql`
- `database/ddl/DDL-2026-087-otorisasi-heap-tanpa-pk/drop.sql`

## Urutan pelaksanaan

1. Jalankan **BAGIAN 4** di production (`SELECT` saja) dan bandingkan.
2. Bila bersih: jalankan `forward.sql` di development, verifikasi lewat BAGIAN 4.
3. Catat di SQL Tracker, lalu jalankan di production pada jendela yang tenang —
   menambahkan PK CLUSTERED pada heap berarti menulis ulang tabelnya (kecil di
   sini: 200 KB dan ~700 KB, tetapi tetap mengunci selama berlangsung).
4. Sesudahnya, periksa `user_seeks` pada `N_HRIS_Role_Menu_Access` mulai naik
   dari nol.

`drop.sql` memuat pengembaliannya. **Berkas itu memakai `DROP INDEX` dan karena
itu tidak boleh dieksekusi oleh agen otomatis** — lihat AGENTS.md.
