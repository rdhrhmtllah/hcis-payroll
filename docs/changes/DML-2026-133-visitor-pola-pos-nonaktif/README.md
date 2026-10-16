# DML-2026-133-visitor-pola-pos-nonaktif

## Ringkas

Enam baris pola jadwal inspeksi menunjuk pos yang sudah nonaktif, dan itu
membuat **seluruh** pengaturan pola gagal disimpan.

| | |
|---|---|
| Change ID | `DML-2026-133-visitor-pola-pos-nonaktif` |
| Target | `Web_HRIS` (development); production hanya lewat SQL Tracker |
| Status | `DEV_EXECUTED` |

## Gejala

Menekan **Simpan** di `/agenda-inspeksi` → tab Pengaturan Slot membalas:

> The selected pos inspeksi is invalid.

Pesannya menunjuk baris yang bahkan tidak sedang disunting, dan buntu itu
**tidak bisa dibereskan dari layar**: baris yang memblokir justru tidak bisa
diperbaiki tanpa mengaktifkan kembali posnya lebih dulu.

## Sebab

`simpanPola()` memvalidasi seluruh payload sekaligus dengan aturan
`Rule::exists(pos)->where('Flag_Aktif', 'Y')`. Sementara itu
`ubahStatusPos()`/`ubahStatusShift()` hanya menonaktifkan masternya dan
membiarkan pola yang menunjuknya tetap `Flag_Aktif='Y'`.

Pola yang tertinggal itu sepintas tak berbahaya — `slotRentang()` hanya
membangun slot dari pos dan shift aktif, jadi ia tidak menghasilkan apa pun.
Yang sebenarnya terjadi lebih buruk: ia tetap ikut dikirim dan divalidasi,
lalu menolak seluruh penyimpanan.

## Perbaikan

| Bagian | Perubahan |
|---|---|
| `AgendaInspeksiController::ubahStatusPos` | Menonaktifkan pos ikut memensiunkan pola yang menunjuknya; jumlahnya dilaporkan di pesan sukses. |
| `AgendaInspeksiController::ubahStatusShift` | Sama untuk shift — pintu kedua dengan penanganan sendiri. |
| `data-change.sql` | Membereskan baris yang terlanjur ada. Idempoten. |

Penjaganya `NonaktifPosMemensiunkanPolaTest`: dua pintu diuji terpisah, plus
satu uji yang menagih **keadaan** (tidak ada pola aktif menunjuk master
nonaktif) supaya jalur ketiga yang menonaktifkan master lewat cara lain ikut
ketahuan.

## Riwayat eksekusi development

| Tanggal | Artifact | Hasil |
|---|---|---|
| 2026-09-22 | `data-change.sql` | OK — 6 pola (5 milik "Pos Utama" shift 1, 1 shift 2) dipensiunkan; kueri verifikasi 0. |

## Pekerjaan user

- Rekam di SQL Tracker.
- Jalankan kueri verifikasi di kaki artefak pada production lebih dulu; kalau
  hasilnya bukan nol, admin di sana sedang mengalami buntu yang sama.
