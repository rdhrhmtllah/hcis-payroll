# DML-2026-136-visitor-matriks-role-admin-tamu

## Ringkas

`ADMIN_TAMU` dan `ADMIN_TAMU_B2B` diberi baris matriks, supaya **matriks
menjadi satu-satunya sumber kebenaran** siapa boleh melihat kategori tamu apa.

| | |
|---|---|
| Change ID | `DML-2026-136-visitor-matriks-role-admin-tamu` |
| Target | `Web_HRIS` (development); production hanya lewat SQL Tracker |
| Status | `DEV_EXECUTED` |

## Sebab

Satu pertanyaan — "siapa boleh melihat tamu kategori apa" — selama ini dijawab
**dua mekanisme yang tidak saling tahu**:

- **Kunci halaman per orang** (`HRIS_Page_Access`) untuk admin monitoring.
  Dihibahkan tangan, lalu terlupakan. `visitorJadwalTamuPage` berakhir **nol
  pemegang** tanpa ada yang menyadarinya.
- **Matriks** (`N_VISITOR_Matriks_Tamu`) untuk departemen. Terikat role,
  sehingga merawat dirinya sendiri.

Penggabungan Jadwal Kedatangan Tamu ke Monitoring Tamu memaksa keduanya
dipilih salah satu. Matriks yang dipilih.

## Yang ditulis

| Role | Kategori | Lihat | Ajukan |
|---|---|---|---|
| `ADMIN_TAMU` | REGULAR, B2B_PROCUREMENT, B2B_LOGISTIC | `Y` | `T` |
| `ADMIN_TAMU_B2B` | B2B_PROCUREMENT, B2B_LOGISTIC | `Y` | `T` |

`Flag_Ajukan` sengaja `T`: yang diperluas hanya **hak melihat**. Hak
mengajukan tamu atas nama kategori tertentu adalah keputusan lain, dan
menyelipkannya ke sini akan mengubah layar pendaftaran tanpa ada yang meminta.

`ADMIN_TAMU_B2B` tidak diberi `REGULAR` — tamu reguler bukan wilayahnya.

## Siapa yang terdampak

Keempat pemegang kunci monitoring (`1885`, `4`, `624`, `654`) **semuanya**
pemegang `ADMIN_TAMU`, sehingga sesudah change ini mereka tetap melihat persis
seperti sebelumnya — tanpa satu pun yang kehilangan akses saat kunci halaman
berhenti dipakai sebagai jalan pintas.

Dari 5 anggota grup Visitor di development, tersisa **1 orang** yang belum
bermatriks (`662` — `HC_OPS_ADMIN` + `ADMIN_PERMIT`). Ia admin permit, bukan
pengurus tamu; bila memang perlu melihat tamu, cukup ditambahkan satu baris
matriks untuk rolenya — tanpa menyentuh kode.

## Yang dijaga artefaknya

- Role dicari lewat **`Role_Code`**, bukan `Id_Role` yang ditempel: Id identity
  berbeda antar lingkungan, dan menempelkannya memberi hak kepada role yang
  salah di production tanpa satu pun galat. Bila ada kode yang tak ditemukan,
  artefak berhenti dengan `THROW` — bukan menulis separuh.
- Idempoten lewat `NOT EXISTS`, dan baris nonaktif dari masa lalu **dinyalakan**
  lewat UPDATE, bukan digandakan.

## Riwayat eksekusi development

| Tanggal | Artifact | Hasil |
|---|---|---|
| 2026-09-22 | `data-change.sql` | OK — 5 baris; keempat admin terbukti tetap melihat ketiga kategori lewat `kategoriBolehLihat()`. |

## Pekerjaan user

- Rekam di SQL Tracker.
- Pekerjaan lanjutannya (penyaringan data laporan tamu memakai matriks,
  penggabungan menu Jadwal ke Monitoring) menyusul di perubahan kode terpisah.
