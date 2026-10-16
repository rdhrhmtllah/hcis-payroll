# DDL-2026-045 - Baseline schema dan data master Visitor

| Bidang | Nilai |
|---|---|
| Change ID | `DDL-2026-045-bootstrap-visitor-master-data` |
| Target | `Web_HRIS` development pada `team311.dyndns.info:1433` |
| Status | `TRACKER_PENDING` (forward DDL and DML development executed; existing schema matched so forward created no objects) |
| Deskripsi | Menyediakan bootstrap schema enam tabel master Visitor dan DML idempotent berisi data operasional terkurasi. |
| Owning module | `VISITOR`, `Id_Grup_Fitur=4`, `Kode_Grup_Fitur=VISITOR` |
| Page mapping | `visitorMasterDataPage`, `/admin/visitor/master-data` |
| Ownership | Submodule `Governance`, feature `Master Data`, layer `Database DDL/DML documentation` |
| Basis registry | Live development `Web_HRIS`, diverifikasi 2026-09-01 |
| Forward DDL | `database/ddl/DDL-2026-045-bootstrap-visitor-master-data/forward.sql` |
| Drop manual | `database/ddl/DDL-2026-045-bootstrap-visitor-master-data/drop.sql` |
| DML | `database/dml/DDL-2026-045-bootstrap-visitor-master-data/data-change.sql` |
| Checksum forward SHA-256 | `ebe0a377c8f4d325d0a63865c225c85ba84d2dc2f2b1f22a0da028ff41b6e14d` |
| Checksum DML SHA-256 | `e5d9c3f7f99cd59cc7b19c169e2f29b90f7b5d0e1d8c14e37c82fb8ef49c6ded` |

## Objek dan batas perubahan

- `N_EMI_VISITOR_Master_Vehicle_Type`
- `N_EMI_VISITOR_Master_Vehicle_Model`
- `N_EMI_VISITOR_Master_Vehicle_Color`
- `N_EMI_VISITOR_Master_Plat_Kendaraan`
- `N_EMI_VISITOR_Master_Purpose`
- `N_EMI_VISITOR_Master_Durasi_Permit`

Forward DDL membuat tabel hanya bila belum ada, lalu memvalidasi kolom penting dan foreign key terhadap baseline live development. Tidak ada Laravel migration. Tidak ada `DROP` atau `TRUNCATE` pada forward/DML. Companion `drop.sql` diblokir dengan `THROW` dan hanya merupakan aid recovery manual; agent tidak menjalankannya.

## Data yang dipromosikan

- 4 tipe kendaraan, 110 model kendaraan canonical, 9 warna, 10 kode plat rekomendasi, 6 purpose, dan 3 durasi.
- Model terdiri dari 30 sepeda motor, 56 mobil penumpang, serta 24 kendaraan berat/komersial. Trim, transmisi, kapasitas mesin, edisi khusus, dan tahun model tidak dibuat sebagai baris tersendiri agar pilihan UI tidak dipenuhi duplikat varian.
- `KUDO + REVISI`, `Range rover + Edit`, `IRENG`, `UII`, dan `BIOR` tidak dihapus; DML hanya memastikan statusnya nonaktif.
- `Audit / Inspeksi Lapangan` disertakan sebagai purpose operasional opsional sesuai permintaan.
- Final state `6 Bulan = 6 MONTH` dibawa dalam validasi DML. Urutan production tetap harus menjalankan exact artifact `DML-2026-021-fix-visitor-durasi-enam-bulan` lebih dahulu agar histori SQL Tracker lama tidak hilang.
- Baris legacy lain yang tidak diminta, termasuk kode plat yang sudah dipakai histori, tidak dihapus atau dinonaktifkan oleh change ini.

Expected development scope berdasarkan snapshot 2026-09-01: 112 insert (`Vehicle_Model` 104, `Vehicle_Color` 3, `Plat_Kendaraan` 4, `Purpose` 1) dan 3 update (`IRENG` nonaktif, `UII` nonaktif, metadata `6 Bulan`). Setelah eksekusi, tabel model diperkirakan memiliki 111 baris fisik: 110 aktif dan 1 legacy nonaktif. Semua predicate berbasis natural key; jumlah production dapat lebih kecil bila baris sudah tersedia.

## Sumber data internet

Tanggal pengambilan: 2026-09-01.

