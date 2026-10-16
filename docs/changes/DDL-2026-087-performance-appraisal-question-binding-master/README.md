# DDL-2026-087 — Master ikatan paket soal per level × penilai

| | |
|---|---|
| Change ID | `DDL-2026-087-performance-appraisal-question-binding-master` |
| Jenis | DDL |
| Forward | `database/ddl/DDL-2026-087-performance-appraisal-question-binding-master/forward.sql` |
| Rollback | `.../drop.sql` |
| Bergantung pada | DDL-2026-086 (paket per kategori penilai), DDL-2026-075 (`N_PA_Rater_Category`) |
| Status | `TRACKER_PENDING` — dev DONE, production PENDING |

## Masalah yang diselesaikan

Pengikatan paket soal hanya ada per siklus. Setiap siklus baru, admin mengisi ulang seluruh
matriks dari nol — pada data sekarang 20 sel, dan bertambah setiap kali ada level atau
kategori penilai baru. Pekerjaan yang sama, diulang, dengan peluang salah yang sama besar
tiap kali.

Tabel ini menyimpan pilihan bakunya. Siklus baru menyalinnya, lalu admin hanya menyesuaikan
yang berbeda.

## Keputusan bentuk

**Yang disimpan adalah pilihan, bukan isi paketnya.** Master menyimpan kode paket HC Learn,
bukan salinan butirnya. Butir yang benar-benar dipakai selalu diambil dari HC Learn saat
penyalinan, karena penilaian berjalan memakai soal yang **ada di sana** — bukan yang pernah
kita catat.

**`Sidik_Susunan`, karena HC Learn bisa berubah tanpa memberi tahu.** Paket yang tahun lalu
memuat sebelas kompetensi bisa tinggal sembilan, skalanya bisa bergeser dari 1–5 ke 1–4, atau
paketnya dihapus. Semua itu terjadi diam-diam di sisi mereka. Master menyimpan SHA-256 atas
seluruh pasangan `(Kode_Soal, Kode_Kompetensi, Jenis_Soal, Skor_Maksimal, Bobot)` yang
terurut; sidik yang berbeda berarti sesuatu bergeser, dan admin diberi tahu **sebelum**
menyalinnya ke siklus, bukan setelah penilaian berjalan.

**Sidiknya atas susunan, bukan teks pertanyaan.** Teks soal tidak pernah masuk ke HCIS, dan
tidak perlu: yang mengubah arti nilai adalah komposisi dan skalanya. Meminta teksnya dari HC
Learn justru menciptakan kebocoran soal.

## Verifikasi di dev

Dijalankan pada `Web_HRIS`. Diverifikasi lewat efek: mengubah susunan paket di simulator HC
Learn membuat sel yang terkait berubah menjadi "susunan bergeser" saat diverifikasi ulang,
dan `verifikasiUlang()` sengaja **tidak** menyegarkan sel bermasalah — pergeserannya tetap
terlihat sampai admin memutuskan.

Cacat yang ditemukan dan diperbaiki sebelum ditutup: penyalinan ke siklus sempat menulis 52
sel padahal hanya 45 yang sah, karena tujuh di antaranya untuk kategori penilai yang memang
tidak menilai level itu.

## Rollback

`drop.sql` membuang tabelnya. Siklus yang sudah menyalin ikatan tidak terpengaruh — salinannya
hidup di `N_PA_Cycle_Question_Package`, dan itu memang disengaja: master adalah cetakan,
snapshot adalah yang berlaku.
