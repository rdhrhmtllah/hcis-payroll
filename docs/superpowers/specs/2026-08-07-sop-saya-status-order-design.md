# SOP Saya — Urutan Default Berdasarkan Status Pengerjaan

## Tujuan

Di halaman "SOP Saya" (`/sop/saya`), karyawan ingin daftar SOP-nya tampil terurut
berdasarkan tahapan pengerjaan: **Baru Diassign → In Progress → Selesai**, bukan
murni berdasarkan urgensi tenggat seperti sekarang.

## Konteks saat ini

- Halaman: `resources/js/Pages/SOP/modules/saya/SopSaya.vue`
- Data list dikirim dari `SopProgressReadService::tugasSaya()` tanpa `ORDER BY`
  di query — pengurutan sepenuhnya dilakukan di frontend (`visibleItems`
  computed, baris ±388–417).
- Setiap item punya field `Status_Paket` yang dihitung on-the-fly di backend:
  `BELUM` (belum ada progres), `PROGRESS` (sudah ada progres, belum tuntas),
  `SELESAI` (semua item wajib tuntas).
- Dropdown "Urutkan" (`listFilters.sort`, default `'urgensi'`) punya opsi:
  `urgensi` (default, sort by tenggat lalu prioritas), `tenggat`,
  `progres_tinggi`, `progres_rendah`, `nama`, `prioritas`. Tidak ada opsi yang
  mengelompokkan berdasarkan `Status_Paket`.
- `itemsTampil` (yang benar-benar dirender) memakai `visibleItems` langsung
  ("Semua kategori") atau subset per-kategori dari `grupKategori`, yang
  dibangun dari `visibleItems` — jadi urutan `visibleItems` konsisten
  terbawa ke kedua tampilan.

## Perubahan

Murni di frontend, satu file: `SopSaya.vue`.

1. **Sort key baru `'status'`**: rank `Status_Paket` sebagai `BELUM=0,
   PROGRESS=1, SELESAI=2`. Dalam rank yang sama, tie-break pakai `daysLeft()`
   ascending (tenggat terdekat dulu) — logika yang sama dipakai opsi
   `tenggat`/`urgensi` yang sudah ada.
2. **Default diubah**: `listFilters.sort` awal menjadi `'status'` (bukan lagi
   `'urgensi'`).
3. **Opsi dropdown baru ditambahkan**: `{ label: "Tahapan (Baru → Proses →
   Selesai)", value: "status" }` ditambahkan ke `filterConfig().filters`
   (opsi `sort`), supaya user yang mengganti sort lalu ingin balik ke urutan
   ini bisa memilihnya lagi secara eksplisit. Opsi `urgensi` ("Paling
   mendesak") tetap ada, tidak dihapus, untuk yang mau urutan lama.
4. Tidak ada perubahan backend/API — `Status_Paket` sudah dikirim per item.

## Cakupan yang tidak berubah

- `grupKategori` (pengelompokan rak per kategori) tetap seperti sekarang;
  perubahan ini hanya mengubah urutan item di dalam rak/list, bukan
  keberadaan pengelompokan kategori itu sendiri.
- Tab filter Grup (`perlu_dibaca`/`terlambat`/`diperbarui`/`selesai`) tidak
  disentuh — itu filter status keterlambatan/urgensi yang beda konsep dari
  `Status_Paket`, dan tetap bekerja sebagai filter di atas hasil sort baru.
- Halaman/komponen admin (`SopMonitoring.vue`, `SopTeam.vue`, dll.) tidak
  tersentuh — di luar cakupan (bukan tampilan karyawan).

## Error handling

Tidak ada state error baru — perubahan murni pengurutan array di memori
(`Array.prototype.sort`), tidak ada request baru, tidak ada kondisi
`Status_Paket` yang undefined (field selalu diisi backend, fallback ke rank
terbesar jika suatu saat kosong, agar tidak crash sort).

## Testing

- Manual: buka `/sop/saya` tanpa memilih kategori, verifikasi urutan kartu:
  semua `BELUM` di atas, lalu `PROGRESS`, lalu `SELESAI` di bawah; dalam
  masing-masing grup, tenggat terdekat di atas.
- Manual: pilih satu kategori (rak) di panel kanan, verifikasi urutan yang
  sama berlaku di dalam rak tersebut.
- Manual: ganti dropdown "Urutkan" ke opsi lain lalu balik ke "Tahapan",
  pastikan urutan kembali sesuai status.
- Tidak ada test otomatis existing untuk `visibleItems` sorting di repo ini;
  tidak menambah test baru mengingat ini logic sort array kecil dan halaman
  ini belum punya harness test frontend.
