{{--
    Cetak massal kartu permit: DUA kartu per lembar A4 potrait, masing-masing
    separuh halaman, dengan garis potong di tengah.

    Kartunya persis kartu tunggal — partial yang sama, gaya yang sama — supaya
    yang dicetak massal tidak pernah berbeda dari yang dicetak satu per satu.
    Warna kategori dipasang inline per kartu, sebab satu lembar bisa memuat
    permit MERAH dan HIJAU sekaligus sementara stylesheet-nya cuma satu.

    Jeda halaman dipasang SESUDAH kartu genap, bukan sebelum kartu ganjil:
    page-break-before pada elemen pertama membuat dompdf membuang satu halaman
    kosong di awal.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kartu Permit ({{ count($daftar) }} kartu)</title>
    <style>
        @page {
            size: a4 portrait;
            margin: 6mm 8mm;
        }

        @include('exports.partials._kartu-permit-css')

        .pemisah-kartu {
            margin: 4mm 0 5mm;
            border-top: 0.45mm dashed #94a3b8;
            padding-top: 2.5mm;
            text-align: center;
        }

        .pemutus-halaman {
            page-break-after: always;
        }
    </style>
</head>
<body>
    @foreach ($daftar as $i => $kartu)
        @include('exports.partials._kartu-permit', ['kartu' => $kartu])

        @if ($i % 2 === 0 && ! $loop->last)
            {{-- Kartu ganjil: garis potong pemisah dengan kartu di bawahnya. --}}
            <div class="pemisah-kartu">
                <div class="cut-guide-text">&bull; &bull; &bull; &nbsp;&nbsp; GARIS POTONG &nbsp;&nbsp; &bull; &bull; &bull;</div>
            </div>
        @elseif (! $loop->last)
            {{-- Kartu genap: lembar penuh, lanjut ke halaman berikutnya. --}}
            <div class="pemutus-halaman"></div>
        @endif
    @endforeach
</body>
</html>
