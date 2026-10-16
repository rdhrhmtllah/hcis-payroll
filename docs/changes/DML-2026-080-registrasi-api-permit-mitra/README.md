# DML-2026-080-registrasi-api-permit-mitra

## Ringkas

Mendaftarkan **satu aplikasi mitra B2B** dan memberinya akses ke ketiga endpoint
permit tamu, sehingga kontraknya muncul di `/api/docs` dan panggilannya diterima
`hris.api.guard`.

| Objek | Perubahan |
|---|---|
| `N_HRIS_Api_Aplikasi` | + 1 baris aplikasi mitra (`Arah='IN'`, `Id_Grup_Fitur=4`, `Kode_Karyawan_PIC` terisi) |
| `N_HRIS_Api_Akses_Endpoint` | + 3 baris hak akses ke endpoint permit tamu |

Endpointnya sendiri sudah terdaftar lewat `DDL-2026-079-visitor-permit-api-b2b`;
berkas ini hanya menautkan aplikasi ke endpoint itu.

## Kenapa terpisah dari DDL-2026-079

Karena isinya **kredensial pihak luar**, dan itu keputusan pemilik repo, bukan
sesuatu yang boleh dikarang agent. `DDL-2026-079` mendaftarkan endpoint — milik
kita, tidak bergantung pada siapa pun. Berkas ini mendaftarkan mitra — dan tanpa
nama serta pasangan kunci yang sungguhan, ia tidak punya isi.

## Tiga nilai wajib, satu opsional

Ketiga yang wajib dideklarasikan sebagai variabel berisi `<ISI_SAAT_EKSEKUSI>`
dan dijaga `RAISERROR`, sehingga skrip menolak jalan bila ada yang terlewat.

| Variabel | Wajib | Keterangan |
|---|---|---|
| `@NamaAplikasi` | ya | Nama mitra, dipakai sebagai kunci idempotensi berkas ini |
| `@ApiPublic` | ya | Kunci publik. Bukan rahasia — terkirim polos di tiap permintaan |
| `@ApiSecret` | ya | **Kunci rahasia.** Setara kunci gerbang |
| `@KodeKaryawanPIC` | **tidak** | Karyawan penanggung jawab. Kosongkan bila tidak ada |

### Kenapa PIC opsional

Permit mitra **tidak diajukan karyawan mana pun**, jadi memaksa adanya kode
karyawan berarti mengarang penanggung jawab. Penelusuran batasan nyatanya:
`Kode_Karyawan_Pemohon` di tabel transaksi nullable dan tanpa FK; alur approval
mengambil approver dari `N_HRIS_Approval_Config`, bukan dari struktur organisasi
pemohon; dan kuota per karyawan sudah dilewati permit mitra. Satu-satunya yang
memaksa adanya nilai adalah proyeksi gerbang — dan itu terpenuhi oleh identitas
aplikasinya sendiri.

Bila `@KodeKaryawanPIC` dikosongkan, pemohon permitnya menjadi aplikasi itu:
kodenya `API#<id>` dan namanya nama aplikasi. Bila diisi, karyawan itulah yang
muncul sebagai pemohon — dan penjaga tetap menolak kode yang tidak ada di tabel
`Karyawan`.

**Konsekuensi bila dikosongkan:** notifikasi ke pemohon tidak terkirim, karena
tidak ada karyawan untuk dikirimi. Tidak merugikan — mitra memang dirancang
mengetahui keputusan lewat endpoint status, bukan lewat notifikasi HCIS. Yang
hilang hanyalah nama orang yang bisa dihubungi; identitas mitranya sendiri tetap
tercatat jelas.

**Kunci tidak pernah ditulis ke berkas ini.** Kunci rahasia yang masuk
repositori akan tetap ada di riwayat git selamanya, bahkan sesudah dihapus dari
berkasnya. Serahkan pasangan kuncinya ke mitra lewat jalur rahasia — bukan lewat
repo, chat, atau tiket.

