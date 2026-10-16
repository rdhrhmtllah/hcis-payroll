# Builder Paket SOP jadi perakit murni + sakelar tipe item

Tanggal: 2026-08-27
Status: disetujui, siap diimplementasikan

## Masalah

Master Dokumen SOP sekarang memiliki identitas dokumen lengkap (jenis, departemen,
APQC, entitas, nomor resmi) dan wizard versi lima langkah dengan pengesahan.
Builder Paket masih membawa duplikat peran itu dari masa sebelum Master ada:

- `ItemFields.vue` masih memuat blok "buat dokumen baru" berisi seluruh identitas
  dokumen (~230 baris template + state `dokBaru` + empat pemuat opsi master-data).
- `SopAdminBuilderController::buatDokumenBaru()` masih bisa membuat dokumen dan
  menerbitkan versi pertamanya dalam satu permintaan.
- `SopVersionPanel.vue` masih menulis versi: tambah, tandai siap rilis, terbitkan,
  hapus draft — melewati wizard Pengajuan → Review → Pengesahan → Pemberkasan.

Dua di antaranya sudah mati di layar tetapi hidup di server, dan yang ketiga masih
hidup di dua-duanya. Sisanya berupa kode yang tak terjangkau: `Mode_Dokumen` tak
pernah bernilai `'baru'` karena tombol pemindah modenya sudah dicabut, sehingga
`modeDokBaru` selalu `false`.

Terpisah dari itu, tujuh tipe item (`DOKUMEN`, `FORM`, `VIDEO`, `KUIS`, `TAUTAN`,
`PARAGRAF`, `ABSENSI`) tak punya sakelar apa pun. Satu-satunya "penonaktifan" yang
ada ditulis keras di satu baris layar:
`tipeList: Object.keys(TIPE_ITEM).filter((t) => t !== 'ABSENSI')`.

## Keputusan

1. **Builder = perakit murni.** Item ber-versi (DOKUMEN/FORM/VIDEO/KUIS) hanya lahir
   dengan menunjuk Dokumen SOP master yang sudah punya versi `PUBLISHED`. Item tanpa
   versi (TAUTAN/PARAGRAF/ABSENSI) tetap dibuat di Builder — Master memang hanya
   menerima tipe ber-versi (`SopAdminDokumenController` validasi `in:VERSIONED`).
2. **Siklus versi milik Master seutuhnya.** Builder hanya membaca.
3. **Sakelar per tipe item**, disimpan di `N_SOP_Setting`, diatur di halaman Setup
   System. Mematikan sebuah tipe **hanya menutup pembuatan baru**; data lama tetap
   hidup dan tetap disajikan ke karyawan.
4. **Tipe mati disembunyikan**, tidak ditampilkan dalam keadaan redup.
5. **Dokumen master bertipe mati tak boleh dijadikan item baru** — memasang item
   adalah pembuatan.
6. **Data lama tidak disentuh.** Tidak ada migrasi, tidak ada DDL.

## Rancangan

### A. Layanan sakelar tipe

`App\Services\LMS\SOP\Support\SopTipeItemAktif` — kelas baru. `SopTipeItem` tetap
murni konstanta dan bebas dari database; yang menyentuh setelan adalah kelas ini.

```
aktif(): array                    // tipe menyala, urut mengikuti SopTipeItem::SEMUA
aktifVersioned(): array           // irisan dengan SopTipeItem::VERSIONED
nyala(string $tipe): bool
kunci(string $tipe): string       // "tipe_item_dokumen"
pesanMati(string $tipe): string   // kalimat penolakan, satu sumber
```

Sumber nilai: `SopSetting::bool(kunci($tipe), default)` — cache database 10 menit
dengan fallback yang sudah ada. Bila `N_SOP_Setting` tak terbaca sama sekali,
seluruh tipe kembali ke defaultnya, jadi tak ada fitur yang mati karena setelan
hilang.

Default — sama persis dengan perilaku hari ini:

