@extends('layouts.master')
@section('title', $title)
@section('content')
@push('vueapp')
<link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet" type="text/css" />
<!--datatable responsive css-->
<link href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap.min.css" rel="stylesheet"
    type="text/css" />
<link href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css" rel="stylesheet" type="text/css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt@latest/dist/frappe-gantt.css">
<link rel="stylesheet" href="https://unpkg.com/frappe-gantt/dist/frappe-gantt.css">

<style>

table.dataTable td,
table.dataTable th {
    padding: 8px 5px !important; /* atau bisa coba 6px 10px */
    vertical-align: center;
}
</style>
@endpush
<section class="section">
    <div class="row" id="basic-table">
        <div class="col-12 col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Master Data {{$title}}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body">
                        <div class="row mb-2">


                            <div class="d-flex justify-content-start gap-2 align-items-center mb-3">
                                <a href="{{ route('task.create') }}"><button type="button" class="btn btn-primary"> Tambah </button></a>

                                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#ganttModal" type="button">Show Timeline</button>
                            </div>
                            @if (Session::has('pesan'))
                                <div class="alert alert-info">{{ Session::get('pesan') }}</div>
                            @endif
                            <div  class="table-responsive">
                                <table class="table table-lg" id="myTable">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Nama Task</th>
                                            <th>Project</th>
                                            <th>User</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th>Priority</th>
                                            <th>Progress</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($data as $item)
                                            <tr class="search-row">
                                                <td>{{ $loop->iteration }}</td>
                                                <td id="task-name" class="task-name">{{ $item->Task }}</td>
                                                <td >{{ $item->project->Kode_Project }} - {{ $item->project->Keterangan }}</td>
                                                <td >{{ $item->user->Nama }}</td>
                                                <td id="Start_Date"  class="Project" data-start='{{ $item->Start_Date }}' >{{  date('j F Y', strtotime($item->Start_Date)) }}</td>
                                                <td id="End_Date"  class="Project" data-end='{{ $item->End_Date }}'>{{ date('j F Y', strtotime($item->End_Date)) }}</td>
                                                <td >
                                                    @if ($item->urgency_status == 1)
                                                        Low
                                                    @elseif($item->urgency_status == 2)
                                                        Medium
                                                    @else
                                                        High
                                                    @endif
                                                </td>

                                                <td>
                                                    <select data-task="{{ $item->Id_Task }}" @if (auth()->user()->Level_Id == 2)
                                                        disabled
                                                    @endif class="form-select progress-select" name="Progress_Id">
                                                        @foreach ($dataProgress as $data)
                                                            <option value="{{ $data->Id_Progress }}"
                                                                @if ($data->Id_Progress == $item->Progress_Id) selected @endif>
                                                                {{ $data->Progress }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td>
                                                    <button style="width: 3.7rem;" id="editButton" data-value="{{ $item->project->Id_Project }}" data-task-id="{{ $item->Id_Task }}" type="button" class="mb-2 btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editModal">Edit</button>
                                                    <form style="width: 10rem; ;" action="task/delete" method="POST" class=" d-inline">
                                                        @csrf
                                                        <input type="hidden" name="id" value="{{$item->Id_Task}}">
                                                        <button type="submit" class="mb-2 btn btn-danger btn-sm">Delete</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                {{-- {{$data->withQueryString()->links('pagination::bootstrap-5')}} --}}
                                <!-- Pesan jika tidak ada hasil -->
                                <p id="noResults" class="text-center mt-2" style="display: none;">Tidak ada data yang ditemukan</p>
                            </div>
                            <div id="chart_div"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Modal Timeline --}}
    <div class="modal fade " id="ganttModal" tabindex="-1" aria-labelledby="ganttModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body">
                </div>
            </div>
        </div>
    </div>

