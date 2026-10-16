# DML-2026-104 — Status penugasan `MENUNGGU_VERIFIKASI`

| | |
|---|---|
| Change ID | `DML-2026-104-status-penugasan-menunggu-verifikasi` |
| Jenis | DML |
| Artefak | `database/dml/DML-2026-104-status-penugasan-menunggu-verifikasi/data-change.sql` |
| Bergantung pada | tidak ada |
| Perubahan kode penyerta | `PaAssignmentBuilderService::STATUS_MENUNGGU_VERIFIKASI`, `PaPayloadMapperService::catatStatusSkor()`, `PaThirdPartyCallbackController`, `PaNotificationService` (commit `8d55944f`) |
| Status | `TRACKER_PENDING` — dev **DONE**, production `PENDING` |

## Yang diselesaikan

`N_PA_Assignment.Status_Penugasan` dijaga **foreign key** ke `N_PA_Assignment_Status`
(`FK_PA_Assignment_Status_Master`). Tanpa baris master ini, setiap penulisan status
`MENUNGGU_VERIFIKASI` ditolak database:

```
The UPDATE statement conflicted with the FOREIGN KEY constraint
"FK_PA_Assignment_Status_Master".
```

Status itu dipakai callback penilaian saat HC Learn melaporkan
`Status_Skor = MENUNGGU_VERIFIKASI`: penilai sudah mengirim, tetapi angkanya belum
disahkan admin di sisi mereka. Keadaan ini sengaja **bukan** salah satu dari dua yang
sudah ada — bukan `SELESAI` karena nilainya belum boleh ikut dihitung, bukan
`BELUM_MULAI` karena orangnya sudah mengerjakan bagiannya dan menagihnya lagi lewat
pengingat membuat pengingat kehilangan wibawanya.

## Bagaimana ini terlewat

Saat membangun fiturnya saya memeriksa `sys.check_constraints` pada `N_PA_Assignment`,
tidak menemukan pembatas pada kolom status, lalu menyimpulkan status baru tidak butuh
perubahan basis data.

Pemeriksaannya benar; kesimpulannya salah. Pembatasnya memang **bukan CHECK melainkan
FOREIGN KEY**, dan keduanya tidak muncul di katalog yang sama.

Yang menemukannya adalah **uji rantai penuh** (`RantaiPenuhTest`) — kegagalan ini tidak
pernah muncul di satu pun uji satuan, karena tak satu pun dari mereka benar-benar menulis
status itu ke basis data. Fiturnya akan gagal di production pada kiriman pertama.

## `Urutan_Alur` 5, bukan di antara

Nomor urut dipakai mengurutkan tampilan. Menyelipkannya di antara `BERJALAN` dan
`SELESAI` berarti menggeser dua baris yang sudah ada — menyentuh data lain demi kerapian
saja. Ditaruh di ujung: alurnya memang **cabang**, bukan tahap ketiga yang dilewati semua
orang.

## Verifikasi

Dijalankan di development, idempotensinya dibuktikan dengan menjalankan dua kali:

```
BELUM_MULAI            urut=1   aktif=Y
BERJALAN               urut=2   aktif=Y
SELESAI                urut=3   aktif=Y
DIBATALKAN             urut=4   aktif=Y
MENUNGGU_VERIFIKASI    urut=5   aktif=Y
baris MENUNGGU_VERIFIKASI: 1 (idempoten terbukti)
```

Sesudahnya `RantaiPenuhTest` hijau — 29 penegasan menempuh buat → kunci → buka →
empat cara mengisi → hitung → terbit.

## Cara membatalkan

```sql
UPDATE N_PA_Assignment_Status SET Flag_Aktif = 'T'
WHERE Kode_Status = 'MENUNGGU_VERIFIKASI';
```

Jangan dihapus selama masih ada penugasan yang memakainya — foreign key akan menolaknya,
dan itu memang penjagaan yang benar.
