# Checklist Regression Manual Staging

Dokumen ini wajib diperbarui setiap kali perubahan menyentuh transaction, upload/storage, Cloud Tasks, Outbox, notification provider, atau status bisnis. Checklist dilakukan di staging setelah deployment dan sebelum production.

## Aturan eksekusi

- Gunakan akun staging sesuai role nyata: employee, approver, admin, dan system/admin bila relevan.
- Gunakan data uji yang diberi label jelas dan dapat dibersihkan.
- Jangan mengirim WhatsApp/provider nyata kecuali skenario sudah disetujui; gunakan mock/sandbox bila tersedia.
- Catat request ID/transaction number, waktu uji, hasil UI/API, status database, queue/task status, dan screenshot/log bila gagal.
- Verifikasi tidak hanya response HTTP, tetapi juga hasil database, side effect file, status task, dan tidak adanya duplikasi.

## Checklist umum setiap perubahan

### Transaction dan data bisnis

- [ ] Skenario submit/create berhasil.
- [ ] Skenario update berhasil.
- [ ] Skenario reject/cancel/delete bila feature memilikinya.
- [ ] Validasi gagal tidak meninggalkan perubahan parsial.
- [ ] Simulasi exception/rollback tidak meninggalkan row bisnis yatim.
- [ ] Tidak ada `Login timeout expired`, deadlock, atau request menggantung.
- [ ] Perubahan hanya terjadi pada scope data yang benar.

### Queue, Cloud Tasks, dan Outbox

- [ ] Task tercipta hanya setelah transaction commit.
- [ ] Task tidak tercipta ketika transaction rollback.
- [ ] Task handler menerima payload dan metadata yang benar.
- [ ] Status berpindah sesuai lifecycle: `QUEUED → DISPATCHED → PROCESSING → COMPLETED`.
- [ ] Error sementara menghasilkan retry terbatas dan `RETRY_PENDING`.
- [ ] Error permanen menghasilkan `FAILED` tanpa retry tak terbatas.
- [ ] Pengiriman ulang dengan idempotency key tidak membuat duplicate execution.
- [ ] Lease/claim yang stale dapat dipulihkan.
- [ ] Operational log dan Outbox dapat ditelusuri memakai transaction/reference ID.

### Upload dan file storage

- [ ] Upload file valid berhasil.
- [ ] File corrupt/format salah ditolak dengan aman.
- [ ] Upload gagal tidak membuat row database menunjuk ke file yang tidak ada.
- [ ] Rollback database tidak meninggalkan file yatim, atau mekanisme cleanup tercatat.
- [ ] File tersimpan pada scope/module/path yang benar.
- [ ] Download/preview/render file berhasil.
- [ ] Replace/delete file tidak menghapus file milik module atau scope lain.

## Test manual A6 — Konten/Kategori

- [ ] Upload konten dokumen baru.
- [ ] Upload PDF yang memerlukan verifikasi/render.
- [ ] Upload video Bunny dan pastikan jalur video tidak memanggil handler PDF.
- [ ] Jalankan verifikasi ulang/render ulang.
- [ ] Pastikan response sukses tidak berarti task hilang: cek task/log/status render.
- [ ] Simulasikan/observasi dispatch gagal dan pastikan konten tetap konsisten serta dapat di-retry manual.
- [ ] Uji scope LMS dan SOP secara terpisah.
- [ ] Hapus konten LMS/SOP dan pastikan file scope lain tidak terhapus.

## Test manual A1 — Update Performance / KPI

Checklist ini diaktifkan setelah perubahan A1 diimplementasikan:

- [ ] Submit KPI dengan satu file.
- [ ] Submit KPI dengan banyak file dalam satu transaksi.
- [ ] Submit KPI tanpa file.
- [ ] Replace/update file KPI.
- [ ] File gagal upload: tidak ada row/path bisnis yang menunjuk file gagal.
- [ ] Simulasikan kegagalan setelah upload sebelum commit: cek cleanup file atau status recovery.
- [ ] Approval/reject KPI tetap berjalan.
- [ ] Verifikasi role employee, approver, dan admin.
- [ ] Pastikan tidak ada duplicate file, duplicate task, atau duplicate notification.
- [ ] Pantau durasi request, lock timeout, dan error log.

## Test manual A2 — Penjadwalan Training / Third Party

Checklist ini wajib dijalankan setelah perubahan A2 karena alurnya mengubah database lokal berdasarkan response sistem pihak ketiga:

- [ ] Create/store penjadwalan baru dengan satu detail ujian.
- [ ] Create/store dengan beberapa detail/aktivitas ujian dalam satu request.
- [ ] Update penjadwalan yang sudah memiliki integrasi pihak ketiga.
- [ ] Update penjadwalan dengan detail yang ditambah, diubah, dan dihapus.
- [ ] Reassign/generate ulang ujian dan pastikan token baru tersimpan pada row integrasi yang benar.
- [ ] Simulasikan HTTP timeout pihak ketiga: tidak ada transaction DB yang tertahan lama dan status lokal tidak setengah berubah.
- [ ] Simulasikan response HTTP 4xx/5xx: status/error lokal sesuai kontrak dan dapat diulang dengan aman.
- [ ] Simulasikan response sukses tanpa token wajib: tidak ada row lokal yang ditulis seolah-olah sukses.
- [ ] Pastikan STORE/UPDATE/REASSIGN tidak terkirim dua kali untuk satu idempotency key/request.
- [ ] Pastikan data token, payload, waktu akses, dan detail lokal konsisten setelah retry.
- [ ] Pastikan rollback/cleanup tidak menghapus data penjadwalan milik request lain.
- [ ] Uji role admin/operator yang berwenang dan role yang tidak berwenang.
- [ ] Verifikasi request latency, lock duration, third-party response code, dan log correlation ID.

