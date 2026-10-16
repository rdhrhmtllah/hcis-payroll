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
                                        <th>Detail</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($data as $item)
                                    <tr class="search-row">
                                        <td>{{ $loop->iteration }}</td>
                                        <td class="ticket-number">{{ $item['No_Ticket'] }}</td>
                                        <td>{{ \Carbon\Carbon::parse($item['Date'])->format('d-M-Y') }}</td>
                                        <td>{{ \Carbon\Carbon::parse($item['Time'])->format('H:i') }}</td>
                                        <td>{{ $item['Description'] }}</td>
                                        <td class="requester">{{ $item['Username'] }}</td>
                                        <td class="division">{{ $item['Name'] }}</td>
                                        <td>
                                            <a href="{{ route('ticket.detail', [$item['Id_Ticket'], 'finish_ticket']) }}"
                                                class="btn btn-info">Detail</a>
                                        </td>
                                        @if ($item['Flag_Approve'] == 'T')
                                        <td><span class="badge bg-danger">Di tolak</span></td>
                                        @elseif ($item['Flag_Approve'] == 'Y' and $item['Flag_Finish_Reciepent'] == 'Y'
                                        and $item['Flag_Finish_Requester'] == null)
                                        <td><span class="badge bg-warning">Menunggu Requester</span></td>
                                        @elseif ($item['Flag_Approve'] == 'Y' and $item['Flag_Finish_Reciepent'] == 'Y'
                                        and $item['Flag_Finish_Requester'] == 'Y')
                                        <td><span class="badge bg-warning">Selesai</span></td>
                                        @else
                                        <td>
                                            <button class="btn btn-success response-ticket"
                                                data-ticket="{{ $item['No_Ticket'] }}"
                                                data-id="{{ $item['Id_Ticket'] }}">
                                                <i class="fa-solid fa-upload" style="color: #f6f7f8;"></i>
                                            </button>
                                        </td>
                                        @endif
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

    <!-- Response Modal -->
    <div class="modal fade" id="responseModal" tabindex="-1" aria-labelledby="responseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="responseModalLabel">Finish Ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="responseForm" method="POST">
                        @csrf
                        <input type="hidden" name="from" value="reciepent">
                        <input type="hidden" name="id_ticket" id="idTicket">
                        <input type="hidden" name="status_note" value="Finish">
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
                    <button type="submit" class="btn btn-primary" id="submitResponse">Submit</button>
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
        $('#noTicket').val(ticketNumber);
        $('#idTicket').val(ticketId);
        // Set the form action dynamically
        $('#responseForm').attr('action', '/finishTicket');
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

    // Search and filter functionality
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

            var matchesSearch = !searchText || ticketNumber.includes(searchText) || 
                              requester.includes(searchText) || description.includes(searchText);
            var matchesDivision = !selectedDivision || division === selectedDivision;

            if (matchesSearch && matchesDivision) {
                $row.show();
                hasVisibleRows = true;
            } else {
                $row.hide();
            }
        });

        // Show "no results" message if needed
        $('.no-results-row').remove();
        if (!hasVisibleRows) {
            $('.table tbody').append(
                `<tr class="no-results-row">
                    <td colspan="${$('.table thead th').length}" class="text-center">
                        Tidak ada data yang ditemukan
                    </td>
                </tr>`
            );
        }

        updateRowNumbers();
    }

    function updateRowNumbers() {
        $('.search-row:visible').each(function (index) {
            $(this).find('td:first').text(index + 1);
        });
    }

    // Search input handling with debounce
    var searchTimeout;
    $('#search').on('input', function () {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(filterTable, 300);
    });

    $('#divisionFilter').on('change', filterTable);

    // Initial filter
    filterTable();
});
</script>
@endsection