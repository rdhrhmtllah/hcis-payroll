# -*- coding: utf-8 -*-
"""
Isi dokumen UAT Modul SOP.

DIPISAH DARI PERAKITNYA dengan sengaja. Merevisi satu skenario adalah pekerjaan
yang akan sering terjadi sepanjang persiapan UAT; kalau isinya menyatu dengan
kode perakit docx, tiap revisi kecil memaksa membaca ulang logika tabel yang
tidak ada hubungannya. Di sini teksnya berdiri sendiri dan bisa dibaca oleh
orang yang tidak peduli bagaimana docx dirakit.

Rancangannya: plans/sop/2026-08-13-uat-sop-design.md
"""

# Penanda untuk fakta organisasi yang TIDAK BOLEH ditebak dari kode. Sengaja
# memakai guillemet supaya mencolok di halaman putih — kalau sampai lolos ke
# dokumen yang ditandatangani, ia terlihat dari jauh.
def penanda(teks):
    return f'«{teks}»'


# ---------------------------------------------------------------- sampul

SAMPUL = {
    'organisasi': 'EVO GROUP',
    'unit': 'Information Technology - Business Analyst',
    'judul': 'USER ACCEPTANCE TESTING (UAT) DOCUMENT',
    'subjudul_1': 'Modul SOP - Standard Operating Procedure',
    'subjudul_2': 'SISTEM HCIS - EVO GROUP',
    'rahasia': 'CONFIDENTIAL - Dokumen ini hanya untuk kebutuhan internal PT Evo Group',
}

INFO_DOKUMEN = [
    ('Nama Dokumen', 'User Acceptance Testing Document'),
    ('Nama Project', 'Modul SOP (Standard Operating Procedure) - Sistem HCIS'),
    ('Doc ID', penanda('Doc ID')),
    ('Versi', '1'),
    ('Status', penanda('Status - tanggal')),
    ('Tanggal UAT', penanda('Tanggal pelaksanaan UAT')),
    ('Dibuat oleh', 'Ridho'),
    ('Departemen', 'Information Technology'),
    ('Requestor', penanda('Requestor')),
]


# ---------------------------------------------------------------- bab 1

TUJUAN_PEMBUKA = (
    'User Acceptance Testing (UAT) ini bertujuan memvalidasi bahwa Modul SOP pada '
    'Sistem HCIS yang telah dikembangkan benar-benar memenuhi kebutuhan pengelolaan '
    'dokumen terkendali dan sosialisasi SOP di PT Evo Group, dari perspektif pengguna '
    'bisnis sehari-hari - bukan dari sisi teknis developer. UAT dilaksanakan setelah '
    'seluruh Test Case dinyatakan Pass pada tahap System Integration Testing (SIT).'
)

TUJUAN_PENGANTAR = 'Secara spesifik, UAT ini memvalidasi dari sudut pandang pengguna:'

TUJUAN_BUTIR = [
    'Admin SOP memiliki kontrol penuh atas seluruh siklus dokumen: mengelola master '
    'data (kategori, berkas, dokumen terkendali), merakit paket SOP, menjadwalkan '
    'penugasan ke unit maupun perorangan, menyusun aturan penugasan otomatis, serta '
    'memantau kepatuhan secara real-time.',

    'Dokumen SOP dikelola sebagai dokumen terkendali: satu dokumen punya satu '
    'identitas resmi, rantai versi yang jelas, blok pengesahan yang lengkap, masa '
    'berlaku yang terpantau, dan riwayat perubahan yang menyebut siapa melakukan apa.',

    'Karyawan dapat menjelajah SOP perusahaan, mengetahui SOP mana yang wajib ia baca '
    'beserta tenggatnya, membaca dokumennya di dalam sistem, mengerjakan kuis '
    'pemahaman, serta memiliki bukti kepatuhan pribadi.',

    'Atasan dapat memantau kepatuhan SOP anggota timnya - dan hanya anggota timnya '
    'sendiri - sehingga tindak lanjut dapat dilakukan tanpa membuka data lintas tim.',

    'Sistem menolak hal-hal yang membahayakan integritas dokumen terkendali: dokumen '
    'tanpa pengesahan tidak dapat diterbitkan, dokumen yang tidak ditugaskan tidak '
    'dapat dibaca isinya, item wajib tidak dapat dilewati, dan halaman pengelolaan '
    'tertutup bagi yang tidak berwenang.',

    'Hasil akhir - laporan kepatuhan, register kontrol dokumen, dan rekam baca per '
    'karyawan - akurat, konsisten lintas paket, dan dapat diunduh untuk kebutuhan '
    'audit dan pengambilan keputusan.',
]


# ---------------------------------------------------------------- bab 2

# Urutan tabel ini mengikuti alur kerja nyata, dan kolom "Modul" pada tabel
# skenario memakai nama yang sama persis. Satu taksonomi untuk seluruh dokumen.
RUANG_LINGKUP = [
    ('Hak Akses & Menu SOP',
     'Halaman pengelolaan SOP hanya terbuka bagi yang berhak; halaman SOP Saya dan '
     'Repository terbuka bagi seluruh karyawan.'),

    ('Master Kategori SOP',
     'Pengelolaan kategori sebagai rak penyimpanan paket SOP, termasuk perlindungan '
     'atas kategori yang masih dipakai.'),

    ('Master Konten (Berkas)',
     'Unggah dan penelusuran berkas SOP terpusat, daftar pemakai berkas, serta '
     'perlindungan atas berkas yang masih dipakai.'),

    ('Master Dokumen Terkendali',
     'Identitas legal dokumen: nomor SOP resmi, rantai versi, blok pengesahan, masa '
     'berlaku, penerbitan, pengembalian versi, dan jejak audit.'),

    ('Builder Paket SOP',
     'Merakit paket dari dokumen dan materi: section, urutan baca, item wajib, mode '
     'akses, penyalinan antar paket, pratinjau, dan penerbitan paket.'),

    ('Penjadwalan & Penugasan',
     'Menyasar penugasan ke unit maupun perorangan, menetapkan tenggat, dan '
     'menambahkan karyawan ke penjadwalan yang sudah berjalan.'),

    ('Kebijakan & Otomasi',
     'Aturan penugasan otomatis berikut pratinjau dampaknya, setelan modul, riwayat '
     'proses, dan indikator kesehatan penjadwal.'),

    ('Repository SOP',
     'Karyawan menjelajah per kategori, mencari, memfavoritkan, dan mengintip SOP di '
     'luar penugasannya secara terbatas.'),

    ('SOP Saya',
     'Daftar tugas SOP karyawan beserta tenggat, progres, dan kartu kepatuhan '
     'pribadi.'),

    ('Membaca & Konfirmasi Dokumen',
     'Pembaca dokumen di dalam sistem, penyimpanan halaman terakhir, konfirmasi baca, '
     'dan penegakan urutan item wajib.'),

    ('Kuis / Checkpoint',
     'Kuis pemahaman setelah membaca dan pengaruh kelulusannya terhadap status selesai '
     'paket.'),

    ('Absensi Check-in',
     'Pencatatan kehadiran sesi SOP sebagai syarat terbukanya materi paket.'),

    ('Kepatuhan Tim (Leader)',
     'Atasan memantau kepatuhan SOP anggota timnya, dengan batas hanya pada anggotanya '
     'sendiri.'),

    ('Monitoring & Laporan',
     'Kepatuhan per paket dan per karyawan, rekam baca, unduhan laporan audit, dan '
     'register kontrol dokumen.'),

    ('Siklus Hidup Dokumen',
     'Revisi yang menyusul otomatis ke seluruh paket pemakai, permintaan baca ulang, '
     'dan penandaan dokumen kadaluarsa.'),

    ('Notifikasi & Rekertifikasi',
     'Pemberitahuan penugasan baru, pengingat menjelang tenggat, dan penjadwalan ulang '
     'pembacaan secara berkala.'),
]

