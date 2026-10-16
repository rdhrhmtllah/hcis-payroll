{{--
    Kartu permit — Template 2: badge zona (kolom kiri) + plat (kolom kanan),
    menempati SEPARUH ATAS A4 potrait, lengkap dengan garis potong.

    Tata letak dan gayanya tinggal di partials/: berkas ini dan lembar batch
    (visitor-permit-kartu-batch) memakai partial yang sama, jadi memperbaiki
    kartu cukup di satu tempat. Menyalinnya kembali ke sini akan melahirkan
    dua kartu yang perlahan berbeda tanpa ada yang menyadarinya.

    dompdf tidak mengenal flexbox maupun grid: seluruh tata letaknya tabel.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kartu Permit {{ $kartu['plat'] }}</title>
    <style>
        @page {
            size: a4 portrait;
            margin: 6mm 8mm;
        }

        @include('exports.partials._kartu-permit-css')
    </style>
</head>
<body>
    @include('exports.partials._kartu-permit', ['kartu' => $kartu])

    <div class="cut-guide-container">
        <div class="cut-guide-text">&bull; &bull; &bull; &nbsp;&nbsp; GARIS POTONG KARTU DASHBOARD SETENGAH A4 (210mm &times; 148.5mm) &nbsp;&nbsp; &bull; &bull; &bull;</div>
    </div>
</body>
</html>