{{-- Modal Edit --}}
<div class="modal fade " id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editModalLabel">Edit task</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form  action="{{ route('task.edit') }}" method="POST">
              @csrf
              <input type="hidden" id="Id_taskModal" name="id">
            <div class="mb-3">
                <label for="Project_Id">Project</label>
                <select name="Project_Id" id="SelectProject" class="form-select @error('Project_Id') is-invalid @enderror" id="">
                    <option value=""> Pilih Project </option>
                    @foreach ($dataProject as $data )
                    <option value="{{ $data->Id_Project }}">{{ $data->Kode_Project }}</option>
                    @endforeach
                </select>
                @error('Project_Id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
             </div>
            <div class="mb-3">
              <label for="Task"  class="col-form-label">Nama Task :</label>
              <input name="Task" id="TaskModal" type="text" class="form-control @error('Task') is-invalid @enderror" id="Name">
              @error('Task')
                  <div class="invalid-feedback">{{ $message }}</div>
              @enderror
             </div>
            <div class="mb-3">
                <label for="Start_Date">Start Date</label>
                <input type="date" id="Start_DateModal" name="Start_Date" class=" @error('Start_Date') is-invalid @enderror datepicker form-control" data-date-format="mm/dd/yyyy">
                @error('Start_Date')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-3">
                <label for="End_Date">End Date</label>
                <input type="date" id="End_DateModal" name="End_Date" class=" @error('End_Date') is-invalid @enderror datepicker form-control" data-date-format="mm/dd/yyyy">
                @error('End_Date')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

      </div>
      <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Update</button>
      </div>
      </form>
     </div>
    </div>
</div>



  <script src="/assets/vendor/jquery/jquery.min.js"></script>
  @push('scripts')

<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>

<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
        google.charts.load('current', {'packages':['gantt']});
        google.charts.setOnLoadCallback(drawChart);

        function daysBetween(start, end) {
            const oneDay = 24 * 60 * 60 * 1000;
            return Math.round((end - start) / oneDay);
        }

        function drawChart() {
            var data = new google.visualization.DataTable();
            data.addColumn('string', 'Task ID');
            data.addColumn('string', 'Task Name');
            data.addColumn('string', 'Resource');
            data.addColumn('date', 'Start Date');
            data.addColumn('date', 'End Date');
            data.addColumn('number', 'Duration');
            data.addColumn('number', 'Percent Complete');
            data.addColumn('string', 'Dependencies');

            data.addRows([
                @foreach($ganttData as $item)
                    [
                        '{{ $item['id'] }}',
                        '{{ $item['name'] }}',
                        null,
                        new Date('{{ $item['start'] }}'),
                        new Date('{{ $item['end'] }}'),
                        null,
                        0,
                        null
                    ]@if (!$loop->last),@endif
                @endforeach
            ]);

            var chart = new google.visualization.Gantt(document.getElementById('chart_div'));

            chart.draw(data, {
                height: 400,
                gantt: {
                    trackHeight: 100
                }
            });
        }
    </script>
<script>

    $(document).ready(function() {



            $('#myTable').DataTable({
                pageLength: 50, // default tampil 5 row
                lengthMenu: [50, 100],
            });
        // update Progress Task
        $(document).on('change', '.progress-select', function(){
    let progress = $(this).val();
    let taskId = $(this).data('task');

    $.ajax({
        url:'/task/updateProgress',
        type:'POST',
        data:{
            Progress_Id: progress,
            Task_Id: taskId
        },
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success:function(response){
            Swal.fire({
                title: 'Success!',
                text: 'Progress berhasil diupdate.',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });

            // ✅ Update tampilan UI kalau perlu di sini (jika reload tidak digunakan)
        },
        error:function(xhr, status, error){
            Swal.fire({
                title: 'Error!',
                text: 'Gagal memperbarui progress.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            console.error('Status :', status);
            console.error('XHR :', xhr);
            console.error('Error :', error);
            console.error('Response Text:', xhr.responseText);
        }
    });
    });



              //get-data-Modal
        $(document).on('click', '#editButton', function(){
            $(this).addClass('edit-task-trigger-click');

            let option = {
                'backdrop' : 'static'
            };
            $('#editModal').modal(option);
        })

        $('#editModal').on('shown.bs.modal', function(){
            let el = $('.edit-task-trigger-click');
            let row = el.closest('.search-row');

            let id = el.data('task-id');
            let Task = row.children('#task-name').text();
            let Start_Date = row.children('#Start_Date').data('start');
            let End_Date = row.children('#End_Date').data('end');
            let Project = el.data('value');
            $('#Id_taskModal').val(id);
            $('#TaskModal').val(Task);
            $('#Start_DateModal').val(Start_Date);
            $('#End_DateModal').val(End_Date);
            $('#SelectProject').val(Project);
        })

        $('#editModal').on('hide.bs.modal', function(){
            $('.edit-task-trigger-click').removeClass('edit-task-trigger-click');
            $("#editForm").trigger('reset');
        })




    });
</script>

@endpush
@endsection