DILUAR_LINGKUP = (
    'Di luar ruang lingkup UAT: pengujian performance/load testing, penetration testing '
    'keamanan (tercakup pada SIT), proses internal sistem kuis eksternal (hanya '
    'integrasi hasilnya yang diuji pada sisi SOP), migrasi data historis dokumen SOP, '
    'serta modul HCIS lain di luar SOP.'
)


# ---------------------------------------------------------------- bab 3

PESERTA_PEMBUKA = (
    'Peserta UAT terdiri dari perwakilan pihak terkait yang akan menandatangani '
    'Sign-Off. Akun Karyawan dan Leader digunakan sebagai data uji oleh para tester '
    'saat menjalankan skenario dari sisi pengguna.'
)

# Nama dibawa dari dokumen UAT WebKarir; kolom peran disesuaikan ke konteks SOP.
PESERTA = [
    ('IVAN IRVANSYAH', 'HC-GA DIVISION HEAD', 'Pengawas Proses UAT'),
    ('ABDUL RACHMAN', 'HC LEARNING & DEVELOPMENT DEPT. HEAD', 'Pengawas Proses UAT'),
    ('MICHELLE', 'COMPLIANCE & BUSINESS PROCESS DEPT. HEAD', 'Pengawas Proses UAT / Pemilik Proses SOP'),
    ('ENDRIEF MUZAKY', 'HC OPERATION DEPT. HEAD', 'Pengawas Proses UAT'),
    ('HENDRYANTO ALAMSYAH', 'INFRASTRUCTURE & TECHNOLOGY DEPT. HEAD', 'Lead Development Modul SOP'),
    ('OKKY ADMIRAL NUGRATAMA', 'TALENT & PERFORMANCE MANAGEMENT ANALYST', 'PIC Modul SOP / Admin Tester'),
    ('QORY AZZAHRA', 'TALENT MANAGEMENT ANALYST', 'PIC Modul SOP / Admin Tester'),
    ('DIFANY MONALISA', 'RECRUITMENT STAFF', 'Karyawan Tester'),
    ('FRANS BACHTIAR', 'WEB PROGRAMMER STAFF', 'Developer Modul SOP'),
    ('RIDHO RAHMATULLAH', 'WEB PROGRAMMER STAFF', 'Developer Modul SOP'),
]


# ---------------------------------------------------------------- bab 3a

PRASYARAT_PEMBUKA = (
    'Skenario UAT Modul SOP saling bergantung secara berantai: dokumen harus terbit '
    'sebelum paket dapat dirakit, dan paket harus terjadwal sebelum karyawan memiliki '
    'tugas. Bagian ini memastikan penguji tidak mencatat Fail atas sesuatu yang '
    'sebenarnya belum disiapkan. Seluruh baris di bawah wajib terpenuhi sebelum sesi '
    'UAT dimulai.'
)

PRASYARAT = [
    ('Akun Admin SOP',
     f'{penanda("NIK / nama")} - akun yang menjalankan seluruh skenario sisi Admin. '
     'Wajib memiliki ketujuh hak akses pada baris berikutnya.'),

    ('Tujuh hak akses halaman SOP',
     'menuSopKategoriPage, menuSopKontenPage, menuSopDokumenPage, menuSopBuilderPage, '
     'menuSopPenjadwalanPage, menuSopMonitoringPage, menuSopKebijakanPage. '
     'PERHATIAN: skrip pemberian akses yang beredar hanya memuat enam page code - '
     'menuSopDokumenPage tidak termasuk di dalamnya karena halaman Master Dokumen '
     'dibuat setelah skrip tersebut ditulis. Bila tidak ditambahkan manual, halaman '
     'Master Dokumen akan tertutup bagi penguji dan tercatat sebagai cacat sistem '
     'yang sebenarnya tidak ada.'),

    ('Akun Leader',
     f'{penanda("NIK / nama")} - wajib memiliki minimal dua bawahan aktif pada data '
     'struktur organisasi, karena menu Kepatuhan Tim menyimpulkan anggota dari data '
     'atasan, bukan dari hak akses.'),

    ('Dua akun Karyawan',
     f'{penanda("NIK / nama")} dan {penanda("NIK / nama")} - keduanya bawahan langsung '
     'dari akun Leader di atas, agar skenario Kepatuhan Tim dapat diverifikasi.'),

    ('Berkas PDF uji',
     'Minimal tiga berkas SOP, salah satunya lebih dari sepuluh halaman untuk menguji '
     'penyimpanan halaman terakhir yang dibaca.'),

    ('Bank soal kuis',
     'Minimal satu paket soal aktif yang dapat ditautkan sebagai item kuis, agar '
     'skenario 034 dan 035 dapat dijalankan.'),

    ('Proses latar berjalan',
     'Pekerja antrean harus hidup selama sesi UAT. Penyemaian progres penugasan '
     'berjalan lewat antrean ketika jumlah sasaran melewati ambang tertentu; bila '
     'proses ini mati, tugas karyawan tidak terbentuk dan skenario 020, 023, serta '
     '028 akan gagal tanpa sebab yang terlihat di layar.'),

    ('Urutan pelaksanaan',
     'Skenario 001 sampai 025 (sisi Admin) wajib berstatus Pass sebelum skenario 026 '
     'sampai 039 (sisi Karyawan dan Leader) dijalankan. Skenario 040 sampai 048 '
     'dijalankan paling akhir karena memverifikasi hasil dari keduanya.'),

    ('Penandaan data uji',
     'Seluruh kategori, dokumen, dan paket yang dibuat selama UAT diberi awalan '
     '"UAT-" pada judulnya, agar mudah dibedakan dari data sebenarnya dan mudah '
     'dibersihkan setelah sesi berakhir.'),
]


# ---------------------------------------------------------------- bab 4

# Bab ini LAMPIRAN, bukan lembar kerja. Kolom Hasil Aktual/Status/Catatan-nya
# sengaja ditandai strip: kalau dibiarkan kosong, penguji akan mengisinya juga -
# 48 baris yang isinya sudah dicakup 28 baris skrip sesi - dan penyederhanaan
# sesi ini kehilangan seluruh maksudnya.
# SKENARIO_PEMBUKA / SKENARIO_CATATAN DIHAPUS: keduanya memperkenalkan bab
# lampiran yang tidak lagi dicetak. Membiarkan teks yang menjelaskan bab tak
# ada lebih menyesatkan daripada tidak ada teks sama sekali.

