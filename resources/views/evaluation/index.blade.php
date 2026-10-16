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
                        <!--  add Button -->


                        <!-- Search and Filter -->
                        <div class="row mb-2">

                            <div class="col-md-1 ">
                                <a href="{{ route('evaluation.create') }}"><button type="button" class="btn btn-primary"> Tambah </button></a>
                            </div>
                            {{-- <div class="col-md-4 mb-2">
                                <input type="text" id="search" class="form-control" placeholder="Cari Nama Case">

                            </div>
                            <div class="col-md-4 mb-2">
                                <select id="caseFilter" class="form-select">
                                    <option value="">Semua Case</option>
                                    @foreach($cases as $case)
                                        <option value="{{ $case->Name }}">{{ $case->Name }}</option>
                                    @endforeach
                                </select>
                            </div> --}}
                        </div>

                        @if (Session::has('pesan'))
                        <div class="alert alert-info">{{ Session::get('pesan') }}</div>
                        @endif
                        <!-- Table with outer spacing -->
                        <div class="table-responsive">
                            <table id="myTable" class="table table-lg">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Nama Evaluation</th>
                                        <th>Keterangan</th>
                                        <th>Status Akftif</th>
                                        <th>Case</th>
                                        {{-- <th>Action</th> --}}
                                    </tr>
                                </thead>
                                <tbody >
                                    @foreach ($data as $item)

                                        <tr id="search-row" class="search-row" >

                                            <td>{{ $loop->iteration }}</td>
                                            <td id="evalName" class="evalName">
                                                {{ $item->Name }}
                                            </td>

                                            <td id="evalDesc">
                                                {{ $item->Description }}
                                            </td>
                                            <td>
                                                @if ($item->Flag_Active == 'Y')
                                                    Aktif

                                                @elseif ($item->Flag_Active == 'N')
                                                Tidak Aktif
                                                @endif
                                            </td>
                                            <td class="caseName" id="caseName" >
                                                {{ $item->cases->Name }}
                                            </td>
                                            {{-- button Edit & delete --}}
                                                {{-- <td>
                                                    <!-- <a href="evaluation/edit/{{$item->Id_Evaluation}}" class="mb-2 btn btn-warning btn-sm">Edit</a> -->
                                                    <button style="width: 3.7rem;" id="editButton" data-value="{{ $item->Id_Division }}" data-case-id="{{ $item->Id_Case }}" type="button" class="mb-2 btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editModal">Edit</button>
                                                    <form style="width: 10rem; ;" action="evaluation/delete" method="POST" class=" d-inline">
                                                        @csrf
                                                        <input type="hidden" name="id" value="{{$item->Id_Evaluation}}">
                                                        <button type="submit" class="mb-2 btn btn-danger btn-sm">Delete</button>
                                                    </form>
                                                </td> --}}
                                        {{-- </tr> --}}
                                    @endforeach
                                </tbody>
                            </table>
                            {{-- {{$data->withQueryString()->links('pagination::bootstrap-5')}}  --}}
                            <!-- Pesan jika tidak ada hasil -->
                            <p id="noResults" class="text-center mt-2" style="display: none;">Tidak ada data yang ditemukan</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>


<!-- Modal Edit -->

<div class="modal fade " id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editModalLabel">Edit {{$title}}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="editForm" method="POST" action="{{ route('case.edit') }}">
            @csrf
            <input type="hidden" id="Id_caseModal" name="id">
          <div class="mb-3">
            <label for="Name" class="col-form-label">Name :</label>
            <input name="Name" type="text" class="form-control @error('Name') is-invalid @enderror" id="Name">
            @error('Name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
          <div class="mb-3">
            <label for="Division_Id" class="col-form-label">Division_id :</label>
            <select name="Division_Id" class="form-select @error('Division_Id') is-invalid @enderror" id="SelectDiv">
                @foreach ($dataDiv as $item )
                <option value="{{ $item->Id_Division }}">{{ $item->Name }}</option>
                @endforeach
            </select>
            @error('Division_Id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label for="Case_Id">Case</label>
            <select name="Case_Id" class="form-select" id="Case_Id">

            </select>
        </div>
        <div class="mb-3">
            <label for="Description" class="col-form-label">Description :</label>
            <textarea name="Description" class="form-control @error('Description') is-invalid @enderror" id="Description"></textarea>
            @error('Description')
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
    });
</script>
@endpush

<script>
    $(document).ready(function () {
        // filter and Search
        // $('#search').on('input', function(){
        //     setTimeout(filterTable, 300);
        // });

        // $('#caseFilter').on('change', filterTable);




        // function filterTable(){
        //     let searchText =$('#search').val().toLowerCase().trim();
        //     let selectedCase = $('#caseFilter').val().toLowerCase().trim();
        //     let hasVisibleRows = false;



        //     $('.search-row').each(function(){
        //         let $row = $(this);
        //         let evaluationName = $row.find('.evalName').text().toLowerCase().trim();
        //         let caseFind = $row.find('#caseName').text().toLowerCase().trim();

        //         let matchesSearch = !searchText || evaluationName.includes(searchText);
        //         let matchesCase = !selectedCase || caseFind == selectedCase;

        //         if(matchesSearch && matchesCase){
        //             $row.show();
        //             hasVisibleRows = true;
        //         }else{
        //             $row.hide()
        //         }
        //     })

        //     $('#noResults').toggle(!hasVisibleRows);

        //     updateRowNumbers();
        // }

        // function updateRowNumbers(){
        //     $('.search-row:visible').each(function (index){
        //         $(this).find('td:first').text(index + 1)
        //     });
        // }



        // filterTable();



        // get Case From Division
        $('#SelectDiv').on('change', function(){
            $('#Case_Id').empty();
            getCase()
        });
        function getCase(){
            let key = $('#SelectDiv').val();
            $.ajax({
                type:'get',
                url:'/evaluation/get',
                data:{'search':key},
                success: function(data){
                    // console.log(data);
                    $('#Case_Id').append(data);
                },error: function (xhr, status, error) {
                console.error('Error AJAX:', error);
                alert('Terjadi kesalahan saat mengambil data.');
                    }
            })
        }

        getCase(); //Pertama load case

        //get-data-Modal
        $(document).on('click', '#editButton', function(){
            $(this).addClass('edit-case-trigger-click');

            let option = {
                'backdrop' : 'static'
            };
            $('#editModal').modal(option);
        })

        $('#editModal').on('shown.bs.modal', function(){
            let el = $('.edit-case-trigger-click');
            let row = el.closest('.search-row');

            let id = el.data('case-id');
            let name = row.children('#evalName').text().trim();
            let description = row.children('#evalDesc').text().trim();
            let division = el.data('value');
            $('#Id_caseModal').val(id);
            $('#Name').val(name);
            $('#Description').val(description);
            // $('#SelectDiv').val(division);

        })

        $('#editModal').on('hide.bs.modal', function(){
            $('.edit-case-trigger-click').removeClass('edit-case-trigger-click');
            $("#editForm").trigger('reset');
        })
    });
</script>

@endsection
