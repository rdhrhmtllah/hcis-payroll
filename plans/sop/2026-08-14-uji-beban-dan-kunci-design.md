# Membuktikan modul SOP tahan banyak orang sekaligus — dan operasi massal tidak mengunci

Tanggal: 14 Agustus 2026

## Pertanyaan yang dijawab

Dua, dan keduanya diajukan dengan kalimat yang berbeda tapi berakar sama:

1. Apakah aman kalau ±80 orang membuka SOP bersamaan?
2. Apakah menugaskan atau mencabut akses puluhan orang sekaligus menimbulkan
   lock yang mengganggu orang lain?

**RCSI mati di `Web_HRIS`.** Artinya pembaca mengantre di belakang penulis. Itu
membuat kedua pertanyaan di atas sebenarnya satu pertanyaan:

> Ketika operasi massal berjalan, apa yang terjadi pada orang yang sedang membaca?

Bukti bahwa ini bukan kekhawatiran teoretis, diambil dari server saat rancangan
ini ditulis:

```
LCK_M_S   3.176.848 ms   ← pembaca menunggu kunci, kumulatif
LCK_M_U   2.719.683 ms
LCK_M_X     247.171 ms
```

`LCK_M_S` adalah penantian kunci **shared** — yaitu pembaca. Lima puluh tiga
menit kumulatif sudah terjadi di server ini.

## Yang TIDAK dijawab rancangan ini

Ditulis di depan supaya tak ada yang salah membaca hasilnya nanti.

- **Throughput lapis web.** Vhost Laragon dibatasi `FcgidMaxProcesses 50`,
  sedangkan produksi memakai Cloud Run yang menggandakan kontainer. Angka
  "berapa permintaan per detik" dari sini tidak berlaku untuk produksi.
- **Saturasi koneksi Cloud SQL.** Insiden "Expired Time" dulu berakar pada
  koneksi Cloud Run ke basis data lewat IP publik. Tak ada di sini yang menirunya.
- **Perilaku kuis pihak ketiga dan kamera.** Di luar lingkup, sama seperti pada
  otomasi UAT.

## Lingkungan

| | |
|---|---|
| Lapis web | Vhost Laragon, `http://hcis-kpi-grouped.test` |
| Basis data | `Web_HRIS` di `team311` — SAMA dengan yang dipakai staging Cloud Run |
| Sesi & cache | `database` (disamakan dengan profil Cloud Run) |
| Prefiks cache | `devlokal_cache_` — WAJIB berbeda dari staging |
| Antrean | `cloudtasks` (apa adanya) |

**Kenapa sesi & cache dipindah ke basis data.** Di Cloud Run keduanya memakai
basis data, sehingga SETIAP permintaan mengerjakan kueri sesi dan cache sebelum
menyentuh satu pun kueri SOP. Membiarkan dev memakai berkas akan mengecilkan
tekanan yang sebenarnya — dan beban per-permintaan itu justru tercatat sebagai
salah satu akar insiden produksi dulu.

**Kenapa prefiks cache harus dipisah.** Tabel `cache` sudah berisi entri milik
staging, termasuk `lms_pdf_page_nonce_consumed_*` — nonce sekali-pakai pembaca
PDF. Uji ini menghantam pembaca dokumen berulang kali; dengan prefiks yang sama,
ia akan melahap nonce milik staging, dan satu `Cache::flush()` di dev akan
menghapus cache staging seluruhnya.

## Bentuk eksperimen

Satu jalan uji, tiga fase berurutan, masing-masing ±60 detik.

| Fase | Yang berjalan | Yang dijawab |
|---|---|---|
| 1 — Dasar | Beban baca saja | Berapa latensi normal? Pembanding untuk fase 2 & 3. |
| 2 — Assign | Beban baca **+** assign 60 karyawan di detik ~20 | Apakah penugasan massal memblokir pembaca? |
| 3 — Cabut | Beban baca **+** `bulkCabut` 60 karyawan di detik ~20 | Apakah pencabutan massal memblokir pembaca? |

Fase 2 dan 3 dibandingkan terhadap fase 1. Menguji beban dan operasi massal
secara terpisah tidak akan menemukan apa pun — dengan RCSI mati, bahayanya lahir
justru dari keduanya bersamaan.

### Bentuk beban baca

Per pengguna maya, berulang selama fase berjalan:

1. `GET /sop/saya` — daftar tugas
2. `GET /sop/{paket}` — detail paket
3. Permintaan pembaca dokumen (token → manifes → satu halaman)

Ditambah 2–3 admin memuat Monitoring, karena riwayat CPU 100% di HCIS ini datang
dari agregasi dashboard, bukan dari halaman karyawan.

### Kenapa `curl_multi`, bukan puluhan proses

Satu proses klien menerbitkan N permintaan serentak. Menyalakan 80 proses PHP di
mesin ini adalah cara tercepat mengulang `STATUS_DLL_INIT_FAILED` yang sudah
tiga kali menghentikan suite hari ini — lalu salah membacanya sebagai temuan.

