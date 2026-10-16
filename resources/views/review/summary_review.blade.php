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
                                        <th>No Review</th>
                                        <th>Divisi</th>
                                        <th>Periode</th>
                                        <th>Tanggal Awal</th>
                                        <th>Tanggal Akhir</th>
                                        <th>User Review</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($data as $item)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $item['No_Review'] }}</td>
                                        <td>{{ $item['division'] }}</td>
                                        <td>{{ $item['Keterangan'] }}</td>
                                        <td>{{ date('d M Y', strtotime($item['Tanggal_Awal'])) }}</td>
                                        <td>{{ date('d M Y', strtotime($item['Tanggal_Akhir'])) }}</td>
                                        <td>{{ $item['username'] }}</td>
                                        <td>
                                            <a href="{{ route('summary.review.detail', [str_replace('/', '@', $item['No_Review']), $item['id']]) }}"
                                                class="btn btn-info btn-sm">Detail</a>
                                            {{-- <form method="get" class="d-inline"> --}}
                                                {{-- @csrf --}}
                                                {{-- <input type="hidden" name="no_review"
                                                    value="{{ $item->No_Review }}"> --}}
                                                {{-- <input type="hidden" name="periode" value="{{ $item-> }}"> --}}
                                                {{-- <button type="submit" class="btn btn-info btn-sm">Detail</button>
                                                --}}
                                                {{-- </form> --}}
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