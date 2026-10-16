@extends('layouts.master')

@section('title', $title)

@section('content')
<section class="section">
    <div class="row mb-2 d-flex justify-content-center align-items-center" id="basic-table">
        <div class="col-xl-8 col-sm-11">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h4 class="card-title">Tambah Data {{$title}}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body">
                        <form method="POST" action="{{ route('project.store') }}">
                            @csrf
                            <div class="row mb-2 ">
                                <div class='col-md-6 my-2'>
                                    <label for="Kode_Project">Nama Project</label>
                                    <input type="text" name="Kode_Project" value="{{ old('Kode_Project') }}" class="form-control @error('Kode_Project') is-invalid @enderror" id="">
                                    @error('Kode_Project')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class='col-md-6 my-2'>
                                    <label for="Keterangan">Keterangan</label>
                                    <input type="text" name="Keterangan" value="{{ old('Keterangan') }}" class="form-control @error('Keterangan') is-invalid @enderror" id="">
                                    @error('Keterangan')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                               
                            </div>
                     
                            <div class="row mb-2">
                                <div class="col-md-12 mb-4">
                                    <div class="d-grid ">
                                        <button class="btn btn-primary" type="submit">Submit</button>
                                    </div>
                                </div>
                            </div> 
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<script src="/assets/vendor/jquery/jquery.min.js"></script>





@endsection
