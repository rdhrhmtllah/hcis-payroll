# DML-2026-131-visitor-role-admin-tamu-654

## Ringkas

`UserId_Web` 654 memegang kunci halaman **`visitorMasterDataTamuPage`** tanpa
role `ADMIN_TAMU`. Rolenya ditambahkan supaya aksesnya punya dasar.

| | |
|---|---|
| Change ID | `DML-2026-131-visitor-role-admin-tamu-654` |
| Target | `Web_HRIS` (development); production hanya lewat SQL Tracker |
| Bergantung pada | `DML-2026-108` / `DML-2026-109` (pemberian role domain pertama) |
| Status | `DEV_EXECUTED` |

## Sebab

Hak akses Visitor punya **dua sumber**: kunci per orang di `HRIS_Page_Access`,
dan role domain di `N_HRIS_Role_User`. Kunci bisa dihibahkan manual; role
tidak. Karena itu `PemisahanMasterDataTest` menagih bahwa setiap pemegang kunci
domain juga memegang role domainnya — kunci tanpa role berarti akses yang tidak
bisa ditelusuri asalnya.

Pemindaian development menemukan satu pelanggaran:

| UserId_Web | Kunci dibuat | Role `ADMIN_TAMU` |
|---|---|---|
| 624 | 2026-09-15 08:45 | ada |
| 4 | 2026-09-16 11:29 | ada |
| 1885 | 2026-09-16 11:29 | ada |
| **654** | **2026-09-21 16:38** | **tidak ada** |

Rekan se-role logistic user 654 (yaitu 624) memang memegang kunci yang sama,
tetapi lewat `ADMIN_TAMU` — bukan lewat `TAMU_DEPT_LOGISTIC`. Jadi role
logistic **tidak** dengan sendirinya memberi hak master data.

Keputusan user (2026-09-22): aksesnya memang dikehendaki, sehingga yang kurang
adalah rolenya — bukan kuncinya yang perlu dicabut.

## Objek

| Tabel | Perubahan |
|---|---|
| `N_HRIS_Role_User` | INSERT satu baris (`UserId_Web` 654, role `ADMIN_TAMU`, `Assigned_By='DML-2026-131'`) |
| `N_SYSTEM_Database_Change` | INSERT pendaftaran change |

Dua hal yang dijaga artefaknya:

- **Role dicari lewat `Role_Code`, bukan `Id_Role` yang ditempel.** Id identity
  berbeda antar lingkungan; menempelkannya membuat artefak ini memberi role
  yang salah di production tanpa satu pun galat.
- **Penjaga `NOT EXISTS`.** `N_HRIS_Role_User` tidak punya kunci unik pada
  (`UserId_Web`, `Id_Role`), jadi dijalankan dua kali tanpa penjaga akan
  meninggalkan dua baris kembar. Baris nonaktif dari masa lalu dinyalakan
  lewat UPDATE, bukan digandakan.

## Riwayat eksekusi development

| Tanggal | Artifact | Hasil |
|---|---|---|
| 2026-09-22 | `data-change.sql` | OK — 1 baris role dibuat; pemegang kunci tanpa dasar 4 → 0. |
| 2026-09-22 | `data-change.sql` (ulang) | OK — tetap 1 baris, tidak ada kembar. Idempoten terbukti. |

## Pekerjaan user

- Rekam di SQL Tracker.
- **Periksa production sebelum menjalankan.** `UserId_Web` di production belum
  tentu orang yang sama. Jalankan kueri verifikasi di kaki `data-change.sql`
  lebih dulu; kalau yang muncul di sana bukan 654, artefak ini perlu
  disesuaikan nomornya — jangan dijalankan apa adanya.
