# Pengumpulan Data Karyawan — Desain

**Tanggal:** 14 Agustus 2026
**Branch:** `feat/pengumpulan-data-user`
**DDL:** `database/pengumpulan_data/2026_08_14_pengumpulan_data_ddl.sql` (dijalankan manual oleh pemilik repo)

## 1. Tujuan

Mengumpulkan 12 data karyawan yang saat ini tidak lengkap atau tidak tepercaya di
sistem induk — terutama untuk keperluan pembaruan Coretax. Data dikumpulkan
langsung dari karyawan lewat formulir yang **wajib** diisi, dengan tekanan yang
naik bertahap sampai tak bisa lagi ditunda.

Hasilnya berhenti di tabel baru. Tabel operasional (`Karyawan` dan turunannya)
tidak disentuh sama sekali.

## 2. Keputusan yang mengunci desain

| Keputusan | Nilai | Alasan |
|---|---|---|
| Sifat fitur | Sekali jalan, field tetap | Bukan mesin kampanye; tidak ada form builder |
| Prefill | **Ada** — diisi dari data lama, karyawan meninjau | Keputusan pemilik **3 Sep 2026**, membalik keputusan 14 Agu ('tidak ada, data lama tidak dipercaya') |
| Setelah kirim | Terkunci permanen | Katup darurat: admin bisa buka kunci per orang |
| Jatah penundaan | 3 kali, **kumulatif di DB** | Tidak bisa direset dengan logout/ganti browser |
| Target | Semua karyawan aktif, minus daftar pengecualian | Pengecualian dikelola admin lewat UI |
| Alur pengisian | Wizard 4 langkah | Mayoritas mengisi dari HP |
| Sinkron ke tabel induk | Tidak ada | Nol risiko ke absensi/cuti/KPI |
| Nada modal | Hero penuh di semua pertemuan | Yang naik hanya suhu warna & kata, bukan turun kelas |
| Frekuensi modal | Konstanta yang bisa diganti | `SESI` / `HARIAN` / `HALAMAN` |

## 3. Skema field (versi 1)

Definisi tunggal di `config/pengumpulan_data.php` → dipakai **server** (validasi
yang mengikat) dan **UI** (label, urutan, aturan tampilan). Tidak ada daftar field
yang ditulis dua kali.

### Langkah 1 · Identitas
| kunci | tipe | aturan |
|---|---|---|
| `nama_lengkap` | teks | wajib, 3–150 karakter |
| `nik_ktp` | teks | wajib, **tepat 16 digit angka** (NIK KTP, bukan nomor karyawan) |
| `tanggal_lahir` | tanggal | wajib, **tidak boleh di masa depan**; batas usia 15–70 DILEPAS 3 Sep 2026 seiring prefill dinyalakan |
| `jenis_kelamin` | pilihan | wajib: `L` / `P` |

### Langkah 2 · Kontak
| kunci | tipe | aturan |
|---|---|---|
| `email` | teks | wajib, format email sah, maks 150 |
| `no_hp` | teks | wajib, 10–14 digit, dinormalkan ke awalan `08` |

### Langkah 3 · Kepegawaian
| kunci | tipe | aturan |
|---|---|---|
| `departemen` | pilihan | wajib, nilainya kode dari master divisi sistem |
| `pendidikan_terakhir` | pilihan | wajib: SD, SMP, SMA/SMK, D1, D2, D3, D4/S1, S2, S3 |
| `status_ptkp` | pilihan | wajib, 8 nilai PTKP resmi: TK/0, TK/1, TK/2, TK/3, K/0, K/1, K/2, K/3 |
| `atasan_spv` | pilihan cari | **wajib bila PHL**, harus kosong bila bukan PHL — nilainya `Kode_Karyawan` |
| `atasan_dept_head` | pilihan cari | sama seperti di atas |

Status PHL dibaca server dari `Status_Karyawan`, dikirim ke UI sebagai penanda,
dan **divalidasi ulang di server saat submit**. Aturan di browser hanya hiasan;
yang mengikat adalah server.

### Langkah 4 · Kontak darurat (satu orang)
| kunci | tipe | aturan |
|---|---|---|
| `darurat_nama` | teks | wajib, 3–150 |
| `darurat_hubungan` | pilihan | wajib: Orang Tua, Pasangan, Anak, Saudara Kandung, Lainnya |
| `darurat_no_hp` | teks | wajib, aturan sama dengan `no_hp` |

