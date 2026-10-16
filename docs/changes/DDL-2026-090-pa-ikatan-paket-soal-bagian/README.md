# DDL-2026-090 — Ikatan paket soal berbasis bagian

**Modul:** KPI / Performance Appraisal
**Tanggal:** 9 September 2026
**Spesifikasi:** `docs/superpowers/specs/2026-09-09-pa-ikatan-paket-soal-section-kompetensi-design.md`
**Rencana:** `docs/superpowers/plans/2026-09-09-pa-ikatan-paket-soal-section-kompetensi.md`

## Kenapa

HC Learn mengubah kontrak paket soal PA. Sebelumnya mereka melempar kode soal **beserta
kompetensinya**, dan seluruh pemeriksaan cakupan di `PaQuestionBindingMasterService`
bersandar pada `Detail[].Kode_Kompetensi`. Sekarang yang dilempar adalah ujian beserta
paket, alat tes, dan bagian-bagiannya — dan bagian-bagian itu **tidak berlabel
kompetensi**.

Kompetensi karena itu berpindah menjadi milik kita: admin HCIS yang memetakan tiap bagian
ke kompetensi mana ia mengukur. Itu menuntut satu tabel turunan di bawah ikatan.

## Tiga keputusan yang membentuk skema ini

**Yang diikat UJIAN, bukan paket.** Dokumentasi HC Learn menyebut `id_master_ujian`
sebagai "satuan yang dijadwalkan kepada peserta", dan `/api/v1/pa/hasil/{id_ujian_token}`
juga berpangkal di sana. Mengikat paket berarti mengikat sesuatu yang tidak bisa
dijadwalkan — dan itu baru ketahuan saat penjadwalan, bukan saat pengikatan. Karena itu
`Kode_Paket_Sumber` diganti nama menjadi `Id_Master_Ujian`, dengan `Id_Master_Paket_Ujian`
disimpan berdampingan sebagai keterangan.

**Identitas bagian MAJEMUK.** HC Learn memastikan pada 9 September 2026 bahwa mereka tidak
punya id per-instance untuk bagian. Yang tersedia hanya `id_jenis_soal`, dan dokumentasi
mereka sendiri menyebutnya pengenal **bank soal**: *"Bank yang sama bisa dipakai beberapa
alat tes."* Jadi identitasnya pasangan `(Id_Jenis_Indikator, Id_Jenis_Soal)`.

Dua field lain yang tampak menggoda dan keduanya ditolak: posisi dalam array bergeser saat
penyisipan sehingga label pindah ke bagian yang salah tanpa gejala, dan `kode_soal` adalah
kode jenis soal yang boleh null dan berulang seperti banknya.

**`Id_Jenis_Indikator` di DETAIL, bukan header.** Satu paket boleh memuat dua alat tes —
bentuk yang dinyatakan HC Learn akan dipakai PA 360 — sehingga satu sel matriks
menyeberangi keduanya. Menaruhnya di header memaksa satu sel hanya bisa memuat satu alat
tes, dan ujian dua-alat-tes menjadi mustahil diikat.

## Objek yang disentuh

| Objek | Perubahan |
|---|---|
| `N_PA_Question_Binding_Master` | `Kode_Paket_Sumber`→`Id_Master_Ujian`, `Nama_Paket_Sumber`→`Nama_Ujian_Sumber`, `Jumlah_Soal`→`Jumlah_Bagian`, `Sidik_Susunan`→`Sidik_Bagian`; +`Id_Master_Paket_Ujian`, +`Nama_Paket_Ujian_Sumber`, +`Status_Kesehatan`, +`Masalah_Json`; isinya dikosongkan |
| `N_PA_Question_Binding_Master_Detail` | **tabel baru** — pemetaan bagian↔kompetensi, dua indeks unik |
| `N_PA_Cycle_Question_Package` | `Kode_Paket_Sumber`→`Id_Master_Ujian`, `Nama_Paket_Sumber`→`Nama_Ujian_Sumber`; +`Id_Master_Paket_Ujian`, +`Nama_Paket_Ujian_Sumber`; isinya dikosongkan |
| `N_PA_Cycle_Question_Package_Item` | `Kode_Soal`→`Id_Jenis_Soal`, `Nama_Soal_Sumber`→`Nama_Bagian_Sumber`; +`Id_Jenis_Indikator`, +`Nama_Alat_Tes`; `PA_Competency_Id` jadi NOT NULL; dua indeks unik; isinya dikosongkan |
| `N_PA_Competency_Kode_Soal` | **DROP** |

## Data yang dihapus, dan kenapa aman

| Tabel | Baris | Alasan |
|---|---|---|
| `N_PA_Question_Binding_Master` | 45 | seluruhnya berkode `SIM-*` — artefak simulasi lokal, mengacu kontrak kode-soal yang sudah tidak berlaku |
| `N_PA_Cycle_Question_Package` | 49 | milik siklus uji development |
| `N_PA_Cycle_Question_Package_Item` | 1.160 | sda. **Wajib** dikosongkan sebelum bagian 5: ada **160 grup (360 baris)** dengan `PA_Competency_Id` rangkap dalam satu paket, sehingga `UX_PA_CycleQItem_Kompetensi` ditolak. Itu wajar pada kontrak lama — satu kompetensi diukur beberapa soal — dan justru itulah yang berubah. `PA_Competency_Id` NULL: **nol** baris, jadi `ALTER COLUMN ... NOT NULL` sendiri sebenarnya lolos |
| `N_PA_Competency_Kode_Soal` | 0 | tak pernah terisi; 0 rujukan kode, 0 FK masuk |

**Terukur di development pada 9 September 2026.** Sebelum dijalankan di production,
periksa dulu apakah keempat angka itu masih nol/artefak di sana — bila production sudah
memuat ikatan sungguhan, `DELETE` pada bagian 1 harus ditinjau ulang lebih dulu.

## Urutan wajib

Bagian 1 (`DELETE`) harus mendahului bagian 5 (`ALTER COLUMN ... NOT NULL` dan indeks
unik). Membaliknya membuat seluruh transaksi gagal.

## Pembalikan

`drop.sql` mengembalikan **struktur** saja. Data yang dihapus tidak dipulihkan, dan memang
tidak seharusnya — baris-baris itu berkontrak kode-soal yang sudah tidak bisa dibaca kode
mana pun setelah perubahan ini.
