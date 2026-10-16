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
                        <form method="POST" action="{{ route('task.store') }}">
                            @csrf
                            <div class="row mb-2 ">
                                <div class='col-md-6 my-2'>
                                    <label for="Task">Task Name</label>
                                    <input type="text" name="Task" value="{{ old('Task') }}" class="form-control @error('Task') is-invalid @enderror" id="">
                                    @error('Task')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 my-2">
                                    <label for="Project_Id">Project</label>
                                    <select name="Project_Id" class="form-select @error('Project_Id') is-invalid @enderror" id="">
                                        <option value=""> Pilih Project </option>
                                        @foreach ($datas as $data )
                                        <option value="{{ $data->Id_Project }}">{{ $data->Keterangan }}</option>
                                        @endforeach
                                    </select>
                                    @error('Project_Id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            @if (auth()->user()->Level_Id == 2)
                                <div class="row mb-2 ">

                                    <div class="col-md-6 my-2">
                                        <label for="Division_Id">Division</label>
                                        <select name="Division_Id" class="form-select @error('Division_Id')  is-invalid @enderror" id="divisi">
                                            <option value=" "> Pilih Divisi </option>
                                            @foreach ($division as $data )
                                            <option value="{{ $data->Id_Division }}">{{ $data->Name }}</option>
                                            @endforeach
                                        </select>
                                        @error('Division_Id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 my-2">
                                        <label for="User_Id">User</label>
                                        <select name="User_Id" class="form-select @error('User_Id') is-invalid @enderror" id="user">

                                        </select>
                                        @error('User_Id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                </div>
                                @else
                                <input type="hidden" name="User_Id" value="{{ auth()->user()->Id_Users }}">
                            @endif
                            <div class="row mb-2">

                                <div class='col-md-4 mb-2'>
                                    <label for="Start_Date">Start Date</label>
                                    <input type="date" name="Start_Date" class=" @error('Start_Date') is-invalid @enderror datepicker form-control" data-date-format="mm/dd/yyyy">
                                    @error('Start_Date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class='col-md-4 mb-2'>
                                    <label for="End_Date">End Date</label>
                                    <input type="date" name="End_Date" class=" @error('End_Date') is-invalid @enderror datepicker form-control" data-date-format="mm/dd/yyyy">
                                    @error('End_Date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label for="urgency_status">Priority</label>
                                    <select name="urgency_status" class="form-select @error('urgency_status') is-invalid @enderror">
                                       <option value="1">Low</option>
                                       <option value="2">Medium</option>
                                       <option value="3">High</option>
                                    </select>
                                    @error('urgency_status')
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