SKENARIO = [
    # ------------------------------------------------ 1. Hak Akses & Menu SOP
    ('UAT-SOP-001', 'Hak Akses & Menu SOP', 'Admin SOP',
     'Sebagai Admin SOP, saya ingin memastikan seluruh halaman pengelolaan SOP terbuka '
     'bagi saya, sehingga saya dapat menjalankan tugas dari master data sampai '
     'pemantauan tanpa terhalang.',
     'Login sebagai Admin SOP. Buka satu per satu menu Kategori SOP, Master Konten, '
     'Master Dokumen, Builder, Penjadwalan, Monitoring, dan Kebijakan SOP.',
     'Ketujuh halaman terbuka dan menampilkan datanya masing-masing; tidak ada halaman '
     'yang menolak akses.'),

    ('UAT-SOP-002', 'Hak Akses & Menu SOP', 'Karyawan',
     '[KONTROL] Sebagai perusahaan, saya ingin karyawan biasa tidak dapat masuk ke '
     'halaman pengelolaan SOP, sehingga dokumen resmi tidak dapat diubah oleh yang '
     'tidak berwenang.',
     'Login sebagai Karyawan biasa. Perhatikan daftar menu SOP yang tampil. Kemudian '
     'ketikkan alamat halaman pengelolaan SOP secara langsung pada bilah alamat '
     'peramban.',
     'Menu pengelolaan SOP tidak muncul sama sekali, dan mengakses alamatnya secara '
     'langsung tetap ditolak. Menu SOP Saya dan Repository SOP tetap dapat dibuka.'),

    # ------------------------------------------------ 2. Master Kategori SOP
    ('UAT-SOP-003', 'Master Kategori SOP', 'Admin SOP',
     'Sebagai Admin SOP, saya ingin mengelola kategori SOP sebagai rak penyimpanan '
     'paket, sehingga karyawan mudah menemukan SOP yang relevan dengan pekerjaannya.',
     'Buka menu Kategori SOP. Tambahkan kategori baru beserta keterangan dan urutan '
     'tampilnya. Ubah salah satu kategori yang sudah ada, lalu simpan.',
     'Kategori tersimpan dan langsung tersedia sebagai pilihan saat merakit paket di '
     'Builder, serta tampil di Repository sesuai urutan yang diatur.'),

    ('UAT-SOP-004', 'Master Kategori SOP', 'Admin SOP',
     '[KONTROL] Sebagai Admin SOP, saya ingin kategori yang masih dipakai tidak dapat '
     'terhapus, sehingga paket SOP tidak tiba-tiba kehilangan tempatnya.',
     'Pilih kategori yang sudah dipakai oleh minimal satu paket SOP. Coba hapus '
     'kategori tersebut.',
     'Sistem menolak penghapusan dan menjelaskan bahwa kategori masih dipakai. Paket '
     'yang memakainya tetap utuh.'),

    # ------------------------------------------------ 3. Master Konten (Berkas)
    ('UAT-SOP-005', 'Master Konten (Berkas)', 'Admin SOP',
     'Sebagai Admin SOP, saya ingin mengunggah berkas SOP ke penyimpanan terpusat, '
     'sehingga satu berkas fisik dapat dipakai berulang tanpa digandakan.',
     'Buka menu Master Konten. Unggah berkas PDF SOP baru beserta judul dan '
     'keterangannya. Simpan, lalu temukan kembali berkas itu melalui pencarian.',
     'Berkas tersimpan, dapat ditemukan kembali melalui pencarian, dan tersedia untuk '
     'dipilih saat membuat versi dokumen.'),

    ('UAT-SOP-006', 'Master Konten (Berkas)', 'Admin SOP',
     '[KONTROL] Sebagai Admin SOP, saya ingin mengetahui siapa saja pemakai sebuah '
     'berkas dan berkas yang terpakai tidak dapat saya hapus, sehingga tidak ada '
     'dokumen yang mendadak kosong.',
     'Pilih berkas yang sudah dipakai pada versi dokumen. Buka daftar pemakainya. '
     'Kemudian coba hapus berkas tersebut.',
     'Daftar pemakai menampilkan dokumen dan paket yang memakainya, dan penghapusan '
     'ditolak disertai penjelasan yang jelas.'),

    # ------------------------------------------------ 4. Master Dokumen Terkendali
    ('UAT-SOP-007', 'Master Dokumen Terkendali', 'Admin SOP',
     'Sebagai Admin SOP, saya ingin mendaftarkan dokumen SOP sebagai dokumen '
     'terkendali dengan identitas resminya, sehingga satu dokumen memiliki satu '
     'identitas di seluruh perusahaan.',
     'Buka menu Master Dokumen. Tambahkan dokumen baru dengan mengisi nomor SOP resmi, '
     'judul, tipe dokumen, dan kategori. Simpan.',
     'Dokumen tercatat pada daftar dokumen terkendali dengan nomor dan judul yang '
     'seragam, dan siap diberi versi.'),

    ('UAT-SOP-008', 'Master Dokumen Terkendali', 'Admin SOP',
     'Sebagai Admin SOP, saya ingin menambahkan versi dokumen tanpa harus menggulir '
     'melewati seluruh riwayatnya, sehingga membuat revisi tidak makin merepotkan '
     'seiring dokumen makin sering direvisi.',
     'Buka detail sebuah dokumen dan perhatikan bahwa layar pertama menampilkan riwayat '
     'versi. Tekan tombol Versi baru di bawah daftar. Isi berkas SOP, nomor versi, '
     'tanggal berlaku, tanggal kadaluarsa, jadwal review, blok pengesahan, dan '
     'ringkasan perubahan. Tekan Simpan sebagai draft.',
     'Formulir tampil menggantikan riwayat, dengan kepala yang menyebut versi yang '
     'sedang dibuat beserta asalnya. Setelah disimpan, layar kembali ke riwayat dan '
     'versi baru muncul berstatus draft.'),

    ('UAT-SOP-009', 'Master Dokumen Terkendali', 'Admin SOP',
     '[KONTROL] Sebagai Admin SOP, saya ingin isian formulir versi tidak terbuang '
     'diam-diam saat saya menekan Kembali, sehingga pekerjaan panjang tidak hilang '
     'karena satu klik.',
     'Tekan Versi baru, isi sebagian formulir, lalu tekan Kembali tanpa menyimpan.',
     'Sistem meminta konfirmasi lebih dahulu sebelum meninggalkan formulir. Bila '
     'konfirmasi dibatalkan, seluruh isian tetap utuh.'),

    # Skenario ini semula berbunyi "tandai versi sebagai siap terbit → status
    # berubah menjadi siap terbit". Keadaan itu MUSTAHIL: CHECK constraint
    # CK_N_SOP_Versi_Status hanya mengizinkan DRAFT/PUBLISHED/OBSOLETE,
    # sedangkan setReady() menulis 'READY'. Data produksi berisi nol baris READY
    # bukan karena tak dipakai, melainkan karena tak bisa. Alur yang benar-benar
    # bekerja adalah DRAFT -> PUBLISHED langsung.
    #
    # ID-nya sengaja DIPERTAHANKAN dan isinya yang diganti. Menghapus satu
    # skenario menggeser penomoran seluruh dokumen, dan nomor UAT-ID sudah
    # dipakai sebagai rujukan silang di Defect Log, di bab Prasyarat, dan pada
    # penandaan uji otomatis.
    ('UAT-SOP-010', 'Master Dokumen Terkendali', 'Admin SOP',
     'Sebagai Admin SOP, saya ingin melengkapi blok pengesahan sebuah versi sebelum '
     'menerbitkannya, sehingga hanya dokumen yang sudah ditandatangani yang dapat naik '
     'menjadi aturan kerja.',
     'Buka versi berstatus draft. Isi blok pengesahan (Disiapkan oleh, Diperiksa oleh, '
     'Disetujui oleh, Disahkan oleh) beserta masa berlakunya, lalu simpan. Terbitkan '
     'versi tersebut.',
     'Blok pengesahan tersimpan lengkap beserta nama dan jabatan penanda tangannya, dan '
     'versi berhasil terbit. Versi yang blok pengesahannya masih kosong ditolak saat '
     'diterbitkan (lihat UAT-SOP-013).'),

    ('UAT-SOP-011', 'Master Dokumen Terkendali', 'Admin SOP',
     'Sebagai Admin SOP, saya ingin melihat dampak sebuah revisi sebelum '
     'menerbitkannya, sehingga saya mengetahui paket dan orang mana yang akan '
     'terpengaruh.',
     # "siap terbit" DICABUT di sini juga. Perbaikan pada 010 semula hanya
     # dipasang di satu skenario, padahal 011 dan 012 memakai premis yang persis
     # sama — penguji akan mencari status yang tak pernah bisa ada, lalu
     # melaporkan cacat pada dirinya sendiri.
     'Pada versi berstatus draft yang belum terbit, buka tampilan Dampak.',
     'Sistem menampilkan daftar paket yang memakai dokumen ini beserta perkiraan jumlah '
     'karyawan yang akan terdampak, sebelum keputusan menerbitkan diambil.'),

    ('UAT-SOP-012', 'Master Dokumen Terkendali', 'Admin SOP',
     'Sebagai Admin SOP, saya ingin menerbitkan versi baru dan memastikan versi lama '
     'otomatis tidak berlaku lagi, sehingga tidak ada dua versi yang sama-sama mengaku '
     'berlaku.',
     'Terbitkan versi berstatus draft yang blok pengesahannya sudah lengkap. Kembali '
     'ke riwayat versi dan perhatikan status seluruh versi dokumen tersebut.',
     'Versi baru berstatus terbit dan menjadi versi aktif; versi sebelumnya berubah '
     'menjadi tidak berlaku. Hanya ada satu versi yang berstatus aktif.'),

    ('UAT-SOP-013', 'Master Dokumen Terkendali', 'Admin SOP',
     '[KONTROL] Sebagai perusahaan, saya ingin dokumen yang belum lengkap tidak dapat '
     'diterbitkan, sehingga tidak ada SOP tanpa berkas atau tanpa pengesahan yang '
     'tersaji ke karyawan.',
     'Buat versi draft baru tanpa mengunggah berkas, lalu coba terbitkan. Ulangi dengan '
     'versi yang berkasnya ada tetapi blok pengesahannya dibiarkan kosong.',
     'Kedua-duanya ditolak, dan sistem menyebutkan bagian mana yang belum lengkap — '
     'berkas pada percobaan pertama, pengesahan pada percobaan kedua. Versi tetap '
     'berstatus draft dan tidak tersaji ke karyawan.'),

    ('UAT-SOP-014', 'Master Dokumen Terkendali', 'Admin SOP',
     'Sebagai Admin SOP, saya ingin mengembalikan dokumen ke versi sebelumnya bila '
     'revisi terakhir keliru, dan setiap perubahan tercatat siapa pelakunya.',
     'Pada riwayat versi, kembalikan dokumen ke versi sebelumnya. Kemudian buka jejak '
     'audit dokumen tersebut.',
     'Versi lama kembali menjadi versi aktif, dan jejak audit mencatat siapa yang '
     'melakukan, kapan, serta apa yang berubah pada tiap langkah siklus versi.'),

    # ------------------------------------------------ 5. Builder Paket SOP
    ('UAT-SOP-015', 'Builder Paket SOP', 'Admin SOP',
     'Sebagai Admin SOP, saya ingin membuat paket SOP sebagai satu kesatuan bacaan bagi '
     'karyawan, sehingga penugasannya tidak terpecah per dokumen.',
     'Buka menu Builder. Buat paket SOP baru dengan mengisi judul, kategori, '
     'keterangan, dan gambar sampul. Simpan.',
     'Paket tersimpan sebagai draft dan siap diisi dokumen serta materi lainnya.'),

    ('UAT-SOP-016', 'Builder Paket SOP', 'Admin SOP',
     'Sebagai Admin SOP, saya ingin menyusun isi paket beserta urutan bacanya dan '
     'menandai mana yang wajib, sehingga karyawan membaca dalam urutan yang saya '
     'maksudkan.',
     'Di dalam paket, tambahkan beberapa section dan item. Ubah urutannya dengan '
     'menyeret. Tandai sebagian item sebagai Wajib dan sebagian sebagai Sorotan, lalu '
     'atur mode aksesnya. Simpan.',
     'Susunan, penandaan wajib dan sorotan, serta mode akses tersimpan dan tercermin '
     'persis pada pratinjau paket.'),

    ('UAT-SOP-017', 'Builder Paket SOP', 'Admin SOP',
     'Sebagai Admin SOP, saya ingin menyisipkan dokumen terkendali yang sudah ada ke '
     'dalam paket, sehingga tata kelolanya tetap satu dan tidak tergandakan.',
     'Pada paket, tambahkan item bertipe dokumen dan pilih dokumen dari Master Dokumen. '
     'Simpan.',
     'Item paket menunjuk dokumen terkendali tersebut beserta versi aktifnya; nomor dan '
     'judulnya mengikuti Master Dokumen, bukan diketik ulang.'),

    ('UAT-SOP-018', 'Builder Paket SOP', 'Admin SOP',
     'Sebagai Admin SOP, saya ingin menyalin item ke paket lain dan tautannya tetap '
     'hidup, sehingga revisi di satu tempat berlaku di semua paket pemakainya.',
     'Pilih item dokumen pada satu paket, salin ke paket lain. Buka paket tujuan dan '
     'periksa dokumen yang tersalin.',
     'Paket tujuan menampilkan dokumen yang sama dengan versi aktif yang sama. Keduanya '
     'menunjuk satu dokumen terkendali, bukan dua salinan yang berdiri sendiri.'),

    ('UAT-SOP-019', 'Builder Paket SOP', 'Admin SOP',
     'Sebagai Admin SOP, saya ingin meninjau paket sebelum menerbitkannya dan '
     'menentukan revisi mana yang mengharuskan karyawan membaca ulang.',
     'Buka pratinjau paket dan periksa tampilannya dari sisi karyawan. Kemudian '
     'terbitkan paket; pada layar penerbitan, tentukan sifat revisi untuk tiap dokumen '
     'yang berubah, lalu konfirmasi.',
     'Pratinjau menampilkan paket persis seperti yang akan dilihat karyawan. Paket '
     'terbit dengan nomor rilis baru, dan sifat revisi tiap dokumen tersimpan sesuai '
     'pilihan.'),

    # ------------------------------------------------ 6. Penjadwalan & Penugasan
    ('UAT-SOP-020', 'Penjadwalan & Penugasan', 'Admin SOP',
     'Sebagai Admin SOP, saya ingin menugaskan paket ke seluruh karyawan pada unit '
     'tertentu berikut tenggatnya, sehingga kewajiban baca memiliki batas waktu yang '
     'jelas.',
     'Buka menu Penjadwalan. Buat penjadwalan baru untuk paket yang sudah terbit, pilih '
     'sasaran per unit atau departemen, tetapkan tanggal mulai dan tenggat, lalu simpan '
     'dan aktifkan.',
     'Penjadwalan tersimpan, jumlah sasaran tampil, dan seluruh karyawan pada unit '
     'tersebut menerima tugas SOP dengan tenggat yang ditetapkan.'),

    ('UAT-SOP-021', 'Penjadwalan & Penugasan', 'Admin SOP',
     'Sebagai Admin SOP, saya ingin menugaskan paket ke orang-orang tertentu dengan '
     'menempelkan daftar NIK, sehingga penugasan khusus tidak perlu dipilih satu per '
     'satu.',
     'Buat penjadwalan dengan sasaran per orang. Tempelkan daftar NIK yang sengaja '
     'memuat beberapa NIK yang tidak dikenal. Simpan.',
     'NIK yang dikenal masuk sebagai sasaran, sementara NIK yang tidak dikenal '
     'dilaporkan secara terbuka kepada Admin - bukan dibuang tanpa pemberitahuan.'),

    ('UAT-SOP-022', 'Penjadwalan & Penugasan', 'Admin SOP',
     '[KONTROL] Sebagai Admin SOP, saya ingin jumlah orang pada pratinjau sasaran sama '
     'persis dengan yang benar-benar tersimpan, sehingga tidak ada karyawan yang luput '
     'tanpa saya sadari.',
     'Buat penjadwalan dengan sasaran berjumlah besar hingga melebihi satu halaman '
     'pratinjau. Catat jumlah yang tertera, telusuri seluruh halaman pratinjau, lalu '
     'simpan dan bandingkan dengan jumlah penugasan yang benar-benar terbentuk.',
     'Jumlah pada pratinjau, jumlah pada seluruh halaman, dan jumlah penugasan yang '
     'terbentuk sama. Bila sistem menerapkan pembatasan jumlah, pembatasan itu '
     'dinyatakan secara terbuka di layar.'),

    ('UAT-SOP-023', 'Penjadwalan & Penugasan', 'Admin SOP',
     'Sebagai Admin SOP, saya ingin menambahkan karyawan ke penjadwalan yang sudah '
     'berjalan, sehingga karyawan baru atau mutasi tidak perlu menunggu penjadwalan '
     'berikutnya.',
     'Buka penjadwalan yang sedang aktif. Tambahkan seorang karyawan. Simpan, lalu '
     'periksa daftar tugas karyawan tersebut.',
     'Karyawan itu langsung memiliki tugas SOP paket tersebut beserta tenggatnya, dan '
     'tercatat pada pemantauan penjadwalan.'),

    # ------------------------------------------------ 7. Kebijakan & Otomasi
    ('UAT-SOP-024', 'Kebijakan & Otomasi', 'Admin SOP',
     'Sebagai Admin SOP, saya ingin menyusun aturan penugasan otomatis, sehingga '
     'karyawan yang memenuhi kriteria tertentu tidak perlu ditugaskan manual setiap '
     'kali.',
     'Buka menu Kebijakan SOP. Buat aturan penugasan baru dengan kriteria tertentu '
     '(misalnya unit atau jabatan) beserta paket sasarannya. Jalankan pratinjau aturan '
     'sebelum menyimpannya.',
     'Pratinjau menampilkan daftar karyawan yang akan terkena aturan, sehingga '
     'dampaknya diketahui sebelum aturan diaktifkan.'),

    ('UAT-SOP-025', 'Kebijakan & Otomasi', 'Admin SOP',
     'Sebagai Admin SOP, saya ingin mengaktifkan aturan, mengubah setelan modul, dan '
     'memastikan proses terjadwalnya sehat, sehingga otomasi tidak berjalan diam-diam '
     'tanpa dapat dipantau.',
     'Aktifkan aturan yang baru dibuat lalu jalankan sekali secara manual. Buka riwayat '
     'jalannya. Ubah salah satu setelan modul SOP dan periksa indikator kesehatan '
     'penjadwal. Nonaktifkan kembali aturan tersebut.',
     'Aturan berjalan dan hasilnya tercatat pada riwayat; perubahan setelan tersimpan; '
     'indikator kesehatan menampilkan kapan proses terjadwal terakhir berjalan. Aturan '
     'yang dinonaktifkan berhenti menugaskan.'),

    # ------------------------------------------------ 8. Repository SOP
    ('UAT-SOP-026', 'Repository SOP', 'Karyawan',
     'Sebagai karyawan, saya ingin menjelajah dan mencari SOP perusahaan, sehingga saya '
     'dapat menemukan aturan kerja yang saya butuhkan tanpa bertanya ke HC.',
     'Login sebagai Karyawan. Buka Repository SOP. Telusuri per kategori, gunakan '
     'pencarian dengan kata kunci, lalu tandai satu SOP sebagai favorit dan buka '
     'kembali daftar favorit.',
     'SOP tampil terkelompok per kategori, pencarian menemukan SOP yang relevan, dan '
     'favorit tersimpan serta dapat dibuka kembali.'),

    ('UAT-SOP-027', 'Repository SOP', 'Karyawan',
     '[KONTROL] Sebagai perusahaan, saya ingin karyawan hanya dapat melihat keterangan '
     'SOP yang tidak ditugaskan kepadanya - bukan isinya - sehingga dokumen terkendali '
     'tidak bocor ke luar penugasannya.',
     'Pada Repository, buka SOP yang tidak ditugaskan kepada akun ini melalui fitur '
     'intip.',
     'Yang tampil hanya keterangan ringkas seperti judul, kategori, dan nomor. Isi '
     'dokumen dan berkasnya tidak dapat dibuka maupun diunduh.'),

    # ------------------------------------------------ 9. SOP Saya
    ('UAT-SOP-028', 'SOP Saya', 'Karyawan',
     'Sebagai karyawan, saya ingin melihat semua SOP yang wajib saya baca beserta '
     'tenggatnya di satu tempat, sehingga saya tidak melewatkan kewajiban.',
     'Buka menu SOP Saya. Perhatikan daftar tugas, tenggat, dan sejauh mana progres '
     'tiap paket.',
     'Seluruh paket yang ditugaskan tampil beserta tenggat dan progresnya; paket yang '
     'mendekati atau melewati tenggat tertandai dengan jelas.'),

    ('UAT-SOP-029', 'SOP Saya', 'Karyawan',
     'Sebagai karyawan, saya ingin melihat kartu kepatuhan pribadi saya, sehingga saya '
     'memiliki bukti bahwa kewajiban SOP saya sudah terpenuhi.',
     'Pada halaman SOP Saya, buka kartu kepatuhan pribadi.',
     'Kartu menampilkan ringkasan kepatuhan pribadi berupa jumlah paket selesai, yang '
     'masih berjalan, dan yang lewat tenggat - sesuai dengan daftar tugasnya.'),

    # ------------------------------------------------ 10. Membaca & Konfirmasi
    ('UAT-SOP-030', 'Membaca & Konfirmasi Dokumen', 'Karyawan',
     'Sebagai karyawan, saya ingin membuka paket SOP dan menemukan isinya tersusun '
     'rapi, sehingga saya tahu harus mulai dari mana.',
     'Dari SOP Saya, buka salah satu paket yang ditugaskan. Perhatikan susunan section '
     'dan itemnya.',
     'Item tampil sesuai urutan dan pengelompokan yang disusun Admin di Builder, '
     'lengkap dengan penanda item wajib dan sorotan.'),

    ('UAT-SOP-031', 'Membaca & Konfirmasi Dokumen', 'Karyawan',
     'Sebagai karyawan, saya ingin membaca dokumen SOP di dalam sistem dan dapat '
     'melanjutkan dari halaman terakhir, sehingga dokumen panjang tidak harus selesai '
     'sekali duduk.',
     'Buka item dokumen dan baca berkasnya pada pembaca bawaan. Gulir sampai beberapa '
     'halaman, tutup paket, lalu buka kembali item yang sama. Coba pula mengunduh atau '
     'mencetak berkasnya.',
     'Pembaca membuka kembali pada halaman terakhir yang dibaca. Berkas tidak dapat '
     'diunduh maupun dicetak dari pembaca.'),

    ('UAT-SOP-032', 'Membaca & Konfirmasi Dokumen', 'Karyawan',
     '[KONTROL] Sebagai perusahaan, saya ingin konfirmasi baca hanya dapat ditekan '
     'setelah dokumennya benar-benar dibaca tuntas, sehingga pernyataan sudah membaca '
     'bukan sekadar formalitas.',
     'Buka item dokumen dan segera coba tekan tombol konfirmasi tanpa membaca sampai '
     'halaman terakhir. Kemudian baca sampai halaman terakhir dan tekan konfirmasi.',
     'Sebelum dokumen terbaca tuntas, tombol konfirmasi tidak dapat ditekan dan '
     'alasannya dijelaskan. Setelah tuntas, konfirmasi berhasil dan waktunya tercatat.'),

    ('UAT-SOP-033', 'Membaca & Konfirmasi Dokumen', 'Karyawan',
     '[KONTROL] Sebagai perusahaan, saya ingin item wajib tidak dapat dilewati, '
     'sehingga urutan pemahaman yang dirancang tetap terjaga.',
     'Pada paket yang memiliki item wajib berurutan, coba buka item berikutnya sebelum '
     'item wajib sebelumnya dikonfirmasi.',
     'Item berikutnya terkunci beserta penjelasannya, dan baru terbuka setelah item '
     'wajib sebelumnya dikonfirmasi.'),

    # ------------------------------------------------ 11. Kuis / Checkpoint
    ('UAT-SOP-034', 'Kuis / Checkpoint', 'Karyawan',
     'Sebagai karyawan, saya ingin mengerjakan kuis pemahaman setelah membaca SOP, '
     'sehingga pemahaman saya terukur - bukan sekadar tercatat membaca.',
     'Pada paket yang memiliki item kuis, buka kuis tersebut dan kerjakan sampai selesai '
     'dengan jawaban yang benar.',
     'Kuis terbuka, jawaban tersimpan, dan nilainya kembali serta tercatat pada progres '
     'paket karyawan tersebut.'),

    ('UAT-SOP-035', 'Kuis / Checkpoint', 'Karyawan',
     '[KONTROL] Sebagai perusahaan, saya ingin karyawan yang belum lulus kuis belum '
     'dianggap menyelesaikan paketnya, sehingga sertifikasi pemahaman memiliki arti.',
     'Kerjakan kuis dengan jawaban yang sengaja salah sehingga nilainya di bawah ambang '
     'lulus. Periksa status paket pada halaman SOP Saya.',
     'Paket belum berstatus selesai, dan karyawan diberi tahu bahwa kuis perlu diulang '
     'sesuai ketentuan yang berlaku.'),

    # ------------------------------------------------ 12. Absensi Check-in
    ('UAT-SOP-036', 'Absensi Check-in', 'Karyawan',
     'Sebagai karyawan, saya ingin melakukan check-in kehadiran pada sesi SOP yang '
     'mensyaratkannya, sehingga kehadiran saya pada sosialisasi tercatat.',
     'Buka paket SOP yang mensyaratkan kehadiran. Lakukan check-in sesuai cara yang '
     'diminta sistem.',
     'Check-in berhasil dan tercatat beserta waktunya; item paket menjadi terbuka '
     'setelahnya.'),

    ('UAT-SOP-037', 'Absensi Check-in', 'Karyawan',
     '[KONTROL] Sebagai perusahaan, saya ingin materi sesi hanya terbuka bagi yang '
     'sudah check-in, sehingga catatan kehadiran tidak dapat dilewati.',
     'Pada paket yang mensyaratkan kehadiran, coba buka itemnya sebelum melakukan '
     'check-in.',
     'Item tetap tersegel disertai penjelasan bahwa check-in diperlukan terlebih '
     'dahulu.'),

    # ------------------------------------------------ 13. Kepatuhan Tim
    ('UAT-SOP-038', 'Kepatuhan Tim (Leader)', 'Leader',
     'Sebagai atasan, saya ingin melihat kepatuhan SOP seluruh anggota tim saya, '
     'sehingga saya mengetahui siapa yang perlu diingatkan.',
     'Login sebagai Leader. Buka menu Kepatuhan Tim dan perhatikan ringkasan kepatuhan '
     'anggota tim.',
     'Ringkasan menampilkan seluruh anggota tim beserta status kepatuhannya: selesai, '
     'berjalan, dan lewat tenggat.'),

    ('UAT-SOP-039', 'Kepatuhan Tim (Leader)', 'Leader',
     '[KONTROL] Sebagai perusahaan, saya ingin atasan hanya melihat anggotanya sendiri, '
     'sehingga data kepatuhan tidak terbuka lintas tim.',
     'Telusuri salah satu anggota untuk melihat rinciannya. Kemudian coba mengakses '
     'data karyawan yang bukan anggota tim ini.',
     'Rincian anggota sendiri terbuka lengkap. Karyawan di luar tim tidak muncul pada '
     'daftar dan aksesnya ditolak.'),

    # ------------------------------------------------ 14. Monitoring & Laporan
    ('UAT-SOP-040', 'Monitoring & Laporan', 'Admin SOP',
     'Sebagai Admin SOP, saya ingin memantau kepatuhan sebuah paket, sehingga saya '
     'mengetahui sejauh mana sosialisasi SOP berhasil.',
     'Buka menu Monitoring dan pilih paket yang sedang ditugaskan. Perhatikan angka '
     'kepatuhannya dan daftar karyawan pada tiap status.',
     'Monitoring menampilkan jumlah selesai, berjalan, dan lewat tenggat beserta daftar '
     'karyawannya. Angkanya sesuai dengan kenyataan yang diuji pada skenario sisi '
     'karyawan.'),

    ('UAT-SOP-041', 'Monitoring & Laporan', 'Admin SOP',
     'Sebagai Admin SOP, saya ingin menelusuri seorang karyawan lintas paket, sehingga '
     'saya dapat menjawab pertanyaan audit mengenai orang tertentu.',
     'Pada Monitoring, telusuri seorang karyawan. Buka rincian paketnya, termasuk rekam '
     'bacanya dan waktu konfirmasinya.',
     'Tampil seluruh paket yang ditugaskan kepada karyawan itu, status tiap item, rekam '
     'baca, dan waktu konfirmasinya.'),

    ('UAT-SOP-042', 'Monitoring & Laporan', 'Admin SOP',
     'Sebagai Admin SOP, saya ingin mengunduh laporan kepatuhan, sehingga data ini '
     'dapat dipakai pada rapat dan audit tanpa membuka sistem.',
     'Dari halaman Monitoring, unduh laporan kepatuhan untuk paket atau periode '
     'tertentu. Buka berkas hasil unduhan.',
     'Berkas terunduh dan isinya sesuai dengan yang tampil di layar: nama karyawan, '
     'paket, status, dan waktu penyelesaian.'),

    ('UAT-SOP-043', 'Monitoring & Laporan', 'Admin SOP',
     '[KONTROL] Sebagai pemilik proses, saya ingin register kontrol dokumen melaporkan '
     'satu dokumen dengan satu status, sehingga laporan kepatuhan tidak saling '
     'bertentangan.',
     'Buka register kontrol dokumen. Cari dokumen yang dipakai lebih dari satu paket '
     '(hasil skenario UAT-SOP-018) dan perhatikan statusnya.',
     'Dokumen tersebut dilaporkan satu kali dengan satu status masa berlaku - bukan '
     'berulang dengan status yang berbeda-beda per paket.'),

    # ------------------------------------------------ 15. Siklus Hidup Dokumen
    ('UAT-SOP-044', 'Siklus Hidup Dokumen', 'Admin SOP & Karyawan',
     'Sebagai pemilik proses, saya ingin revisi dokumen otomatis berlaku di semua paket '
     'yang memakainya, sehingga tidak ada unit yang masih memegang versi lama.',
     'Terbitkan versi baru atas dokumen yang dipakai lebih dari satu paket (hasil '
     'skenario UAT-SOP-018). Buka kedua paket tersebut dari sisi Admin, lalu buka pula '
     'dari akun Karyawan.',
     'Kedua paket menampilkan versi terbaru tanpa perlu diubah satu per satu. Karyawan '
     'melihat versi yang sama di paket mana pun ia membukanya.'),

    ('UAT-SOP-045', 'Siklus Hidup Dokumen', 'Karyawan',
     'Sebagai karyawan, saya ingin diberi tahu bila SOP yang sudah saya baca direvisi '
     'secara mendasar, sehingga saya tidak bekerja dengan aturan yang sudah berubah.',
     'Dengan akun Karyawan yang sudah mengkonfirmasi dokumen pada skenario UAT-SOP-032, '
     'buka kembali paket tersebut setelah revisi wajib diterbitkan pada skenario '
     'UAT-SOP-044.',
     'Dokumen itu kembali diminta untuk dibaca dan dikonfirmasi ulang, dan status '
     'paketnya menyesuaikan. Revisi yang tidak bersifat wajib tidak meminta baca '
     'ulang.'),

    ('UAT-SOP-046', 'Siklus Hidup Dokumen', 'Admin SOP',
     '[KONTROL] Sebagai pemilik proses, saya ingin dokumen yang lewat masa berlakunya '
     'tertandai, sehingga SOP kadaluarsa tidak diam-diam tetap dipakai.',
     'Siapkan dokumen dengan tanggal kadaluarsa yang sudah lewat. Buka register kontrol '
     'dokumen dan halaman Monitoring.',
     'Dokumen tersebut tertandai kadaluarsa pada register maupun Monitoring, dan '
     'penandaannya terlihat jelas tanpa perlu dicari.'),

    # ------------------------------------------------ 16. Notifikasi & Rekertifikasi
    ('UAT-SOP-047', 'Notifikasi & Rekertifikasi', 'Karyawan',
     'Sebagai karyawan, saya ingin diberi tahu saat ada SOP baru yang harus saya baca '
     'dan diingatkan menjelang tenggat, sehingga saya tidak terlambat karena lupa.',
     'Setelah penjadwalan pada skenario UAT-SOP-020 diaktifkan, periksa pemberitahuan '
     'pada akun Karyawan. Jalankan pengingat sesuai kadens yang disetel pada Kebijakan, '
     'lalu periksa kembali.',
     'Pemberitahuan penugasan baru diterima dengan isi yang benar, dan pengingat '
     'menjelang tenggat diterima sesuai kadens yang disetel.'),

    ('UAT-SOP-048', 'Notifikasi & Rekertifikasi', 'Admin SOP & Karyawan',
     'Sebagai pemilik proses, saya ingin SOP tertentu dibaca ulang secara berkala tanpa '
     'dijadwalkan manual, sehingga pemahaman karyawan tetap terjaga.',
     'Setel rekertifikasi berkala pada paket uji dengan periode yang sudah terlampaui, '
     'lalu jalankan proses rekertifikasi. Periksa daftar tugas pada akun Karyawan yang '
     'sebelumnya sudah menyelesaikan paket tersebut.',
     'Karyawan yang masa berlaku bacaannya sudah habis kembali menerima tugas membaca '
     'paket tersebut, tanpa Admin perlu membuat penjadwalan baru.'),
]


