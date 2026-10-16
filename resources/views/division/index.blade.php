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
                        <!-- Table with outer spacing -->
                        <div class="table-responsive ">
                            <table class="table table-lg ">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Nama Divisi</th>
                                        <th>Keterangan</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($data as $item)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $item->Name }}</td>
                                            <td>{{ $item->Description }}</td>
                                            <td>
                                                <a href="division/edit/{{$item->id}}" class="btn btn-warning btn-sm">Edit</a>
                                                <form action="division/delete/{{$item->id}}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                                </form>
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