## Test manual A3 — Permit dan dokumen kendaraan

- [ ] Store permit dengan tiga dokumen valid: kendaraan, STNK, dan SIM.
- [ ] Store permit dengan file gambar dan PDF sesuai tipe yang diizinkan.
- [ ] Uji satu upload GCS lambat/gagal dan pastikan request tidak meninggalkan row dokumen tanpa file valid.
- [ ] Uji kegagalan insert checklist/approval setelah upload dan verifikasi seluruh file baru dibersihkan.
- [ ] Uji kegagalan commit/rollback dan pastikan tidak ada orphan file pada folder nomor transaksi.
- [ ] Uji fallback storage hanya bila disetujui; verifikasi metadata disk dan URL pembacaan benar.
- [ ] Uji endpoint upload dokumen terpisah: jika insert database gagal, file yang baru dibuat harus dibersihkan.
- [ ] Pastikan tiga tipe dokumen tidak tertukar dan path database menunjuk file yang benar.
- [ ] Submit permit auto-approved dan membutuhkan approval.
- [ ] Pastikan retry request tidak membuat dokumen duplikat tanpa idempotency/cleanup yang jelas.
- [ ] Pantau durasi lock, request latency, deadlock, dan `Login timeout expired`.

## Test manual A4 — Pengajuan cuti dan lampiran

- [ ] Ajukan cuti tanpa lampiran.
- [ ] Ajukan cuti dengan lampiran PDF, JPG, dan PNG yang valid.
- [ ] Simulasikan kegagalan upload GCS dan pastikan tidak ada transaksi/record setengah jadi.
- [ ] Simulasikan kegagalan pembuatan approval flow setelah upload dan verifikasi file GCS dibersihkan.
- [ ] Simulasikan kegagalan database setelah upload dan verifikasi file GCS dibersihkan.
- [ ] Pastikan file yang berhasil tersimpan dapat dibuka melalui temporary URL.
- [ ] Uji pengajuan yang membutuhkan approval dan auto-approved bila konfigurasi mendukung.
- [ ] Uji notifikasi approver dan verifikasi tidak ada notifikasi ganda bila request di-retry.
- [ ] Pantau apakah notifikasi terkirim sebelum commit; bila ya, catat sebagai risiko side-effect terpisah.
- [ ] Pantau lock duration, request latency, deadlock, dan `Login timeout expired`.

## Test manual A5 — Absensi, izin, sakit, dan lampiran

- [ ] Submit foto absensi normal dan pastikan foto tersimpan serta record absensi konsisten.
- [ ] Submit `izinFull`/sakit dengan lampiran valid.
- [ ] Submit cuti/cuti hutang dengan lampiran valid.
- [ ] Submit izin terlambat/pulang cepat dengan lampiran valid.
- [ ] Simulasikan upload GCS gagal pada masing-masing tiga cabang `submitIzin()`.
- [ ] Simulasikan kegagalan approval-flow atau insert database setelah upload dan pastikan file baru dibersihkan.
- [ ] Submit tanpa lampiran dan pastikan path database tetap null/tidak salah referensi.
- [ ] Pastikan file JPG/PNG/PDF dapat dibuka melalui endpoint/temporary URL yang benar.
- [ ] Verifikasi tidak ada record izin/cuti setengah jadi ketika upload gagal.
- [ ] Verifikasi tidak ada orphan file atau file duplicate setelah retry.
- [ ] Uji notifikasi/WA dan catat apakah side-effect terjadi sebelum commit.
- [ ] Pantau lock duration, request latency, deadlock, dan `Login timeout expired`.

## Test manual B4 — Nomor transaksi dan submit konkuren

- [ ] Submit dua request Overtime secara bersamaan; nomor harus unik dan format tetap sama.
- [ ] Submit Overtime admin dengan beberapa karyawan; semua detail memakai nomor transaksi header yang benar.
- [ ] Submit Overtime biasa dan retry request setelah timeout; tidak boleh menghasilkan nomor duplikat.
- [ ] Submit Form KPI individual secara konkuren.
- [ ] Submit Form KPI team dengan beberapa anggota; setiap pengajuan mendapat nomor yang sesuai dan unik.
- [ ] Submit izin/cuti/sakit dan pastikan satu request hanya memakai satu nomor transaksi.
- [ ] Verifikasi counter baru pada awal periode melakukan self-seed dari data lama tanpa duplikat.
- [ ] Pantau durasi lock counter, request latency, deadlock, dan `Login timeout expired`.
- [ ] Catat gap jika generator masih dipanggil setelah transaction dibuka.

## Bukti yang wajib dicatat

| Field | Isi |
|---|---|
| Environment | staging |
| Build/commit | commit deployment |
| User/role | akun uji dan role |
| Feature | module/feature |
| Test case | nomor checklist |
| Reference | transaction/request/file ID |
| Expected | hasil yang diharapkan |
| Actual | hasil aktual |
| DB/task evidence | status row, Outbox, task/log |
| Result | pass/fail |
| Notes | error, screenshot, log |

Production tidak boleh dianggap aman hanya karena test unit/integration lulus. Checklist manual staging harus selesai dan hasilnya disetujui sebelum rollout production.
