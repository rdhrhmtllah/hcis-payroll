# Standar Deklarasi Fitur & Tata Kelola RBAC HCIS

Dokumen ini adalah pedoman baku bagi pengembang (developer) saat menambahkan halaman, menu, atau fitur bisnis baru ke platform EVO Group Unified Platform (HCIS).

---

## 1. Prinsip Utama Otorisasi

1. **Fitur sebagai Objek Bisnis**: Developer tidak boleh langsung membuat Role baru setiap kali membuat menu atau halaman baru. Peran mencerminkan fungsi kerja organisasi, sedangkan fitur mencerminkan kemampuan kerja sistem.
2. **Pemisahan Wewenang**: Hak akses navigasi aplikasi dan keanggotaan verifikator alur persetujuan (*Pool Approval*) dikelola terpisah namun terhubung dalam satu kontrak fitur.
3. **Fail-Closed (Tutup Default)**: Setiap fitur dan kemampuan baru yang belum dipetakan ke peran otomatis tertutup total (HTTP 403 Forbidden).
4. **Bebas Bypass (Zero Direct Grant)**: Tidak ada lagi penambahan baris perorangan manual pada `HRIS_Page_Access`. Seluruh hak diberikan melalui peran resmi atau melalui mekanisme *Menu Global*.

---

## 2. Hirarki Konsep Otorisasi

```
MODUL (N_HRIS_Master_Grup_Fitur)
  │  Contoh: ESS, KPI, LMS, VISITOR, DMS
  ▼
FITUR BISNIS (N_HRIS_Fitur)
  │  Contoh: ESS.CUTI, VISITOR.PERMIT, KPI.PERFORMANCE
  ▼
KEMAMPUAN KERJA (N_HRIS_Fitur_Kemampuan)
  │  Format 3 tingkat: [MODUL].[FITUR].[AKSI]
  │  Contoh: ESS.CUTI.LIHAT, ESS.CUTI.AJUKAN, ESS.CUTI.VERIFIKASI
  ▼
PERAN FUNGSIONAL (N_HRIS_Role_Functional & N_HRIS_Role_Kemampuan)
  │  Paket kemampuan yang diberikan pada fungsi kerja staf
  ▼
PENUGASAN STAF (N_HRIS_Role_User & N_HRIS_Role_User_Cakupan)
     Orang + Peran + Batas Data Unit (Cakupan) + Masa Berlaku
```

---

## 3. Format Penamaan Baku 3 Tingkat

Setiap kemampuan sistem wajib dideklarasikan dengan format 3 segmen:

$$\text{[KODE\_MODUL]} . \text{[KODE\_FITUR]} . \text{[AKSI]}$$

### Contoh Baku:
| Kode Kemampuan | Modul | Tugas Bisnis | Aksi Sistem |
|---|---|---|---|
| `ESS.CUTI.LIHAT` | ESS | Melihat Hak & Saldo Cuti | `VIEW` |
| `ESS.CUTI.AJUKAN` | ESS | Mengajukan Permohonan Cuti Baru | `CREATE` |
| `ESS.CUTI.VERIFIKASI` | ESS | Memverifikasi Permohonan Cuti Staf | `APPROVE` |
| `VISITOR.PERMIT.AJUKAN` | VISITOR | Mengajukan Permit Masuk Kendaraan | `CREATE` |
| `VISITOR.PERMIT.VERIFIKASI` | VISITOR | Menerbitkan & Menyetujui Permit | `APPROVE` |
| `KPI.PERF.ADMIN` | KPI | Mengonfigurasi Formula & Bobot KPI | `EDIT` |

---

## 4. Panduan Registrasi Fitur Baru (DML)

Saat merilis fitur baru, developer menyertakan berkas DML pada `database/dml/`:

### Langkah A: Daftarkan Fitur Bisnis (`N_HRIS_Fitur`)
```sql
INSERT INTO dbo.N_HRIS_Fitur (
    Kode_Fitur, Id_Grup_Fitur, Nama_Fitur, Deskripsi, Jenis_Page,
    Flag_Punya_Approval, Id_Approval_Config, Halaman_Antrean_Approval,
    Flag_Aktif, Created_By
)
VALUES (
    'ESS.LEMBUR', 1, 'Penugasan & Konversi Lembur',
    N'Pengelolaan surat perintah kerja lembur dan konversi jam lembur ke cuti.',
    'OvertimeManagement', 'Y', 11, 'adminHCCutiManagementPage',
    'Y', 4
);
```

