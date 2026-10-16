<?php

namespace App\Services\SYSTEM\Application\Reports\Kontrak;

/**
 * Dataset yang bisa menyebutkan sendiri pilihan isian saringannya.
 *
 * Kontrak TERPISAH dari DatasetLaporan, bukan method tambahan di sana: dataset
 * yang seluruh saringannya berupa teks/tanggal bebas tidak perlu dipaksa
 * mengembalikan array kosong hanya untuk memenuhi antarmuka.
 *
 * Alasan keberadaannya: sebelum ini layar Studio mengambil daftar pilihan
 * langsung dari endpoint monitoring SOP. Tiga akibatnya —
 *
 *  1. Komponen bersama milik SYSTEM menyebut alamat modul bisnis, sehingga
 *     modul kedua ikut memanggil endpoint SOP.
 *  2. Endpoint itu dijaga hak halaman yang berbeda dari Studio, jadi pemegang
 *     hak Studio saja mendapat 403 dan dropdown-nya kosong tanpa penjelasan.
 *  3. Pilihannya tidak tahu bentuk nilai yang diminta saringan. Saringan paket
 *     dan kategori menyaring KOLOM ID, tetapi daftarnya berisi NAMA — memilih
 *     satu pun langsung menggagalkan query dengan galat konversi.
 *
 * Karena itu nilai dan label dipisah: `value` adalah yang dikirim ke saringan,
 * `label` adalah yang dibaca manusia.
 */
interface PenyediaOpsiSaringan
{
    /**
     * Pilihan per KUNCI SARINGAN (bukan per kolom).
     *
     * @return array<string, list<array{value: string|int, label: string}>>
     */
    public function opsiSaringan(): array;
}
