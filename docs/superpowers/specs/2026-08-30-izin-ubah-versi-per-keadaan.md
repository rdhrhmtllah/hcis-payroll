# Izin ubah versi SOP per keadaan

**Tanggal**: 30 Agustus 2026
**Modul**: LMS/SOP — siklus versi dokumen
**Status**: rancangan disetujui user, siap dikerjakan

## Masalahnya

Hari ini `ubahVersi()` cuma mengenal dua keadaan: DRAFT boleh, selain itu tidak.
Kasarnya tepat, tapi salah di dua arah sekaligus.

**Terlalu longgar** ke satu sisi: identitas dokumen (`SopDokumenService::ubah()`)
tak punya penjaga keadaan sama sekali. Enam field di sana — Jenis, Entitas
Pemilik, Departemen, Ruang Lingkup, APQC, No_Dokumen — adalah segmen pembentuk
nomor resmi. Mengubahnya pada dokumen yang revisinya sudah beredar akan menulis
ulang nomor yang tercetak di dokumen yang dipegang karyawan. *(Di luar lingkup
change ini; dicatat supaya tak hilang.)*

**Terlalu ketat** ke sisi lain: versi yang sudah terbit terkunci total, padahal
ada perubahan yang wajar terjadi tanpa melahirkan revisi — memperpanjang masa
berlaku, menggeser jadwal review berikutnya, membetulkan ringkasan, atau
memperbaiki salah ketik pada blok pengesahan. Memaksa semua itu lewat versi baru
melahirkan revisi yang isinya sama persis.

## Keputusan

Keadaan versi menentukan **field mana** yang boleh diubah, bukan sekadar boleh
atau tidak.

| Keadaan | Yang boleh diubah |
|---|---|
| DRAFT (berjalan) | seluruh field seperti sekarang |
| PUBLISHED | `Ringkasan_Perubahan`, `Tanggal_Kadaluarsa`, `Tanggal_Review_Berikutnya`, blok `Pengesah` (+ `Tanggal_Pengesahan`, `Nomor_Pengesahan`) |
| OBSOLETE | **tak satu pun** |
| Dibatalkan (CANCEL) | **tak satu pun** |

Ditambah satu aturan lintas-keadaan:

> **Tanggal yang sudah lewat tak bisa digeser lagi.** Bila nilai TERSIMPAN sebuah
> field tanggal sudah terlewati hari ini, nilainya terkunci — mengubah tanggal
> yang sudah lewat berarti menulis ulang masa lalu.

Aturan itu sekaligus mengunci `Tanggal_Berlaku` dengan sendirinya pada versi yang
sudah terbit, tanpa perlu menyebutnya sebagai pengecualian: pada versi berlaku,
tanggal itu hampir selalu sudah terlewati.

### Menyimpan, bukan menerbitkan ulang

Perubahan pasca-terbit lewat pintu **simpan biasa**. Tidak menyentuh pointer
dokumen, tidak melahirkan versi, tidak merakit ulang nomor resmi, dan tidak
menjalankan rekonsiliasi paket. Karena itu pertanyaan minor/mayor memang tak
muncul — tak ada kewajiban karyawan yang dibuka ulang.

Jalur terbit yang ada hanya menerima versi DRAFT dan membawa fan-out penuh
(meng-obsolete versi berlaku, menggeser pointer, merekonsiliasi seluruh paket
perujuk). Memakainya demi satu kolom tanggal berarti menjalankan mesin itu untuk
pekerjaan yang tak membutuhkannya.

### Blok pengesahan pada versi terbit

Diputuskan user 30 Agu 2026: **boleh dikoreksi**. Saya sempat keberatan karena
blok itu catatan siapa menandatangani dokumen yang sudah beredar, dan ia bukan
tanpa pembaca — `Disahkan_Oleh` dan `Jabatan_Pengesah` dibaca register
kepatuhan, Export Studio, dan penyaring "tanpa pengesahan".

Keputusannya tetap, dengan satu syarat yang membuatnya aman: **perubahannya
tercatat di jejak audit dengan nilai lama dan barunya**. Dengan begitu "betulkan
ejaan" bisa dibedakan dari "ganti pengesah" bila suatu saat ditanya. Jejaknya
sudah ada (`SopDiff` + `jejak('VERSI_UBAH', …)`); yang dipastikan change ini
adalah blok pengesah benar-benar ikut terekam.

## Rancangan

### Satu penentu, di server