# ---------------------------------------------------------------- bab 5

KRITERIA_PEMBUKA = (
    'UAT dinyatakan berhasil dan Modul SOP layak untuk Go Live apabila kriteria berikut '
    'terpenuhi secara keseluruhan:'
)

KRITERIA = [
    # Yang mengikat adalah bab 4 - itu yang dijalankan dan ditandatangani. Lampiran
    # per modul tidak dinilai baris per baris: kalau ia ikut mengikat, penguji harus
    # Yang mengikat adalah bab 4 - itu yang dijalankan dan ditandatangani.
    'Seluruh fitur pada Daftar Uji SOP berstatus Pass - tidak ada yang Fail atau '
    'Blocked saat sesi UAT berakhir. Sebuah fitur berstatus Pass hanya bila SELURUH '
    'poin pada barisnya terpenuhi, termasuk poin [TOLAK].',

    'Tidak ada Issue dengan severity Critical yang masih Open pada Defect/Issue Log saat '
    'sesi UAT berakhir.',

    'Issue dengan severity Major (jika ada) sudah memiliki rencana perbaikan, PIC, dan '
    'tenggat yang disepakati sebelum Go Live.',

    'Issue dengan severity Minor (jika ada) dapat diterima untuk Go Live dengan catatan '
    'perbaikan pada periode hypercare.',

    'Seluruh poin bertanda [TOLAK] berstatus Pass. Kriteria ini tidak dapat ditawar '
    'menjadi Minor: sistem dokumen terkendali yang gagal menolak hal yang salah tidak '
    'layak Go Live meskipun seluruh jalur normalnya berjalan mulus.',

    'Konsistensi data kepatuhan terverifikasi tanpa selisih antara halaman SOP Saya, '
    'Kepatuhan Tim, Monitoring, dan berkas laporan yang diunduh.',

    'Seluruh peserta UAT menyatakan persetujuan tertulis pada bagian Sign-Off UAT.',
]


