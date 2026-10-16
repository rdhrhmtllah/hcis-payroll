# DML-2026-113 — Status penugasan TERKUMPUL

**Modul:** KPI / Performance Appraisal
**Objek:** `N_PA_Assignment_Status` (satu baris)
**Status:** dev DONE, tracker PENDING
**Tanggal:** 7 September 2026

## Sebab

Pengiriman satu-pintu penilai (`PaRaterSubmitService`, 7 September 2026) menambah satu
keadaan di antara "berjalan" dan "selesai": lembar yang sudah diisi penuh dan terpetakan
utuh, tetapi penilainya belum mengirim seluruh tugasnya di siklus itu. Namanya
**TERKUMPUL**, dan nilainya belum ikut dihitung.

`N_PA_Assignment.Status_Penugasan` bukan kolom teks bebas: ia foreign key ke
`N_PA_Assignment_Status.Kode_Status` lewat `FK_PA_Assignment_Status_Master`. Tanpa barisnya
di master, setiap penulisan TERKUMPUL ditolak basis data.

## Kenapa ini luput

Saat merancang status itu, yang diperiksa adalah CHECK constraint pada `N_PA_Assignment` —
dan memang tidak ada satu pun. Kesimpulannya keliru: **untuk kolom berisi kode, CHECK bukan
satu-satunya penjaga.** Foreign key ke tabel master melakukan pekerjaan yang sama, dan tidak
muncul di `sys.check_constraints`.

## Bentuk kegagalannya

Ini bagian yang paling mahal, dan sebabnya dicatat di sini: penolakannya **tidak** muncul
sebagai galat yang menyebut foreign key.

Kiriman penilai masuk dalam dua langkah — payload disimpan lebih dulu, baru dipetakan. Yang
gagal langkah kedua. Akibatnya:

| Yang terjadi | Yang terlihat |
| --- | --- |
| Payload tersimpan, `Flag_Terpeta='T'`, nol butir terpetakan | Kiriman "berhasil" |
| `Status_Penugasan` tetap `BELUM_MULAI` | Layar penilai: lembar itu **belum dinilai** |
| Penjaga kiriman kembar mengenali hash yang sama | Isi ulang: **"sudah pernah terkirim"** |

Dua pesan yang saling bertentangan pada satu baris yang sama, dan tak satu pun menyebut
sebab sebenarnya. Terlihat di dev pada penugasan 22545 (Aji, kategori SELF).

## Yang dijalankan

Satu `INSERT` ber-`IF NOT EXISTS`:

```
Kode_Status  = 'TERKUMPUL'
Nama_Status  = 'Terisi, Belum Dikirim'
Urutan_Alur  = 6
Flag_Aktif   = 'Y'
```

`Urutan_Alur` **6, di ekor** — bukan diselipkan di antara BERJALAN (2) dan SELESAI (3).
Menyelipkannya berarti menggeser nomor tiga baris yang sudah ada, dan nomor yang bergeser
bisa dipegang layar atau laporan mana pun yang menyimpannya.

## Yang diperiksa di dev

- Enam status terdaftar; lima yang lama utuh (ada penjaga tersendiri untuk itu).
- Payload 1942 dipetakan ulang setelah baris master ada: 22 butir, kontrak lengkap,
  `Status_Penugasan` naik ke `TERKUMPUL`, `Finished_At` terisi.
- Nol payload lain yang tersangkut (`Flag_Terpeta='T'`).

## Pelajaran yang berlaku ke depan

Sebelum menulis nilai baru ke kolom berisi kode, tanyakan **dua-duanya**: CHECK constraint
dan foreign key. Dan waspadai jalur dua langkah yang commit-nya terpisah — kegagalan di
langkah kedua meninggalkan keadaan setengah jadi yang terbaca sebagai keberhasilan.