| Kunci | Default |
|---|---|
| `tipe_item_dokumen` | `Y` |
| `tipe_item_form` | `Y` |
| `tipe_item_video` | `Y` |
| `tipe_item_kuis` | `Y` |
| `tipe_item_tautan` | `Y` |
| `tipe_item_paragraf` | `Y` |
| `tipe_item_absensi` | `T` |

`tipe_item_absensi` bawaannya mati karena memang sudah mati hari ini — bedanya
alasannya kini bisa dibaca dan diubah admin, bukan tersembunyi di satu baris filter.

### B. Tempat sakelar berlaku

Penjaga sungguhannya di server; layar hanya berhenti menawarkan. Aturan yang cuma
dipasang di satu dari dua pintu adalah cacat berulang di modul ini.

| Pintu | Perubahan |
|---|---|
| `SopAdminBuilderController::storeItem` | tolak 422 bila tipe mati |
| `SopAdminBuilderController::updateItem` | tolak 422 bila tipe **berubah** ke tipe mati (menyunting item lama bertipe mati tetap boleh) |
| `SopAdminDokumenController::storeDokumen` | `in:` jadi irisan `VERSIONED ∩ aktif` |
| `SopAdminBuilderController::index` | kirim `tipeItemAktif` sebagai prop halaman |
| `SopBuilder.vue` | `tipeList` dari prop, bukan konstanta frontend |
| `SopDokumenPickerModal` | tak menawarkan dokumen bertipe mati |

Tak berubah: halaman karyawan, progres, monitoring, export, penjadwalan.

### C. Pencabutan di Builder

**Server**

- `buatDokumenBaru()` dihapus beserta cabang pemanggilnya di `storeItem`.
  Cabang itu sudah tak terjangkau (`pastikanIdentitasItem` menolak lebih dulu bila
  dokumen kosong, dan penjaga `filled(Dokumen_Baru)` menolak bila terisi), jadi yang
  dihapus adalah kode mati — tetapi kode mati yang masih memegang kemampuan
  menerbitkan dokumen tanpa pengesah bila satu penjaga di atasnya bergeser.
- Seluruh aturan validasi `Dokumen_Baru.*` (≈18 baris) dihapus dari `validateItem`.
- Pesan `pastikanIdentitasItem` berhenti menawarkan "isi judulnya untuk membuat
  dokumen baru" dan mengarahkan ke Master.
- Lima rute tulis versi dicabut dari `routes/LMS/SOP/Governance/SopGovernance.php`:
  `POST /item/{id}/versi/store`, `PATCH /versi/{id}/ready`,
  `PATCH /versi/{id}/publish`, `DELETE /versi/{id}`. `GET` daftar versi dan
  `GET` konten tetap. (`PATCH /versi/{id}/rollback` yang dipanggil layar memang
  tak pernah punya rute — tombolnya selalu 404.)
- Metode `storeVersi`, `setReady`, `publishVersi`, `rollbackVersi`, `destroyVersi`
  di `SopBerkasController` dihapus.

> Rute `publish` dulu sengaja dibuka untuk memecah jalan buntu rilis pertama: publish
> paket menolak selama ada versi `DRAFT`, dan tak ada pintu lain yang bisa
> menerbitkannya. Setelah rombakan ini jalan buntu itu tak bisa terjadi — item hanya
> boleh menunjuk dokumen yang **sudah** punya versi `PUBLISHED`
> (`pastikanDokumenSiap`). Mencabutnya menutup satu-satunya jalur yang melewati
> wizard pengesahan.

**Layar**

- `ItemFields.vue`: blok `modeDokBaru`, state `dokBaru`, pemuat `jenisOpsi` /
  `apqcOpsi` / `departemenOpsi` / `entitasOpsi`, `ikutkanLingkup`,
  `pakaiDokumenBaru`, `pakaiDokumenAda`, `sasaranUjian` cabang dokumen-baru, dan
  blok "Konten Versi 1" dihapus. Prop `forceKontenPick` dicabut.