# ---------------------------------------------------------------- bab 6

DEFECT_PEMBUKA = (
    'Tabel berikut dikosongkan pada dokumen ini - akan diisi oleh fasilitator UAT (IT '
    'Business Analyst) selama sesi UAT berlangsung, merujuk pada UAT-ID skenario tempat '
    'issue tersebut ditemukan.'
)

SEVERITY = [
    'Critical - proses inti terhenti, data kepatuhan salah, atau dokumen yang belum sah '
    'tersaji kepada karyawan. Menghalangi Go Live.',

    'Major - fitur penting tidak berfungsi dengan benar dan hanya dapat diakali dengan '
    'cara memutar yang merepotkan. Harus diperbaiki sebelum Go Live.',

    'Minor - masalah tampilan, penulisan, atau kenyamanan pemakaian; proses bisnis tetap '
    'dapat berjalan.',
]

DEFECT_BARIS_KOSONG = 5


# ---------------------------------------------------------------- bab 7

HASIL_KESIMPULAN = [
    ('Lulus', 'Semua skenario Pass, tidak ada issue Critical/Major.'),
    ('Lulus Bersyarat', 'Ada issue Minor yang sudah diakui dan disepakati, diperbaiki '
                        'dalam periode hypercare.'),
    ('Tidak Lulus', 'Ada issue Critical/Major yang belum teratasi saat sesi UAT '
                    'berakhir, atau ada poin [TOLAK] yang Fail.'),
]