- Kode wilayah TNKB: Lampiran Peraturan Polri Nomor 7 Tahun 2021, sumber resmi Korlantas/BPK: https://korlantas.polri.go.id/wp-content/uploads/2021/05/PERATURAN-POLRI-NOMOR-7-TAHUN-2021-TENTANG-REGISTRASI-DAN-IDENTIFIKASI-KENDARAAN-BERMOTOR.pdf
- Toyota Avanza, Innova, Calya, dan lini Toyota: https://www.toyota.astra.co.id/shopping-tools/pricelist
- Honda Vario dan BeAT: https://www.astra-honda.com/brochures/Vario-125-2020-Brochure-01.pdf dan https://www.astra-honda.com/brochures/Honda-BeAT-2020.pdf
- Yamaha NMAX: https://www.yamaha-motor.co.id/product/nmax-turbo/
- Lini Yamaha lain: https://www.yamaha-motor.co.id/products/
- Lini sepeda motor dan mobil Suzuki: https://www.suzuki.co.id/ dan https://www.suzuki.co.id/motorcycle
- Lini mobil Honda Indonesia: https://www.honda-indonesia.com/brio
- Lini Daihatsu Indonesia: https://daihatsu.co.id/profile/brosur/
- Mitsubishi Pajero Sport/Xpander sebagai validasi nama lini kendaraan: https://www.mitsubishi-motors.co.id/
- Lini Hyundai Indonesia: https://www.hyundai.com/id/id/find-a-car/landing-page
- Lini Wuling Indonesia: https://wuling.id/id/faq/cloud-ev
- Hino 300/500/700 dan nama legacy Ranger: https://www.hino.co.id/news-detail/397/memperkuat-idenditas-global-hino-indonesia-resmi-mengubah-nama-produk
- Mitsubishi Fuso Canter/Fighter X: https://www.ktbfuso.co.id/siaran-pers/mitsubishi-fuso-akhirnya-mengungkap-total-29-varian-canter-dan-fighter-x-dengan-standar-euro-4/
- Isuzu Traga/ELF/GIGA: https://isuzu-astra.com/
- UD Trucks Quester/Kuzer/Croner: https://www.udtrucks.com/indonesia/trucks

Purpose dan durasi adalah master kebijakan internal, bukan fakta internet. Nilainya mengikuti requirement bisnis dan kontrak field aplikasi. Warna memakai daftar standar yang diberikan, dengan ejaan UI yang dinormalisasi.

## Dependency dan urutan production

1. Review dan jalankan `DDL-2026-023-add-visitor-master-soft-status` melalui SQL Tracker bila target existing belum memilikinya.
2. Jalankan exact `DML-2026-021-fix-visitor-durasi-enam-bulan` melalui SQL Tracker.
3. Jalankan `forward.sql` change ini melalui SQL Tracker.
4. Jalankan `data-change.sql` change ini melalui SQL Tracker.
5. Verifikasi jumlah/nilai aktif dan perilaku UI production.

## Agent completed

- Audit pending Visitor changes dan dirty worktree; dua perubahan frontend milik user tidak disentuh.
- Verifikasi live target development, schema, data, foreign key, dan module/page registry.
- Verifikasi pemakaian histori sebelum menetapkan strategi nonaktif tanpa penghapusan.
- Kurasi kandidat data dari sumber resmi dan menyiapkan forward/drop/DML/documentation.
- Menjalankan validasi SQL Server `PARSEONLY`; forward DDL dan DML lulus tanpa mutation.
- Setelah approval user pada 2026-09-01, menjalankan exact `data-change.sql` ber-checksum pada development `Web_HRIS`.
- Memverifikasi hasil development: 150 baris fisik pada enam master, terdiri dari 145 aktif dan 5 nonaktif.
- Memverifikasi 111 baris model: 30 motor aktif, 56 mobil penumpang aktif, 24 kendaraan berat/komersial aktif, dan 1 model legacy nonaktif; tidak ada natural-key model duplikat.
- Memverifikasi seluruh lima data exclude nonaktif dan `6 Bulan = 6 MONTH`, keterangan `Berlaku 6 Bulan`, urutan 3.
- Setelah instruksi lanjutan user pada 2026-09-01, menjalankan exact `forward.sql` ber-checksum `ebe0a377c8f4d325d0a63865c225c85ba84d2dc2f2b1f22a0da028ff41b6e14d` pada development `Web_HRIS`; sembilan batch berhasil, seluruh `CREATE TABLE` menjadi no-op karena objek sudah ada, dan validasi 24 kolom serta foreign key lulus.

## User tasks

- Review nama model tambahan, inclusion `Audit / Inspeksi Lapangan`, dan cakupan kode plat terhadap lokasi operasional aktual.
- Catat exact forward/DML dan checksum ke SQL Tracker; jalankan manual ke production sesuai urutan dependency.
- Verifikasi schema, data aktif, form Permit/Tamu, dan histori production; baru kemudian status dapat bergerak ke `COMPLETED`.
