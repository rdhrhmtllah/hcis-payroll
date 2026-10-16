@extends('layouts.master')

@section('title', $title)

@section('content')
<section class="section">
    <div class="row" id="basic-table">
        <div class="col-12 col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{$title}}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body">
                        <!-- Search and Filter -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <input type="text" id="search" class="form-control"
                                    placeholder="Ketik No Ticket / Pengaju">
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
                                        <th>No Ticket</th>
                                        <th>Tanggal Pengajuan</th>
                                        <th>Jam Pengajuan</th>
                                        <th>Keterangan</th>
                                        <th>Pengaju</th>
                                        <th>Divisi</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($data as $item)
                                    <tr class="search-row">
                                        <td>{{ $loop->iteration }}</td>
                                        <td class="ticket-number">{{ $item->No_Ticket }}</td>
                                        <td>{{ \Carbon\Carbon::parse($item->Date)->format('d-M-Y') }}</td>
                                        <td>{{ \Carbon\Carbon::parse($item->Time)->format('H:i:s') }}</td>
                                        <td>{{ $item->Description }}</td>
                                        <td class="requester">{{ $item->Nama_User }}</td>
                                        <td class="division">{{ $item->Division}}</td>
                                        <td>
                                            <button class="btn btn-primary response-ticket"
                                                data-ticket="{{ $item->No_Ticket }}"
                                                data-id="{{ $item->Id_Ticket }}" data-from="Y">

                                                <i class="fa-regular fa-circle-check" style="color: var(--hcis-white);"></i>
                                            </button>
                                            <button class="btn btn-danger response-ticket"
                                                data-ticket="{{ $item->No_Ticket }}"
                                                data-id="{{ $item->Id_Ticket }}" data-from="T">

                                                <i class="fa-solid fa-xmark" style="color: var(--hcis-white);"></i>
                                            </button>
                                        </td>

                                        {{-- <td>
                                            <a href="#" class="btn btn-primary response-ticket"
                                                data-url="/responseTicket/{{$item->Id_Ticket}}/Y"
                                                data-title="Meresponse">
                                                <i class="fa-regular fa-circle-check" style="color: var(--hcis-white);"></i>
                                            </a>
                                            <a href="#" class="btn btn-primary response-ticket"
                                                data-url="/responseTicket/{{$item->Id_Ticket}}/T" data-title="Menolak">
                                                <i class="fa-solid fa-xmark" style="color: var(--hcis-white);"></i>
                                            </a>

                                        </td> --}}
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    {{--
    <!-- Response Modal -->
    <div class="modal fade" id="responseModal" tabindex="-1" aria-labelledby="responseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="responseModalLabel">Isi Data dibawah</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="responseForm" method="POST">
                        @csrf
                        <input type="hidden" value="requester" name="from">
                        <input type="hidden" value="revisi" name="revisi">
                        <input type="hidden" name="id_ticket" id="idTicket">
                        <div class="mb-3">
                            <label for="noTicket" class="form-label">No Ticket</label>
                            <input type="text" class="form-control" id="noTicket" name="no_ticket" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="keterangan" class="form-label">Keterangan</label>
                            <textarea class="form-control" id="keterangan" name="keterangan" rows="3"
                                required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="submitResponse">Approve</button>
                </div>
            </div>
        </div>
    </div> --}}
    <!-- Response Modal -->
    <div class="modal fade" id="responseModal" tabindex="-1" aria-labelledby="responseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="responseModalLabel">Finish Ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="responseForm" method="post">
                        @csrf
                        <input type="hidden" name="from" value="reciepent">
                        <input type="hidden" name="status" id="status">
                        <input type="hidden" name="id_ticket" id="idTicket">
                        <div class="mb-3">
                            <label for="noTicket" class="form-label">No Ticket</label>
                            <input type="text" class="form-control" id="noTicket" name="no_ticket" readonly>
                        </div>
                        <div class="mb-3" id="estimasi_tanggal_selesai_parent">
                            <label for="estimasi_tanggal_selesai" class="form-label">Estimasi Tanggal Selesai</label>
                            <input type="date" class="form-control" id="estimasi_tanggal_selesai"
                                name="est_tgl_selesai">
                        </div>
                        <div class="mb-3">
                            <label for="keterangan" class="form-label">Keterangan</label>
                            <textarea class="form-control" id="keterangan" name="keterangan" rows="3"
                                required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary btnforText" id="submitResponse">Submit</button>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- Scripts -->
<script src="/assets/vendor/jquery/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    $(document).ready(function () {

        // Modal handling
        const responseModal = new bootstrap.Modal(document.getElementById('responseModal'));
        
        $('.response-ticket').on('click', function(e) {
        e.preventDefault();
        const ticketNumber = $(this).data('ticket');
        const ticketId = $(this).data('id');
        const from = $(this).data('from')
        $('#noTicket').val(ticketNumber);
        $('#idTicket').val(ticketId);
        $('#status').val(from);

            let statusButton = ""

            if (from == 'Y') {
                statusButton = "Terima";
                $('#estimasi_tanggal_selesai_parent').show();
            }else {
                statusButton = "Tolak";
                $('#estimasi_tanggal_selesai_parent').hide();
            }
        $('.btnforText').text(statusButton)
        
        // Set the form action dynamically
        // print('hello')
        $('#responseForm').attr('action', "/responseTicket");
        responseModal.show();
        });
        
        $('#submitResponse').on('click', function(e) {
        e.preventDefault();
        const keterangan = $('#keterangan').val();
        
        if (!keterangan) {
        Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Keterangan harus diisi!'
        });
        return;
        }
        
        $('#responseForm').submit();
        });

    function filterTable() {
        var searchText = $('#search').val().toLowerCase();
        var selectedDivision = $('#divisionFilter').val().toLowerCase();
        var hasVisibleRows = false;

        $('.search-row').each(function () {
            var $row = $(this);
            var ticketNumber = $row.find('.ticket-number').text().toLowerCase();
            var requester = $row.find('.requester').text().toLowerCase();
            var division = $row.find('.division').text().toLowerCase();
            var description = $row.find('td:eq(4)').text().toLowerCase();

            var matchesSearch = !searchText || ticketNumber.includes(searchText) || requester.includes(searchText) || description.includes(searchText);
            var matchesDivision = !selectedDivision || division === selectedDivision;

            if (matchesSearch && matchesDivision) {
                $row.show();
                hasVisibleRows = true;
            } else {
                $row.hide();
            }
        });

        // Menampilkan pesan jika tidak ada hasil
        $('.no-results-row').remove();
        if (!hasVisibleRows) {
            $('.table tbody').append(
                `<tr class="no-results-row">
                    <td colspan="${$('.table thead th').length}" class="text-center">Tidak ada data yang ditemukan</td>
                </tr>`
            );
        }

        // Memperbarui nomor urut
        updateRowNumbers();
    }

    function updateRowNumbers() {
        $('.search-row:visible').each(function (index) {
            $(this).find('td:first').text(index + 1);
        });
    }

    var searchTimeout;
    $('#search').on('input', function () {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(filterTable, 300);
    });

    $('#divisionFilter').on('change', filterTable);

    filterTable(); // Filter saat halaman dimuat
});

</script>
@endsection