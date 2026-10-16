# Prompt untuk Claude Design — meredesign design system HCIS

Proyek Claude Design: **HCIS UI Kit**. Isinya 68 kartu preview yang dipanen dari
komponen Vue sungguhan di repo ini, plus berkas CSS aslinya.

Pakai berurutan. Jangan lompat ke Gelombang 2 sebelum Gelombang 1 disetujui —
mengubah token sesudah komponen diredesign berarti mengulang semuanya.

---

## 0 — Prompt pembuka (tempel pertama, sekali saja)

```
Kamu akan meredesign design system sebuah aplikasi HRIS internal bernama HCIS.

Di proyek ini ada 68 kartu preview. Semuanya BUKAN mockup: itu keluaran render
komponen Vue yang sungguhan dipakai di produksi, dipanen apa adanya beserta
CSS aslinya. Kartu "Fondasi" berisi seluruh kosakata dasarnya — tiga lapis
token, palet peran beserta angka kontrasnya, skala huruf, bentuk & jarak, dan
gerak.

Sebelum mengusulkan apa pun, baca kartu Fondasi sampai habis, lalu jawab dulu
tiga pertanyaan ini dengan bukti dari kartu-kartu yang ada:

1. Apa yang sudah bekerja dan tidak boleh diganggu?
2. Di mana sistem ini tidak konsisten dengan dirinya sendiri?
3. Mana yang benar-benar masalah desain, dan mana yang cuma selera?

Jangan mengusulkan perubahan apa pun di jawaban pertama ini. Aku ingin
melihat kamu paham sistemnya dulu.
```

---

## 1a — Gelombang 1 REVISI (pakai ini, bukan 1 di bawah)

Dipakai setelah Prompt 0 dijawab dan tiga temuannya sudah diperbaiki di repo
pada 19 Agustus 2026. Prompt 1 asli di bawah dipertahankan sebagai arsip.

```
Analisismu bagus dan sebagian besar sudah kuverifikasi ke repo. Tiga hal perlu
kamu ketahui sebelum melanjutkan.

SATU — tiga temuanmu sudah diperbaiki di kode sumbernya, dan kit ini sudah
diperbarui. Jangan laporkan lagi:

  1. Token jarak/radius hantu. --hcis-space-1-5/-2-5/-3-5 dan --hcis-radius-xs
     kini didefinisikan, pada nilai yang sama persis dengan cadangan yang
     selama ini menggambar. Nol perubahan rupa.
  2. Halo fokus. Ternyata ada EMPAT terang, bukan dua: 0,5 tombol, 0,2 isian,
     0,15 textarea, dan 0,18 di HcisField.vue yang tidak kamu temukan karena
     ia hidup di blok <style> komponen, bukan di CSS yang kamu baca. Semuanya
     kini var(--hcis-focus-ring). Ditambah dua peran baru,
     --hcis-focus-ring-danger dan -success, supaya isian yang sedang error
     tetap sejelas isian biasa saat difokus.
  3. .hcis-table ganda. Yang salah ternyata FONDASINYA: ia menulis
     border-collapse: collapse padahal setiap tabel tergambar separate — dan
     itu disengaja, karena kepala tabel lengket memakai bayangan yang tidak
     tergambar andal pada tabel ber-collapse. Nilai yang menang dipindahkan ke
     fondasi, definisi kembar di komponen dihapus. Catatan untuk pembacaan
     berikutnya: hanya 3 dari 20 selektor di blok itu yang benar-benar
     duplikat; sisanya milik komponen dan memang tidak ada di fondasi.

DUA — satu temuanmu kutolak. prefers-reduced-motion BUKAN celah aksesibilitas
di sini; ia ditinggalkan dengan sengaja, dan alasannya tertulis di
design-system.css baris 4083-4086. Pemicunya kini [data-hcis-motion] (129
rujukan), baku gerak penuh untuk semua orang, dan daftar pengembaliannya
tertutup. Jangan usulkan mengembalikannya.

TIGA — mulai sekarang, JANGAN MENYUNTING BERKAS APA PUN. Berkas di css/ adalah
cermin dari repo; setiap kali aku menyinkronkan, suntinganmu di sana tertimpa
tanpa jejak. Perbaikan mendarat di repo, bukan di sini. Yang kuminta darimu
adalah usulan, dan proyek ini adalah permukaan tinjau — bukan tempat kerja.

Sekarang tugasmu. Dari temuanmu yang masih berdiri, tiga di antaranya tidak
bisa kuperbaiki tanpa keputusan lebih dulu. Untuk masing-masing, beri SATU
rekomendasi beserta alasannya — bukan daftar pilihan:

  a. Kurva mana yang kanonik? --hcis-ease adalah cubic-bezier(.22,1,.36,1),
     tapi cubic-bezier(.16,1,.3,1) ditulis mentah 33 kali (lebih banyak dari
     21 pemakaian tokennya). Yang mana yang menang, dan apa yang terjadi pada
     yang kalah?
  b. Bobot 800 ditulis mentah 18 kali sementara tangga token berhenti di 700
     (weight-black). Tambah anak tangga kelima, atau geser weight-black ke
     800? Sebutkan konsekuensinya pada hierarki judul-kartu-tabel yang kamu
     bilang rata.
  c. Tangga durasi delapan tingkat, empat di antaranya (200/220/240/250)
     berdesakan dalam 50ms. Jadi berapa tingkat, dan yang mana yang hilang?
     Kalimatmu sendiri jadi patokan: "sistem yang tidak bisa menjawab yang
     mana akan selalu dilangkahi."

Lalu urutkan sisa temuanmu — z-index liar, dua hijau, primitif di glow stat,
kompresi skala radius — berdasarkan biaya perbaikan dibanding manfaatnya, dan
katakan mana yang menurutmu tidak layak dikerjakan sama sekali.
```