**Duplikat NIK/email/HP tidak memblokir submit** — user bisa terjebak karena
kesalahan orang lain. Duplikat dilaporkan di pemeriksa kualitas data sisi admin.

## 4. Perjalanan user

```
login
  └─ shared prop ringan memberi tahu shell: status pengisian
       ├─ DIKECUALIKAN → tidak pernah melihat apa pun
       ├─ SELESAI      → tidak pernah melihat apa pun lagi
       └─ BELUM        → modal hero muncul sesuai `frekuensi_modal`
              ├─ "Nanti dulu"  → Jumlah_Tunda += 1 (di DB)
              │      └─ Jumlah_Tunda >= maks → status jadi TERKUNCI
              └─ "Isi sekarang" → /pengumpulan-data
                     └─ wizard 4 langkah (draf tersimpan tiap pindah langkah)
                            └─ layar peninjauan → Kirim
                                   └─ SELESAI → halaman ringkasan, terkunci
```

Selama status `BELUM` atau `TERKUNCI`, bar tipis menetap di setiap halaman
(bisa dimatikan lewat konfigurasi).

### Mesin status

| dari | aksi | ke |
|---|---|---|
| `BELUM` | tunda, `Jumlah_Tunda < maks` | `BELUM` |
| `BELUM` | tunda, `Jumlah_Tunda >= maks` | `TERKUNCI` |
| `BELUM` / `TERKUNCI` | submit sah | `SELESAI` |
| `SELESAI` | admin buka kunci | `BELUM` (`Jumlah_Tunda` **tidak** direset) |
| mana pun | admin menambah pengecualian | `Is_Dikecualikan = 'Y'` |

Catatan sengaja: buka kunci tidak mereset jatah penundaan. Karyawan yang sudah
pernah menghabiskan jatahnya akan langsung menghadapi modal terkunci — dan itu
memang yang diinginkan, karena buka kunci diberikan justru untuk minta perbaikan
segera.

### Modal terkunci

Pertemuan ke-4 dan seterusnya: tanpa tombol tutup, tanpa Esc, tanpa klik-luar.

Dua penopang yang dibutuhkan modal ini:

- **Jebakan fokus** — pakai `trapFocusWithin(event)` dari
  `resources/js/utils/dialogFocus.js` yang sudah ada. Jangan tulis ulang.
- **Kunci gulir** — **belum ada** di repo ini. Yang dipakai sekarang adalah
  `document.body.style.overflow = 'hidden'` yang ditulis ad-hoc di
  `modernFilter.vue` dan `childDateRage.vue`. Pola itu rusak begitu ada dua
  lapisan bertumpuk: lapisan kedua yang ditutup mengembalikan gulir milik
  lapisan pertama yang masih terbuka. Fase 3 membuat
  `resources/js/composables/useScrollLock.js` yang **berhitung** (pasang
  menaikkan pencacah, lepas menurunkannya, gulir baru pulih di nol) dan
  melepaskan kuncinya saat komponen dilepas — supaya modal terkunci tidak bisa
  meninggalkan halaman beku permanen. Komponen lama tidak ikut diubah di
  lingkup ini; composable-nya berdiri sendiri agar bisa diadopsi bertahap.

## 5. Model data

Empat tabel baru, semuanya diikat lewat `Kode_Karyawan`.

### `N_HRIS_Pengumpulan_Data_Submission`
Isi jawaban, disimpan sebagai JSON. **Menambah field di kemudian hari tidak
memerlukan `ALTER TABLE`** — cukup menaikkan `Versi_Skema`.

Kolom penting:
- `Data_Json` — seluruh jawaban, `NVARCHAR(MAX)` dengan `CHECK (ISJSON(...) = 1)`
- `Snapshot_Lama_Json` — salinan nilai dari sistem induk pada detik pengiriman,
  sumber kolom "berbeda dari data lama" di sisi admin
- `Flag_Aktif` — `Y` untuk versi berlaku. Kiriman ulang setelah buka kunci
  menulis baris baru dan menurunkan yang lama jadi `T`; tidak ada yang hilang
  tertimpa.

### `N_HRIS_Pengumpulan_Data_Status`
Satu baris per karyawan. **Inilah satu-satunya yang dibaca di setiap kunjungan
halaman** — sengaja dipisah dari tabel submission supaya yang dibaca berulang
adalah baris ringan, bukan JSON gemuk.

Berisi `Jumlah_Tunda`, `Status`, `Is_Dikecualikan`, jejak buka kunci, dan
`Draft_Json` (jawaban sementara wizard).

