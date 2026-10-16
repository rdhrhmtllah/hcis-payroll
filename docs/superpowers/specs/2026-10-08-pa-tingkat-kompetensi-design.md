# Tingkat Kompetensi & Matriks Kompetensi per Kompetensi — Desain

Tanggal: 2026-10-08 · Modul: KPI / Performance Appraisal / Master Data · Disetujui user di chat (UAT 8 Okt).

## Keputusan user
1. Pilihan A — tingkat yang **dituntut per level jabatan** (Officer K01 Level 2, AM K01 Level 4).
2. Matriks Kompetensi **per kompetensi menggantikan** matriks per jenis: sel = tingkat dituntut; tanpa baris = tidak dinilai.
3. Jumlah tingkat **berbeda per kompetensi**; tiap tingkat punya nama dan Expected Behaviour.
4. Tahap ini **acuan tampilan saja**; perhitungan nilai tidak berubah, tanpa gap analysis.

## Data (DDL-2026-175, DML-2026-176)
- `N_PA_Competency_Proficiency` — tingkat per kompetensi (Tingkat, Nama_Tingkat, Expected_Behaviour).
- `N_PA_Competency_Level_Requirement` — level × kompetensi (+ tingkat dituntut, boleh NULL).
- `N_PA_Cycle_Competency_Level_Snapshot` — salinan per siklus saat siklus dibuat, termasuk bunyi
  Nama_Tingkat dan Expected_Behaviour.
- DML awal: matriks per kompetensi diturunkan dari `N_PA_Competency_Requirement` × kompetensi aktif,
  sehingga keadaan hari ini tidak berubah.

## Kompatibilitas
- `N_PA_Competency_Requirement` (per jenis) tetap ada dan **diselaraskan otomatis** setiap matriks per
  kompetensi disimpan: jenis wajib bila ada ≥1 kompetensinya dituntut di level itu. Resolver, override
  per peserta, dan salinan per jenis tetap bekerja tanpa diubah.
- Penjabaran "jenis → seluruh kompetensi aktif" diganti "kompetensi di matriks" (ikatan paket soal
  master & siklus, peringatan master). Siklus tanpa salinan per kompetensi tetap memakai penjabaran lama.

## Tampilan
- Master Kompetensi: bagian Tingkat di dialog sunting.
- Matriks Kompetensi: baris level, kolom kompetensi (dikelompokkan per jenis), sel pilihan tingkat / tidak dinilai.
- Penampil Expected Behaviour (leader, balancing, wizard, detail hasil, peserta) memakai perilaku tingkat
  yang dituntut untuk level orang itu bila ada; cadangannya `N_PA_Competency.Expected_Behaviour`.

## Perubahan 10 Okt 2026 — Expected Behaviour hanya per tingkat
- Atas keputusan user, Expected Behaviour yang menempel langsung di kompetensi (`N_PA_Competency.Expected_Behaviour`)
  **tidak dipakai lagi**: tidak ada di form/detail master, tidak dibaca katalog, dan **tidak lagi menjadi cadangan**
  di tampilan. Perilaku yang tampil (Penilaian Tim, Balancing, PA Saya, laporan, pratinjau kalibrasi) hanya dari
  tingkat yang dituntut level orang itu; tanpa tingkat/EB tingkat → tidak ada perilaku yang ditampilkan.
- Kolom di tabel dibiarkan (tidak di-DROP); jalur tulis masih menerima `expected_behaviour` agar klien lama aman.
