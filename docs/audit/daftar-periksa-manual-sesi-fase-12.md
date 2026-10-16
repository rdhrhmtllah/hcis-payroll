# Daftar periksa manual — perubahan sesi Fase 12 (6 Sep 2026)

Disusun dari **diff dua PR yang masih terbuka** (#321, #322), bukan dari ingatan.
Isinya bukan daftar umum "coba semua modul": ia menyasar tepat pada permukaan
yang berubah, lalu pada lubang yang otomatis memang tak bisa menjangkaunya.

> **Batas yang harus disadari sebelum mulai.** Uji otomatis di repo ini hanya
> menyentuh **rute GET**; seluruh rute yang MENGUBAH data belum pernah disapu,
> karena tak boleh dijalankan otomatis di dev bersama. Bagian B di bawah adalah
> tepat lubang itu — di situlah pemeriksaan manual bernilai paling besar, dan di
> situ pula pemeriksaan manual TAK TERGANTIKAN.

---

## A. Yang saya ubah — periksa dulu, sebab di sinilah kesalahan saya akan muncul

### A1 · Hapus role (`deleteRole`) — SYSTEM

`admin/role-manager` → hapus sebuah role.

| langkah | yang harus terjadi |
|---|---|
| Hapus role yang **masih punya user aktif** | ditolak 422, menyebut jumlah usernya. Tak ada yang berubah. |
| Hapus role **tanpa user aktif** | berhasil; role hilang dari daftar |
| Lalu buka detail role itu | permission-nya ikut bersih, scope-nya non-aktif |

**Yang dicari:** tak boleh ada role yang hilang dari daftar tetapi
permission-nya masih tersisa, atau sebaliknya. Perubahannya membungkus ketiga
tulisan dalam satu transaksi; kalau ada yang separuh jalan, justru itu tandanya
saya keliru.

### A2 · Kiriman WhatsApp — ESS / KPI / SYSTEM

Perubahannya membuat job WA menunggu commit pada koneksi APA PUN. Jalur yang
paling mudah dipicu:

- buat user baru dari **Team / Karyawan** (`karyawanTeamController@simpanUser`)
- kirim ulang akun user (`kirimUlangUser`)
- ajukan sesuatu yang memicu notifikasi approval (cuti / KPI)

| yang harus terjadi | yang menandakan masalah |
|---|---|
| Halaman **membalas cepat**, tidak menggantung 10–20 detik | halaman diam lama sebelum sukses |
| WA tetap terkirim (cek `Whatsapp_Log`) | tak ada baris log sama sekali |
| Bila prosesnya GAGAL di tengah dan dibatalkan, **tak ada WA terkirim** | WA "phantom" untuk transaksi yang batal |

Poin ketiga yang paling berharga diperiksa manual, dan paling sulit diuji
otomatis.

### A3 · Struktur penjadwalan training — LMS

76 baris identik di dua controller diangkat jadi satu service. Perilakunya
harus **sama persis**.

- `lms/training/catalog` → buka satu training → assign / update peserta
- `LmsMateri` → tautkan materi ke penjadwalan

| yang harus terjadi | yang menandakan masalah |
|---|---|
| Jadwal baru memuat seluruh section & item silabus | ada modul yang tak muncul |
| **Jadwal LAMA tidak berubah** saat silabus diperbarui | modul baru tiba-tiba muncul di jadwal lama, dan peserta yang sudah selesai jadi "belum selesai" |

Poin kedua adalah aturan snapshot — inti dari kode itu. Kalau rusak, gejalanya
justru terlihat sebagai progres peserta yang mundur.

### A4 · Leader Shift — 81 baris kode mati dihapus

`leader-shift` → assign shift, offday, tukar hari, batalkan.
Kedua metode yang dihapus **tak dipanggil dari mana pun**, jadi seharusnya nol
perubahan perilaku. Halaman ini diperiksa semata untuk memastikan asumsi itu.

### A5 · Tiga perbaikan tampilan (#321)

| di mana | yang harus terlihat |
|---|---|
| SOP → item ber-dokumen yang **sudah ada** (bukan baru) | lencana **"Dikelola di Master Dokumen"** muncul di sebelah label "Dokumen Terpilih" |
| Monitoring SOP, buka `…/monitoring?tab=dokumen` | layar jatuh ke **Ringkasan** DAN parameter `?tab=` **hilang dari address bar** |
| Report Studio / Export Studio → tab Studio | kalimat **"Satu baris = satu …"** tampil di atas perakit |

Yang ketiga paling penting: kalimat itu menyatakan satu baris laporan mewakili
apa. Tanpanya angka di export tak bisa dinilai terhitung ganda atau tidak.

---

## B. Lubang yang otomatis TAK bisa menjangkaunya — di sinilah manual bernilai

Bukan bagian dari perubahan saya. Ini yang belum pernah tersapu sama sekali.

1. **Semua rute yang mengubah data.** Sapuan otomatis hanya GET. Setiap submit,
   approve, reject, cancel, publish, dan hapus di seluruh modul belum pernah
   dijalankan oleh perkakas mana pun.
2. **Cabang di dalam rute.** "Tersapu" berarti dijalankan SEKALI dengan
   parameter baku — bukan setiap cabang di dalamnya dilewati.
3. **DMS sebagai grup tersendiri.** Audit menyisirnya sebagai bagian LMS.
4. **Enam dari sembilan fase audit berstatus SEBAGIAN.**

Saat memeriksa, yang paling berguna dicatat bukan "error" saja, melainkan juga:
halaman yang **lambat tak wajar** (tanda kunci tertahan), dan angka yang
**berbeda antara dua layar** untuk data yang sama.

---

## C. Yang sudah diketahui merah — jangan dilaporkan sebagai temuan baru

| hal | keadaan |
|---|---|
| CI **Frontend quality** merah | 23 uji; sejak 18 Agustus; menunggu 4 keputusan |
| Report Studio **di ponsel** hanya menampilkan ringkasan | galeri & bilah aksi tak dirender — belum diputuskan sengaja atau tidak |
| Tautan **Master Dokumen** tetap muncul walau item belum bertaut dokumen | fallback ditulis eksplisit di kode; menunggu keputusan |
| 427 warna hard-coded | ratchet design-system |

---

## D. Empat keputusan yang menahan CI hijau

1. **Ratchet design-system (19 uji)** — dinaikkan, atau warnanya dibereskan?
2. **`SopPanelProfilTipe` (3 uji)** — ia menegaskan "tanggal berlaku untuk SEMUA
   tipe", padahal peta profil kini tak memberi blok berkas kepada KUIS. Mana
   yang benar?
3. **`TabRingkasan` (1 uji)** — sub-nav yang tak pernah ada: dibuat, atau
   ujinya dilepas?
4. **Dua penyempitan di bagian C** — disengaja atau regresi?

---

## Urutan menuju "solid"

1. Merge **#321** dan **#322** (tak berbagi berkas; urutan bebas)
2. Pemeriksaan manual bagian **A**, lalu **B**
3. Jawab **D** → CI Frontend hijau untuk pertama kalinya sejak 18 Agustus
4. Rancang sapuan **rute tulis** yang aman untuk dev bersama

Langkah 3 penting bukan karena angkanya: selama CI merah, kerusakan baru
tenggelam dalam derau. Tiga regresi UI yang ditemukan sesi ini lolos berminggu-
minggu persis karena itu.
