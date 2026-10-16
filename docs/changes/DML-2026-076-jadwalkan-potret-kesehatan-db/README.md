# DML-2026-076 — Jadwalkan potret kesehatan basis data

| | |
|---|---|
| Change ID | `DML-2026-076-jadwalkan-potret-kesehatan-db` |
| Target database | `Web_HRIS` (development) — production menyusul lewat SQL Tracker |
| Jenis | DML saja; **tidak ada DDL, tidak ada tabel baru, tidak ada perubahan kode** |
| Status | `REVIEW_REQUIRED` — belum dieksekusi di mana pun |
| Skrip | `database/dml/DML-2026-076-jadwalkan-potret-kesehatan-db/data-change.sql` |

## Tujuan

Mendaftarkan perintah `db:health-snapshot` ke penjadwal, supaya pertanyaan
"transaksi **mana** yang mengunci" bisa dijawab dengan bukti alih-alih penalaran.

## Kenapa ini ada

Perintahnya sudah dibangun, sudah bergerbang, dan docblock-nya menyebut ia
"OUT-OF-BAND (proses terjadwal terpisah)". Ia tidak pernah didaftarkan.
Akibatnya `N_HRIS_DB_Health_Snapshot` hanya berisi **8 baris dari percobaan
manual** 4 September 2026 — `Context_Info` ketiganya tertulis `KORBAN` dan
SQL-nya `UPDATE N_HRIS_Pengumpulan_Data_Config SET Is_Aktif = Is_Aktif`, yaitu
pernyataan tanpa efek yang memang dipakai untuk memancing kunci.

Selama itu tak berubah, setiap kesimpulan soal lock hanya bisa diturunkan dari
gejala. Dua kesimpulan terakhir memang begitu, dan keduanya bertahan:

- `api/v1/update-performance/store/temporary` menunggu 13,7 detik pada `MAX(...)
  + lockForUpdate` di tabel **124 baris tanpa indeks** — mustahil sebagai
  pemindaian, jadi antrean.
- `api/v1/activities` menunggu 17,4 detik pada `TOP 20` atas join **82 × 109
  baris** — mustahil juga, jadi antrean.

Keduanya masuk akal, dan tak satu pun terbukti. Yang menjawabnya dengan bukti
hanya rantai blocking yang direkam saat kejadian.

## Objek yang tersentuh

| Tabel | Operasi | Perkiraan baris |
|---|---|---|
| `N_HRIS_Scheduler_Configs` | INSERT | 1 |

INSERT-nya idempoten (`IF NOT EXISTS` atas `Command`), jadi menjalankannya dua
kali tidak menggandakan penjadwalan.

## Yang dibayar, dan yang tidak

Tiap menit: satu kunci penjadwal (`withoutOverlapping(15)` + `onOneServer()`,
lewat cache database — sudah berlaku untuk semua entri) plus **satu kueri DMV**.

Perintahnya **bergerbang**: ia menulis hanya bila ada sesi yang benar-benar
terblok, atau jumlah permintaan aktif melewati `DB_HEALTH_SNAPSHOT_ACTIVE_THRESHOLD`
(baku 20). Pada sistem yang sehat ia tidak menulis satu baris pun, dan karena
jalur diamnya tidak mencetak apa-apa, `scheduler.log` juga tidak tumbuh.

**Ini bukan pemantauan realtime.** Pembacaan DMV berulang-ulang yang berbahaya
di produksi adalah Layer 4 (`DbHealthWatch`), yang tetap mati dan tetap dijaga
`GerbangDiagnostikRealtime` dengan tiga kunci. Yang didaftarkan di sini Layer 3,
yang justru dirancang aman untuk produksi.

## Kenapa tiap menit, bukan lebih longgar

Episode yang menyakitkan berumur 10–20 detik. Interval lima menit akan
melewatkan sebagian besar di antaranya, dan tabel yang kosong akan disalahartikan
sebagai "tidak ada masalah kunci" — kesimpulan yang justru berbahaya karena
terdengar seperti bukti.

## Pekerjaan yang tersisa untuk pengguna

1. Tinjau dan jalankan skripnya di development.
2. Biarkan mengumpulkan beberapa hari pemakaian nyata.
3. Baca hasilnya: baris ber-`Blocking_Session_Id <> 0` adalah yang terblok;
   sesi yang muncul sebagai `Blocking_Session_Id` milik orang lain adalah
   penahannya. Bila Layer 2 (`REQUEST_DIAGNOSTICS_TAG_SESSION`) sedang menyala,
   `Context_Info` berisi `Trace_Id` sehingga rantainya bisa ditarik sampai ke
   rute pemanggilnya.
4. Bawa ke production lewat SQL Tracker bila hasilnya di development memuaskan.
