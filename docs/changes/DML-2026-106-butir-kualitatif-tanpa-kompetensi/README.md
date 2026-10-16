# DML-2026-106 — Butir uraian yang lahir tanpa kompetensi

| | |
|---|---|
| Change ID | `DML-2026-106-butir-kualitatif-tanpa-kompetensi` |
| Jenis | DML |
| Artefak | `database/dml/DML-2026-106-butir-kualitatif-tanpa-kompetensi/data-change.sql` |
| Bergantung pada | DDL-2026-088 — **wajib dijalankan sesudahnya** |
| Status | `TRACKER_PENDING` — dev DONE, production PENDING |

## Masalah yang diselesaikan

Enam belas butir uraian lahir tanpa `PA_Competency_Id`. Butir yang tidak menunjuk kompetensi
tidak bisa ditempatkan di panel mana pun: jawabannya tersimpan lengkap di
`N_PA_Assignment_Score`, tetapi tidak pernah sampai ke report peserta.

Sampai DDL-2026-088, kekosongan itu ditutupi jalan pintas — perannya dicari lewat
`PA_Result_Note_Type_Id` pada butirnya. Jalan pintas itu **menaruhnya di tempat yang salah**,
dan buktinya ada pada data ini sendiri:

| Butir | Menunjuk | Artinya di report |
|---|---|---|
| QL03 Initiative | jenis catatan 1 | tampil sebagai **Kekuatan** |
| QL04 Recommendation | jenis catatan 2 | tampil sebagai **Area Pengembangan** |

Jawaban tentang inisiatif seseorang terbaca sebagai kekuatannya, dan usulan tindak lanjut
terbaca sebagai kekurangannya. Tidak ada galat — hanya kalimat yang benar di bawah judul yang
salah, dan tidak seorang pun punya alasan untuk mencurigainya.

## Keputusan bentuk

**Dicocokkan lewat nama yang tersimpan, bukan awalan kode.** `Kode_Soal` memang berbentuk
`QL03-01-PEER`, dan memotong empat huruf pertamanya akan memberi jawaban yang benar untuk
keenam belas baris ini. Itu tetap tidak dilakukan: bentuk kode itu kebiasaan katalog HC Learn,
bukan aturan yang dijamin siapa pun, dan penambang awalan akan diam-diam salah pada katalog
yang menomori soalnya dengan cara lain. Yang dipakai `Nama_Soal_Sumber` — nilai yang
benar-benar ada di barisnya, dan pada data ini sama persis dengan `Nama_Competency`.

**Nama yang tidak tunggal tidak dipetakan.** Bila satu nama dipakai lebih dari satu kompetensi
kualitatif aktif, barisnya dilewati dan tetap kosong. Memilih salah satunya berarti menebak,
dan tebakan yang salah di sini tidak meninggalkan jejak apa pun di layar.

**Hanya kompetensi kualitatif yang dipertimbangkan.** Membuka padanan ke seluruh master berarti
satu nama yang kebetulan sama membuat butir uraian ikut dirata-ratakan sebagai angka.

**Penunjuk lamanya dikosongkan.** Berbeda dari `N_PA_Result_Note`, yang penunjuk lamanya
sengaja dibiarkan sebagai jejak, di sini nilainya terbukti salah — menyimpannya hanya menyimpan
kekeliruan. `CK_PA_Cycle_Question_Item_Peran` juga melarang satu butir memegang kompetensi dan
jenis catatan sekaligus, jadi mengisi yang baru tanpa mengosongkan yang lama akan ditolak
basis data.

**Penjaganya melaporkan, tidak menggagalkan.** Butir berskala yang memang belum dipetakan admin
juga masuk hitungan "tanpa kompetensi", dan itu keadaan sah — pemetaannya pekerjaan admin di
layar master, bukan urusan skrip ini.

## Verifikasi di dev

Dijalankan pada `Web_HRIS`. 16 butir dan **504 baris** `N_PA_Assignment_Score` terisi; sesudahnya
`butir aktif tanpa kompetensi: 0` dan `jawaban aktif tanpa kompetensi: 0`. Dijalankan dua kali —
jalan kedua tidak mengubah apa pun.

Diverifikasi lewat **efeknya**, bukan lewat jumlah barisnya saja: peserta 671313 yang sebelumnya
tertahan dengan alasan *"Jenis kompetensi wajib tidak tercakup penilaian: Qualitative Question"*
menjadi `DIHITUNG`, dan keempat dimensi kualitatifnya kini berada di panel yang benar —
Initiative sebagai Initiative, bukan sebagai Kekuatan.

## Rollback

Tidak ada skrip pembalik, dan itu disengaja. Yang dikembalikan hanya bisa berupa keadaan
"kolomnya kosong lagi" — yaitu keadaan cacat yang justru diperbaiki di sini. Bila pemetaannya
ternyata keliru untuk suatu baris, perbaikannya menyetel `PA_Competency_Id` baris itu ke
kompetensi yang benar, bukan mengosongkan seluruhnya.