Pola ini mengikuti preseden `DML-2026-075-registrasi-api-kalender-ho`.

## Empat penjaga sebelum menulis apa pun

1. **Ketiga variabel wajib terisi.** Menolak `<ISI_SAAT_EKSEKUSI>` yang tertinggal.
2. **`@KodeKaryawanPIC` ada di tabel `Karyawan` — bila diisi.** Kosong itu sah;
   yang tidak sah adalah terisi tetapi tidak ada. PIC karangan baru ketahuan
   jauh di hilir: penerbitan permitnya gagal karena `Nama_Pemohon` di proyeksi
   bersifat `NOT NULL`. Galatnya tidak menyebut PIC sama sekali.
3. **`@ApiPublic` belum dipakai aplikasi lain.** Kunci publik dipakai untuk
   *mencari* aplikasi di setiap permintaan; dua aplikasi berkunci sama membuat
   permintaan salah satunya dikenali sebagai milik yang lain.
4. **Ketiga endpoint sudah terdaftar.** Kalau belum, `DDL-2026-079` belum
   dijalankan di basis data ini dan hak akses yang dibuat tidak menautkan apa
   pun.

## Perilaku saat dijalankan ulang

Idempoten. Aplikasi dan hak akses tidak digandakan. Bila aplikasinya sudah ada,
`Kode_Karyawan_PIC`-nya diluruskan tetapi **pasangan kuncinya tidak ditimpa** —
menimpa kunci yang sedang dipakai mitra memutus integrasinya seketika, dan tidak
ada yang tahu penyebabnya selain "tiba-tiba 401".

## Untuk mitra kedua dan seterusnya

Salin berkas ini sebagai change baru dengan nomor sendiri. Jangan menjalankan
ulang berkas ini dengan nilai berbeda: catatan change harus menggambarkan satu
tindakan yang benar-benar terjadi.

## Verifikasi sesudah eksekusi

Bagian 3 berkas ini mengembalikan satu baris. Harapan: `Jumlah_Endpoint` = 3.
`Kode_Karyawan_PIC` `NULL` itu **sah** — pemohonnya menjadi aplikasi itu sendiri.
Yang tidak sah adalah `Kode_Karyawan_PIC` terisi sementara `Nama_PIC` `NULL`;
penjaga di atas seharusnya sudah menolaknya lebih dulu.

Sesudah itu mitra dapat memanggil `GET /api/docs/openapi.json` dengan header
`X-HC-Public` miliknya dan menerima kontrak lengkap ketiga endpoint — sudah
diuji tersaji utuh, termasuk tag `Permit Tamu` dan skema `PermitTamuRequest`
serta `PermitTamuStatus`.

## Berkas

| Peran | Path |
|---|---|
| DML | `database/dml/DML-2026-080-registrasi-api-permit-mitra/data-change.sql` |
| Prasyarat | `database/ddl/DDL-2026-079-visitor-permit-api-b2b/forward.sql` |
| Desain | `docs/superpowers/specs/2026-09-08-api-b2b-permit-coklat-design.md` |

## Status

Development: **belum dieksekusi**. Menunggu tiga nilai wajib dari pemilik repo —
dan sesungguhnya hanya satu yang fakta bisnis (nama mitra); kedua kuncinya
dibangkitkan saat eksekusi.

## Pekerjaan agent

- Menulis DML beserta keempat penjaganya dan kueri verifikasinya.
- Menulis dokumentasi ini dan entri `database/ddl/status.json`.

## Pekerjaan user

- Menentukan nama mitra. Karyawan PIC opsional — kosongkan bila tidak ada.
- Membangkitkan pasangan kunci, mengisi ketiga variabel wajib, dan menjalankan
  berkas ini ke development lalu production lewat SQL Tracker.
- Menyerahkan `Api_Secret` ke mitra lewat jalur rahasia.
- Menetapkan prosedur pencabutan aplikasi (`Flag_Aktif='N'` pada
  `N_HRIS_Api_Aplikasi`) sebelum darurat, bukan menemukannya saat darurat.
