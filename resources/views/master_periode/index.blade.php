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
                       <a href="{{ route('master.periode.display_tambah') }}" class="btn btn-primary">Tambah Periode</a>
                        <!-- Table with outer spacing -->
                        <div class="table-responsive ">
                            <table class="table table-lg ">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Kode Periode</th>
                                        <th>Keterangan</th>
                                        <th>Tanggal Awal</th>
                                        <th>Tanggal Akhir</th>
                                        {{-- <th>Action</th> --}}
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($data as $item)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $item->Kode_Periode }}</td>
                                        <td>{{ $item->Keterangan }}</td>
                                        <td>{{ date('d M Y', strtotime($item->Tanggal_Awal)) }}</td>
                                        <td>{{ date('d M Y', strtotime($item->Tanggal_Akhir)) }}</td>
                                        {{-- <td>
                                            <a href="division/edit/{{$item->id}}"
                                                class="btn btn-warning btn-sm">Edit</a>
                                            <form action="division/delete/{{$item->id}}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
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
</section>
@endsection