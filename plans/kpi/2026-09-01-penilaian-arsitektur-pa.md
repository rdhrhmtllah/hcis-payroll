# Seberapa solid arsitektur PA — penilaian, bukan daftar tugas

1 September 2026. Menilai rancangannya sendiri, **tanpa** memasukkan soal integrasi
HC Learn yang dibahas terpisah.

---

## Yang sudah solid, dan alasannya

**Tiga lapisan konfigurasi.** Cetak biru lintas siklus → salinan per siklus dan level →
penyimpangan per orang. Bentuknya sudah diuji lewat perdebatan, bukan diterima begitu
saja: lapisan kedua sempat diusulkan dihapus lalu ditolak karena penanda penyimpangan
butuh acuan yang diam dan riwayat aturan akan hilang; lapisan ketiga sempat dirancang
materialisasi penuh lalu dijatuhkan karena menaruh risiko di jalur tulis. Keduanya
bertahan dengan alasan yang bisa diperiksa ulang.

**Semantik pembekuan.** Salinan siklus membeku saat kunci, kecuali bobot pilar yang
sengaja tidak — dan pengecualian itu punya sebab yang tertulis, bukan kelalaian.

**Pola versi dan jejak.** Menghitung ulang tidak pernah menghapus: baris lama
dinonaktifkan, baris baru disisipkan bersama nomor versi. Dipakai konsisten di tiga
tempat — pemetaan ulang skor, jejak kategori penilai, dan nilai per kompetensi.

**Kamus versus aturan (K26).** Aturan disalin per siklus, kamus tidak. Sembilan master
tertutup, dan batasnya sudah teruji: usulan master metode nilai KPI ditolak karena
kolomnya tidak pernah berbeda-beda.

**Identitas karyawan.** Kunci asing komposit `(Kode_Perusahaan, Kode_Karyawan)` di semua
tempat, mengikuti kunci utama `Karyawan` yang sebenarnya.

**Bukti asal-usul.** Nilai KPI disimpan ter-cap dan sebelum cap; bobot yang benar-benar
terpakai disimpan terpisah dari bobot aturan; catatan hasil menyimpan apakah datang dari
penilai atau diketik admin. Ketiganya menjawab pertanyaan "kenapa angkanya begitu" tanpa
menghitung ulang.

---

## Kelemahan sistemik: 18 aturan yang hanya hidup sebagai prosa

Sembilan change PA punya blok "yang sengaja tidak dijaga database". Isinya sekitar
**18 aturan**, dan tidak satu pun sudah diimplementasikan — semuanya masih berupa
kalimat di README.

Yang membuatnya berbahaya bukan jumlahnya, melainkan bahwa 18 itu sebenarnya hanya
**lima bentuk yang berulang**:

| Bentuk | Berapa kali muncul | Contoh |
|---|:---:|---|
| **Σ harus 100** | 4 | bobot detail penilai, target grade, bobot hasil resolusi per peserta, `Bobot_Dalam_Kategori` |
| **Bergantung `Status_Cycle`** | 4 | salinan beku saat `LOCKED`, override dilarang sesudah kunci, paket soal terkunci, payload ditolak sesudah `CLOSED` |
| **Wajib bersyarat berliteral bisnis** | 2 | `Alasan_Manual` saat `MANUAL`, `Alasan_Tertahan` saat `TERTAHAN` |
| **Kelengkapan lintas tabel** | 2 | paket menutup jenis wajib, seluruh butir kontrak benar-benar datang |
| **Aturan penyiapan peserta** | 2 | siapa layak masuk siklus, peserta `DIIKUTKAN` wajib menunjuk salinan |

Dua bentuk teratas menguasai delapan dari sepuluh kasus utama. Itu berarti keduanya
layak punya **satu penjaga masing-masing**, bukan delapan pemeriksaan tersebar di
delapan tempat pemanggilan.

**Kenapa ini bukan kekhawatiran teoretis.** Modul SOP di proyek ini sudah pernah kena
persis pola itu: aturan dipasang di satu dari dua jalur yang setara, dan celahnya baru
ketahuan berhari-hari kemudian. Delapan jalur jauh lebih mudah bocor daripada dua.