NARASI_PEMBUKA = (
    '*(Narasi Kesimpulan UAT diisi oleh IT Business Analyst / Fasilitator UAT setelah '
    'seluruh skenario dijalankan.)'
)

NARASI_BARIS = 5
NARASI_TITIK = '.' * 118


# ---------------------------------------------------------------- bab 8

SIGNOFF_PEMBUKA = (
    'Dengan menandatangani dokumen ini, pihak-pihak terkait menyatakan telah mengikuti, '
    'memahami, dan menerima hasil User Acceptance Testing Modul SOP sebagaimana '
    'tercantum pada dokumen ini.'
)


# =============================================================================
# DAFTAR UJI SESI — poin per fitur
# =============================================================================
#
# Yang diuji saat sesi adalah POIN FITURNYA, bukan langkah demi langkah. Satu
# baris = satu fitur, isinya beberapa poin pendek tentang apa yang harus bisa
# dilakukan fitur itu. Bagaimana cara menekannya dijabarkan lisan oleh
# fasilitator saat sesi, bukan ditulis di sini.
#
# Poin berawalan [TOLAK] adalah hal yang harus DITOLAK sistem. Poin itu dihitung
# setara dengan poin jalur normal: modul dokumen terkendali yang gagal menolak
# hal yang salah tidak layak Go Live meskipun seluruh jalur normalnya mulus.
# (Celah 006 — berkas terpakai yang ternyata bisa dihapus dan ikut menghapus
# berkas milik modul LMS — hanya ketahuan karena hal semacam ini diuji.)
#
# AWAS TABRAKAN NOMOR. Kode kasus UAT-SOP-001..013 di sini adalah nomor
# TEST CASE pada dokumen yang ditandatangani. Ia BUKAN UAT-ID yang dipakai
# #[Group('UAT-SOP-###')] di tests/ — itu penomoran 48 skenario lama, dan
# nomor yang sama menunjuk hal berbeda (003 lama = Master Kategori, 003 di
# sini = Master Konten). Nomor dari laporan UAT tidak boleh dicari langsung
# di kode; jembatannya adalah unsur terakhir tiap kasus di bawah, yang
# menyebut UAT-ID mana saja yang dicakupnya.
#
# Unsur cakupan itu TIDAK dicetak ke dokumen — ia peta cakupan yang tinggal
# di berkas ini dan di riwayat git, tempat menelusuri kembali kalau ada poin
# yang gagal dan perlu rinciannya.
#
# Bentuk tiap baris:
#   (kode, fitur, penguji, [poin], harapan, 'uat-id yang dicakup')

