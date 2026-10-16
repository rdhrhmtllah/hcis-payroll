@extends('layouts.master')

@section('title', $title)

@section('content')
<section class="section">
    <div class="row" id="basic-table">
        <div class="col-12 col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Tambah Data {{$title}}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body">
                        <form method="POST" action="/storeCase">
                            @csrf
                            <div class="row">
                                <div class='col-md-4'>
                                    <label for="Name"> Name</label>
                                    <input type="text" name="Name" class="form-control @error('Name') is-invalid @enderror" id="">
                                    @error('Name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label for="Division_Id">Division_id</label>
                                    <select name="Division_Id" class="form-select" id="">
                                    
                                        @foreach ($datas as $data )
                                        <option value="{{ $data->Id_Division }}">{{ $data->Name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="row">

                                <div class='col-md-8 my-4'>
                                    <label for="Description">Description</label>
                                    <textarea style="height:10rem" name="Description" class="form-control" id=""></textarea>
                                </div> 
                            </div>
                            <div class="row">
                                <div class="col-md-8 mb-4">
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
