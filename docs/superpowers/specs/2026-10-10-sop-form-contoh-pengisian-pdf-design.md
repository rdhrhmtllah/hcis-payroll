# Contoh pengisian FORM: wajib PDF, izin unduh diatur per paket

Tanggal: 10 Okt 2026. Status: disetujui user di obrolan, langsung dieksekusi.

## Masalahnya

Dokumen FORM punya dua berkas: berkas utama (contoh pengisian) dan lampiran
form kosong (DDL-2026-158). Contoh pengisian selama ini boleh berformat apa
saja dan SELALU bisa diunduh — `SopAksesBerkas::bolehUnduh()` mengecualikan
FORM dari `Mode_Akses`. Admin tidak bisa menahan contoh pengisian agar hanya
dibaca.

## Keputusan (user, 10 Okt 2026)

| Pertanyaan | Jawaban |
|---|---|
| Format contoh pengisian | **Wajib PDF**, ke depan. |
| Izin unduh contoh pengisian | Diatur **per paket** lewat `Mode_Akses` item (Hanya lihat / Boleh unduh). |
| Lampiran form kosong | **Selalu** bisa diunduh, tidak berubah. |
| Item FORM yang sudah ada | **Tetap boleh unduh** — DML mengubahnya ke `VIEW_UNDUH`. Item FORM baru default "Hanya lihat". |
| Contoh pengisian lama non-PDF | **Dibiarkan**. Aturan PDF hanya untuk penerbitan berikutnya. |

## Rancangan

### Aturan akses — `SopAksesBerkas::bolehUnduh($mode, $tipe, $mime, $path)`

- FORM berkas PDF → `Mode_Akses === 'VIEW_UNDUH'`, sama dengan DOKUMEN.
- FORM berkas non-PDF (warisan) → selalu boleh: pembaca aman tak bisa
  menampilkannya, jadi menahannya berarti berkasnya tak bisa dipakai sama sekali.
- Tipe lain tidak berubah.

`apiVersiKonten` membaca mime/path berkas versi di kueri item yang sudah ada,
dan membuang URL mentah untuk FORM yang tak boleh diunduh — sama seperti
DOKUMEN VIEW_ONLY. Endpoint lampiran tidak disentuh.

Payload item karyawan (`SopProgressReadService`) mendapat `Boleh_Unduh` dari
aturan yang sama, supaya layar tidak menebak.

### Wajib PDF — gerbang terbit

`SopDokumenService::pastikanIsiSah()` menolak versi FORM yang berkasnya bukan
PDF. Gerbang ini dipakai kedua pintu terbit (Master Dokumen dan publish paket).
"Kembalikan versi" memakai gerbang yang sama tetapi TIDAK memeriksa PDF: versi
itu pernah sah berlaku, dan aturan ini tidak berlaku surut.

Di wizard versi, `KontenPickerModal` untuk tipe FORM hanya menawarkan PDF.
Pemilih lampiran memakai aturan tersendiri (`LAMPIRAN_FORM`, semua format).

### Layar admin — `ItemFields.vue`

Blok "Hak Akses & Keamanan" (izin berkas + watermark) tampil untuk DOKUMEN dan
FORM. Untuk FORM judulnya "Izin Contoh Pengisian" dengan catatan bahwa form
kosong selalu bisa diunduh.

### Layar karyawan

- `SopKontenViewer`: FORM yang tak boleh diunduh dan sudah ter-render masuk
  `SopPdfReader` dalam mode `readonly` — tanpa dwell, pelacakan halaman, dan
  banner selesai, karena progres FORM tetap dari "Tandai selesai" di kartu.
  Tombol "Unduh" FORM hanya muncul bila boleh.
- `SopDetail`: label tombol kartu FORM tetap "Lihat Contoh Pengisian" untuk PDF.

### Data — DML-2026-165-sop-form-izin-unduh

`N_SOP_Paket_Item` dan `N_SOP_Paket_Draft_Item` ber-`Tipe_Item='FORM'` yang
belum `VIEW_UNDUH` → `Mode_Akses='VIEW_UNDUH'`, `Flag_Watermark='T'`.
Idempoten. **Wajib dijalankan di production sebelum PR di-merge**, kalau tidak
seluruh contoh pengisian PDF sempat terkunci "hanya lihat".

## Uji

- `SopFormBerkasTest`: aturan unduh FORM PDF × tiga Mode_Akses, FORM non-PDF
  selalu boleh, endpoint menolak `unduh=1` pada FORM PDF VIEW_ONLY dan tidak
  mengirim URL mentah, `Boleh_Unduh` di payload.
- Gerbang terbit: versi FORM non-PDF ditolak, PDF lolos.
- `SopKontenViewer.test.js`: cabang pembaca aman untuk FORM hanya-lihat,
  iframe + unduh untuk FORM boleh-unduh.
- `ItemFields.test.js`: blok izin tampil untuk FORM.
- `KontenPickerModal`: FORM hanya PDF, `LAMPIRAN_FORM` semua format.
