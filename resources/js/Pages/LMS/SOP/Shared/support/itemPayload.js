/**
 * Muatan item Builder yang siap dikirim.
 *
 * SATU jalur dokumen saja sejak 27 Agu 2026: item ber-versi lahir dengan
 * MENUNJUK Dokumen SOP yang sudah punya versi berlaku. Mode "buat dokumen baru"
 * dicabut bersama endpoint-nya — dokumen kini hanya lahir di Master Dokumen,
 * lewat lima langkah versinya.
 *
 * Karena itu `Mode_Dokumen` dan `Dokumen_Baru` tak lagi dikirim. Keduanya tetap
 * DIBUANG di sini, bukan sekadar diabaikan: formulir yang dibuka sebelum
 * halaman disegarkan masih bisa membawanya, dan server menolak muatan yang
 * memuat `Dokumen_Baru` — penolakan yang membingungkan untuk field yang bahkan
 * tak ada lagi di layar.
 *
 * Dipisah dari komponennya supaya aturan ini bisa diuji sebagai aturan, bukan
 * lewat merakit seluruh halaman Builder.
 */
export function payloadItem(form) {
    const p = { ...(form || {}) };

    // Penanda layar & jalur yang sudah dicabut — server tak mengenal keduanya.
    delete p.Mode_Dokumen;
    delete p.Dokumen_Baru;
    /* Kesiapan dokumen & pointer versi aktif: keduanya TURUNAN, bukan masukan.
       Pointer digeser oleh penerbitan versi, tak pernah oleh formulir item;
       mengirimnya balik hanya mengundang suatu saat ada yang menuliskannya. */
    delete p.Dokumen_Punya_Versi;
    delete p.Id_Item_Versi_Aktif;
    delete p.Paket_Lain_Pemakai;
    // Penanda asal-usul baris, dihitung server tiap kali daftar dibaca.
    delete p.Sudah_Pernah_Terbit;

    return p;
}
