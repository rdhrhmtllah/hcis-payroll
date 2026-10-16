<?php

/*
|--------------------------------------------------------------------------
| Pengumpulan Data Karyawan
|--------------------------------------------------------------------------
| Sumber kebenaran TUNGGAL untuk daftar pertanyaan. Dipakai dua arah:
| server memvalidasi kiriman dari sini, Vue merender wizard dari sini juga.
|
| Hal yang perlu bisa diubah TANPA deploy (sakelar kampanye, kalimat
| pengumuman) TIDAK di berkas ini — itu di tabel N_HRIS_Pengumpulan_Data_Config.
*/

/*
 * Batas usia dipegang SATU tempat. Aturan server di bawah dan batas kalender di
 * browser sama-sama diturunkan dari angka ini — kalau dipisah, kalender akan
 * menawarkan tanggal yang kemudian ditolak server, dan itu kegagalan yang paling
 * membingungkan: sudah dipilih dari daftar yang disodorkan, tetap disalahkan.
 */
$usiaMin = 15;
$usiaMaks = 70;

/*
 * Nomor HP juga dipegang satu tempat, dan polanya DISUSUN dari angka-angka ini
 * — bukan ditulis ulang di sebelahnya. Sebelumnya browser hanya memeriksa
 * panjang sementara pola server menuntut awalan tertentu, sehingga nomor
 * seperti 021-2333… lolos empat langkah lalu ditolak saat Kirim dengan kalimat
 * yang tidak menyebut apa yang kurang.
 *
 * Satuannya angka yang DIKETIK orang (tanpa '+62' dan tanpa '0' di depan).
 *
 * Bentuk TERSIMPANnya '62…', bukan '08…'. Itu bentuk yang sudah dipakai
 * seluruh sistem: 958 dari 958 Karyawan.HP berbentuk '62' + 11 angka, dan
 * WhatsappNotificationMirrorService::normalizePhone() mengubah apa pun yang
 * diterimanya menjadi '62…' sebelum mengirim. Pola '^08' yang dipakai di sini
 * sampai 4 Sep 2026 adalah bentuk asing yang cuma hidup di fitur ini — akibat
 * paling nyata: prefill harus membengkokkan data master supaya lolos aturan
 * fiturnya sendiri, dan nomor yang sama tersimpan dalam dua bentuk berbeda di
 * dua tabel.
 */
$awalanHp = '8';
$awalanSimpanHp = '62';
$hpMin = 9;
$hpMaks = 13;
$polaHp = '/^'.$awalanSimpanHp.$awalanHp.'[0-9]{'.($hpMin - 1).','.($hpMaks - 1).'}$/';

/*
 * Jumlah tanggungan. Batas atasnya dipakai DUA arah: aturan server ('max:')
 * dan batas ketikan di kotaknya. Kalau dipisah, kotak yang hanya menerima dua
 * angka akan menolak angka yang sebenarnya masih sah — atau sebaliknya,
 * menerima angka yang pasti ditolak saat Kirim.
 */
$tanggunganMaks = 20;