`SopVersiKeadaan::fieldBolehUbah(?string $status, ?object $tahap): array`
mengembalikan **daftar field** yang boleh diubah. Larik kosong berarti terkunci
penuh. Tak ada nilai sentinel "semua" — daftar eksplisit selalu, supaya layar
tak perlu menerjemahkan apa pun.

### Ditegakkan di ubahVersi()

Tiga lapis, berurutan:

1. Larik izin kosong → tolak dengan pesan yang menyebut keadaannya.
2. Field yang **tak diizinkan tetapi nilainya BERBEDA** dari yang tersimpan →
   tolak, sebut nama fieldnya. Nilai yang sama diabaikan diam-diam: formulir
   memang mengirim seluruh isian, dan menolak field yang tak berubah akan
   membuat menyimpan menjadi mustahil.
3. Field tanggal yang nilai tersimpannya sudah lewat dan nilainya berbeda →
   tolak.

Penulisan kolomnya **dibatasi pada field yang diizinkan** untuk keadaan selain
DRAFT. Menulis seluruh larik seperti sekarang akan mengosongkan kolom yang tak
dikirim formulir parsial — cacat yang berdiri sendiri, terpisah dari izinnya.

### Dikirim ke layar

Muatan riwayat versi membawa `Field_Boleh_Ubah` per baris. Layar menggambar
hanya yang dibolehkan dan mematikan sisanya, sehingga tak ada lagi tombol atau
isian yang ditawarkan lalu ditolak server — pola yang berulang sepanjang 30 Agu
2026 dan yang membuat admin kehilangan konteks.

## Pengujian

Tiap penjaga wajib pernah dilihat **merah**.

1. OBSOLETE dan CANCEL menolak setiap field.
2. PUBLISHED menerima keempat field yang diizinkan.
3. PUBLISHED menolak field di luar daftar — dan **isinya tak berubah**.
4. PUBLISHED menerima kiriman berisi field terlarang yang nilainya **sama**
   (formulir mengirim seluruh isian; ini yang membuat menyimpan tetap mungkin).
5. Tanggal yang sudah lewat tak bisa digeser; yang belum lewat bisa.
6. Menyimpan versi terbit **tidak** menggeser `Id_Versi_Aktif`, tidak mengubah
   `Nomor_SOP_Resmi`, dan tidak melahirkan versi baru.
7. Muatan membawa `Field_Boleh_Ubah` yang isinya sama dengan yang ditegakkan
   service — diuji lewat alur, bukan lewat metode.

## Di luar lingkup

- Penguncian **identitas dokumen** (segmen pembentuk nomor) sesudah versi
  pertama terbit. Nyata, tercatat di atas, keputusan produk tersendiri.
- Tipe KUIS/VIDEO/FORM yang tak bisa terbit sama sekali (temuan 9 audit).

## Perubahan 5 Okt 2026: isi versi terbit terbuka sampai dijadwalkan

Permintaan produk: berkas dan ujian di langkah Pemberkasan masih boleh diganti
sesudah versi terbit, **selama dokumennya belum dijadwalkan ke karyawan**.
Masuk paket di SOP Builder saja belum mengunci; yang mengunci adalah adanya
penjadwalan.

- `SopVersiKeadaan::FIELD_ISI` = `Id_Master_Konten`, `Id_Master_Ujian`,
  `Nama_Ujian`. Nomor versi tetap terkunci.
- `fieldBolehUbah()` dan `fakta()` menerima `$sudahDijadwalkan` dengan bawaan
  `true` (terkunci). Kelasnya tetap murni; faktanya dijawab pemanggil.
- "Sudah dijadwalkan" = ada item paket aktif yang merujuk dokumen itu, dan
  paketnya punya penjadwalan dengan setidaknya satu karyawan aktif
  (`SopDokumenService::dokumenSudahDijadwalkan()`, hitungan yang sama dengan
  `SopDokumenFanoutService::hitungDampak()`). Penugasan yang aksesnya dicabut
  tetap dihitung.
- `ubahVersi()` menolak penggantian isi pada versi yang sudah dijadwalkan
  dengan alasan yang menyebut penjadwalannya. Muatan riwayat versi
  menanyakannya sekali per dokumen.
- Tautan ujian HC Learn dicetak saat karyawan menekan "Mulai kuis" dan membaca
  ujian dari versinya, jadi selama belum dijadwalkan belum ada token yang
  terikat ke ujian lama.

Butir 3 di bagian Pengujian kini dibagi dua: versi terbit yang belum
dijadwalkan **menerima** penggantian berkas, dan versi yang sudah dijadwalkan
menolaknya dengan isi tak berubah.
