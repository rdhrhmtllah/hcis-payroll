@extends('layouts.master')

@section('title', $title)

@push('vueapp')
<link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet" type="text/css" />
<!--datatable responsive css-->
<link href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap.min.css" rel="stylesheet"
    type="text/css" />
<link href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css" rel="stylesheet" type="text/css" />
@endpush

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
                    <div class="row mb-4">

                        <div class="col-md-1 mb-2">
                            <a href="{{ route('cross.add.periode') }}"><button type="button" class="btn btn-primary">
                                    Tambah </button></a>
                        </div>


                    </div>

                    @if (Session::has('pesan'))
                    <div class="alert alert-info">{{ Session::get('pesan') }}</div>
                    @endif
                    <div class="table-responsive">
                        <table id="myTable" class="table table-lg">
                            <thead>
                                <tr>
                                    <th>Periode Code</th>
                                    <th>Description</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Division</th>
                                    <th>Created By</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($data as $item )
                                <tr>
                                        <td>{{$item->Kode_Periode}}</td>
                                        <td>{{$item->Keterangan}}</td>
                                        <td>{{date('j F Y', strtotime($item->Tanggal_Awal))}}</td>
                                        <td>{{date('j F Y', strtotime($item->Tanggal_Akhir))}}</td>
                                        <td>{{$item->division->Name}}</td>

                                        @if ($item->User_Id != null)
                                        <td>{{$item->user->Username}}</td>
                                        @else
                                        <td>Tidak Diketahui</td>
                                        @endif

                                        @if ($item->Flag_Sudah_Review == 'Y')
                                            <td><span class="badge bg-success">Telah Direview</span></td>
                                            @else
                                            <td><span class="badge bg-danger">Belum Direview</span></td>

                                        @endif

                                        <td>
                                            @if ($item->Flag_Sudah_Review == 'Y')
                                            <button class="btn btn-danger" disabled>Hapus</button>

                                            @else
                                            <form action="{{route('cross.destroy.periode')}}" method="POST">
                                                @csrf
                                                <input name="id" type="hidden" value="{{$item->Id_Periode}}">
                                                <button class="btn btn-danger">Hapus</button>
                                            </form>


                                        @endif
                                        </td>
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
</section>

<!-- Modal Edit -->


<script src="/assets/vendor/jquery/jquery.min.js"></script>

<script>

    //
</script>

@endsection

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
