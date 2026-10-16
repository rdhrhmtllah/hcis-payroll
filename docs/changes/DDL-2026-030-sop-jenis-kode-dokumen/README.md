# DDL-2026-030 — Kode jenis dokumen untuk nomor dokumen gabungan

| Bidang | Nilai |
|---|---|
| Change ID | `DDL-2026-030-sop-jenis-kode-dokumen` |
| Jenis | DDL |
| Target development | `Web_HRIS` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |
| Forward | `database/ddl/DDL-2026-030-sop-jenis-kode-dokumen/forward.sql` |
| Drop (manual saja) | `database/ddl/DDL-2026-030-sop-jenis-kode-dokumen/drop.sql` |

## Isi

```text
N_SOP_Master_Jenis_Dokumen.Kode_Jenis_Dokumen  varchar(10) NULL
UX_SOP_Jenis_Kode_Jenis_Dokumen                UNIQUE, filtered (WHERE ... IS NOT NULL)
```

## Dua kode, dua peran

| Kolom | Menjawab | Umur | Boleh disunting admin |
|---|---|---|---|
| `Kode_Jenis` | "baris mana yang dimaksud" — kunci mesin bagi seed, test, integrasi | selamanya tetap | tidak |
| `Kode_Jenis_Dokumen` | "apa yang tercetak di dokumen" — identitas bisnis | ikut keputusan bisnis | **ya** |

Memakai ulang `Kode_Jenis` untuk keduanya memaksa memilih salah satu sifat dan
mengorbankan yang lain: entah admin tak bisa menyunting kodenya sama sekali,
atau setiap suntingan admin diam-diam memutus seed dan test yang mencari baris
lewat kode itu.

Ini juga alasan kolomnya tidak mengikuti pola "kode terkunci setelah dibuat"
seperti `N_HRIS_Master_Departemen.Kode_Departemen`. Di sana kodenya memang
satu-satunya identitas; di sini kunci mesinnya sudah dipegang kolom lain, jadi
kode cetak bebas berubah tanpa memutus apa pun.

## Kenapa filtered index, bukan unique index biasa

SQL Server menganggap seluruh `NULL` **sama** di unique index. Dengan unique
index biasa, baris kedua yang kode cetaknya belum diisi akan ditolak — padahal
kolomnya memang lahir kosong dan sebagian jenis boleh menunggu kepanjangan
resminya ditetapkan.

Filtered index menjaga yang benar-benar penting: dua jenis tak boleh memakai
kode yang sama, karena `SOP/ENB/…` lalu tak bisa dibaca kembali menjadi satu
jenis tertentu — nomornya jadi ambigu.

## Nullable, dan tetap nullable

Jenis yang belum punya kode cetak tetap sah disimpan sebagai kerangka. Yang
dijaga adalah **pemakaiannya**: `SopNomorDokumen::rakit()` mengembalikan `null`
begitu ada satu segmen yang kosong, jadi nomor lengkap memang tak pernah terbit
setengah jadi.

## Catatan eksekusi

Dijalankan di `Web_HRIS` (development): **3 batch OK**.

Kolomnya sempat lahir bernama `Kode_Cetak` lalu dikoreksi menjadi
`Kode_Jenis_Dokumen` lewat `sp_rename` — **sebelum** perubahan ini meninggalkan
development, dan tanpa kehilangan data. Berkas `forward.sql` di sini sudah
bernama final: dijalankan di environment bersih, ia langsung membentuk nama yang
benar tanpa perlu koreksi apa pun.

## Rollback

`drop.sql` **tidak pernah dijalankan agen**. Menjalankannya menghapus kode cetak
seluruh baris master, dan setiap nomor dokumen yang sudah terbit kehilangan
segmen pertamanya.