### Langkah B: Daftarkan Kemampuan Riil (`N_HRIS_Fitur_Kemampuan`)
Tentukan cakupan data yang diizinkan untuk setiap kemampuan (`DIRI`, `TIM`, `UNIT`, `SEMUA`):
```sql
MERGE dbo.N_HRIS_Fitur_Kemampuan AS target
USING (VALUES
    ('ESS.LEMBUR.LIHAT', 'ESS.LEMBUR', 'Melihat Riwayat Jam Lembur', 'VIEW', 'DIRI,TIM,UNIT,SEMUA', 'T', 1),
    ('ESS.LEMBUR.AJUKAN', 'ESS.LEMBUR', 'Mengajukan Perintah Lembur', 'CREATE', 'DIRI,TIM', 'T', 2),
    ('ESS.LEMBUR.ADMIN', 'ESS.LEMBUR', 'Konfirmasi Lembur Admin', 'EDIT', 'UNIT,SEMUA', 'T', 3)
) AS source (Kode_Kemampuan, Kode_Fitur, Nama_Kemampuan, Jenis_Aksi, Cakupan_Tersedia, Flag_Wajib_Approval, Sort_Order)
ON target.Kode_Kemampuan = source.Kode_Kemampuan
WHEN NOT MATCHED THEN
    INSERT (Kode_Kemampuan, Kode_Fitur, Nama_Kemampuan, Jenis_Aksi, Cakupan_Tersedia, Flag_Wajib_Approval, Sort_Order, Flag_Aktif)
    VALUES (source.Kode_Kemampuan, source.Kode_Fitur, source.Nama_Kemampuan, source.Jenis_Aksi, source.Cakupan_Tersedia, source.Flag_Wajib_Approval, source.Sort_Order, 'Y');
```

### Langkah C: Petakan ke Peran Standar (`N_HRIS_Role_Kemampuan`)
Hubungkan kemampuan tersebut ke peran yang relevan (`[MODUL]_SUPER_ADMIN`, `[MODUL]_ADMIN`, atau peran verifikator/pelaksana):
```sql
INSERT INTO dbo.N_HRIS_Role_Kemampuan (Id_Role, Kode_Kemampuan, Flag_Aktif, Created_By)
SELECT rf.Id_Role, 'ESS.LEMBUR.ADMIN', 'Y', 4
FROM dbo.N_HRIS_Role_Functional rf
WHERE rf.Role_Code = 'ESS_ADMIN'
  AND NOT EXISTS (
      SELECT 1 FROM dbo.N_HRIS_Role_Kemampuan rk
      WHERE rk.Id_Role = rf.Id_Role AND rk.Kode_Kemampuan = 'ESS.LEMBUR.ADMIN'
  );
```

---

## 5. Aturan Menu Layanan Mandiri Karyawan (*Self-Service*)

Halaman yang sifatnya digunakan oleh seluruh karyawan untuk urusan pribadi mereka (misalnya melihat absensi sendiri, mengajukan cuti sendiri, atau mengisi evaluasi KPI pribadi) **wajib diset sebagai Menu Global (`Is_Global_Menu = 'Y'`)** pada `N_HRIS_Menus`.

- **Dilarang**: Memberikan akses halaman personal melalui tabel bypass `HRIS_Page_Access`.
- **Keamanan Data**: Backend controller wajib membatasi data hanya untuk `Auth::id()` atau karyawan login yang bersangkutan.

---

## 6. Pemeriksaan Wewenang di Backend Controller

Developer dapat memeriksa wewenang secara bersih dan ekspresif menggunakan helper `$user->bisa(...)`:

### Contoh di Controller:
```php
use Illuminate\Http\Request;

public function simpan(Request $request)
{
    // Memeriksa izin kemampuan bisnis secara fail-closed
    abort_unless($request->user()->bisa('ESS.CUTI.AJUKAN'), 403, 'Anda tidak berwenang mengajukan cuti.');

    // Jalankan logika bisnis...
}
```

### Logika Otomatis `$user->bisa(...)`:
1. Jika kemampuan tidak terdaftar di `N_HRIS_Fitur_Kemampuan`, otomatis tolak `false` (mencegah typo developer).
2. Jika pengguna adalah **System Admin**, otomatis `true`.
3. Jika pengguna adalah **Super Admin (Dept Head)** modul yang menaungi fitur tersebut, otomatis `true`.
4. Jika pengguna memegang peran aktif yang memuat kode kemampuan tersebut, mengembalikan `true`.
5. Selain itu, mengembalikan `false`.

---

## 7. Rangkuman Struktur Peran Standar per Modul

Setiap modul di lingkungan HCIS wajib mengikuti struktur peran baku berikut:

1. **`[MODUL]_SUPER_ADMIN` (Tingkat: Super Admin / Dept Head)**:
   - Akses penuh otomatis ke seluruh menu dan fitur modul.
   - Mengangkat dan mencabut PIC Admin Modul.
2. **`[MODUL]_ADMIN` (Tingkat: Admin Modul / PIC Pengelola)**:
   - Membagikan penugasan peran pelaksana dan verifikator alur persetujuan.
   - Dibatasi oleh unit kerja (cakupan).
3. **Peran Alur Persetujuan (`POOL_APPROVAL` / Tingkat: Pengguna)**:
   - Anggota verifikator transaksi (misal: `ADMIN_PERMIT`, `ADMIN_TAMU_B2B`, `ADMIN_HC_CUTI_VERIFIKATOR`, `ROLE_KPI_VERIF`).
4. **Peran Pelaksana Operasional (`AKSES` / Tingkat: Pengguna)**:
   - Staf pelaksana menu fungsional harian (misal: `HC_OPS_ADMIN`, `GA_PHL_ADMIN`).
