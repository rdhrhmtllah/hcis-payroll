@extends('layouts.master')
@section('title', $title)
@section('content')
@push('vueapp')
<link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet" type="text/css" />
<!--datatable responsive css-->
<link href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap.min.css" rel="stylesheet"
    type="text/css" />
<link href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css" rel="stylesheet" type="text/css" />
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

                            <div class="col-md-1">
                                <a href="{{ route('project.create') }}"><button type="button" class="mb-2 btn btn-primary"> Tambah </button></a>
                            </div>
                            @if (Session::has('pesan'))
                                <div class="alert alert-info">{{ Session::get('pesan') }}</div>
                            @endif
                            <div  class="table-responsive">
                                <table class="table table-lg" id="myTable">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Nama Project</th>
                                            <th>Keterangan</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($data as $item)
                                            <tr class="search-row">
                                                <td>{{ $loop->iteration }}</td>
                                                <td id="project-name" class="project-name">{{ $item->Kode_Project }}</td>
                                                <td id="Keterangan"  class="Project">{{ $item->Keterangan }}</td>
                                                <td>
                                                    <button style="width: 3.7rem;" id="editButton"  data-project-id="{{ $item->Id_Project }}" type="button" class="mb-2 btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editModal">Edit</button>
                                                    <form style="width: 10rem; ;" action="project/delete" method="POST" class=" d-inline">
                                                        @csrf
                                                        <input type="hidden" name="id" value="{{$item->Id_Project}}">
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
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Modal Edit --}}
<div class="modal fade " id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editModalLabel">Edit project</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form  action="{{ route('project.edit') }}" method="POST">
              @csrf
              <input type="hidden" id="Id_projectModal" name="id">
            {{-- <div class="mb-3">
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
             </div> --}}
            <div class="mb-3">
              <label for="Kode_Project"  class="col-form-label">Nama Project :</label>
              <input name="Kode_Project" id="ProjectModal" type="text" class="form-control @error('Kode_Project') is-invalid @enderror" id="Name">
              @error('Kode_Project')
                  <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            <div class="mb-3">
              <label for="Keterangan"  class="col-form-label">Keterangan :</label>
              <input name="Keterangan" id="KeteranganModal"  type="text" class="form-control @error('Keterangan') is-invalid @enderror" id="Name">
              @error('Keterangan')
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
<script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
<script>
    $(document).ready(function() {
            $('#myTable').DataTable({
                pageLength: 5, // default tampil 5 row
                lengthMenu: [5, 10, 25, 50, 100]
            });

              //get-data-Modal
        $(document).on('click', '#editButton', function(){
            console.log('Keterangan');
            $(this).addClass('edit-project-trigger-click');

            let option = {
                'backdrop' : 'static'
            };
            $('#editModal').modal(option);
        })

        $('#editModal').on('shown.bs.modal', function(){
            let el = $('.edit-project-trigger-click');
            let row = el.closest('.search-row');

            let id = el.data('project-id');
            let Project = row.children('#project-name').text();
            let Keterangan = row.children('#Keterangan').text();
            $('#Id_projectModal').val(id);
            $('#ProjectModal').val(Project);
            $('#KeteranganModal').val(Keterangan);

        })

        $('#editModal').on('hide.bs.modal', function(){
            $('.edit-project-trigger-click').removeClass('edit-project-trigger-click');
            $("#editForm").trigger('reset');
        })




    });
</script>

@endpush
@endsection
