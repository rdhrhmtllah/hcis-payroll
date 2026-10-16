# DDL-2026-153-visitor-permit-efektif-nfc-inbio

- **Target:** Web_HRIS development & production (SQL Tracker)
- **Artefak:** `database/ddl/DDL-2026-153-visitor-permit-efektif-nfc-inbio/forward.sql` (sha256 `f4e20c3b6a223bff85d6b106933acd590c0afe3a34bdcf4410f0e34f99f79056`), `drop.sql` (pembersihan manual, tidak dijalankan agent)
- **Objek:** `dbo.N_VISITOR_Permit_Efektif` + kolom `NFC_Inbio varchar(30) NULL`
- **Pemilik:** VISITOR › Operations › Expiration (`VisitorEfektifService`), sumber kartu `KartuNfcPemohon::nfcInbio`

## Status

Kolom **ditambahkan user sendiri di dev** (29 Sep 2026) dengan
`ALTER TABLE N_VISITOR_Permit_Efektif ADD NFC_Inbio varchar(30)`. Artefak ini mencatatnya
untuk SQL Tracker/production dan idempoten (`IF COL_LENGTH ... IS NULL`), jadi aman
dijalankan di dev yang kolomnya sudah ada.

## Tujuan

Aplikasi palang membaca nomor kartu ID Card pemegang izin langsung dari baris efektif.

Diisi `VisitorEfektifService` di kedua jalur penulisan (insert **dan** update):
- `terbitkanAksesInspeksi` — akses sementara saat pemohon memesan slot inspeksi;
- `syncPermitEfektif` — setelah permit disetujui / disinkron ulang.

Sumber: `N_EMI_Binding_NFC.kode_karyawan = Kode_Karyawan_Pemohon` → `id_nfc` →
`N_EMI_Master_NFC.nfc_inbio`, dengan aturan kartu sah yang sama dengan pengecekan ID Card
saat pengajuan: binding aktif, kartu aktif **dan** tervalidasi sistem, tidak dipegang orang
lain; binding terbaru menang.

| Kasus | NFC_Inbio |
|---|---|
| Permit karyawan, kartu sah | nomor kartunya |
| Kartu diblokir / belum divalidasi / ganda / binding nonaktif | NULL |
| Izin tamu (COKLAT) atau mitra (API) | NULL — pemegangnya bukan karyawan pengaju |
| Kartu diganti lalu disinkron ulang | mengikuti kartu terbaru; `Flag_Sync = 'T'` |

## Perilaku bila kolom belum ada

Service memeriksa keberadaan kolom (`Schema::hasColumn`) dan melewatinya bila belum ada,
jadi penerbitan izin tidak gagal di lingkungan yang belum menjalankan DDL ini.

## Data lama

Baris efektif yang sudah ada tetap NULL sampai disinkron ulang (approval/koreksi). Belum ada
DML backfill.

## Tugas user

1. Jalankan `forward.sql` lewat SQL Tracker di production.