### `N_HRIS_Pengumpulan_Data_Config`
Satu baris. Sakelar kampanye aktif/nonaktif + judul dan teks pengumuman yang
bisa diubah tanpa deploy.

**Pembagian tugas dengan berkas config** — supaya tidak membingungkan:
- `config/pengumpulan_data.php` → perilaku teknis: frekuensi modal, jatah
  penundaan, versi skema, definisi field.
- Tabel `_Config` → hal yang perlu diubah saat sistem berjalan tanpa deploy:
  sakelar on/off dan kalimat pengumuman.

### `N_HRIS_Pengumpulan_Data_Audit`
Siapa mengaksi apa, ke siapa, kapan. Mencatat buka kunci, penambahan/penghapusan
pengecualian, dan perubahan sakelar.

## 6. Konfigurasi

```php
// config/pengumpulan_data.php
'aktif_default'          => true,      // dipakai bila baris _Config belum ada
'frekuensi_modal'        => 'SESI',    // SESI | HARIAN | HALAMAN
'maks_penundaan'         => 3,
'tampilkan_bar_menetap'  => true,
'versi_skema'            => 1,
'field'                  => [ /* definisi 14 field, lihat bagian 3 */ ],
```

Pelacakan "sudah tampil?" untuk mode `SESI` dan `HARIAN` dilakukan di sisi
browser (`sessionStorage` / `localStorage` berkunci tanggal) — **tanpa tulisan ke
database**. Mode `HALAMAN` tidak perlu pelacakan sama sekali. Yang tersimpan di
DB hanyalah `Jumlah_Tunda`, yang naik hanya saat user benar-benar menekan
"Nanti dulu".

## 7. Bagian yang dibangun

Enam potong dengan batas jelas, masing-masing bisa dipahami tanpa membaca isi
yang lain:

1. **Definisi skema** (`config/pengumpulan_data.php` + `SkemaPengumpulanData`)
   — sumber kebenaran tunggal untuk field, aturan validasi, dan urutan langkah.
2. **`StatusPengumpulanService`** — membaca/menulis status per karyawan.
   Dipakai shared prop; wajib murah.
3. **`PengirimanDataService`** — validasi lengkap, ambil snapshot data lama,
   tulis submission, ubah status. Satu transaksi pendek, tanpa panggilan HTTP di
   dalamnya.
4. **Komponen shell** — `ModalPembaruanData.vue` + `BarPembaruanData.vue`,
   dipasang di `resources/js/Layouts/AppShell.vue`.
5. **Halaman wizard** — `Pages/PengumpulanData/` (wizard, peninjauan, ringkasan).
6. **Halaman admin** — `Pages/PengumpulanData/Admin/`.

## 8. Sisi admin

Rute `/admin/pengumpulan-data`, menu di bawah **Administrator HCIS**
(tambah baris di `N_HRIS_Menus`, lalu flush cache menu).

- **Pantau progres** — berapa sudah/belum, dipecah per departemen; daftar nama
  yang bisa dicari dan disaring.
- **Detail jawaban** — buka satu karyawan, lihat seluruh jawaban dan waktu kirim.
- **Ekspor Excel** — menumpang mesin ekspor terpusat (`N_HRIS_Export_Jobs` +
  `AbstractExportService`), bukan bangun baru.
- **Kelola pengecualian & buka kunci** — dengan pencatatan audit.
- **Sakelar kampanye on/off** — rem tangan tanpa deploy.
- **Pemeriksa kualitas data** — menyorot NIK bukan 16 digit, NIK/email/HP kembar
  antar karyawan, nomor HP janggal.
- **Kolom "berbeda dari data lama"** — dari `Snapshot_Lama_Json`.
- **Kirim pengingat** ke yang belum mengisi — notifikasi dalam sistem; bila
  WhatsApp dipakai, **wajib di luar transaksi DB dan lewat antrean**. Repo ini
  pernah mengalami "Login timeout expired" massal persis karena panggilan HTTP
  WhatsApp berjalan di dalam transaksi.

Rute karyawan `/pengumpulan-data`, menu di bawah **ESS**.

## 9. Performa

Fitur ini menyentuh **setiap** permintaan halaman, jadi bebannya dihitung, bukan
ditebak:

- Status dikirim sebagai **prop Inertia lazy** (closure), mengikuti pola `auth`
  dan `layout` yang sudah ada — jadi polling notifikasi dan XHR JSON tidak ikut
  menyeretnya.
- Baris status di-cache per karyawan; cache dibuang saat tunda, submit, buka
  kunci, atau perubahan pengecualian.