Bentuk konkretnya yang saya sarankan: satu `PaCycleGate` yang menjawab "operasi apa yang
masih boleh pada status siklus ini", dan satu penjumlah bobot yang dipanggil di setiap
tempat yang butuh Σ 100. Keduanya diuji lewat efeknya, bukan lewat pemanggilannya —
karena penjaga yang tidak pernah terlihat menyala sama dengan tidak ada.

---

## Tiga kelemahan lain

**Arsitekturnya belum punya jalur tulis.** Empat puluh tabel berdiri; kode aplikasinya
satu berkas. Belum ada jalan dari "admin membuat siklus" menuju "salinan aturan dan
peserta terisi". Seluruh pembaca sudah dirancang, penulisnya baru satu yang dibayangkan.
Selama itu belum ada, tidak ada yang bisa diuji ujung ke ujung.

**Kontrak resolver dijaga uji yang belum pernah dijalankan.** K32 menyatakan hanya
`PaParticipantRuleResolver` yang boleh menggabungkan salinan dengan override, dan
penjaganya sudah ditulis — tetapi belum sekali pun dieksekusi. Penjaga yang belum pernah
dilihat menyala belum terbukti menjaga.

**RBAC tidak bisa menyatakan model akses yang dirancang.** PA punya empat dashboard
dengan cakupan berbeda, sementara role di HCIS memberi **seluruh** halaman satu grup
fitur — granularitasnya nol. "Leader hanya melihat timnya" tidak bisa diungkapkan
olehnya. Ini ketidakcocokan arsitektural, bukan pekerjaan yang belum sempat; jalan
keluarnya mungkin menuntut penyimpanan baru.

---

## Satu asumsi yang belum pernah dinyatakan

`N_PA_Cycle` tidak punya `Kode_Perusahaan`, padahal identitas karyawan komposit dan
membawa kode perusahaan. Artinya satu siklus berlaku lintas perusahaan.

Hari ini aman — hanya ada perusahaan `001` dengan 958 karyawan aktif. Tetapi ini asumsi
yang tidak pernah diputuskan, hanya terjadi. Kalau perusahaan kedua muncul, pertanyaannya
akan datang saat siklus sudah berjalan.

---

## Kesimpulan

**Fondasinya solid; arsitekturnya belum lengkap.** Yang sudah dibangun bertahan terhadap
pemeriksaan, dan beberapa keputusan justru menguat karena sempat ditantang. Yang belum
ada bukan tabel, melainkan **penegakan** dan **jalur tulis**.

Urutan yang saya sarankan:

1. Jalankan uji resolver — menutup utang verifikasi tertua, dan memastikan penjaga K32
   benar-benar menyala.
2. Kumpulkan 18 aturan itu jadi satu daftar, lalu bangun dua penjaga yang menguasai
   delapan di antaranya.
3. Bangun service persiapan siklus di atas keduanya, sehingga jalur tulisnya lahir
   dengan penjaganya sudah terpasang — bukan ditambahkan belakangan.

---

## Pembaruan 1 September 2026 — apa yang sudah tidak berlaku dari penilaian di atas

Penilaian ini ditulis ketika PA masih **empat puluh tabel tanpa jalur tulis**. Tiga
kelemahan yang disebutnya kini sudah bergerak:

| Yang dinilai lemah | Keadaan sekarang |
|---|---|
| "Arsitekturnya belum punya jalur tulis" | 20 fitur, 20 berkas uji, seluruhnya hijau; siklus penuh 859 peserta pernah dijalankan ujung ke ujung |
| "Kontrak resolver dijaga uji yang belum pernah dijalankan" | `ResolverSatuPintuTest` hijau, dan daftar izinnya bertambah satu dengan alasan tertulis |
| "18 aturan yang hanya hidup sebagai prosa" | diaudit dengan MELANGGARNYA; delapan dari sembilan yang tercatat "belum berpenjaga" terbukti dijaga, satu ditutup hari itu |

Yang **masih** berlaku dari penilaian ini: bobot pilar tiga belas level belum ditinjau
pemilik proses (kini tercatat sebagai ketentuan **B11**), dan angka performa tidak boleh
dibawa dari dev ke production tanpa menghitung ulang latensinya.
