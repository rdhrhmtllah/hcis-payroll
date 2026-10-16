# Aturan inspeksi & approval permit Visitor

Dicatat 25 September 2026. Dua ketentuan di bawah ditetapkan user dan sebelumnya
tidak pernah tertulis di mana pun — keduanya menentukan bentuk kode yang sudah
berjalan, jadi membacanya lebih dulu menghemat pembatalan keputusan orang lain.

## 1. Inspeksi kendaraan: dua bentuk yang berbeda

| | Permit karyawan (HIJAU/MERAH) | Permit tamu B2B (COKLAT) |
|---|---|---|
| Siapa datang | Pemohon datang ke pos | **Tim SHE yang mendatangi pos gerbang** |
| Jadwal | Pemohon memilih slot (`pesan()`) | **Jadwal kunjungan tamunya**; tidak ada slot |
| Kuota slot | Memakan kursi (`TERJADWAL`) | **Tidak memakan kursi** |
| Pos | Dari slot yang dipilih | Dari setelan `services.visitor.permit.pos_tamu_b2b` |
| Pemindahan jadwal | `pindah()` / `pindahOlehPetugas()` | Tidak ada — ubah jadwal **kunjungannya** |
| Tidak hadir | Hitungan ke-`maks_tidak_hadir` **menggugurkan permit** | **Tidak menggugurkan** apa pun |

Alasan kolom terakhir: yang tidak datang pada baris B2B adalah **tamunya**, bukan
pemegang permitnya. Memakai ulang `tidakHadir()` milik permit karyawan berarti
menghukum pemegang permit atas kelalaian pihak lain.

Pencatatan hasil B2B memakai `JadwalInspeksiService::catatKedatanganTamuB2b()`.
Barisnya lahir **langsung final** (`SELESAI` / `TIDAK_HADIR`) di
`N_VISITOR_Inspeksi_Booking` dan tidak pernah melewati `TERJADWAL`; dari situlah
dua sifatnya berasal sekaligus — nol dampak ke kuota, dan tidak pernah
bertabrakan dengan indeks unik `UX_VISITOR_Inspeksi_Booking_Aktif`. Hasil yang
salah tekan **diperbaiki di baris yang sama**, bukan ditumpuk.

**Agenda SHE tidak punya tombol "Selesai".** Baris tamu hanya membawa tombol
**Review** yang melompat ke halaman review permitnya — di sanalah kendaraannya
diperiksa dan keputusannya diambil — plus **Tidak Hadir** untuk tamu yang tidak
pernah muncul. Status `SELESAI` ditulis OTOMATIS oleh keputusan approval
(`VisitorApprovalDecisionService::approvePermit()` →
`JadwalInspeksiService::tandaiSelesaiKedatanganTamu()`), sejajar dengan permit
karyawan yang inspeksinya ditandai selesai saat disetujui. Pos yang belum
disetel membuat pencatatan itu dilewati dengan catatan log — approval tidak
boleh gagal karena setelan yang belum diisi.

**Urutannya: review dulu, baru selesai.** Permit COKLAT diputus verifikator lebih
dahulu — itulah yang memberi tamunya akses gerbang — baru tim SHE memeriksa
kendaraannya saat tamu tiba. Karena itu tombol **Selesai** hanya muncul (dan
pencatatannya hanya diterima) bila permitnya sudah `Flag_Approval='Y'`; menutup
pekerjaan SHE di atas pengajuan yang masih menggantung membuat agenda bersih
sementara meja verifikator tidak, dan tak ada satu layar pun yang menunjukkan
pasangan itu. **Tidak Hadir** sengaja tidak ikut aturan itu: tamu yang tidak
datang harus bisa dicatat kapan pun, sebab itulah satu-satunya cara menutup
barisnya. Permit yang sudah ditolak tidak bisa dicatat sama sekali.

Posnya tidak bisa diturunkan dari data barisnya sendiri (tak ada slot), jadi ia
datang dari satu setelan. Belum disetel = baris tetap tampil di agenda, tetapi
tombol pencatatannya tidak ditawarkan dan pemanggilan langsung ditolak dengan
pesan yang menyebut nama setelannya. Di development setelannya menunjuk pos
`Pos Security` (`Id_Pos_Inspeksi = 238`).

Penjaganya: `tests/Integration/VISITOR/Permit/Inspection/InspeksiKedatanganTamuB2bTest.php`.

## 2. Approval tamu B2B: satu meja, bukan dua

Kunjungan tamu B2B **tidak lagi meminta approval**; yang menunggu verifikator
hanya permit COKLAT-nya di meja `ADMIN_PERMIT`. Desain awalnya dua meja
berurutan (DDL-2026-121) — kunjungan disetujui `ADMIN_TAMU_B2B` dulu, baru
permitnya diajukan — dan itu menunda tanpa menambah keputusan: yang memberi
akses gerbang adalah permitnya, dan meja permit memeriksa hal yang sama.

Berlaku untuk **kedua pintu B2B**, API mitra maupun layar web, karena aturannya
milik tipe tamunya (`TipeTamu::modulApproval`) bukan pintunya. Meja
`ADMIN_TAMU_B2B` tidak dibongkar: setel `VISITOR_TAMU_B2B_NEEDS_APPROVAL=true`
untuk menghidupkannya kembali.

Akibat yang menyertainya, dan ketiganya dijaga di
`tests/Feature/VISITOR/Guest/Visit/TamuB2BApiTest.php`: kunjungan langsung
`Flag_Approval='Y'` + `SCHEDULED`, proyeksi gerbang tamunya terbit seketika,
permit COKLAT terbit seketika dalam keadaan menunggu, dan **tidak ada** baris
`HRIS_Approval_Request` untuk nomor kunjungannya.

Dua penjaga lama yang subjeknya *penjangkar* rantai approval
(`PenanggungJawabMitraTest`, `MitraTidakMenentukanKepemilikanTest`) kini
menyalakan setelan itu sendiri — tanpa itu keduanya hijau sambil tidak menguji
apa pun.