---

## 1 — Gelombang 1: Fondasi (token) — ARSIP, sudah digantikan 1a

```
Sekarang fokus HANYA ke kartu Fondasi. Jangan sentuh komponen dulu.

Tugasmu: usulkan perbaikan pada lapis PERAN — bukan lapis primitif, bukan
warna mentah.

Arsitekturnya tiga lapis dan urutan ini tidak bisa ditawar:
  primitif   --hcis-indigo-500, --hcis-slate-200   (ramp mentah, jangan dipakai langsung)
  peran      --hcis-brand-600, --hcis-text-muted, --hcis-border-strong  (INI yang dipakai)
  tema       [data-hcis-theme='laut'|'hutan'] menimpa ramp merek saja

Memakai var(--hcis-indigo-500) di sebuah komponen sama salahnya dengan menulis
#6366f1. Kalau kamu merasa butuh warna yang belum ada perannya, usulkan NAMA
PERAN baru beserta alasan kapan ia dipakai — jangan pinjam primitif.

Pagar yang sudah diputuskan dan tidak untuk didiskusikan ulang:
- Alfa ditulis rgb(var(--hcis-brand-500-rgb) / 0.5), TIDAK PERNAH rgba().
  Kalau rgba(), halo dan bayangan membeku saat tema berganti.
- --hcis-border  = pemisah dekoratif (kartu, baris tabel). Sengaja terang.
- --hcis-border-strong = batas KONTROL (input). Di sini kontras 3:1 wajib,
  karena garis itu satu-satunya penanda di mana kotak isian berada.
- Putih di atas --hcis-brand-600 wajib >= 4,5:1 di SETIAP tema.
- Setiap tema wajib menimpa SELURUH langkah ramp, bukan sebagian.

Keluarkan usulanmu sebagai tabel: nama token peran, nilai sekarang, nilai
usulan, alasan, dan komponen mana saja yang ikut berubah karenanya. Sertakan
angka kontras untuk setiap perubahan warna teks dan batas.
```

---

## 2 — Gelombang 2: Komponen inti

