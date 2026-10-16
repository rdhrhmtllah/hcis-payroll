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

                                        <th>Tujuan</th>
                                        <th>Status</th>

                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($data as $item)
                                    <tr class="search-row">
                                        <td>{{ $loop->iteration }}</td>
                                        <td class="ticket-number">{{ $item['No_Ticket'] }}</td>
                                        <td>{{ $item['Date'] }}</td>
                                        <td>{{ $item['Time'] }}</td>
                                        {{-- <td>{{ \Carbon\Carbon::parse($item['Date'])->format('d-M-Y') }}</td>
                                        <td>{{ \Carbon\Carbon::parse($item['Time'])->format('H:i') }}</td> --}}
                                        <td>{{ $item['Description'] }}</td>

                                        <td class="recipient">{{ $item['Username']}}</td>
                                        @if ($item['Flag_Finish_Reciepent'] == 'Y' and $item['Flag_Finish_Requester'] ==
                                        'Y'
                                        and $item['Flag_Finish'] =='Y')
                                        <td><span class="badge bg-success">Selesai</span></td>
                                        @elseif ($item['Flag_Approve'] =='Y' and $item['Flag_Finish_Requester'] == null
                                        and
                                        $item['Flag_Finish_Reciepent'] == null)
                                        <td><span class="badge bg-warning">On Process</span></td>
                                        @elseif ($item['Flag_Approve'] =='Y' and $item['Flag_Finish_Requester'] == null
                                        and
                                        $item['Flag_Finish_Reciepent'] =='Y')
                                        <td>
                                            <div class="d-flex justify-space-between">
                                                <button class="btn btn-danger response-ticket"
                                                    data-ticket="{{ $item['No_Ticket'] }}"
                                                    data-id="{{ $item['Id_Ticket'] }}">
                                                    Revisi
                                                </button>

                                                <a class="btn btn-primary"
                                                    href="{{ route('ticket.review', $item['Id_Ticket']) }}"
                                                    style="margin-left: 10px !important">
                                                    Done
                                                </a>
                                            </div>
                                        </td>
                                        @elseif ($item['Flag_Approve'] =='T')
                                        <td><span class="badge bg-danger">Di Tolak</span></td>
                                        @else
                                        <td><span class="badge bg-info">Waiting</span></td>
                                        @endif
                                        <td>
                                            <a href="{{ route('ticket.detail', [$item['Id_Ticket'], 'display_ticket']) }}"
                                                class="btn btn-info">Detail</a>
                                        </td>
                                        {{-- <td>
                                            @if ($item->Flag_Finish == NULL && $item->Flag_Approve == 'Y')
                                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal"
                                                data-bs-target="#detailModal{{ $item->Id_Ticket }}">
                                                <i class="fa-solid fa-eye text-white"></i>
                                            </button>
                                            <form action="/approveFinishTicket/{{ $item->Id_Ticket }}" method="POST"
                                                class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i class="fa-solid fa-check"></i>
                                                </button>
                                            </form>
                                            <button class="btn btn-warning btn-sm" data-bs-toggle="modal"
                                                data-bs-target="#revisionModal{{ $item->Id_Ticket }}">
                                                <i class="fa-solid fa-pencil text-grey"></i>
                                            </button>
                                            @elseif ($item->Flag_Finish == 'Y' && $item->Flag_Feedback == NULL)
                                            Rate
                                            @else
                                            Tidak Ada Aksi
                                            @endif
                                        </td> --}}
                                    </tr>

                                    <!-- Detail Modal -->
                                    <div class="modal fade" id="detailModal{{ $item['Id_Ticket'] }}" tabindex="-1"
                                        aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Detail Ticket</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                        aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <!-- Detail content will be added here -->
                                                    <p>Detail information for ticket {{ $item['No_Ticket'] }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Revision Modal -->
                                    <div class="modal fade" id="revisionModal{{ $item['Id_Ticket'] }}" tabindex="-1"
                                        aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Revision Ticket</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                        aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <form action="/revisionFinishTicket/{{ $item['Id_Ticket'] }}"
                                                        method="POST">
                                                        @csrf
                                                        <div class="mb-3">
                                                            <label class="form-label">No Ticket</label>
                                                            <input type="text" class="form-control"
                                                                value="{{ $item['No_Ticket'] }}" readonly>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Keterangan Revisi</label>
                                                            <textarea class="form-control" name="keterangan"
                                                                required></textarea>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Deadline</label>
                                                            <input type="date" class="form-control" name="deadline"
                                                                required>
                                                        </div>
                                                        <button type="submit" class="btn btn-primary">Submit
                                                            Revision</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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
                        <input type="hidden" value="requester" name="from">
                        <input type="hidden" name="status_note" value="Revisi">
                        <input type="hidden" name="revisi" value="revisi">
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

    function filterTable() {
        var searchText = $('#search').val().toLowerCase();
        var hasVisibleRows = false;

        $('.search-row').each(function () {
            var $row = $(this);
            var ticketNumber = $row.find('.ticket-number').text().toLowerCase();
            var requester = $row.find('.recipient').text().toLowerCase();
            var description = $row.find('td:eq(4)').text().toLowerCase();

            var matchesSearch = !searchText || ticketNumber.includes(searchText) || 
                              requester.includes(searchText) || description.includes(searchText);

            if (matchesSearch) {
                $row.show();
                hasVisibleRows = true;
            } else {
                $row.hide();
            }
        });

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

    var searchTimeout;
    $('#search').on('input', function () {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(filterTable, 300);
    });

    filterTable();
});
</script>
@endsection