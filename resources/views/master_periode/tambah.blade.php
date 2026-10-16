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
                        @if (Session::has('pesan'))
                        <div class="alert alert-info">{{ Session::get('pesan') }}</div>
                        @endif
                        @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $msg)
                                <li>{{ $msg }}</li>
                                @endforeach
                            </ul>
                        </div>
                        @endif
                        <form action="{{ route('master.periode.add.data') }}" method="POST">
                            @csrf
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="">Kode Periode</label>
                                        <input type="text" name="kode_periode" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label for="">Keterangan</label>
                                        <input type="text" name="keterangan" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label for="">Tanggal Awal</label>
                                        <input type="date" name="tanggal_awal" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label for="">Tanggal Akhir</label>
                                        <input type="date" name="tanggal_akhir" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label for="">Division</label>
                                        <select name="division_id" class="form-select">
                                            <option>Pilih Disivisi</option>
                                            @foreach ($division as $item)
                                            <option value="{{ $item->Id_Division }}">{{ $item->Name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <button class="btn btn-primary">Tambah Periode</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>
@endsection