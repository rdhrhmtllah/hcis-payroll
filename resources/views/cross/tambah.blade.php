@extends('layouts.master')

@section('title', $title)

@section('content')
<section class="section">
    <div class="row mb-2 d-flex justify-content-center align-items-center" id="basic-table">
        <div class="col-xl-8 col-sm-11">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h4 class="card-title">{{$title}}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body">
                        <form method="POST" action="{{ route('cross.store.periode') }}">
                            @csrf
                            <div class="row mb-2 ">
                                <div class='col-md-6 my-2'>
                                    <label for="Kode_Periode">Kode_Periode</label>
                                    <input type="text" name="Kode_Periode" value="{{ old('Kode_Periode') }}" class="form-control @error('Kode_Periode') is-invalid @enderror" id="">
                                    @error('Kode_Periode')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 my-2">
                                    <label for="Division_Id">Division</label>
                                    <select name="Division_Id" class="form-select @error('Division_Id')  is-invalid @enderror" id="divisi">
                                        <option value=" "> Pilih Divisi </option>
                                        @foreach ($datas as $data )
                                        <option value="{{ $data->Id_Division }}">{{ $data->Name }}</option>
                                        @endforeach
                                    </select>
                                    @error('Division_Id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                            </div>

                            <div class="row mb-2 ">
                                <div class="col-md-6 my-2">
                                    <label for="Tanggal_Awal">Start Date</label>
                                    <input type="date" name="Tanggal_Awal" class=" @error('Tanggal_Awal') is-invalid @enderror datepicker form-control" data-date-format="mm/dd/yyyy">
                                    @error('Tanggal_Awal')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class='col-md-6 my-2'>
                                    <label for="Tanggal_Akhir">End Date</label>
                                    <input type="date" name="Tanggal_Akhir" class=" @error('Tanggal_Akhir') is-invalid @enderror datepicker form-control" data-date-format="mm/dd/yyyy">
                                    @error('Tanggal_Akhir')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                            </div>

                            <div class="row mb-2">
                                <div class="col-md-12 mb-2">
                                    <label for="Keterangan">Description</label>
                                    {{-- <div class="form-floating"> --}}
                                    <textarea name="Keterangan"  class="form-control @error('Keterangan') is-invalid @enderror"></textarea>
                                    <input type="hidden" name="User_Id" value="{{auth()->user()->Id_Users}}">
                                    {{-- </div> --}}
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

<script>
    $(document).ready(function(){
        let ada_isi = false;
        $('#divisi').on('change', function(){
            $('#user').empty();
            kirim();
        });

       if (!ada_isi) {
            $('#user').prop('disabled', true);
            $('#user').html('<option>Tidak Ada User</option>');
        }else{
            $('#user').prop('disabled', false);


        }

        function kirim(){
            let divisi = $('#divisi').val();
            if(!divisi) return;

            $.ajax({
                type:'get',
                url:'/task/getDivision',
                data:{'divisi': divisi},
                success: function(data){

                    if(divisi && data.trim() !== '' ){
                        ada_isi = true;
                        $('#user').append(data).prop('disabled', false);
                    }else{
                        ada_isi = false;
                        $('#user').prop('disabled', true);
                        $('#user').html('<option>Tidak Ada User</option>');

                    }
                }
            })
        }
    })
</script>





@endsection
