# Panduan Akun, Alur & Transaksi — UAT VMS Permit (1 Oktober 2026)

Pendamping `docs/uat/IT_BA_UAT VMS-PERMIT.docx`. Lingkungan: development
`Web_HRIS` (dibersihkan lewat DML-2026-157 pada 30 Sep 2026 — nol permit,
nol tamu). Kata sandi tidak dicantumkan; minta ke tim IT.

## 1. Akun

| Label | Username | Kode | Nama | Peran di UAT | Akses halaman | Kartu (ID Card) |
|---|---|---|---|---|---|---|
| **P1** | `NOIR` | H45 | Ridho | Pemohon #1 **dan** Admin | Transaksi Permit, Verifikator Permit, Agenda Inspeksi, Monitoring, Master Data, Export Studio Permit | Terdaftar & tervalidasi |
| **P2** | `tari` | Y62 | Tari | Pemohon #2 — **karyawan biasa** | Transaksi Permit saja | Terdaftar & tervalidasi |
| **P0** | `anita` | B8 | Anita | Pemohon **tanpa ID Card** | Transaksi Permit saja | Belum dipasangkan |
| **V1** | `andre` | B6 | Andre | Verifikator / SHE utama | Verifikator Permit, Agenda Inspeksi, Monitoring, Master Data, Export Studio Permit | Terdaftar (dipakai untuk uji "approve milik sendiri") |
| **V2** | `arya` | C9 | Arya | Verifikator / SHE kedua | sama dengan V1 | Diblokir — **jangan** dipakai sebagai pemohon |

Catatan penting:

- Approver permit = semua pemegang role `ADMIN_PERMIT`: **NOIR, andre, arya**.
  Ketiganya menerima notifikasi pengajuan baru. Karena NOIR juga approver,
  permit milik NOIR **wajib** diputus oleh andre/arya (sistem menolak
  approve milik sendiri).
- Uji `[TOLAK]` akses halaman (UAT-PMT-001) memakai **tari**, bukan NOIR —
  NOIR memegang semua halaman.
- Semua keterangan pengajuan diawali `UAT-` (mis. `UAT-T1 HIJAU NOIR`) dan
  nomor PMT dicatat di kolom Catatan.

## 2. Rencana transaksi

H = Kamis 1 Okt (hari sesi), H+1 = Jumat 2 Okt. Minggu tidak ada slot.
Slot 15 menit di **Klinik SHE** atau **Area Parkir Motor/Mobil**.
Slot yang mulainya **kurang dari 30 menit** dari sekarang tidak ditawarkan
(lihat §5), jadi pesan slot "hari ini" minimal 30–45 menit ke depan.

| Trx | Pemohon | Kategori | Slot | Dipakai untuk | Akhir status |
|---|---|---|---|---|---|
| **T1** | P1 NOIR | HIJAU | H (±45 mnt ke depan) | 005, 007, 010 (koreksi + approve oleh **V1**), 012, 013 cabut satu | Dicabut |
| **T2** | P2 tari | MERAH | H | 006, 009 agenda, 010 checklist MERAH (approve oleh **V2**), 012 cek kartu orang lain, 013 cabut massal | Dicabut |
| **T3** | P1 NOIR | HIJAU | H+1 | 004 jatah 2/2 (bersama T1), 008 jadwal ulang ×2 lalu batal | Dibatalkan |
| *(T4)* | P1 NOIR | — | — | 004 `[TOLAK]` pengajuan ke-3 saat T1 + T3 memakai jatah 2/2 | tidak terbit |
| *(—)* | P2 tari | — | — | 004 `[TOLAK]` ajukan lagi saat T2 masih Menunggu | tidak terbit |
| *(—)* | P0 anita | — | — | 004 `[TOLAK]` ID Card belum terdaftar | tidak terbit |
| **T5** | P2 tari | HIJAU | H (terdekat) | 009 pindah jadwal oleh SHE + Tidak Hadir ×2 → gugur | Ditolak (gugur) |
| **T6** | P1 NOIR | HIJAU | H+1 | 011 tolak (dan `[TOLAK]` tanpa alasan) | Ditolak |
| **T7** | V1 andre | HIJAU | H | 010 `[TOLAK]` andre approve milik sendiri; 010/014 `[TOLAK]` approve setelah batas lewat (§4) | Kedaluwarsa approval |
| **T8** | P2 tari | HIJAU | H | 014 kedaluwarsa otomatis (§4), 015 tapping kartu expired | Kedaluwarsa |

