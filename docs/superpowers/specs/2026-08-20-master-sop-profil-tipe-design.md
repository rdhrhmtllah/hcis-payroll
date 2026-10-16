# Master SOP — Profil Tipe untuk Panel Item

**Tanggal:** 20 Agustus 2026
**Modul:** LMS › SOP › Governance (Master SOP, Builder)
**Terkait:** penggantian nama "Master Dokumen SOP" → "Master SOP" (disetujui, belum dikerjakan)

Dokumen ini **tidak** mengubah skema database. Ia mengubah *apa yang diminta
layar* per tipe item, menyatukan sumbernya, dan membersihkan data yang terlanjur
terkumpul karena layar meminta hal yang salah.

---

## 1. Masalah

Master SOP memegang empat tipe item — DOKUMEN, FORM, VIDEO, KUIS — dan
menyodorkan **satu setelan field yang sama** untuk keempatnya. Setelan itu
dirancang untuk dokumen SOP.

### 1.1 Panelnya memang tidak membedakan apa pun

| Berkas | Baris | Cabang per-tipe |
| --- | ---: | ---: |
| `SopDokumenDetailDrawer.vue` | 350 | **0** |
| `SopDokumenVersiModal.vue` | 1082 | 2 |

Dua cabang yang ada (`isKuis`, `isForm`) hanya mengatur hal mekanis — berkas vs
ujian, pratinjau vs unduh. Field pengesahan dirender tanpa syarat tipe sama
sekali: `v-if`-nya memeriksa *apakah nilainya ada*, bukan *apakah tipe ini
membutuhkannya*.

### 1.2 Dan datanya menunjukkan bedanya ada di dua lapis yang tak tersentuh

Diukur dari `Web_HRIS` pada 20 Agustus 2026.

**Identitas — `N_SOP_Dokumen.Nomor_SOP_Resmi`:**

| Tipe | Terisi |
| --- | --- |
| DOKUMEN | 14 dari 19 |
| FORM | **0 dari 12** |
| KUIS | **0 dari 4** |
| VIDEO | **0 dari 1** |

Bukan jarang — nol. Tidak satu pun dari 17 item non-dokumen pernah punya nomor
resmi.

**Tata kelola — kolom pengesahan di `N_SOP_Item_Versi`:**

| Kolom | DOKUMEN (28) | FORM (19) | KUIS (2) | VIDEO (2) |
| --- | --- | --- | --- | --- |
| `Nomor_Pengesahan` | 29% | 47% | 0% | 50% |
| `Disahkan_Oleh` | 36% | 42% | 0% | 50% |
| `Tanggal_Pengesahan` | 29% | 32% | 0% | 0% |

Dan blok tanda tangan `N_SOP_Item_Versi_Pengesah`: **FORM 17 baris pada 7 versi**,
DOKUMEN 14 baris pada 7 versi, VIDEO dan KUIS nol.

### 1.3 Sebabnya, dan kenapa ini bukan salah pengguna

Perhatikan dua angka berdampingan: nomor resmi **0%** di form, nomor pengesahan
**47%** di form. Bedanya bukan kebutuhan — bedanya **layar yang satu meminta,
layar yang lain tidak**. Orang mengisi apa yang disodorkan.

Pemilik proyek memastikannya pada sesi ini: pengisian itu **mengikuti UI, bukan
kebutuhan**. Form tidak butuh pengesahan formal.

Akar masalahnya karena itu bukan "panelnya kurang rapi", melainkan **UI
memutuskan sendiri field apa yang berlaku, dan tidak ada pihak kedua yang
menyetujuinya.** Validasi server tidak tahu apa-apa soal tipe.

---

## 2. Keputusan yang mengikat

Diambil pemilik proyek, 20 Agustus 2026. Tidak dibuka ulang saat implementasi.

1. **Pendekatan: profil tipe yang deklaratif.** Bukan memperbanyak `v-if`, bukan
   empat komponen panel terpisah.
2. **Data pengesahan non-dokumen dibersihkan**, bukan sekadar disembunyikan —
   supaya tidak ambigu saat dibaca. Pemilik menyatakan ini aman karena masih di
   lingkungan pengembangan.
3. **`Tanggal_Berlaku` tetap untuk semua tipe.** Kapan sebuah versi mulai berlaku
   bermakna untuk form, video, dan kuis juga; ia bukan bagian dari pengesahan
   formal.
4. **Nomor SOP Resmi hilang sama sekali dari layar untuk non-dokumen**, bukan
   ditampilkan-tapi-terkunci.

