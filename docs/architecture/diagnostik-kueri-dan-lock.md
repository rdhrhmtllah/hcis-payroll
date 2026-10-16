# Diagnostik kueri & lock (khusus pengembangan)

Alat untuk menjawab tiga pertanyaan yang berbeda, dan sengaja dipisah karena
tidak ada satu sumber pun yang bisa menjawab ketiganya.

| pertanyaan | alat | sumber |
|---|---|---|
| siapa mengunci siapa **saat ini** | Pantau Langsung | DMV, dibaca saat diminta |
| kueri mana yang **paling mahal** | Kueri Terberat | `sys.dm_exec_query_stats` |
| **fitur mana** yang menjalankannya | Rekam Sesi Uji | `DB::listen` di sisi aplikasi |

Ketiganya di `/admin/activity` → tab **Kesehatan Sistem**.

## Kenapa tiga, bukan satu

Masing-masing buta terhadap yang lain, dan kebutaannya tidak bisa ditambal:

- **Potret DMV** hanya melihat yang sedang berjalan pada satu detik. Kueri yang
  selesai dalam 30 ms tetapi dijalankan 2.000 kali hampir tak pernah
  tertangkap — padahal gabungan itulah yang menghabiskan CPU dan koneksi.
- **`dm_exec_query_stats`** tahu sebuah kueri mahal dan sering, tetapi sama
  sekali tidak tahu endpoint mana pemanggilnya. Yang ingin diperbaiki orang
  adalah fiturnya, bukan kalimat SQL-nya.
- **Sisi aplikasi** tahu rutenya, tetapi tidak melihat lock, tidak melihat
  sesi lain, dan tidak tahu berapa halaman yang dibaca server.

## Gerbang tiga kunci

Membaca DMV berulang-ulang murah di dev dan **merusak di produksi**: tiap
sampel memakai satu koneksi dari pool yang sama yang sedang habis, jadi ia
menambah beban tepat pada saat sistemnya paling rapuh — persis keadaan yang
sedang ia coba jelaskan.

`GerbangDiagnostikRealtime` menuntut **ketiganya** setuju:

1. `app.env` ada di `request-diagnostics.realtime.lingkungan`
2. `DB_REALTIME_WATCH_ENABLED=true` — **baku MATI**
3. host DB tidak cocok pola di `host_terlarang` (`prod`, `cloudsql`, …)

Kunci ketiga ada karena dua yang pertama sama-sama env var: satu salah set saat
deploy meruntuhkan keduanya sekaligus. Host DB datang dari sumber lain.

## Cara memakai

### Menyisir fitur dengan tangan

```
# .env dev
DB_REALTIME_WATCH_ENABLED=true
REQUEST_DIAGNOSTICS_TAG_SESSION=true    # perlu untuk atribusi lock ke rute
```

1. `/admin/activity` → Kesehatan Sistem
2. **Tandai mulai uji** (panel Kueri Terberat) dan **Mulai rekam** (panel Rekam Sesi Uji)
3. Jalankan fiturnya
4. **Berhenti** → **Muat hasil**

### Membiarkan suite uji yang menyisir

Jauh lebih luas, dan tidak bocor — menyisir dengan tangan membuat yang terlewat
tidak ketahuan terlewat.

```
php artisan diagnostik:rekam mulai
DIAGNOSTIK_REKAM_UJI=true php artisan test
php artisan diagnostik:rekam berhenti
php artisan diagnostik:laporan --simpan
```

Sesinya sengaja **tidak** dimulai otomatis oleh uji: kalau tiap berkas uji
memulai sesinya sendiri, tiap berkas menimpa yang sebelumnya.

### Memantau lock selama pengujian

```
php artisan db:health-watch --detik=300 --interval=2
```

Merekam **tiap** sampel tanpa ambang, ke `N_HRIS_DB_Health_Snapshot`. Berbeda
dari `db:health-snapshot`, yang sekali jalan dan berambang ≥20 permintaan aktif
— ambang yang tak pernah tersentuh dalam uji berisi dua orang, sehingga ia diam
persis pada keadaan yang ingin diukur.

## Biaya, terukur

| | |
|---|---|
| perekaman per uji | **0,27 ms** |
| satu round-trip ke DB dev | **126–142 ms** |
| nisbah | **0,22% dari satu kueri** |

Untuk 1.500 uji: 0,4 detik total. Tidak layak dioptimalkan lebih jauh.

Satu sampel Pantau Langsung ~1,5 detik, dan itu **hampir seluruhnya latensi** —
`SELECT 1` polos saja 126 ms ke DB dev yang remote. Karena itu denyut panelnya
5 detik: denyut yang lebih rapat daripada biaya satu sampel berarti pemantaunya
tak pernah selesai sebelum diminta lagi.

## Di mana datanya

| bagian | tempat | bertahan? |
|---|---|---|
| lock (`db:health-watch`) | `N_HRIS_DB_Health_Snapshot` | ya |
| rekaman sesi uji | `storage/app/diagnostik/rekaman-*.jsonl` | ya |
| penunjuk sesi | `storage/app/diagnostik/sesi-*.txt` | ya |
| garis dasar kueri | cache | selama sesi |
| Pantau Langsung | — | tidak, hanya tampilan |

**Berkas, bukan tabel**, untuk rekaman sesi uji. Tiga alasan: menulis ke basis
data yang sedang diukur berarti mengukur diri sendiri; tabel baru menuntut DDL
untuk data yang hidup beberapa menit; dan penambahan baris ber-`LOCK_EX` aman
terhadap permintaan bersamaan, sedangkan akumulasi di cache tidak — dua
permintaan serentak saling menimpa, tepat saat beban paling ramai.

Penunjuk sesi juga berkas: `phpunit.xml` memasang `CACHE_DRIVER=array`, jadi
sesi yang dimulai proses artisan tak akan pernah terlihat oleh proses uji.

Yang disimpan hanya SQL **berparameter**; bindings tidak ikut, jadi tak ada
nilai milik orang yang tertulis.

## Ambang penilaian

Tertulis di `PenilaiTemuanDiagnostik`, dan disebutkan ulang pada tiap temuan —
ambang yang disembunyikan membuat orang berdebat tentang angkanya alih-alih
tentang masalahnya.

| jenis | ambang |
|---|---|
| N+1 | kueri sama ≥5× **per permintaan** |
| kueri berlebih | ≥30 kueri dalam satu permintaan |
| scan | ≥5.000 logical reads sekali jalan |
| kueri lambat | rata-rata ≥500 ms |
| gagal ambil koneksi | ≥3.000 ms **tanpa satu kueri pun** |

N+1 dinilai per permintaan, bukan dari total: lima permintaan yang
masing-masing menjalankannya sekali bukan N+1 meski totalnya lima.

## Yang TIDAK bisa

- **Vitest tidak ikut, dan tidak bisa.** Ia berjalan di jsdom tanpa PHP dan
  tanpa koneksi basis data. Playwright ikut sendirinya karena memukul aplikasi
  lewat HTTP.
- **`dm_exec_query_stats` tidak lengkap.** Kueri ber-`OPTION(RECOMPILE)` atau
  yang rencananya tak di-cache tidak muncul, dan hitungannya hilang saat
  rencananya tergusur atau server restart.
- **Cakupannya seluruh server.** Kalau orang lain memakai dev bersamaan,
  kuerinya ikut terhitung. Kueri katalog Management Studio sudah disaring, tapi
  kueri aplikasi milik orang lain tidak bisa dibedakan.
