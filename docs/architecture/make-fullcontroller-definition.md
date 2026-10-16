# `make:fullcontroller` Generator Definition

Status: **DEFINITION_ONLY**. Dokumen ini belum mengubah `app/Console/Commands/MakeFullController.php`.

## Tujuan

Command `make:fullcontroller` tetap menjadi generator Laravel project, tetapi pada sesi implementasi berikutnya harus mengikuti module registry dan base folder Laravel. Command tidak boleh menjadi pengecualian arsitektur.

## Prinsip

- Tetap membuat controller di `app/Http/Controllers/`.
- Tetap membuat route di `routes/`.
- Tidak membuat `app/Modules`.
- Modul, submodul, dan fitur harus eksplisit.
- Modul harus divalidasi terhadap registry resmi.
- Modul baru tidak boleh dibuat otomatis.
- Developer adalah metadata/ownership kerja, bukan penentu modul bisnis.
- Route developer lama tidak boleh menjadi tempat default lintas modul tanpa keputusan migrasi terpisah.

## Target konseptual

Input harus mampu membedakan:

```text
MODUL / SUBMODUL / FITUR / DEVELOPER
```

Contoh target:

```text
SOP / Export / SopExport / Frans
```

Output minimal yang dibutuhkan:

```text
app/Http/Controllers/SOP/Export/SopExportController.php
app/Http/Controllers/SOP/Export/SopExportApiController.php  # hanya bila API diperlukan
routes/SOP/Export/SopExportWeb.php
routes/SOP/Export/SopExportApi.php                          # hanya bila API diperlukan
```

Nama dan format command final harus ditentukan saat sesi implementasi, setelah backward compatibility terhadap penggunaan existing diperiksa. Jangan mengubah command pada fase definisi ini.

## Validasi wajib pada implementasi nanti

1. Modul ada di registry.
2. Submodul dan fitur memiliki nama valid.
3. Namespace sesuai lokasi PSR-4.
4. File tidak ditimpa tanpa konfirmasi/guard.
5. Route loader/require tidak diduplikasi.
6. Developer route tidak mencampur ownership bisnis.
7. API file/controller tidak dibuat jika fitur hanya memakai Inertia.
8. Generator harus memberi pesan jelas jika klasifikasi modul ambigu atau modul belum disetujui.
9. Generator harus menyediakan dry-run atau preview sebelum menulis file jika memungkinkan.

## Batasan fase definisi

Jangan menjalankan generator, memindahkan legacy, mengubah namespace, mengubah route loader, atau mengedit command sampai sesi implementasi tersendiri disetujui.
