# Verifikasi manual — Master Dokumen SOP

Daftar ini menutup seluruh perubahan pada proyek Master Dokumen SOP, bukan hanya
yang terakhir. Urutannya sengaja dari yang paling berisiko: bagian A menyentuh
status kewajiban karyawan sungguhan, sisanya tak mengubah data siapa pun.

Kolom **Gejala kalau salah** ada supaya Anda tahu apa yang dicari, bukan sekadar
"lihat apakah jalan".

---

## Persiapan

Sebagian uji butuh keadaan tertentu. Siapkan dulu:

- [ ] Satu dokumen yang dipakai **lebih dari satu paket terbit** (peta alur di
      panel dokumen memperlihatkan jumlah paket pemakainya).
- [ ] Satu karyawan pada paket itu yang statusnya **SELESAI** — dia yang jadi
      bukti apakah kewajibannya benar-benar dibuka ulang.
- [ ] Satu berkas (konten) yang sudah dipakai dokumen lain, untuk menguji notis
      "sudah terpakai".
- [ ] Satu form ber-PDF dan satu form non-PDF.

---

## A. Dokumen dipakai banyak paket

Ini bagian yang perilakunya baru dan akibatnya nyata ke karyawan.

### A1. Terbit dari Master merekonsiliasi paket lain

- [ ] Master Dokumen → buka dokumen bersama → tab **Versi** → buat versi baru,
      isi kontennya → **Pratinjau terbit** → pilih **Revisi mayor** → terbitkan.
- [ ] Cek karyawan yang tadinya SELESAI **di paket lain** (bukan paket yang Anda
      pikirkan saat menerbitkan).

**Diharapkan:** statusnya tidak lagi SELESAI, dan dia menerima notifikasi
dokumen diperbarui.
**Gejala kalau salah:** status tetap SELESAI padahal versinya sudah berganti —
inilah bug yang diperbaiki; kalau muncul lagi, rekonsiliasinya tak jalan.

### A2. Satu notifikasi per orang, bukan per paket

- [ ] Pakai karyawan yang ditugaskan ke **beberapa paket** yang memakai dokumen
      yang sama. Terbitkan revisi mayor sekali.

**Diharapkan:** dia menerima **satu** notifikasi, menyebut dokumennya dan
paket-paket yang terdampak.
**Gejala kalau salah:** 3–6 notifikasi sekaligus untuk satu revisi.

### A3. Revisi minor tidak mengganggu siapa pun

- [ ] Ulangi A1, tapi pilih **Revisi minor**.

**Diharapkan:** karyawan yang sudah SELESAI **tetap** SELESAI, dan tak ada
notifikasi. Item yang baru ditambahkan tetap jadi kewajiban baru.
**Gejala kalau salah:** orang yang sudah selesai jadi wajib baca ulang hanya
karena perbaikan ketik.

### A4. Publish paket memperlihatkan dampak ke paket lain

- [ ] Builder → paket yang memuat dokumen bersama → **Terbitkan**.

**Diharapkan:** modal menyebut dokumen mana yang naik versi (dari nomor berapa
ke berapa) dan baris "juga dipakai: <nama paket> (N org)". Pilihan mayor/minor
ada **di dalam** modal itu.
**Gejala kalau salah:** langsung terbit tanpa modal, atau modalnya tak menyebut
paket lain sama sekali.

### A5. Gagal menghitung dampak tidak boleh diam-diam terbit

- [ ] Sulit dipicu sengaja; cukup perhatikan: bila modal gagal memuat, ia harus
      **menutup diri dengan pesan error**, bukan membiarkan tombol terbit aktif.

---

## B. Metadata & blok pengesah

### B1. Pengesah sebagai referensi, tetap bisa diketik lepas

- [ ] Tab **Versi & pengesahan** → tambah pengesah → ketik nama karyawan →
      pilih dari daftar.

**Diharapkan:** jabatannya terisi sendiri, dan nama yang tersimpan adalah
**snapshot** — tak ikut berubah kalau jabatan orangnya berubah nanti.
- [ ] Ketik nama yang **tidak ada** di daftar (mis. pihak eksternal).
**Diharapkan:** tetap boleh tersimpan.

### B2. Pengesah lebih dari satu, berurutan

- [ ] Tambah 3 pengesah, ubah urutannya.

**Diharapkan:** urutannya tersimpan dan tampil sama saat modal dibuka ulang.
Ini catatan deklaratif — tak ada alur persetujuan apa pun yang terpicu.

### B3. Peran memakai pilihan tetap

- [ ] Buka dropdown **Peran**.

**Diharapkan:** persis lima pilihan — Disiapkan oleh, Diperiksa oleh, Disetujui
oleh, Disahkan oleh, Mengetahui. Tak ada isian bebas (supaya bisa di-grouping).

### B4. Nama ujian tersimpan pada versi KUIS

- [ ] Buat versi tipe KUIS, pilih ujiannya, simpan, tutup, buka lagi.

**Diharapkan:** nama ujiannya tetap tampil.
**Gejala kalau salah:** kolomnya kosong atau cuma menampilkan ID.