Kriteria penerimaan "alur utuh per kategori" dipenuhi oleh **T1 (HIJAU)** dan
**T2 (MERAH)**: diajukan → diinspeksi → disetujui → kartu dicetak & sah →
dicabut & terbaca tidak berlaku.

## 3. Alur sesi

**Fase A — Admin & SHE (001–003, wajib Pass dulu)**

1. **001** — login tiap akun, cocokkan menu dengan tabel §1. Dengan **tari**,
   buka langsung alamat Verifikator Permit / Agenda Inspeksi / Monitoring /
   Master Data → harus ditolak.
2. **002** (NOIR) — Master Data Permit: HIJAU/MERAH aktif; ubah durasi &
   urutan; kelola pertanyaan (umum, khusus MERAH, khusus tipe); tipe/model/
   warna/plat; export lalu import dengan pratinjau. Pastikan COKLAT dan
   kategori nonaktif tidak muncul di form tari.
3. **003** (andre) — Pengaturan Slot: cek pos/shift/kuota/pola; buat
   pengecualian tanggal (mis. **Sabtu 3 Okt**, bukan H/H+1); nonaktifkan
   sementara satu pos dan pastikan slotnya hilang di form, **lalu aktifkan
   kembali** sebelum fase B.

**Fase B — Pemohon (004–009)**

4. **004** — anita buka Buat Baru → ditolak karena ID Card.
5. **005/007** — NOIR ajukan **T1**. Cek nomor PMT, status Menunggu, Riwayat,
   notifikasi di andre/arya, Pass Digital menampilkan jadwal & akses
   "Akses Inspeksi – Belum Disetujui" (jendela 15 mnt sebelum s.d. 15 mnt
   sesudah slot). Uji tekan Submit berulang.
6. **006** — tari ajukan **T2** MERAH. Lalu tari coba ajukan lagi → ditolak
   (maks 1 menunggu keputusan).
7. **004 jatah** — NOIR ajukan **T3** (slot H+1) → jatah 2/2. NOIR coba
   pengajuan ketiga (T4) → ditolak jatah.
8. **008** — NOIR pindahkan jadwal **T3** (hari ini = H-1, boleh) dua kali;
   percobaan ketiga ditolak. Lalu batalkan **T3** → slot dilepas, jatah
   kembali 1/2.
9. **009** (andre) — Agenda Inspeksi hari H memuat T1 & T2 (HIJAU & MERAH).

**Fase C — Keputusan (010–011)** — tunggu jam slot T1/T2 tiba.

10. **010** — andre buka **T1**: detail, dokumen, checklist; koreksi satu
    data dengan alasan (coba juga tanpa alasan/tanpa perubahan → ditolak);
    coba approve tanpa checklist/durasi → ditolak; pilih durasi, **Approve**.
    arya **Approve T2** (checklist memuat pertanyaan MERAH).
11. **010 `[TOLAK]` milik sendiri** — andre ajukan **T7**, lalu andre coba
    approve T7 → ditolak. (T7 dibiarkan untuk §4.)
12. **009 tidak hadir** — tari ajukan **T5** (slot terdekat). andre pindahkan
    jadwal dari agenda dengan alasan. Setelah slot lewat → **Tidak Hadir**;
    tari jadwal ulang, lewat lagi → Tidak Hadir ke-2 → gugur, tari
    menerima notifikasi.
