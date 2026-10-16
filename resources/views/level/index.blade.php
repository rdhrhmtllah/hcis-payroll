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
                        <!-- Search Box -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <input type="text" id="search" class="form-control" placeholder="Cari Nama Jabatan">
                            </div>
                        </div>

                        <!-- Table with outer spacing -->
                        <div class="table-responsive">
                            <table class="table table-lg">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Nama Jabatan</th>
                                        <th>Keterangan</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="tableBody">
                                    @foreach ($data as $item)
                                        <tr class="search-row">
                                            <td>{{ $loop->iteration }}</td>
                                            <td class="job-name">{{ $item->Name }}</td>
                                            <td>{{ $item->Description }}</td>
                                            <td>
                                                <a href="level/edit/{{$item->id}}" class="btn btn-warning btn-sm">Edit</a>
                                                <form action="level/delete/{{$item->id}}" method="POST" class="d-inline">
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
            var hasVisibleRows = false;

            $('.search-row').each(function () {
                var $row = $(this);
                var jobName = $row.find('.job-name').text().toLowerCase();

                if (jobName.includes(searchText)) {
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

        filterTable(); // Jalankan filter pertama kali
    });
</script>

@endsection