---

## C. Versi

### C1. Penomoran otomatis mulai dari 00

- [ ] Dokumen baru → versi pertama, biarkan nomornya **Otomatis**.

**Diharapkan:** tampil **00**, berikutnya **01**, **02** — selalu dua angka.

### C2. Penomoran manual

- [ ] Isi nomor manual yang belum terpakai → tersimpan.
- [ ] Isi nomor yang **sudah** terpakai pada dokumen itu.

**Diharapkan:** ditolak dengan pesan jelas, bukan tersimpan ganda.

### C3. Versi bisa diedit — hanya DRAFT

- [ ] Edit versi berstatus DRAFT → berhasil.
- [ ] Coba edit versi **PUBLISHED** atau **OBSOLETE**.

**Diharapkan:** tombol editnya tidak tersedia. Versi yang sudah sah tak boleh
berubah isinya tanpa jejak.

### C4. Hapus versi — hanya DRAFT

- [ ] Sama seperti C3, untuk penghapusan.

### C5. Rollback

- [ ] Panel dokumen → versi **OBSOLETE** → **Kembalikan**.

**Diharapkan:** modal berjudul "Kembalikan ke <nomor versi>", memperlihatkan
dampaknya lebih dulu. Rollback **selalu** diperlakukan mayor — semua wajib baca
ulang, tanpa pilihan minor.
**Gejala kalau salah:** ada pilihan minor pada rollback, atau kewajiban karyawan
tak dihitung ulang setelahnya.

### C6. Pointer tidak mundur

- [ ] Setelah rollback, periksa dokumen menyebut versi yang benar sebagai aktif.

**Diharapkan:** versi aktif dokumen dan versi yang disajikan item **sama**.

---

## D. Gerbang isi yang sah

### D1. Versi tanpa isi tak bisa terbit

- [ ] Buat versi tanpa memilih konten → coba terbitkan **dari Master Dokumen**.
- [ ] Ulangi lewat **publish paket di Builder**.

**Diharapkan:** kedua pintu menolak dengan pesan yang menyebut item mana yang
bermasalah.
**Gejala kalau salah:** satu pintu menolak, pintu lain meloloskan — persis pola
yang berulang kali jadi sumber bug di modul ini.

### D2. Tipe konten harus cocok

- [ ] Versi bertipe DOKUMEN diberi konten video (atau sebaliknya).

**Diharapkan:** ditolak.

### D3. Paket dengan item wajib tanpa versi tak bisa terbit

- [ ] Builder → paket yang punya item wajib tanpa versi berlaku → Terbitkan.

**Diharapkan:** ditolak, menyebut judul itemnya.

> Catatan: `php artisan sop:dokumen-verify` saat ini masih melaporkan **1**
> pelanggaran jenis ini pada data nyata — item `TEST` di paket "SOP Uji Coba
> Builder" (wajib, tanpa versi, paket sudah terbit, 5 karyawan). Itu data lama,
> bukan bug; perlu keputusan Anda.

---

## E. Tampilan & navigasi

### E1. Popover pada peta alur

- [ ] Klik node versi di peta alur dokumen.

**Diharapkan:** muncul pilihan — lihat dokumen / **Pratinjau terbit** / lihat
paket.
**Gejala kalau salah:** klik tak menghasilkan apa-apa (pernah terjadi: event
klik tertelan oleh penangkapan pointer).

### E2. Form PDF bisa dilihat, bukan cuma diunduh

- [ ] Form ber-PDF di **tiga** tempat: panel Master Dokumen, modal versi, dan
      halaman karyawan.

**Diharapkan:** ada tombol **Lihat** di ketiganya.
- [ ] Form non-PDF (mis. .docx/.xlsx).
**Diharapkan:** hanya **Unduh** — tak ada tombol lihat yang gagal saat diklik.

### E3. Notis "berkas sudah terpakai"

- [ ] Saat memilih konten yang sudah dipakai dokumen lain — baik di Master
      Dokumen maupun di Builder.

**Diharapkan:** muncul notis menyebut dokumen dan paket mana saja yang sudah
memakainya, plus tawaran memakai ulang yang sudah ada. Anda **tetap boleh**
melanjutkan dan menduplikasi — notis ini memberi tahu, bukan melarang.

### E4. Paginasi daftar Master Dokumen

- [ ] Pindah halaman, lalu terapkan filter/pencarian.

**Diharapkan:** jumlah total konsisten, dan tak ada dokumen yang hilang atau
muncul dua kali antar halaman.

### E5. Judul panjang tidak merusak tata letak

- [ ] Dokumen berjudul sangat panjang, di daftar dan di modal versi.

**Diharapkan:** terpotong rapi, tidak mendorong tombol keluar dari kartunya.

---

## Kalau ada yang janggal

Sebutkan **gejalanya** (apa yang Anda lihat), bukan dugaan penyebabnya — bagian
mana, langkah keberapa, dan apa yang muncul di layar. Untuk hal yang menyangkut
status karyawan, sebutkan juga kode karyawan dan paketnya supaya bisa ditelusuri
langsung ke datanya.
