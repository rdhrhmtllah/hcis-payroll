# Table Documentation

Dokumentasi permanen setiap tabel baru berada di `docs/database/tables/<modul>/`. Gunakan nama file lowercase dengan tanda `-`, sedangkan nama tabel di dalam isi tetap mengikuti nama SQL resmi.

Contoh:

```text
docs/database/tables/sop/n-sop-dokumen-versi.md
```

Setiap dokumen tabel harus memuat: identitas dan tujuan, struktur kolom, nullable/default, arti flag, audit actor dan sumbernya, relasi, constraint/index beserta alasan, controller/model/service/job/command/endpoint aktual, data flow, change ID, dan path SQL. Referensi aplikasi harus diperoleh dari inspeksi repository, bukan asumsi.

Dokumentasi perubahan spesifik berada di `docs/changes/<change-id>/`.
