# DML-2026-087 — Jadwal harian pengingat penilai PA

| | |
|---|---|
| Change ID | `DML-2026-087-jadwal-pengingat-penilai-pa` |
| Jenis | DML |
| Artifact revision | **r1** |
| Artifact | `database/dml/DML-2026-087-jadwal-pengingat-penilai-pa/data-change.sql` |
| Status | `TRACKER_PENDING` — dev DONE, production PENDING |

## Yang dilakukan

Satu baris `N_HRIS_Scheduler_Configs`:

```
Command          pa:ingatkan-penilai
Cron_Expression  45 1 * * *
Timezone         Asia/Jakarta
Flag_Aktif       Y
```

## Kenapa baris tabel, bukan suntingan `Kernel.php`

Penjadwal proyek ini **digerakkan tabel**. `App\Console\Kernel::schedule()` membaca
`N_HRIS_Scheduler_Configs` lalu mendaftarkan tiap baris — lengkap dengan
`withoutOverlapping(15)` dan `onOneServer()` yang berlaku untuk semuanya. Dua belas
perintah lain sudah hidup dengan cara ini.

Menuliskan jadwal ke dalam kode akan membuat **jalur kedua** yang harus dijaga sama
persis, dan orang yang kelak ingin mematikannya harus tahu ada dua tempat — padahal ia
akan mencari di tempat yang sama dengan sebelas jadwal lainnya.

## Kenapa perintah, bukan dipicu kejadian

Penugasan diberitahukan sekali saat dibentuk. Sesudah itu **tidak ada kejadian apa pun**
yang bisa memicu pengingat: yang berubah hanyalah tanggal, dan hanya sesuatu yang berjalan
menurut waktu yang bisa memperhatikannya.

## Kenapa 01:45

Mengikuti tetangganya, yang semuanya berjalan dini hari:

| Perintah | Jam |
|---|---|
| `sop:rule-scan` | 00:10 |
| `sop:reminder-scan` | 00:30 |
| `talent-pool:kedaluwarsa` | 01:00 |
| `sop:recert-scan` | 01:15 |
| **`pa:ingatkan-penilai`** | **01:45** |

Menit 45 belum terpakai, sehingga pemindai-pemindai ini tidak berebut basis data pada
menit yang sama. Pemberitahuan in-app bertahan sampai dibaca, jadi menjalankannya dini
hari tidak menunda apa pun bagi penerimanya — ia membacanya saat membuka HCIS pagi harinya.

## Aman dijalankan berulang

Kunci pemberitahuannya memuat **tanggal**, sehingga perintah yang berjalan dua kali dalam
sehari tidak menghasilkan dua pemberitahuan. Penjaga di dalam artefaknya juga menolak dua
keadaan: jadwalnya tidak terpasang (`50377`), dan jadwalnya lebih dari satu baris
(`50378`) — yang kedua ada karena `Command` tidak punya unique constraint, dan dua baris
identik berarti perintahnya berjalan dua kali tiap malam tanpa ada yang menyadarinya.

## Verifikasi

Dijalankan **dua kali** pada `Web_HRIS` dev, 1 September 2026. Keduanya OK, baris tetap 1.

Diverifikasi lewat **efek**, bukan isi tabel:

```
php artisan schedule:list
  45 1 * * *  php artisan pa:ingatkan-penilai  ... Next Due: 4 hours from now
```

Isi tabel hanya membuktikan barisnya ada; keluaran di atas membuktikan penjadwalnya
benar-benar mengenalinya.
