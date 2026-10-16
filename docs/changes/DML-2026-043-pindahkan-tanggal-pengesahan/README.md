# DML-2026-043 — Pindahkan tanggal pengesahan ke tanggal akhir approval

| Bidang | Nilai |
|---|---|
| Change ID | `DML-2026-043-pindahkan-tanggal-pengesahan` |
| Jenis | DML |
| Target development | `Web_HRIS` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |

## Alasan

Nomor Pengesahan dan Tanggal Pengesahan dibuang dari formulir versi. Tanggal
Akhir Approval sudah menjawab pertanyaan yang sama — kapan pengesahannya tuntas
— dan ia tercap sendiri saat tahap bergeser, sehingga tak perlu diketik ulang.

## Datanya dipindahkan dulu

| Sebelum | Sesudah |
|---|---|
| 14 versi punya `Tanggal_Pengesahan` | dipindahkan ke `Tanggal_Akhir_Approval` |
| 1 versi punya `Tanggal_Akhir_Approval` | menjadi 15 |
| 0 baris bentrok (keduanya terisi & berbeda) | pemindahan tak menimpa apa pun |

Membuang field-nya dari layar tanpa memindahkan isinya akan membuat empat belas
tanggal pengesahan nyata berhenti terlihat di mana pun, padahal itulah tanggal
yang tercetak di dokumen fisiknya.

## Kolomnya sengaja TIDAK di-drop

`Nomor_Pengesahan` masih terisi pada **19 versi** dan dibaca ekspor kepatuhan,
monitoring, serta register kontrol dokumen. Nomor SK yang tercetak di dokumen
fisik tak punya pengganti di kolom mana pun.

Yang dilakukan pekerjaan ini hanyalah **berhenti memintanya** di formulir versi.
Yang sudah tercatat tetap tersimpan dan tetap terbaca laporan; yang baru tak lagi
diisi.

## Bug yang ikut ditutup

`ubahVersi()` menulis `?? null` untuk kedua kolom. Begitu formulir berhenti
mengirimnya, menyunting draft lama akan **menghapus** nomor SK-nya. Kini nilai
lama dipertahankan bila kuncinya tak dikirim — mengosongkannya tetap bisa dengan
mengirim kunci bernilai `null`, pola yang sama dengan `Id_Master_Konten`.

Dijaga dua test regresi, keduanya terbukti merah saat perbaikannya dibatalkan.