Beban dinaikkan bertahap: **5 → 20 → 50**. Plafon efektifnya 50
(`FcgidMaxProcesses`). Lima puluh permintaan yang SEDANG BERJALAN jauh lebih
berat daripada 80 orang yang mengklik sesekali — 80 pengguna biasa hampir tak
pernah menghasilkan 80 kueri bersamaan.

## Yang diukur, dan dari mana

Hampir seluruh alat ukur sisi server **sudah terpasang di kode ini**. Yang
dibangun hanya pembangkit beban dan perakit laporan.

| Sumber | Sudah ada | Perannya |
|---|---|---|
| Sisi klien (`curl_multi`) | — | p50 / p95 / maks, non-200, timeout, per fase |
| `N_HRIS_Request_Diagnostics` | ya | Menulis HANYA bila permintaan ≥3 dtk atau 5xx → **daftar korban**, bukan sampel latensi. Membawa `Conn_Acquire_Ms` dan SQL terlambat. |
| `REQUEST_DIAGNOSTICS_TAG_SESSION` | ya (default mati) | Menandai sesi DB dengan `Trace_Id`; membuat "sesi X memblokir sesi Y" bisa ditelusuri ke permintaan HTTP mana. Dinyalakan HANYA selama uji. |
| `db:health-snapshot` | ya | Potret rantai blocking + wait stats. **Dipanggil pembangkit beban tiap detik**, bukan oleh scheduler — dev tidak menjalankan scheduler. |
| `sys.dm_os_wait_stats` | ya | Delta `LCK_M_S` / `LCK_M_U` / `LCK_M_X` per fase. Akses sudah dipastikan ada. |

`LCK_M_S` adalah angka yang paling menentukan: ia pembaca yang menunggu. Kalau
deltanya melonjak di fase 2–3 tapi datar di fase 1, pertanyaannya terjawab
dengan angka.

## Kriteria lulus

Ditetapkan **sebelum** menjalankan. Kriteria yang disusun setelah melihat hasil
hanyalah pembenaran atas apa pun yang kebetulan terjadi.

| # | Kriteria | Dari mana |
|---|---|---|
| 1 | Nol permintaan gagal dan nol timeout di ketiga fase | klien |
| 2 | Nol `Lock request time out` | klien + log |
| 3 | Tidak ada pembaca terblokir >2 detik | `db:health-snapshot` |
| 4 | Satu transaksi potongan massal selesai <5 detik | instrumentasi perintah |
| 5 | p95 baca fase 2 & 3 ≤ 2× p95 fase 1 | klien |
| 6 | Nol baris tersisa setelah pembersihan | `sop:uat-bersihkan` |

Nomor 5 satu-satunya yang angkanya pilihan, bukan turunan sistem. Katup
`SET LOCK_TIMEOUT 10000` (middleware `SetDatabaseLockTimeout`) membuat nomor 2
menjadi sinyal biner: pembaca yang terblokir >10 detik GAGAL dengan pesan tegas,
bukan menggantung diam-diam.

## Keamanan

Kontrak yang sama dengan otomasi UAT, karena basis datanya juga sama:

- Seluruh baris bernama `UAT-AUTO`, tercatat jurnal, dihapus `sop:uat-bersihkan`.
- Assign menyentuh **karyawan sungguhan** — barisnya milik kita (penyisipan, bisa
  dihapus), tapi mereka sempat melihat satu penugasan palsu dan satu
  pemberitahuan. Disetujui pemilik sistem, dan dibersihkan segera setelah uji.
- `REQUEST_DIAGNOSTICS_TAG_SESSION` dimatikan lagi setelah selesai; ia menambah
  satu round-trip per permintaan, yaitu membebani hal yang sedang diukur.
- Beban bertahap dengan penghenti otomatis bila error rate melewati ambang.

## Komponen yang dibangun

1. **`sop:beban-dunia`** — dunia uji ber-jurnal: satu paket terbit berisi
   beberapa item dokumen, ditugaskan ke N karyawan nyata (baku 60, melewati
   ambang async 30). Perluasan `UatPembangunDunia` yang sudah ada, bukan mesin baru.
2. **`sop:beban-jalan`** — pembangkit beban `curl_multi` + orkestrator tiga fase
   + pemanggil `db:health-snapshot` per detik.
3. **`sop:beban-laporan`** — merakit sisi klien, daftar korban, potret blocking,
   dan delta wait menjadi satu markdown.

## Tahap berikutnya (di luar rancangan ini)

Setelah hasil dev dibaca, harness yang sama diarahkan ke **staging Cloud Run**
dengan mengganti `UAT_BASE_URL`. Sesi tetap bekerja karena `SESSION_DRIVER`
di sana juga `database` — barisnya lahir di basis data yang sama. Itulah satu-
satunya tempat pertanyaan lapis web dan saturasi koneksi bisa dijawab.
