# DML-2026-108-role-verifikator-per-domain

## Ringkas

Antrean verifikasi Visitor dipecah per domain, melengkapi pemisahan halamannya
(`DML-2026-105`).

| Config approval | Sebelum | Sesudah |
|---|---|---|
| `PERMIT APPROVAL` | SHE Admin (`ROLE:SHE_ADMIN`) | **Verifikator Permit** (`ROLE:ADMIN_PERMIT`) |
| `TAMU APPROVAL` | SHE Admin (`ROLE:SHE_ADMIN`) | **Verifikator Tamu** (`ROLE:ADMIN_TAMU`) |

## Kenapa muncul sekarang

Pertanyaan user 2026-09-15: apakah role verifikator permit dan tamu terpisah
atau menyatu? Jawabannya **menyatu** — kedua config menunjuk role yang sama.

Akibatnya pemisahan halaman pada `DML-2026-105` baru memisahkan **siapa boleh
menekan tombol**, bukan **kepada siapa pengajuan dikirim**. Mencabut kunci
halaman Permit dari seseorang tidak memindahkan antreannya ke orang lain; ia
tetap terkirim ke role yang sama dan berhenti di sana — menggantung, bukan
berpindah, dan tanpa satu pun galat yang menjelaskannya.

## Keputusan desain

**Keanggotaan disalin, bukan dikosongkan.** Kedua role baru mewarisi anggota
aktif `SHE_ADMIN`. Antrean yang tiba-tiba tak berpemilik tidak menampilkan galat
apa pun — pengajuannya hanya berhenti diproses, dan itu baru ketahuan ketika ada
yang menanyakan izinnya. Pemangkasan per orang adalah pekerjaan user setelahnya.

**`SHE_ADMIN` tidak disentuh.** Ia dipakai hal lain di modul SHE; mencabut
keanggotaannya di sini akan mematikan sesuatu yang tidak berhubungan dengan
pekerjaan ini.

**Pengajuan yang belum berkeputusan ikut dipindahkan.** Config hanya mengatur
pengajuan BARU. Tanpa langkah ini, 17 pengajuan yang sudah terbit tetap menunjuk
`SHE_ADMIN`, dan begitu keanggotaannya dipangkas, sisanya tidak dapat disetujui
siapa pun.

Pemeriksaan lanjutan menunjukkan **14 dari 17 itu sudah EXPIRED** (`Expired_At`
terlampaui), bukan sedang menunggu; hanya 4 yang berstatus CURRENT dan
keempatnya permit. Pemindahannya tetap benar — baris EXPIRED masih menunjuk
rolenya dan masih muncul di riwayat — tetapi angka 17 jangan dibaca sebagai
antrean hidup.

**Domainnya dikenali dari PREFIKS nomor transaksi**, bukan dari JOIN ke tabel
transaksinya: tiga pengajuan tamu di development sudah tidak punya baris
kunjungan lagi, dan JOIN akan melewatkannya begitu saja — meninggalkan tepat
baris-baris yang paling mudah terlupakan.

**Id dicari lewat kode, bukan ditulis angka.** `Id_Jenis_Approval` dan `Id_Role`
bernilai IDENTITY dan berbeda antara development dan production; angka yang
ditulis tangan akan mengarahkan approval ke role milik modul lain.

**`DISTINCT` + penjaga `NOT EXISTS` pada penyalinan keanggotaan.**
`(UserId_Web, Id_Role)` pada `N_HRIS_Role_User` tidak unik, dan barisnya memang
sudah pernah kembar di tabel ini.

## Berkas

| Peran | Path |
|---|---|
| DML | `database/dml/DML-2026-108-role-verifikator-per-domain/data-change.sql` |

Tidak ada DDL dan tidak ada perubahan kode: seluruhnya konfigurasi.

## Status

Development: **DONE** (2026-09-15, `Web_HRIS`).

Diverifikasi dari basis data dan dari perilaku aplikasinya:

- `PERMIT` → `Verifikator Permit [ROLE:ADMIN_PERMIT]`,
  `TAMU` → `Verifikator Tamu [ROLE:ADMIN_TAMU]`.
- `ADMIN_PERMIT` dan `ADMIN_TAMU` masing-masing 1 anggota aktif; `SHE_ADMIN`
  tetap 1 dan tidak berubah.
- Pengajuan menggantung: 14 pada `ADMIN_PERMIT`, 3 pada `ADMIN_TAMU`, dan tidak
  ada lagi yang tertinggal di `SHE_ADMIN`.
- `VisitorApprovalPolicyService::resolveApprovers()` memulangkan `ADMIN_PERMIT`
  untuk fitur PERMIT dan `ADMIN_TAMU` untuk fitur TAMU — jadi pengajuan baru
  benar-benar terbit ke role yang benar, bukan hanya konfigurasinya yang berubah.

Uji sudah ditulis tetapi **belum dijalankan** atas permintaan user.

## Pekerjaan user

- Merekam change ini di SQL Tracker bersama `DML-2026-105`/`106`/`107`, lalu
  mengeksekusi ke production dan memverifikasinya.
- **Menentukan anggota tiap role.** Saat ini keduanya berisi orang yang sama
  (warisan `SHE_ADMIN`), jadi pemisahannya baru **mungkin**, belum **terjadi**.
- **Menyelaraskan role dengan kunci halaman.** Keduanya kini dua hal terpisah:
  anggota `ADMIN_TAMU` menerima antrean tamu, tetapi tetap butuh
  `visitorVerifikatorTamuPage` untuk membukanya. Orang yang punya salah satunya
  saja akan melihat antrean tanpa bisa memprosesnya, atau sebaliknya.
- Tiga pengajuan tamu menggantung di development menunjuk kunjungan yang barisnya
  sudah tidak ada (`VSR0826-0001` dan dua lainnya). Ia tidak akan pernah bisa
  disetujui; pertimbangkan menutupnya lewat change tersendiri.
