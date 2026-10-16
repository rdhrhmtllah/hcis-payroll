# DML-2026-031 — Seed tujuh kode jenis dokumen

| Bidang | Nilai |
|---|---|
| Change ID | `DML-2026-031-seed-jenis-kode-dokumen` |
| Jenis | DML |
| Target development | `Web_HRIS` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |
| Artifact | `database/dml/DML-2026-031-seed-jenis-kode-dokumen/data-change.sql` |

## Hasil

| Kode cetak | Kunci mesin | Nama | Level | Tipe item |
|---|---|---|---|---|
| `PLC` | `KEBIJAKAN` | Kebijakan | 1 | DOKUMEN |
| `SK` | `SURAT_KEPUTUSAN` | Surat Keputusan | 2 | DOKUMEN |
| `SOP` | `PROSEDUR` | Prosedur | 3 | DOKUMEN |
| `IK` | `INSTRUKSI_KERJA` | Instruksi Kerja | 4 | DOKUMEN, VIDEO |
| `STD` | `STANDAR` | Standar | 5 | DOKUMEN |
| `FRM` | `FORMULIR` | Formulir | 6 | FORM |
| `MAN` | `MANUAL` | Manual | 7 | DOKUMEN |

Empat baris lama hanya **diisi** kode cetaknya — `Id_SOP_Jenis_Dokumen` dan
`Kode_Jenis` tidak disentuh, sehingga `N_SOP_Dokumen.Jenis_Dokumen_Id` dan
seluruh seed/test yang mencari lewat kunci mesin tetap menemukan barisnya.

## Nama adalah placeholder

Kepanjangan resmi tiap kode belum ditetapkan saat seed ini ditulis. Nama di atas
adalah pengisi sementara dan **deskripsi sengaja dikosongkan** — mengisinya
dengan tebakan membuat yang belum diputuskan tampak sudah final.

Admin melengkapinya lewat halaman Master Jenis Dokumen, tanpa rilis. Itulah
alasan `Kode_Jenis_Dokumen` dirancang bisa disunting sejak awal.

## Urutan level

Mengikuti urutan kode saat diberikan. `MAN` berada di posisi 7; pada piramida
mutu yang lazim, Manual justru duduk paling atas. Dibiarkan apa adanya karena
`Level_Hirarki` tidak dipakai logika mana pun — ia hanya mengurutkan tampilan,
dan admin bisa menggesernya kapan saja.

## Pemetaan tipe item

Tiga jenis baru dipetakan ke `DOKUMEN`. Hanya `FRM` yang berpasangan dengan
tipe `FORM`, dan pasangan itu sudah ada sejak `DML-2026-017`. Pemetaan ini
menentukan jenis mana yang ditawarkan saat admin memilih tipe item, dan
seluruhnya bisa diubah lewat halaman masternya.

## Rollback

Tidak ada. Menarik sebuah jenis dari peredaran dilakukan dengan
menonaktifkannya (`Flag_Aktif = 'T'`) lewat halaman master, bukan menghapus
barisnya — dokumen yang sudah menunjuknya harus tetap bisa dibaca.
