# DML-2026-100 — Cabut menu Assignment Penilai

| | |
|---|---|
| Change ID | `DML-2026-100-cabut-menu-assignment-penilai` |
| Jenis | DML |
| Artefak | `database/dml/DML-2026-100-cabut-menu-assignment-penilai/data-change.sql` |
| Bergantung pada | DML-2026-093 |
| Perubahan kode penyerta | rute + aksi `halamanPenilai` dicabut; penyunting pindah ke `PaStepPenugasan.vue` |
| Status | `TRACKER_PENDING` — dev DONE, production PENDING |

## Yang diselesaikan

Halaman `/performance-appraisal/penilai` **dibubarkan**. Pekerjaannya pindah ke **Tahap 5**
wizard penyiapan, tab "Peserta": tiap baris punya tombol **Kelola Penilai** yang membuka
penyunting yang sama di tempat.

Sebabnya perjalanan admin. Dulu ia melihat *"peserta ini kurang penilai"* di Tahap 5, lalu
dilempar ke halaman lain, harus menemukan orangnya lagi di daftar sana, menyuntingnya, lalu
kembali — kehilangan tab, saringan level, dan posisi gulirnya. Satu pekerjaan, dua layar.

Rute dan aksi controller-nya sudah dicabut di kode. Butir menu yang tertinggal akan mengajak
admin ke halaman yang tidak ada lagi — **404 pada menu yang tampak sah**.

## Yang TIDAK dicabut

Endpoint API-nya: `usulan-penilai`, `penugasan`, `override/penilai`. Yang dibubarkan
**halamannya**, bukan kemampuannya — penyunting di Tahap 5 memakai endpoint yang sama, dan
komponen `PaAssignmentDialog` dipakai ulang apa adanya, bukan ditulis ulang.

## Dinonaktifkan atau dihapus, dan kenapa berbeda

| Objek | Tindakan | Alasan |
|---|---|---|
| `N_HRIS_Menus` | `Is_Show_Sidebar = 'T'` | Riwayat "kapan ada, kapan hilang" tetap terjawab; `Id_Menu` tidak terbebas untuk dipakai ulang butir lain |
| `N_HRIS_Grup_Fitur_Map` | **DIHAPUS** | Kueri akses role **tidak** menyaring `Flag_Aktif` pada tabel ini — baris yang dimatikan tetap memberi akses. Diuji dan dicatat di DML-2026-099 |
| `HRIS_Page_Access` | `Flag_Aktif = 'T'` | Kalau halamannya dihidupkan lagi, siapa yang tadinya berhak masih bisa dibaca. Hak atas halaman tanpa rute tidak berbahaya |

## Sesudah dijalankan

**Wajib** diikuti `InertiaShellService::flushMenuCache()`.

## Verifikasi di dev

Diverifikasi lewat efek pada menu yang dibangun ulang: header **Konfigurasi Cycle** kini berisi
`Assessment Cycle` saja, dan tidak ada satu pun butir yang alamatnya menunjuk
`/performance-appraisal/penilai`.

Penjaganya juga menuntut header itu **tidak menjadi kosong** — header tanpa butir tampil sebagai
judul yang tidak bisa diklik, dan admin akan menyangka isinya gagal dimuat.

## Cacat yang ketahuan saat memindahkan

`PaAssignmentDialog` memancarkan `save` dan `calculate` **tanpa argumen**, sedangkan halaman
lama menuliskan penangannya `simpanPenugasan({ participantId })` — mendestrukturisasi
`undefined`, yang melempar TypeError sebelum satu baris pun tersimpan. Artinya tombol Simpan di
dialog itu **tidak pernah bisa bekerja** dari halaman yang sekarang dibubarkan.

Cacat itu tidak diwarisi: penangan di Tahap 5 mengambil id dari keadaan dialog, satu-satunya
sumber yang memang tahu peserta mana yang sedang dibuka.
