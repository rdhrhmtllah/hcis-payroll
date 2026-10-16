@extends('layouts.master')

@section('title', $title)

@section('content')
<section class="section">
    <div class="row" id="basic-table">
        <div class="col-12 col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Master Data {{$title}}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body">
                        <!-- Search and Filter -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <input type="text" id="search" class="form-control" placeholder="Cari Nama Karyawan">
                            </div>
                            <div class="col-md-4">
                                <select id="divisionFilter" class="form-select">
                                    <option value="">Semua Divisi</option>
                                    @foreach($divisions as $division)
                                        <option value="{{ $division->Name }}">{{ $division->Name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Table with outer spacing -->
                        <div class="table-responsive">
                            <table class="table table-lg">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Nama Karyawan</th>
                                        <th>Email</th>
                                        <th>Divisi</th>
                                        <th>Jabatan</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($data as $item)
                                        <tr class="search-row">
                                            <td>{{ $loop->iteration }}</td>
                                            <td class="employee-name">{{ $item->Username }}</td>
                                            <td>{{ $item->Email }}</td>
                                            <td class="division">{{ $item->divisions->Name }}</td>
                                            <td>{{ $item->levels->Name }}</td>
                                            <td>
                                                <a href="division/edit/{{$item->id}}" class="btn btn-warning btn-sm">Edit</a>
                                                <form action="division/delete/{{$item->id}}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <!-- Pesan jika tidak ada hasil -->
                            <p id="noResults" class="text-center mt-2" style="display: none;">Tidak ada data yang ditemukan</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<script src="/assets/vendor/jquery/jquery.min.js"></script>

<script>
    $(document).ready(function () {
        function filterTable() {
            var searchText = $('#search').val().toLowerCase().trim();
            var selectedDivision = $('#divisionFilter').val().toLowerCase().trim();
            var hasVisibleRows = false;

            $('.search-row').each(function () {
                var $row = $(this);
                var employeeName = $row.find('.employee-name').text().toLowerCase();
                var division = $row.find('.division').text().toLowerCase();

                var matchesSearch = !searchText || employeeName.includes(searchText);
                var matchesDivision = !selectedDivision || division === selectedDivision;

                if (matchesSearch && matchesDivision) {
                    $row.show();
                    hasVisibleRows = true;
                } else {
                    $row.hide();
                }
            });

            // Tampilkan pesan jika tidak ada hasil
            $('#noResults').toggle(!hasVisibleRows);

            // Perbarui nomor urut setelah filter
            updateRowNumbers();
        }

        function updateRowNumbers() {
            $('.search-row:visible').each(function (index) {
                $(this).find('td:first').text(index + 1);
            });
        }

        $('#search').on('input', function () {
            setTimeout(filterTable, 300);
        });

        $('#divisionFilter').on('change', filterTable);

        filterTable(); // Jalankan filter pertama kali
    });
</script>

@endsection