13. **011** — NOIR ajukan **T6**; andre coba tolak tanpa alasan → ditolak;
    tolak dengan alasan → Ditolak, jatah NOIR kembali.

**Fase D — Kartu, Monitoring, Cabut (012–013)**

14. **012** — NOIR cetak kartu T1 dari Pass Digital; NOIR (admin) cetak kartu
    yang sama dari Monitoring → identik. Cetak Bundel (T1, T2 + satu yang
    belum aktif → dilewati). Pindai QR dengan kamera biasa (hanya kode 32
    karakter). tari pindai kartunya sendiri → KARTU SAH; tari pindai kartu
    NOIR → "BUKAN KARTU ANDA" tanpa identitas; andre pindai → sah + identitas.
15. **013** — Monitoring: statistik, filter, detail, Export Studio. Cabut
    **T1** (satu) dan **T2** (massal, sertakan T6 yang sudah Ditolak →
    ditolak/dilewati); cabut tanpa alasan → ditolak. Pindai ulang kartu T1/T2
    → tidak berlaku.

**Fase E — Kedaluwarsa & gerbang (014–015, terakhir)**

16. tari ajukan **T8**, andre approve (1 Bulan).
17. **014** — IT jalankan simulasi waktu §4 untuk **T7** dan **T8**, tunggu
    ≤5 menit: T8 Kedaluwarsa & jatah tari kembali; T7 tidak bisa diputus
    dan akses inspeksinya mati. Jalankan pemeriksaan konsistensi Lampiran B.
18. **015** — tapping di palang: trial (permit Menunggu dalam jendela
    inspeksi), permit disetujui, permit expired (T8)/dicabut (T1), kartu tamu
    & mitra, Emergency/VIP, tuas manual.

## 4. Simulasi waktu oleh IT (hanya development)

Masa berlaku minimal 1 bulan dan batas approval 2 hari tidak mungkin terlewat
dalam satu sesi, jadi dimajukan secara manual. Ganti nomor PMT sesuai catatan.

```sql
-- T7: batas approval lewat (PMT-010 [TOLAK] & PMT-014)
UPDATE HRIS_Approval_Request
SET Expired_At = DATEADD(MINUTE, -1, GETDATE())
WHERE No_Transaksi = 'PMT1026-xxxx' AND Flag_Approval IS NULL;

-- T8: masa berlaku habis (PMT-014); penjadwal membalik Efektif <= 5 menit
UPDATE N_EMI_VISITOR_Transaksi_Permit_Detail
SET Tanggal_Kadaluarsa = DATEADD(DAY, -1, CAST(GETDATE() AS date))
WHERE No_Transaksi = 'PMT1026-yyyy';
```

## 5. Hal yang perlu diputuskan sebelum sesi

- **Jeda minimum slot**: setelan server `VISITOR_PERMIT_JEDA_MINIMUM_MENIT = 30`,
  dokumen UAT menulis 0 menit. Samakan salah satunya.
- **Jatah permit**: `VISITOR_PERMIT_MAX_PENGAJUAN_AKTIF = 2`, sedangkan tabel
  aturan dokumen menulis "belum ada pembatasan" (padahal PMT-004 menguji
  jatah 2). Perbaiki teks dokumen.
- **Tapping (015)**: kartu NOIR/tari di dev memakai nomor dummy
  (`77705923`, `77705924`). Bila palang memakai kartu fisik, kartu fisik itu
  harus dipasangkan dulu ke H45/Y62.
- **Tamu & mitra (015)**: data tamu ikut dikosongkan; buat satu kunjungan
  tamu dan satu mitra sebelum uji tapping kartu tamu.
- Akses inspeksi maks 2×/hari per karyawan: tidak ada poin khususnya di
  daftar uji; bila ingin diuji, siklus ajukan–tolak–ajukan tiga kali di satu
  akun pada hari yang sama.