KASUS_PEMBUKA = (
    'Daftar berikut yang dipakai dan ditandatangani saat sesi. Satu baris = satu '
    'fitur, berisi poin-poin penting yang harus bisa dilakukan fitur itu. Cara '
    'menjalankannya dijabarkan fasilitator saat sesi berlangsung.'
)

KASUS_CATATAN = (
    'Poin berawalan [TOLAK] menguji apa yang sistem TOLAK, bukan apa yang ia '
    'izinkan - dan dinilai setara dengan poin jalur normal. Sebuah fitur baru '
    'berstatus Pass bila SELURUH poin pada barisnya terpenuhi.'
)

KASUS = [
    ('UAT-SOP-001', 'Hak Akses & Menu SOP', 'Admin SOP + Karyawan',
     ['Seluruh menu SOP terbuka bagi Admin SOP',
      '[TOLAK] Karyawan biasa: menu tak muncul DAN URL langsung ditolak'],
     'Akses mengikuti peran, bukan sekadar disembunyikan dari sidebar.',
     '001, 002'),

    ('UAT-SOP-002', 'Master Kategori SOP', 'Admin SOP',
     ['Tambah, ubah, dan hapus kategori',
      '[TOLAK] Hapus kategori yang masih dipakai paket'],
     'Kategori terkelola penuh; yang masih terpakai tidak bisa dihapus.',
     '003, 004'),

    ('UAT-SOP-003', 'Master Konten (Library Berkas)', 'Admin SOP',
     ['Buat folder / kategori konten',
      'Unggah berkas PDF, Excel, dan jenis lain',
      'Hapus berkas bebas dan folder kosong',
      '[TOLAK] Hapus berkas yang sedang dipakai versi dokumen'],
     'Berkas terunggah dan terbaca; yang sedang terpakai terlindungi.',
     '005, 006'),

    ('UAT-SOP-004', 'Master Dokumen - pendaftaran', 'Admin SOP',
     ['Daftarkan dokumen: nomor SOP resmi, judul, tipe',
      'Detail dokumen membuka riwayat versinya',
      '[TOLAK] Keluar dari formulir versi tanpa simpan tanpa konfirmasi - '
      'bila dibatalkan, isian harus tetap utuh'],
     'Dokumen tercatat dengan nomor resminya dan riwayatnya terbaca.',
     '007, 008, 009'),

    ('UAT-SOP-005', 'Master Dokumen - versi & proteksi', 'Admin SOP',
     ['Buat versi draft berikut blok pengesahannya '
      '(Disiapkan, Diperiksa, Disetujui, Disahkan)',
      'Lihat Dampak sebelum menerbitkan',
      'Terbitkan versi; versi lama menjadi tidak berlaku',
      'Kembalikan ke versi sebelumnya, tercatat di jejak audit',
      '[TOLAK] Terbitkan versi tanpa berkas, atau tanpa blok pengesahan lengkap'],
     'Hanya ada satu versi aktif, dan penerbitan tanpa syarat lengkap ditolak.',
     '010, 011, 012, 013, 014'),

    ('UAT-SOP-006', 'Builder Paket SOP', 'Admin SOP',
     ['Buat paket; tambah section dan item; urutkan dengan menyeret',
      'Tandai item Wajib / Sorotan dan atur mode akses berkasnya',
      'Item dokumen menunjuk Master Dokumen; salin item ke paket lain',
      'Pratinjau tampilan dari sisi karyawan',
      'Terbitkan paket beserta sifat revisi tiap dokumen'],
     'Susunan dan penandaan tercermin persis di pratinjau; paket terbit '
     'dengan nomor rilis baru.',
     '015, 016, 017, 018, 019'),

    ('UAT-SOP-007', 'Penjadwalan & Penugasan', 'Admin SOP',
     ['Sasaran per unit kerja dan per orang (tempel daftar NIK)',
      'Atur tenggat dan pengingat',
      'Tambah karyawan pada jadwal yang sedang berjalan',
      'Hapus akses user dari penjadwalan',
      '[TOLAK] NIK tak dikenal dibuang diam-diam - harus dilaporkan'],
     'Sasaran ter-resolve dengan benar dan tersimpan seluruhnya, bukan hanya '
     'sehalaman pratinjau.',
     '020, 021, 022, 023'),

    # Kebijakan & Otomasi (dulu kasus 8, mencakup UAT-ID 024 & 025) DIKELUARKAN
    # dari daftar uji atas keputusan pemilik proses. Kedua UAT-ID itu karenanya
    # tidak lagi tercakup dokumen ini — dicatat di sini supaya ketiadaannya
    # terbaca sebagai keputusan, bukan sebagai kelalaian.

    ('UAT-SOP-008', 'Sisi Karyawan: Repository, SOP Saya, Baca, Konfirmasi',
     'Karyawan',
     ['Repository: telusur per kategori dan pencarian',
      'SOP Saya: daftar tugas, tenggat, progres, kartu kepatuhan',
      'Pembaca melanjutkan di halaman terakhir yang dibaca',
      'Konfirmasi bacaan',
      '[TOLAK] Unduh atau cetak berkas dari pembaca',
      '[TOLAK] Intip SOP yang tidak ditugaskan (hanya ringkasannya yang boleh)',
      '[TOLAK] Konfirmasi sebelum halaman terakhir, dan lompati item wajib '
      'yang berurutan'],
     'Karyawan hanya melihat yang berhak dilihatnya, dan konfirmasi hanya bisa '
     'setelah benar-benar dibaca.',
     '026, 027, 028, 029, 030, 031, 032, 033'),

    ('UAT-SOP-009', 'Absensi Check-in', 'Karyawan',
     ['SIAPKAN DULU: gerbang absensi aktif, perangkat berkamera, akses HTTPS',
      'Isi paket terkunci sebelum check-in',
      'Check-in tercatat dan bukti fotonya bisa dibuka admin',
      '[TOLAK] Check-in memakai foto dari galeri'],
     'Isi paket terbuka hanya setelah kehadiran benar-benar tercatat.',
     '036, 037'),

    # HARAPAN KUIS DIPERBAIKI. Sebelumnya tertulis "paket tuntas hanya bila
    # lulus", dan itu bukan yang dilakukan sistem: SopCompletionService
    # ::submitKuisResult menetapkan $hasilFinal = $lulus || $percobaanFinalGagal,
    # lalu menulis Status = SELESAI dan Persen_Selesai = 100. Dengan
    # SopKuisDispatchService::MAKS_PERCOBAAN = 1, percobaan pertama SEKALIGUS
    # percobaan terakhir — jadi item selalu tertutup sesudah dikerjakan, lulus
    # maupun tidak. Kelulusan hidup di Status_Lulus dan Skor_Quiz, bukan pada
    # terbuka-tertutupnya item. Kalimat lama akan membuat penguji melaporkan
    # Fail atas perilaku yang justru benar.
    ('UAT-SOP-010', 'Kuis / Checkpoint', 'Karyawan',
     ['SIAPKAN DULU: item Kuis tertaut ujian HC Learn, batas nilai lulus terisi',
      'Kerjakan kuis dari dalam paket; nilai kembali dan progres naik',
      'Kerjakan dengan nilai di bawah batas lulus - item tetap tertutup, '
      'tetapi tercatat tidak lulus'],
     'Nilai kembali ke itemnya. Percobaan hanya SATU: sesudah dikerjakan item '
     'selesai, lulus maupun tidak - kelulusan terbaca dari skor dan status '
     'lulusnya, bukan dari item yang dibiarkan terbuka.',
     '034, 035'),

    ('UAT-SOP-011', 'Kepatuhan Tim (Leader)', 'Leader',
     ['Daftar anggota beserta status baca dan tunggakannya',
      'Detail per anggota',
      '[TOLAK] Menelusuri karyawan di luar timnya'],
     'Leader melihat timnya, dan hanya timnya.',
     '038, 039'),

    ('UAT-SOP-012', 'Monitoring & Laporan', 'Admin SOP',
     ['Ringkasan, corong penyelesaian, sebaran per paket',
      'Tab Karyawan dan Dokumen beserta penyaringnya',
      'Ekspor laporan rinci',
      'Register kontrol dokumen'],
     'Angka cocok dengan keadaan sebenarnya; ekspor sekolom dengan layar; yang '
     'belum dikerjakan tampil sebagai tanda hubung, bukan angka nol.',
     '040, 041, 042, 043'),

    ('UAT-SOP-013', 'Siklus Hidup & Notifikasi (jalankan terakhir)',
     'Admin SOP + Karyawan',
     ['Revisi MAYOR membuka kembali kewajiban baca di SELURUH paket pemakai '
      'dokumen itu',
      'Dokumen kadaluarsa ditandai di register kontrol dokumen',
      'Notifikasi penugasan dan pengingat menjelang tenggat'],
     'Perubahan dokumen menjalar ke semua paket pemakainya, dan pengingat '
     'datang sesuai kadens yang disetel.',
     '044, 045, 046, 047, 048'),
]
