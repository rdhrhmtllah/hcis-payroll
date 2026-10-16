# DML-2026-042 — Alur versi empat langkah, satu tahap per langkah

| Bidang | Nilai |
|---|---|
| Change ID | `DML-2026-042-sederhanakan-alur-versi` |
| Jenis | DML |
| Target development | `Web_HRIS` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |

## Alur yang berlaku

```text
1 Pengajuan    admin mengisi identitas versi, lalu lanjut
2 Review       catatan review bila ada; kalau mulus, langsung lanjut
3 Pengesahan   isi pengesah dan legalitasnya, lalu lanjut
4 Pemberkasan  di sinilah ditentukan: TERBIT atau BATAL jadi arsip
```

Tiga klik dari versi lahir sampai titik keputusan.

## Yang dibuang

**`APPROVED` sebagai tahap kedua di langkah 3.** Langkah Pengesahan diwakili dua
tahap — Approval lalu Approved — sehingga admin menekan "Simpan & Lanjut" dua
kali untuk satu langkah. Pemisahan itu tak menjawab pertanyaan siapa pun: yang
dicatat sistem adalah siapa menandatangani dan kapan, bukan "sedang disetujui"
versus "sudah disetujui".

**`HOLD` sepenuhnya.** Menahan pekerjaan tak butuh status: admin yang belum siap
melanjutkan cukup TIDAK menekan lanjut, dan versinya memang tinggal di tahap itu.
Status yang tak mengubah apa pun selain namanya sendiri hanya menambah satu
keputusan yang harus dipikirkan.

**Syarat `CATATAN_REVIEW` untuk maju ke Pengesahan.** Review yang berjalan mulus
memang tak meninggalkan catatan; memaksa mengarang satu putaran kosong demi
melewati gerbang membuat catatannya berhenti berarti.

## Yang tetap dijaga

Satu gerbang: **Filing and Registered** menuntut berkas final dan pengesah nyata.
Di sanalah dokumen mulai mengikat orang.

`Butuh_Pengesahan` pada Pengesahan sengaja `T` — pengesah diisi DI DALAM langkah
itu, bukan sebelum memasukinya. Menuntutnya sebagai syarat masuk membuat langkah
itu mustahil dicapai.

## Pembatalan hanya dari Pengesahan

Transisi ke `CANCEL` dari tahap lain dinonaktifkan. Membatalkan dari langkah awal
menawarkan keputusan besar sebelum ada yang bisa ditimbang.

## Dinonaktifkan, bukan dihapus

`Flag_Aktif = 'T'` menyimpan arti baris lama yang mungkin pernah menyinggungnya,
dan mengembalikannya cukup satu `UPDATE`. Nol versi nyata terdampak: sebelum
perubahan, tak satu pun dari 76 versi berada di HOLD atau APPROVED.
