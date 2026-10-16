# DDL-2026-034 — Tanggal proses pada identitas versi

| Bidang | Nilai |
|---|---|
| Change ID | `DDL-2026-034-sop-versi-tanggal-proses` |
| Jenis | DDL |
| Target development | `Web_HRIS` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |
| Forward | `database/ddl/DDL-2026-034-sop-versi-tanggal-proses/forward.sql` |

## Isi

```text
N_SOP_Item_Versi.Tanggal_Permintaan       date NULL
N_SOP_Item_Versi.Tanggal_Respon           date NULL
N_SOP_Item_Versi.Tanggal_Akhir_Approval   date NULL
N_SOP_Master_Tahap_Versi.Cap_Tanggal      varchar(30) NULL
```

## Empat tanggal diminta, tiga kolom dibuat

`Tanggal_Kadaluarsa` **sudah ada sejak lama** di `N_SOP_Item_Versi` — enam dari
76 versi terisi — tetapi tak satu pun jalur SOP menyentuhnya: tak ada di form,
tak ada di controller, tak ada yang menulisnya. Data yatim.

Ia **dimunculkan**, bukan diduplikasi. Menambah kolom kedua dengan arti yang
sama akan membuat enam baris lama itu tak pernah ikut terbaca, dan tak ada yang
menyadarinya sampai ada yang bertanya kenapa masa berlakunya kosong.

## Cap tanggal sebagai data

`Cap_Tanggal` menyimpan **nama kolom** pada `N_SOP_Item_Versi` yang dicap saat
tahap itu dimasuki. Ini mengikuti pola yang sama dengan `Status_Teknis` dan
`Butuh_Berkas` di tabel yang sama: admin yang menambah tahap bisa sekaligus
menyatakan tanggal apa yang tercap, tanpa satu baris kode berubah.

Kosong berarti tahap itu tak mencap apa pun, dan itu keadaan mayoritas.

**Nama kolom dari data tidak pernah dipercaya begitu saja.**
`SopTahapVersiService::KOLOM_CAP` menyaringnya dengan daftar putih, sehingga
salah ketik di master berakhir sebagai pencapan yang dilewati — bukan sebagai
kueri yang menyebut kolom karangan. Ada tes yang menjaganya.

## Dicap hanya bila masih kosong

Aturan itu melindungi dua hal sekaligus:

- **tanggal asli**, saat versi bolak-balik antar tahap (Review → Drafting → Review)
- **koreksi manual admin** — surat yang ditandatangani minggu lalu tetapi baru
  dicatat hari ini tak boleh tertimpa oleh sebuah klik

Jejak setiap perpindahan tetap utuh di `N_SOP_Log`, jadi riwayatnya tak hilang
meski kolomnya hanya menyimpan yang pertama.

## Eksekusi

`Web_HRIS` (development): **5 batch OK**.

## Rollback

`drop.sql` **tidak pernah dijalankan agen**. Ia menghapus jejak waktu yang tak
bisa direkonstruksi dari mana pun — log menyimpan perpindahan tahap, tetapi
tidak menyimpan tanggal yang diketik ulang admin.