- `SopVersionPanel.vue` (1.316 baris, hanya dipakai Builder) diganti
  `SopVersiRingkas.vue` yang read-only: versi berlaku, riwayat, lihat konten, satu
  tautan ke Master Dokumen. Diganti, bukan dipangkas, supaya tak menyisakan seribu
  baris yang separuhnya mati.
- `SopBuilder.vue`: cabang "ganti tipe" (`editTypeChanged`, `editOrigFile`,
  `editNowFile`, `editTypeToFile`) dan pemeriksaan `needKonten` di `saveItem`
  dihapus. Urutan "pilih tipe → pilih dokumen" DIPERTAHANKAN, bukan dibalik jadi
  dokumen-dulu: tipe yang dipilih itulah yang menyaring isi `SopDokumenPickerModal`
  (`:tipe="form.Tipe_Item"`). Membalik urutannya berarti menawarkan seluruh
  dokumen lalu menolak yang tipenya tak cocok — penolakan yang datang setelah
  memilih.
- `itemPayload.js`: `Mode_Dokumen` dan `Dokumen_Baru` tak lagi dikirim.

### D. Data lama

Item yang dokumennya belum punya versi terbit tetap hidup dan tetap tampil.
`SopVersiRingkas` menampilkan keadaannya apa adanya beserta tautan ke wizard versi
Master. Tidak ada baris yang diubah.

## Pengujian

Setiap penjaga baru punya uji yang menuntut **penolakan** — penjaga yang tak pernah
terlihat merah bukan penjaga.

1. `storeItem` tipe ber-versi tanpa `Id_SOP_Dokumen` → 422, dan `N_SOP_Dokumen`
   tidak bertambah satu baris pun.
2. `storeItem` membawa `Dokumen_Baru` → 422.
3. `storeItem` / `storeDokumen` bertipe mati → 422 walau layar tak menawarkannya.
4. `updateItem` yang **tidak** mengubah tipe pada item bertipe mati → tetap 200.
5. Lima rute versi Builder → 404/405.
6. Sakelar default = perilaku lama: enam tipe hidup, `ABSENSI` mati.
7. Uji **efek**, bukan konstanta: item VIDEO yang sudah terpasang tetap tersaji ke
   karyawan setelah `tipe_item_video` dimatikan.

Vitest: palet tak merender tipe mati; `ItemFields` tak lagi punya mode dokumen baru;
`SopVersiRingkas` tak punya satu pun tombol tulis.

Karena `SopTipeItemAktif` menyentuh beberapa controller sekaligus, suite SOP
dijalankan penuh, bukan hanya berkas yang disentuh.

### E. Dua pintu lain yang ikut ditutup

Ditemukan saat mengerjakan, dan keduanya persoalan yang sama — Dokumen SOP lahir
di luar Master:

1. **Item ber-versi baru di paket TERBIT tanpa DDL-2026-033.** Jalur lamanya
   menyimpan judul + konten kandidat, lalu `SopPaketDraftService::publish` →
   `ensureForItem()` → `createForItem()` membuatkan dokumennya saat promosi —
   menyalin `Nomor_SOP_Resmi` tanpa lewat penjaga nomor kembar, dan tanpa satu
   pun tahap pengesahan. Sekarang ditolak 422 dengan pesan yang menyebut DDL-nya;
   layar menyebutkan alasannya di depan.
2. **Mengubah tipe item menjadi tipe ber-versi.** `changeType()` melahirkan versi
   pertama dari konten Library dan `ensureForItem()` membuatkan dokumennya —
   pintu yang sama, lewat tombol yang berbeda. Ditolak, dengan jalan keluar yang
   disebutkan: hapus item, buat dokumennya di Master, tambahkan item baru.

Keduanya membuat `validateKontenForTipe()` di `SopAdminBuilderController` tak
punya pemanggil lagi; ikut dihapus.

## Yang sengaja TIDAK dikerjakan

- Tidak ada DDL. Baris `N_SOP_Setting` opsional — halaman setelan jatuh ke default
  bila barisnya belum ada.
- Tidak ada perubahan pada tabel, kolom, atau data.
- Tidak ada penyaringan tipe mati di halaman karyawan, monitoring, atau export.