---

## 3. Profil tipe

Rumahnya `app/Services/LMS/SOP/Support/SopTipeItem.php` — ia sudah menjadi
kosakata tipe di kode ini (`SEMUA`, `FILE`, `VERSIONED`). Profil ditambahkan di
sana, bukan di kelas baru, supaya "apa itu tipe" tetap punya satu alamat.

Tiga nilai, dan bedanya mengikat:

- **wajib** — blok dirender, dan validasi server menolak bila kosong.
- **berlaku** — blok dirender, boleh kosong.
- **—** — blok **tidak dirender sama sekali**, dan validasi server **menolak**
  bila field-nya tetap dikirim. Bukan sekadar disembunyikan: itulah yang
  membedakan rencana ini dari menyembunyikan `v-if`.

| Blok | DOKUMEN | FORM | VIDEO | KUIS |
| --- | --- | --- | --- | --- |
| Nomor SOP Resmi | wajib | — | — | — |
| Blok pengesahan (5 kolom + tabel penanda tangan) | wajib | — | — | — |
| Berkas | wajib | wajib | wajib | — |
| Ujian tertaut | — | — | — | wajib |
| Judul · Deskripsi · Versi · Ringkasan Perubahan | wajib | wajib | wajib | wajib |
| Tanggal Berlaku | berlaku | berlaku | berlaku | berlaku |
| Wajib Baca Ulang | berlaku | berlaku | berlaku | berlaku |

> `Wajib_Baca_Ulang` terisi 100% di semua tipe. Angka itu **tidak** membuktikan
> ia bermakna di semua tipe — kolomnya bisa saja punya nilai bawaan. Ia
> dipertahankan karena belum ada alasan mencabutnya, bukan karena datanya
> mendukung.

**Tiga konsumen, satu peta:**

1. **UI** — `SopDokumenDetailDrawer` dan `SopDokumenVersiModal` merender blok
   yang profilnya nyatakan berlaku.
2. **Validasi server** — menolak field yang tidak berlaku bagi tipenya, alih-alih
   menerimanya diam-diam.
3. **Penjaga terbit** — lihat §4.

Konsumen kedua itulah yang membedakan rencana ini dari sekadar merapikan
tampilan. Tanpa server ikut membaca peta yang sama, derau seperti 47% itu bisa
lahir lagi lewat jalur lain — Builder, impor, atau endpoint yang belum ada.

---

## 4. Penjaga terbit jadi sadar tipe

`SopDokumenService::pastikanAdaPengesah()` hari ini **buta tipe**: ia menolak
versi mana pun yang tak punya penanda tangan, dengan pesan *"dokumen terkendali
harus punya orang yang bertanggung jawab atasnya."*

Ia sengaja diletakkan di service, bukan controller, karena penerbitan punya **dua
pintu**: Master SOP lewat controllernya, dan publish paket di Builder yang
memanggil `terbitkanVersi()` langsung.

Perubahannya satu gerbang: bila profil tipe menyatakan pengesahan tidak berlaku,
penjaga lolos lebih awal.

> **URUTAN INI TIDAK BOLEH TERBALIK.** Penjaga sadar-tipe harus mendarat
> **sebelum** pembersihan data §5. Kalau dibalik, tujuh versi FORM kehilangan
> blok tanda tangannya sementara penjaga masih menuntutnya — dan form berikutnya
> yang diterbitkan ditolak tanpa jalan keluar.

---

## 5. Pembersihan data

Cakupan persis, diukur 20 Agustus 2026:

| Sasaran | Jumlah |
| --- | --- |
| Versi non-DOKUMEN dengan ≥1 kolom pengesahan terisi | **10** |
| — `Nomor_Pengesahan` | 10 |
| — `Disahkan_Oleh` | 9 |
| — `Jabatan_Pengesah` | 9 |
| — `Tanggal_Pengesahan` | 6 |
| — `Kode_Karyawan_Pengesah` | 6 |
| Baris `N_SOP_Item_Versi_Pengesah` non-dokumen | **17 pada 7 versi** |
| Di antaranya versi berstatus **PUBLISHED** | **1** |

**Versi PUBLISHED itu ikut dibersihkan.** Ini dicatat sebagai keputusan sadar,
bukan efek samping: pemilik meminta pembersihan menyeluruh agar tidak ambigu, dan
lingkungannya pengembangan. Konsekuensinya satu item yang sedang terbit
kehilangan blok tanda tangan yang pernah tampil padanya.

