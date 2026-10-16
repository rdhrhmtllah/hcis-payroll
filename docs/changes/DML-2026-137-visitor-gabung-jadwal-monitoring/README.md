# DML-2026-137-visitor-gabung-jadwal-monitoring

## Ringkas

**Jadwal Kedatangan Tamu dilebur ke Monitoring Tamu.** Satu menu hilang, dan
izin datanya berpindah dari kunci halaman ke matriks.

| | |
|---|---|
| Change ID | `DML-2026-137-visitor-gabung-jadwal-monitoring` |
| Target | `Web_HRIS` (development); production hanya lewat SQL Tracker |
| Bergantung pada | `DML-2026-136` (matriks untuk role admin) |
| Status | `DEV_EXECUTED` |

## Sebab

Dua menu menjawab pertanyaan yang sama — "tamu apa yang datang" — dengan **dua
mekanisme izin yang berbeda dan tidak saling tahu**:

| | Jadwal Kedatangan Tamu | Monitoring Tamu |
|---|---|---|
| Izin menu | hibah **role** | kunci halaman **per orang** |
| Lingkup data | matriks departemen × kategori | tidak disaring |

Pemakai tidak punya cara menebak mana yang harus dibuka. Lebih buruk, kunci
per orang dihibahkan tangan lalu terlupakan: `visitorJadwalTamuPage` berakhir
**nol pemegang** tanpa ada yang menyadarinya, sementara menunya tetap tampil.

## Yang berubah

| Objek | Perubahan |
|---|---|
| `N_HRIS_Menus.visitorJadwalTamuPage` | `Is_Show_Sidebar` → `T` (disembunyikan, **tidak dihapus**) |
| `N_HRIS_Grup_Fitur_Map.visitorMonitoringTamuPage` | `Flag_Hibah_Role` → `Y` |

Baris menunya **tidak dihapus**: ia juga tempat bergantung hibah grup dan
riwayat akses, dan menghapusnya membuat pemulihan berarti membangun ulang,
bukan menyalakan kembali.

## Kode yang menyertainya

- `LaporanVisitorController`: daftar tamu **dan angka kartunya** disaring
  `MatriksTamuService::saring()`. Keduanya memakai penyaring yang sama —
  daftar dan angka yang berbeda lingkup membuat kartu berkata "12 tamu"
  sementara tabelnya berisi 3.
- **Kunci halaman berhenti menjadi jalan pintas.** Admin tetap melihat semua
  karena `DML-2026-136` memberi `ADMIN_TAMU` ketiga kategori.
- Panel **"Akses Kategori Tamu Anda"** pindah ke Monitoring
  (`AksesKategoriTamuCard`). Tanpa itu, pemakai tanpa hak hanya menemukan
  tabel kosong tanpa sebab — dan kosong yang tidak menjelaskan dirinya selalu
  terbaca sebagai "sistemnya rusak".
- `/jadwal-kedatangan-tamu` **dialihkan 301** ke `/monitoring-tamu`. Endpoint
  datanya sengaja tetap hidup dan tetap berkunci; ia masih dipakai layar lain.

## Siapa yang terdampak

Dari 5 anggota grup Visitor di development, **4 tercakup matriks** dan
**1 belum** (`662` — `HC_OPS_ADMIN` + `ADMIN_PERMIT`). Ia akan melihat kartu
"Belum ada hak" berikut petunjuknya, bukan layar hampa. Memberinya akses cukup
**satu baris matriks** untuk rolenya — tanpa menyentuh kode.

## Riwayat eksekusi development

| Tanggal | Artifact | Hasil |
|---|---|---|
| 2026-09-22 | `data-change.sql` | OK — menu Jadwal `sidebar=T`, Monitoring `hibah_role=Y`. |

Uji: `MonitoringTamuLingkupMatriksTest` 4 lulus (berhak melihat, B2B per unit,
kunci bukan jalan pintas, tanpa hak kosong); Reporting 142 lulus,
Feature/VISITOR 219 lulus, Guest 77 lulus. Dua merah tersisa
(`MonitoringPermitDimensiBaruTest`, `VisitorReportingPageSplitTest`) terbukti
gagal juga sebelum perubahan ini.

## Pekerjaan user

- Rekam di SQL Tracker; urutan `DML-2026-136` lalu change ini.
- **Periksa production lebih dulu**: pastikan pemegang kunci
  `visitorMonitoringTamuPage` di sana juga pemegang `ADMIN_TAMU`. Bila tidak,
  mereka kehilangan akses saat penyaringan matriks menyala.