return [
    // Naik ke 2 pada 19 Agu 2026: kode pajak PTKP dicabut, diganti pertanyaan
    // status pernikahan berbahasa manusia. Naik ke 3 pada 23 Agu 2026: jumlah
    // tanggungan ditanyakan kembali, tapi hanya kepada yang menikah. Kiriman
    // lama tetap menyandang versi yang berlaku saat ia dikirim. Naik ke 4 pada
    // 17 Sep 2026: pilihan pendidikan 'D4/S1' dipisah jadi 'D4' dan 'S1'.
    // Kiriman versi <= 3 yang berisi 'D4/S1' SENGAJA dibiarkan: tidak bisa
    // dipecah tanpa bertanya ulang kepada orangnya.
    'versi_skema' => 4,

    // SESI | HARIAN | HALAMAN
    //   SESI    = sekali per sesi login  (paling sopan; 3 jatah tunda baru
    //             habis setelah 3 kali login terpisah)
    //   HARIAN  = sekali per hari kalender (3 jatah habis dalam 3 hari)
    //   HALAMAN = tiap perpindahan halaman sampai diisi atau terkunci
    //             (paling menekan; jatah bisa habis dalam hitungan menit)
    'frekuensi_modal' => 'HALAMAN',
    'maks_penundaan' => 3,
    'tampilkan_bar_menetap' => true,

    // Dipakai hanya bila baris N_HRIS_Pengumpulan_Data_Config belum ada.
    'aktif_default' => false,

    'langkah' => [
        ['kunci' => 'identitas',   'judul' => 'Data Diri',        'deskripsi' => 'Informasi pribadi sesuai dokumen KTP Anda', 'field' => ['nama_lengkap', 'nik_ktp', 'tanggal_lahir', 'jenis_kelamin']],
        ['kunci' => 'kontak',      'judul' => 'Informasi Kontak', 'deskripsi' => 'Nomor WhatsApp dan alamat email aktif Anda', 'field' => ['email', 'no_hp']],
        // 'judul_tanpa_phl' dipakai ketika TIDAK ada satu pun field khusus PHL
        // yang tersisa di langkah ini — bagi karyawan tetap (dan bila ketiga
        // pertanyaan PHL dimatikan lewat env), yang tertinggal hanya
        // pendidikan, status pernikahan, dan tanggungan. Menyebutnya "Info
        // Pekerjaan" membuat orang mencari-cari kolom pekerjaan yang memang
        // tidak akan pernah muncul untuknya.
        ['kunci' => 'kepegawaian', 'judul' => 'Info Pekerjaan',    'deskripsi' => 'Info unit kerja, atasan, pendidikan, dan status pernikahan', 'field' => ['departemen', 'pendidikan_terakhir', 'status_pernikahan', 'jumlah_tanggungan', 'atasan_spv', 'atasan_dept_head'], 'judul_tanpa_phl' => 'Pendidikan & Keluarga', 'deskripsi_tanpa_phl' => 'Pendidikan terakhir, status pernikahan, dan jumlah tanggungan'],
        ['kunci' => 'darurat',     'judul' => 'Kontak Darurat',   'deskripsi' => 'Kerabat atau keluarga yang dapat dihubungi saat situasi darurat', 'field' => ['darurat_nama', 'darurat_hubungan', 'darurat_no_hp']],
    ],

    /*
    |----------------------------------------------------------------------
    | Sifat field DIDEKLARASIKAN di sini, tidak ditebak dari labelnya.
    |----------------------------------------------------------------------
    | 'format'      => 'nik' | 'hp' | 'email'. Menentukan papan ketik angka,
    |                  batas ketikan, dan pesan galat di browser.
    | 'maks_digit'  => banyak ANGKA yang boleh diketik di kotaknya. Untuk 'hp'
    |                  dihitung tanpa '+62' dan tanpa '0' di depan, karena
    |                  keduanya tidak diketik orang -- jadi 13 di sini sama
    |                  dengan '62' + 13 angka pada aturan server di bawahnya.
    | 'min_digit'   => batas bawah dengan satuan yang sama.
    | 'prefix'      => angka PERTAMA yang diketik orang ('8').
    | 'prefix_simpan' => awalan yang dipasang sebelum disimpan ('62'). Dibaca
    |                  browser (nilaiSimpanHp) DAN uji, supaya bentuk simpan
    |                  tidak ditulis ulang di tiga tempat yang bisa berselisih.
    | 'ikon'        => ikon di sebelah label.
    | 'tampil_jika' => syarat munculnya pertanyaan, dibaca DUA arah: browser
    |                  menyembunyikan kotaknya, server membuang jawabannya dan
    |                  tidak menuntutnya. Ditulis sekali di sini supaya keduanya
    |                  tidak menyimpulkan sendiri-sendiri — kalau menyimpulkan
    |                  terpisah, yang paling mungkin terjadi adalah kotaknya
    |                  hilang di layar tapi tetap dituntut saat Kirim, dan tidak
    |                  ada isian yang bisa memuaskannya.
    |
    | Sebelumnya sifat itu ditebak dari potongan kata pada label. Label
    | 'Status Pernikahan' mengandung 'nik', sehingga pertanyaan status
    | pernikahan dituntut '16 digit angka sesuai KTP' dan tidak ada jawaban
    | yang bisa memuaskannya. Karena itu penebakan dicabut habis: yang tidak
    | tertulis di sini tidak berlaku.
    */
    /*
    |----------------------------------------------------------------------
    | Kalimat galat dari SERVER.
    |----------------------------------------------------------------------
    | Server adalah penentu, jadi kalimatnya ikut terbaca karyawan. Tanpa daftar
    | ini yang muncul adalah bawaan Laravel: "The tanggal lahir field must be a
    | date before -15 years." — nama kolom mentah, potongan aturan mentah, dan
    | bahasa yang bukan bahasa penggunanya. Kalimat khusus per field ditulis di
    | 'pesan' pada field yang bersangkutan.
    */
    'pesan_umum' => [
        'required' => ':attribute wajib diisi.',
        'date' => ':attribute harus berupa tanggal yang benar.',
        'email' => ':attribute belum berbentuk alamat email yang benar.',
        'digits' => ':attribute harus :digits angka.',
        'integer' => ':attribute harus berupa angka bulat.',
        'in' => 'Pilihan :attribute tidak dikenali. Pilih salah satu dari daftar.',
        'regex' => 'Format :attribute belum benar.',
        'string' => ':attribute harus berupa teks.',
        'min' => ':attribute terlalu pendek.',
        'max' => ':attribute terlalu panjang.',
        'before' => ':attribute belum masuk akal — periksa kembali.',
        'after' => ':attribute belum masuk akal — periksa kembali.',
    ],

    'field' => [
        'nama_lengkap' => [
            'label' => 'Nama Lengkap',
            'tipe' => 'teks',
            'ikon' => 'bi bi-person-badge',
            'petunjuk' => 'Nama lengkap sesuai KTP Anda',
            'aturan' => ['required', 'string', 'min:3', 'max:150'],
        ],
        'nik_ktp' => [
            'label' => 'Nomor KTP (NIK)',
            'tipe' => 'teks',
            'format' => 'nik',
            'maks_digit' => 16,
            'ikon' => 'bi bi-card-heading',
            'petunjuk' => '16 digit nomor NIK pada KTP Anda',
            'aturan' => ['required', 'digits:16'],
        ],
        'tanggal_lahir' => [
            'label' => 'Tanggal Lahir',
            'tipe' => 'tanggal',
            'format' => 'tanggal_lahir',
            'ikon' => 'bi bi-calendar3',
            'petunjuk' => 'Sesuai dengan tanggal lahir di KTP',
            'aturan' => ['required', 'date', 'before_or_equal:today'],
            'pesan' => [
                'before_or_equal' => 'Tanggal lahir tidak boleh di masa depan.',
                'date' => 'Tanggal lahir belum terisi dengan benar.',
            ],
        ],
        'jenis_kelamin' => [
            'label' => 'Jenis Kelamin',
            'tipe' => 'pilihan',
            'ikon' => 'bi bi-gender-ambiguous',
            'pilihan' => ['L', 'P'],
            'label_pilihan' => ['L' => 'Laki-laki', 'P' => 'Perempuan'],
            'aturan' => ['required', 'in:L,P'],
        ],
        'email' => [
            'label' => 'Alamat Email Aktif',
            'tipe' => 'teks',
            'format' => 'email',
            'ikon' => 'bi bi-envelope',
            'petunjuk' => 'Email ini digunakan untuk informasi resmi kantor (contoh: nama@gmail.com)',
            'aturan' => ['required', 'email:rfc', 'max:150'],
        ],
        'no_hp' => [
            'label' => 'Nomor HP / WhatsApp',
            'tipe' => 'teks',
            'format' => 'hp',
            'prefix' => $awalanHp,
            'prefix_simpan' => $awalanSimpanHp,
            'maks_digit' => $hpMaks,
            'min_digit' => $hpMin,
            'ikon' => 'bi bi-telephone',
            'petunjuk' => 'Nomor handphone atau WhatsApp yang dapat dihubungi',
            'aturan' => ['required', 'regex:'.$polaHp],
            'pesan' => [
                // Kalimatnya memakai SATUAN KOTAKNYA: kotak sudah menampilkan
                // '+62' sebagai awalan tetap, jadi yang diketik orang dimulai
                // dari '8'. Menyuruhnya "diawali 08" menyebut angka yang tidak
                // pernah ia ketik — dan '0' itu ditambahkan sendiri oleh
                // aplikasi sebelum disimpan.
                'regex' => "Nomor HP diketik setelah +62 dan diawali {$awalanHp}, panjangnya {$hpMin}-{$hpMaks} angka (contoh: {$awalanHp}123456789).",
            ],
        ],
        // Khusus PHL: departemen karyawan tetap sudah tepat di sistem induk,
        // yang tidak dapat dipercaya hanya milik PHL. Non-PHL tidak ditanyai,
        // dan nilai yang tetap dikirim dari browser mereka dibuang server.
        'departemen' => [
            'label' => 'Departemen / Unit Kerja',
            'tipe' => 'pilihan',
            'ikon' => 'bi bi-building',
            'sumber_pilihan' => 'divisi',
            'petunjuk' => 'Pilih departemen tempat Anda bertugas saat ini',
            'khusus_phl' => true,
            'aturan' => ['required', 'string', 'max:150'],
        ],
        'pendidikan_terakhir' => [
            'label' => 'Pendidikan Terakhir',
            'tipe' => 'pilihan',
            'ikon' => 'bi bi-mortarboard',
            'pilihan' => ['SD', 'SMP', 'SMA/SMK', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3'],
            'petunjuk' => 'Tingkat pendidikan tertinggi yang telah Anda selesaikan',
            'aturan' => ['required', 'in:SD,SMP,SMA/SMK,D1,D2,D3,D4,S1,S2,S3'],
        ],
        // Kode pajak TK/0..K/3 dicabut 19 Agu 2026 atas permintaan user: yang
        // ditanyakan sekarang status pernikahan saja, dengan kata yang langsung
        // dimengerti. Jumlah tanggungan menyusul 23 Agu 2026 sebagai pertanyaan
        // TERPISAH yang hanya muncul bagi yang menikah — bukan sebagai kode.
        //
        // Nilai yang tersimpan tetap istilah resmi; yang dilembutkan hanya
        // kalimat yang dibaca karyawan lewat 'label_pilihan'.
        'status_pernikahan' => [
            'label' => 'Status Pernikahan',
            'tipe' => 'pilihan',
            'ikon' => 'bi bi-people',
            'pilihan' => ['Belum Menikah', 'Menikah', 'Cerai Hidup', 'Cerai Mati'],
            'label_pilihan' => [
                'Belum Menikah' => 'Belum Menikah',
                'Menikah' => 'Menikah',
                'Cerai Hidup' => 'Bercerai',
                'Cerai Mati' => 'Ditinggal wafat oleh pasangan',
            ],
            'petunjuk' => 'Status pernikahan Anda saat ini',
            'aturan' => ['required', 'in:Belum Menikah,Menikah,Cerai Hidup,Cerai Mati'],
        ],
        // Ditanyakan kepada semua KECUALI yang belum menikah: anak tetap menjadi
        // tanggungan setelah bercerai atau ditinggal wafat pasangan. Daftarnya
        // ditulis sebagai nilai yang MEMUNCULKAN, bukan sebagai pengecualian —
        // supaya pilihan status yang ditambahkan kelak tidak diam-diam ikut
        // memunculkan pertanyaan ini tanpa ada yang memutuskan.
        'jumlah_tanggungan' => [
            'label' => 'Jumlah Tanggungan',
            'tipe' => 'teks',
            'format' => 'angka',
            'maks_digit' => strlen((string) $tanggunganMaks),
            'nilai_min' => 0,
            'nilai_maks' => $tanggunganMaks,
            'ikon' => 'bi bi-people-fill',
            'petunjuk' => 'Termasuk anak dan istri/suami. Tidak termasuk orang tua.',
            'tampil_jika' => [
                'field' => 'status_pernikahan',
                'nilai' => ['Menikah', 'Cerai Hidup', 'Cerai Mati'],
            ],
            'aturan' => ['required', 'integer', 'min:0', 'max:'.$tanggunganMaks],
            'pesan' => [
                // 'min'/'max' umum berbunyi "terlalu pendek/panjang" — satuan
                // panjang teks, bukan banyak orang.
                'required' => 'Jumlah tanggungan wajib diisi. Isi 0 bila belum ada tanggungan.',
                'integer' => 'Jumlah tanggungan harus berupa angka, tanpa titik atau koma.',
                'min' => 'Jumlah tanggungan tidak boleh kurang dari 0.',
                'max' => "Jumlah tanggungan paling banyak {$tanggunganMaks} orang.",
            ],
        ],
        'atasan_spv' => [
            'label' => 'Supervisor / Atasan Langsung',
            'tipe' => 'cari_karyawan',
            'ikon' => 'bi bi-person-check',
            'petunjuk' => 'Cari dan pilih nama supervisor langsung Anda',
            'khusus_phl' => true,
            // Kode_Karyawan aktif dengan level yang diizinkan: aturan 'exists'
            // berlevel itu disusun SkemaPengumpulanData (butuh objek Rule,
            // yang tidak boleh tinggal di berkas config yang di-cache).
            'aturan' => ['required', 'string', 'max:20'],
            'pesan' => [
                'exists' => 'Pilih atasan dari hasil pencarian karyawan aktif.',
            ],
            // Yang BERLAKU saat ini (19 Sep 2026): teks bebas. Blok ini menimpa
            // tipe/petunjuk/aturan/pesan di atas selama sakelar
            // services.hcis.pengumpulan_data.atasan_pakai_pencarian mati —
            // definisi pencarian di atas sengaja DISIMPAN, bukan dihapus.
            'isian_teks' => [
                'tipe' => 'teks',
                'petunjuk' => 'Tulis nama lengkap supervisor langsung Anda',
                'aturan' => ['required', 'string', 'min:3', 'max:150'],
                'pesan' => [
                    'min' => 'Tulis nama lengkap atasan, minimal 3 huruf.',
                ],
            ],
        ],
        'atasan_dept_head' => [
            'label' => 'Kepala Departemen (Dept. Head)',
            'tipe' => 'cari_karyawan',
            'ikon' => 'bi bi-person-check',
            'petunjuk' => 'Cari dan pilih nama Kepala Departemen Anda',
            'khusus_phl' => true,
            'aturan' => ['required', 'string', 'max:20'],
            'pesan' => [
                'exists' => 'Pilih atasan dari hasil pencarian karyawan aktif.',
            ],
            // Lihat catatan 'isian_teks' pada atasan_spv.
            'isian_teks' => [
                'tipe' => 'teks',
                'petunjuk' => 'Tulis nama lengkap Kepala Departemen Anda',
                'aturan' => ['required', 'string', 'min:3', 'max:150'],
                'pesan' => [
                    'min' => 'Tulis nama lengkap atasan, minimal 3 huruf.',
                ],
            ],
        ],
        'darurat_nama' => [
            'label' => 'Nama Kontak Darurat',
            'tipe' => 'teks',
            'ikon' => 'bi bi-person-badge',
            'petunjuk' => 'Nama lengkap kerabat atau anggota keluarga dekat',
            'aturan' => ['required', 'string', 'min:3', 'max:150'],
        ],
        'darurat_hubungan' => [
            'label' => 'Hubungan Keluarga',
            'tipe' => 'pilihan',
            'ikon' => 'bi bi-people',
            'pilihan' => ['Orang Tua', 'Pasangan', 'Anak', 'Saudara Kandung', 'Lainnya'],
            'petunjuk' => 'Hubungan Anda dengan kontak darurat',
            'aturan' => ['required', 'in:Orang Tua,Pasangan,Anak,Saudara Kandung,Lainnya'],
        ],
        'darurat_no_hp' => [
            'label' => 'Nomor HP Kontak Darurat',
            'tipe' => 'teks',
            'format' => 'hp',
            'prefix' => $awalanHp,
            'prefix_simpan' => $awalanSimpanHp,
            'maks_digit' => $hpMaks,
            'min_digit' => $hpMin,
            'ikon' => 'bi bi-telephone',
            'petunjuk' => 'Nomor telepon kerabat yang mudah dihubungi',
            'aturan' => ['required', 'regex:'.$polaHp],
            'pesan' => [
                // Kalimatnya memakai SATUAN KOTAKNYA: kotak sudah menampilkan
                // '+62' sebagai awalan tetap, jadi yang diketik orang dimulai
                // dari '8'. Menyuruhnya "diawali 08" menyebut angka yang tidak
                // pernah ia ketik — dan '0' itu ditambahkan sendiri oleh
                // aplikasi sebelum disimpan.
                'regex' => "Nomor HP diketik setelah +62 dan diawali {$awalanHp}, panjangnya {$hpMin}-{$hpMaks} angka (contoh: {$awalanHp}123456789).",
            ],
        ],
    ],
];