```
Gelombang 1 sudah disetujui. Sekarang komponen, dan hanya enam ini dulu:
button, badge, card, field, alert, table.

Keenamnya adalah yang paling sering muncul di 300+ halaman, jadi perbaikan di
sini terasa paling luas — dan kesalahan di sini juga paling mahal.

Bahasa visual yang sudah ditetapkan pemilik proyek (hasil wawancara, bukan
tebakan — jangan tanyakan ulang):

  Empat sifat, semuanya bertoken:
    1. Panel kaca berlapis    --hcis-glass-bg / -blur / -border / -shadow
    2. Gradasi lembut di area besar   --hcis-gradient-hero / -brand / -aurora
    3. Cahaya di sekitar elemen aktif --hcis-glow-brand / -success / -danger
    4. Penanda yang meluncur          --hcis-slide-ease / -duration

  TAKARANNYA SUDAH DITURUNKAN: kaca maksimal 3 area per halaman, gradasi besar
  maksimal 3 area per halaman. Acuan lama punya 42 gradasi dalam satu berkas
  dan itu dinyatakan "terlalu berat". Kartu isi tetap padat, bukan berkaca.

  Layout TIDAK ditetapkan sebagai aturan — ia situasional, ditentukan isi
  halaman. Yang tetap hanya empat sifat di atas.

Angka yang sudah diputuskan, jangan tanya lagi:
  kartu besar per halaman 12 | baris tabel per halaman 24
  judul kartu dipotong 1 baris | keterangan dipotong 2 baris
  keadaan memuat WAJIB skeleton yang meniru bentuk isinya

Untuk tiap komponen, keluarkan:
  - sebelum / sesudah yang bisa dilihat berdampingan
  - setiap nilai baru dinyatakan sebagai NAMA TOKEN PERAN, bukan hex
  - daftar varian dan state yang ikut terpengaruh (hover, focus-visible,
    disabled, loading, error) — kalau satu state tidak kamu sebut, aku anggap
    kamu belum memikirkannya
  - apa yang TIDAK kamu ubah, dan kenapa

Satu peringatan dari pengalaman: sebelum merancang kemampuan baru, periksa
dulu apakah komponen yang ada sebenarnya SUDAH punya kemampuan itu. Kesalahan
paling sering di proyek ini adalah merakit ulang sesuatu yang sudah tersedia.
```

---

## 3 — Gelombang 3: sisanya, per grup

```
Lanjutkan per grup, satu grup per giliran, dengan aturan yang sama seperti
Gelombang 2:

  Formulir     22 komponen — paling banyak, kerjakan terakhir
  Wadah         8 — card, container, dialog, drawer, collapse, data-table
  Umpan balik  10 — alert, message, toast, skeleton, empty-state, result-state
  Navigasi      5 — header, tabs, breadcrumb, context-banner, steps
  Data          6 — table, pagination, avatar, stat-group
  Aksi & status 6 — button, badge, dropdown, stat-card, status-badge
  Visualisasi   4 — matrix-grid, pipeline-visualizer, org-chart, timeline
  Tata letak    2 — layout, splitter
  Fondasi       4 — icon, motion, divider, bootstrap-icons

Mulai dari grup yang menurutmu paling tidak konsisten, dan katakan alasannya.
```

---

## Lampiran — pagar yang berlaku di semua gelombang

Ini bukan preferensi. Semuanya dijaga uji otomatis
(`resources/js/Components/ui/designSystemGuards.test.js`, 29 tes), dan usulan
yang melanggarnya tidak bisa dipakai apa adanya.

| Aturan | Kenapa |
| --- | --- |
| Tidak ada warna mentah (`#hex`, `rgba(...)`) di berkas fondasi & komponen | NOL pelanggaran hari ini; biayanya nol untuk dijaga |
| `--hcis-*` hanya boleh didefinisikan di `design-system.css` | definisi kembar membuat tema berhenti bekerja diam-diam |
| `.el-*` tidak boleh ditimpa di berkas komponen | Element Plus digayai lewat `element-plus-bridge.css` saja |
| `transition: all` dilarang | memicu tata letak ulang; pakai `--hcis-transition-props` |
| z-index memakai skala `--hcis-z-base` … `-toast` | halaman pernah memakai 200000 |
| Ikon lewat `<HcisIcon>`, bukan emoji atau `<i class="bi">` mentah | HcisIcon membawa kelas ukurannya sendiri |

### Yang perlu Claude Design tahu tentang preview-nya

- Preview adalah **tangkapan statis** komponen Vue, bukan komponen hidup.
  Ada skrip kecil yang mengembalikan buka-tutup panel, akordeon, tab, centang,
  sakelar, dan dialog — tapi itu tiruan perilaku, bukan Vue.
- Bagian **"Setelah digerakkan"** dan **"Lapisan ter-teleport"** pada sebuah
  kartu adalah keadaan yang hanya muncul setelah komponennya diklik: menu
  terbuka, toast, dialog, panel tanggal. Itu bagian dari desain, bukan sampah.
- Berkas CSS di `css/` adalah berkas **asli** dari repo, disalin apa adanya.
  `komponen.css` berisi gaya ber-scope hasil panen — jangan usulkan mengeditnya
  langsung; ia keluaran, bukan sumber.

### Ukuran keberhasilan

Pemilik proyek menyatakannya dalam satu kalimat:

> **"seharusnya kamu merasa"**

Jangan hanya menakar kepatuhan pada aturan di atas. Takar juga apakah
halamannya enak dipakai. Usulan yang patuh tetapi terasa kaku adalah usulan
yang gagal.
