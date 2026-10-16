# DML-2026-013 — Bersihkan pengesahan pada item SOP non-dokumen

**Tanggal:** 20 Agustus 2026
**Target:** `Web_HRIS` (development, host team311)
**Spec:** `docs/superpowers/specs/2026-08-20-master-sop-profil-tipe-design.md` §5

## Sasaran

Menghapus data pengesahan pada item SOP bertipe FORM, VIDEO, dan KUIS.

## Alasan

Pengesahan hanya berlaku untuk tipe DOKUMEN — ditetapkan di profil tipe
(`SopTipeItem::PROFIL`) pada sesi 20 Agustus 2026.

Data yang ada pada tipe lain terkumpul **karena layar menyodorkan fieldnya**,
bukan karena dibutuhkan. Dua angka berdampingan yang membuktikannya:

| | |
| --- | --- |
| Versi FORM dengan `Nomor_Pengesahan` | **47%** |
| Item non-dokumen dengan `Nomor_SOP_Resmi` | **0 dari 17** |

Bedanya bukan kebutuhan. Layar yang satu meminta, yang lain tidak, dan orang
mengisi apa yang disodorkan.

Membiarkannya membuat panel ambigu dibaca: sebuah form tampak "disahkan oleh"
seseorang untuk sesuatu yang memang tidak mengenal pengesahan.

## Cakupan

Diukur tepat sebelum eksekusi:

| Sasaran | Jumlah |
| --- | --- |
| Versi non-DOKUMEN dengan ≥1 kolom pengesahan terisi | 10 |
| Baris `N_SOP_Item_Versi_Pengesah` non-dokumen | 17 pada 7 versi |
| Di antaranya berstatus **PUBLISHED** | 1 |

**Versi PUBLISHED ikut dibersihkan.** Keputusan sadar pemilik proyek —
pembersihan diminta menyeluruh agar tidak ambigu, dan lingkungannya
pengembangan. Konsekuensinya satu item yang sedang terbit kehilangan blok tanda
tangan yang pernah tampil padanya.

**`Tanggal_Berlaku` tidak disentuh.** Kapan sebuah versi mulai berlaku bermakna
untuk form, video, dan kuis juga; ia bukan bagian dari pengesahan formal.

## Prasyarat

Penjaga terbit **sudah** sadar tipe — `SopDokumenService::pastikanAdaPengesah()`,
commit `9a436ef1`.

Tanpa itu, tujuh versi yang kehilangan blok tanda tangan tidak bisa diterbitkan
lagi: penjaga menuntut sesuatu yang profil tipenya sendiri nyatakan tidak
berlaku. **Urutan ini tidak boleh terbalik.**

## Verifikasi sesudah eksekusi

```bash
php artisan tinker --execute="
\$q = DB::table('N_SOP_Item_Versi as v')->join('N_SOP_Dokumen as d','d.Id_SOP_Dokumen','=','v.Id_SOP_Dokumen')->where('d.Tipe','<>','DOKUMEN');
echo 'versi berkolom pengesahan (harus 0): '.(clone \$q)->where(function(\$w){ foreach(['Nomor_Pengesahan','Disahkan_Oleh','Jabatan_Pengesah','Tanggal_Pengesahan','Kode_Karyawan_Pengesah'] as \$k) \$w->orWhereNotNull('v.'.\$k); })->count().PHP_EOL;
echo 'Tanggal_Berlaku non-dokumen (harus TETAP 6): '.(clone \$q)->whereNotNull('v.Tanggal_Berlaku')->count().PHP_EOL;
"
```

Dan pastikan pengesahan DOKUMEN tidak ikut terhapus:

```bash
php artisan tinker --execute="
echo 'baris blok pengesah DOKUMEN (harus TETAP 14): '.DB::table('N_SOP_Item_Versi_Pengesah as p')->join('N_SOP_Item_Versi as v','v.Id_SOP_Item_Versi','=','p.Id_SOP_Item_Versi')->join('N_SOP_Dokumen as d','d.Id_SOP_Dokumen','=','v.Id_SOP_Dokumen')->where('d.Tipe','DOKUMEN')->count().PHP_EOL;
"
```

## Pemulihan

Tidak ada jalur pemulihan otomatis — data pengesahan non-dokumen tidak
direplikasi ke mana pun. Bila pembersihan ini kelak dianggap keliru, data harus
diisi ulang manual lewat panel versi (yang setelah perubahan ini hanya
menyediakan blok pengesahan untuk DOKUMEN).

Karena itu **ambil cadangan kedua tabel sebelum menjalankan** bila lingkungan
target bukan pengembangan.