Dijalankan lewat **jalur DML wajib** sesuai `AGENTS.md`: change ID,
`database/dml/<id>/data-change.sql`, README di `docs/changes/<id>/`, entri di
`database/ddl/status.json`, dan pendaftaran ke `N_SYSTEM_Database_Change`. Bukan
`UPDATE` sekali jalan.

`Tanggal_Berlaku` **tidak** ikut dibersihkan (keputusan §2.3).

---

## 6. Masa berlaku: sudah dicabut, jangan dihidupkan lagi

Selama perancangan sempat diduga `Tanggal_Kadaluarsa` dan
`Tanggal_Review_Berikutnya` masih diminta form. **Dugaan itu salah**, dan
diverifikasi:

| | |
| --- | --- |
| `Tanggal_Review_Berikutnya` di `app/` | **0** kemunculan |
| `Tanggal_Kadaluarsa` di modul SOP | **0** kemunculan (19 sisanya milik VISITOR — izin kendaraan memang punya kedaluwarsa) |
| Keduanya di UI SOP | **tidak ada** |

Masa berlaku SOP memang sudah dihapus lintas-lapis pada 15 Agustus 2026. Sisa
11% pada `Tanggal_Kadaluarsa` dan 5% pada `Tanggal_Review_Berikutnya` di
`N_SOP_Item_Versi` adalah **data lama dari sebelum pencabutan**, bukan tanda
field-nya masih hidup.

Karena itu tidak ada pekerjaan di sini. Yang berlaku hanya satu larangan: profil
tipe **tidak boleh** memunculkan kembali kedua field itu untuk tipe mana pun.
Kolomnya sengaja tidak dijatuhkan dari database karena menyimpan riwayat.

---

## 7. Yang sengaja tidak dikerjakan

- **Bukan empat komponen panel terpisah.** VIDEO punya 1 item dan KUIS punya 4.
  Merancang panel penuh untuk keduanya berarti merancang dari kebisingan. Profil
  deklaratif bisa diubah semurah menyunting satu baris ketika polanya kelak
  benar-benar terlihat.
- **Tidak menjatuhkan kolom database.**
- **Tidak menyentuh `Jenis_page`, URL, atau nama tabel.**

---

## 8. Jebakan yang sudah diketahui

1. **Dua pintu penerbitan.** Master SOP dan Builder. Aturan yang hanya dipasang
   di satu controller menjaga satu pintu saja — itu sebabnya penjaga tinggal di
   service. Perubahan apa pun di §4 wajib diuji lewat **kedua** jalur.
2. **`SopDokumenDetailDrawer` dipakai dua pemanggil dengan gerbang izin
   berbeda** (tertulis di komentarnya sendiri). Mengubah bentuk propsnya
   menyentuh keduanya.
3. **Penjaga tidak berlaku surut.** Versi PUBLISHED tidak pernah melewati
   `pastikanIsiSah()` lagi. Jangan merancang seolah ia akan memvalidasi ulang.
4. **Tabel overlay draft.** Builder punya tabel bayangan (`N_SOP_Paket_Draft_*`)
   yang PK-nya beririsan dengan tabel live. Validasi baru wajib jelas ia bicara
   ke tabel yang mana.

---

## 9. Strategi uji

| Uji | Menuntut |
| --- | --- |
| profil tipe | DOKUMEN menyatakan pengesahan berlaku; FORM/VIDEO/KUIS tidak |
| penjaga terbit — dokumen | versi DOKUMEN tanpa pengesah **tetap ditolak** |
| penjaga terbit — non-dokumen | versi FORM tanpa pengesah **bisa terbit** |
| dua pintu | keduanya diuji lewat Master SOP *dan* Builder |
| validasi server | field yang tak berlaku bagi tipenya **ditolak**, bukan diterima diam-diam |
| UI dokumen | panel DOKUMEN menampilkan nomor resmi dan blok pengesahan |
| UI non-dokumen | panel FORM/VIDEO/KUIS **tidak merender** keduanya |
| field mati | `Tanggal_Kadaluarsa` dan `Tanggal_Review_Berikutnya` tak ada di form mana pun |

Penjaga "versi FORM tanpa pengesah bisa terbit" wajib **pernah dilihat MERAH**
sebelum §4 dikerjakan — kalau ia hijau sejak awal, ia tidak menguji apa pun.

---

## 10. Keputusan terbuka

Tidak ada. Empat keputusan di §2 menutup semuanya yang ditemukan selama
perancangan.