- Baris `_Config` di-cache dengan TTL lebih panjang, dibuang saat sakelar diubah.
- Tidak ada query tambahan untuk pelacakan "sudah tampil" — itu urusan browser.

Hasil bersih pada keadaan mantap: **nol query tambahan** untuk user yang sudah
`SELESAI` atau dikecualikan (cache), dan satu pembacaan baris ber-indeks untuk
sisanya.

## 10. Penanganan galat

| Keadaan | Perilaku |
|---|---|
| Submit padahal sudah `SELESAI` | Ditolak server (409), UI mengarahkan ke halaman ringkasan |
| Validasi server gagal | Galat per-field; wizard melompat ke langkah yang bermasalah |
| Draf gagal tersimpan ke server | Tidak menghalangi user; draf tetap disimpan cadangan di `localStorage` |
| Kampanye dimatikan | Modal & bar hilang; endpoint form menolak dengan pesan ramah |
| Field atasan dikirim oleh non-PHL | Diabaikan server, tidak ikut tersimpan |
| Field atasan kosong padahal PHL | Ditolak server meski browser diakali |
| **Prefill menyalin `Tgl_Lahir` yang rusak** | **Diterima apa adanya.** Di DB dev 56 dari 957 karyawan aktif punya `Tgl_Lahir` 2019–2025 — jelas bukan tanggal lahir (A2 = 2025-04-10). Sejak batas usia dilepas, nilai itu lolos validasi dan ikut terkumpul sebagai jawaban resmi. Karyawan bisa mengoreksinya di layar, tetapi yang tidak memeriksa akan mengirimkannya kembali. **Perbaikan master data adalah pekerjaan terpisah yang tetap perlu dilakukan**, dan angka di atas harus dicek ulang di produksi. |

## 11. Pengujian

Yang wajib lulus:

1. Jatah penundaan berhenti tepat di `maks_penundaan` dan **tidak** bisa direset
   dengan logout–login atau ganti browser.
2. Karyawan yang dikecualikan tidak pernah melihat modal maupun bar.
3. Submit kedua ditolak, kecuali admin sudah membuka kunci.
4. Buka kunci menulis baris submission **baru**; baris lama turun ke `Flag_Aktif = 'T'`.
5. Field atasan wajib untuk PHL, ditolak/diabaikan untuk non-PHL — diuji lewat
   permintaan server langsung, bukan lewat UI.
6. Draf terpulihkan: tutup browser di langkah 3, buka lagi, kembali di langkah 3.
7. Modal terkunci tidak bisa ditutup lewat Esc, klik-luar, maupun tombol tutup.
8. Kunci gulir terlepas dengan benar saat modal ditutup dan saat komponen dilepas.
9. Ketiga nilai `frekuensi_modal` berperilaku sesuai namanya.
10. Sakelar mati menyembunyikan modal **dan** menutup endpoint-nya.

Untuk uji ujung-ke-ujung, Playwright dijalankan lewat vhost
(`hcis-kpi-grouped.test`), bukan `artisan serve` — `artisan serve` di Windows
melayani satu permintaan pada satu waktu dan gejalanya menyamar jadi "elemen
tidak ditemukan".

## 12. Di luar lingkup

- Form builder / kampanye yang bisa dibuat sendiri oleh HR
- Sinkronisasi otomatis ke tabel `Karyawan`
- Alur verifikasi/persetujuan HR atas jawaban
- Verifikasi OTP untuk email dan nomor HP

## 13. Urutan pengerjaan

| Fase | Isi | Bisa dilihat hasilnya? |
|---|---|---|
| 1 | DDL, config, skema field, model, `StatusPengumpulanService`, shared prop | Belum (fondasi) |
| 2 | Halaman wizard 4 langkah + peninjauan + ringkasan + `PengirimanDataService` | Ya — form sudah bisa diisi lewat URL langsung |
| 3 | Modal hero + bar menetap di `AppShell`, tiga mode frekuensi | Ya — fitur terasa utuh bagi karyawan |
| 4 | Admin: pantau, detail, pengecualian, buka kunci, sakelar, audit | Ya — HR bisa bekerja |
| 5 | Ekspor Excel, pemeriksa kualitas data, kirim pengingat | Ya — lingkaran tertutup |

Setiap fase berdiri sendiri dan aman dirilis tanpa fase berikutnya. Fase 1–2 bisa
naik ke produksi dengan sakelar kampanye dalam keadaan mati.
