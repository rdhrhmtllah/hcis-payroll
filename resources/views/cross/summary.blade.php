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

                        <!-- Table with outer spacing -->
                        <div class="table-responsive ">
                            <table class="table table-lg ">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        {{-- <th>No Review</th> --}}
                                        <th>Periode</th>
                                        <th>Divisi</th>
                                        <th>Tanggal Awal</th>
                                        <th>Tanggal Akhir</th>
                                        <th>AVG</th>
                                        {{-- <th>User Review</th> --}}
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>

                                    @foreach ($data as $item)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        {{-- <td>{{ $item['No_Review'] }}</td> --}}
                                        <td>{{ $item['periode']}}</td>
                                        <td>{{ $item['Division']}}</td>
                                        <td>{{ date('d M Y', strtotime($item['Tanggal_Awal'])) }}</td>
                                        <td>{{ date('d M Y', strtotime($item['Tanggal_Akhir'])) }}</td>
                                        <td>{{ round($item['Score'], 2)}}</td>
                                        {{-- <td>{{ $item['username']}}</td> --}}
                                        <td>
                                            <a href="{{ route('summary.cross.review.detail', [ $item['Id'], $item['Id_Division']]) }}"
                                                class="btn btn-info btn-sm">Detail</a>

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
@endsection
