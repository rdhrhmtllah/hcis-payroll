{{-- @extends('layouts.master')
@section('title', $title)
@section('content')
<style>
    .toggle-btn {
        cursor: pointer;
        margin-left: 10px;
        bottom: 0.5rem;
        right: 1rem;
        opacity: 50%;
    }
</style>
<section class="section">
    <div class="row flex justify-content-center" id="basic-table">
        <div class="col-12 col-md-6 ">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{$title}}</h4>
                </div>
                <div class="card-content">
                    <div class="card-body">
                        @if (Session::has('pesan'))
                        <div class="alert alert-danger ">{{ Session::get('pesan') }}</div>
                        @endif
                        <form class="d-grid gap-4" method="POST" action="{{ route('password.update') }}">
                            @csrf


                            <div class="w-full position-relative">
                                <label for="oldPass">Password Lama</label>
                                <input id="oldPass" type="password" name="oldPass"
                                    class="form-control @error('oldPass') is-invalid @enderror "
                                    placeholder="Masukan Password Lama">
                                <span id="toogle" class="toggle-btn position-absolute "><i class="bi bi-eye"></i></span>
                                @error('oldPass')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="w-full position-relative">
                                <label for="newPass">Password Baru</label>
                                <input type="password" name="newPass"
                                    class="form-control @error('newPass') is-invalid @enderror"
                                    placeholder="Masukan Password Baru">
                                <span id="toogle" class="toggle-btn position-absolute "><i class="bi bi-eye"></i></span>
                                @error('newPass')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="w-full position-relative">
                                <label for="VerifyPass">Password Baru</label>
                                <input type="password" name="VerifyPass"
                                    class="form-control @error('VerifyPass') is-invalid @enderror"
                                    placeholder="Verifikasi Password Baru">
                                <span id="toogle" class="toggle-btn position-absolute "><i class="bi bi-eye"></i></span>
                                @error('VerifyPass')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="d-grid mt-4">
                                <button class="btn btn-primary" type="submit">Update Password</button>
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
    $(document).ready(function() {
    $('.toggle-btn').on('click', function() {
                let passwordField = $(this).prev('input[type="password"], input[type="text"]');

                let fieldType = passwordField.attr('type') === 'password' ? 'text' : 'password';
                passwordField.attr('type', fieldType);

                let icon = $(this).find('i');
                icon.toggleClass('bi-eye bi-eye-slash');
                });
        });

</script>
@endsection --}}



@extends('layouts.master')
@section('title', $title)
@section('content')
<style>
    .toggle-btn {
        cursor: pointer;
        margin-left: 10px;
        bottom: 0.5rem;
        right: 1rem;
        opacity: 50%;
    }
    .nav-btn {
        cursor: pointer;
        text-align: center;
        padding: 10px;
        background-color: #f8f9fa;
        border-radius: 6px;
        transition: all 0.3s ease;
    }
    .nav-btn.active {
        background-color: #0d6efd;
        color: white;
    }
    .form-container {
        /* display: none; */
       position: absolute;
        width: 100%;
        transition: all 0.5s ease;
        opacity: 0;
        transform: translateX(100%);
    }
    .form-container.active {
        position: relative;
        display: block;
        opacity: 1;
        transform: translateX(0)

    }
    .form-container.left{
        transform: translateX(-100%)
    }
    .wrapper-card{
        overflow: hidden;
    }
</style>
<section class="section">
    <div class="row justify-content-center align-items-center">
        <div class="col-md-7">
            <div class="row wrapper-card bg-white align-items-center justify-content-center p-2 rounded shadow-sm">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title fs-4 text-center fw-bold">Settings Your Account</div>
                        <div class="">
                            {{-- Navigation buttons --}}
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div id="reset-password-btn" class="nav-btn  fw-semibold">Reset Password</div>
                                </div>
                                <div class="col-md-6">
                                    <div id="change-data-btn" class="nav-btn fw-semibold active">Profile</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- card content --}}
                    <div class="card-body">
                        {{-- Reset Password Form --}}
                        <div id="reset-password-form" class="form-container ">
                            <div class="card-content mt-2">
                                @if (Session::has('pesan'))
                                <div class="alert alert-danger">{{ Session::get('pesan') }}</div>
                                @endif
                                <form class="d-grid gap-4" method="POST" action="{{ route('password.update') }}">
                                    @csrf
                                    <div class="w-full position-relative">
                                        <label for="oldPass">Password Lama</label>
                                        <input id="oldPass" type="password" name="oldPass"
                                            class="form-control @error('oldPass') is-invalid @enderror"
                                            placeholder="Masukan Password Lama">
                                        <span class="toggle-btn position-absolute"><i class="bi bi-eye"></i></span>
                                        @error('oldPass')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="w-full position-relative">
                                        <label for="newPass">Password Baru</label>
                                        <input id="newPass" type="password" name="newPass"
                                            class="form-control @error('newPass') is-invalid @enderror"
                                            placeholder="Masukan Password Baru">
                                        <span class="toggle-btn position-absolute"><i class="bi bi-eye"></i></span>
                                        @error('newPass')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="w-full position-relative">
                                        <label for="verifyPass">Password Baru</label>
                                        <input id="verifyPass" type="password" name="VerifyPass"
                                            class="form-control @error('VerifyPass') is-invalid @enderror"
                                            placeholder="Verifikasi Password Baru">
                                        <span class="toggle-btn position-absolute"><i class="bi bi-eye"></i></span>
                                        @error('VerifyPass')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="d-grid mt-4">
                                        <button class="btn btn-primary" type="submit">Update Password</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        {{-- Change Data Form --}}
                        <div id="change-data-form" class="form-container active">
                            <div class="card-content mt-2">
                                @if (Session::has('data_message'))
                                <div class="alert alert-{{ Session::get('data_alert_type', 'info') }}">{{ Session::get('data_message') }}</div>
                                @endif
                                <form class="d-grid gap-4" method="POST" action="{{route('change.data')}}">
                                    @csrf
                                    <input  type="hidden" name="Id_Users" value="{{Auth()->user()->Id_Users}}">
                                    <div class="w-full">
                                        <label for="Nama">Nama</label>
                                        <input disabled id="Nama" type="text" name="Nama"
                                            class="form-control @error('Nama') is-invalid @enderror"
                                            placeholder="Masukan Nama" value="{{ auth()->user()->Nama ?? old('Nama') }}">
                                        @error('Nama')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="w-full">
                                        <label for="jabatan">Jabatan</label>
                                        <input disabled id="jabatan" type="jabatan" name="jabatan"
                                            class="form-control @error('jabatan') is-invalid @enderror"
                                            placeholder="Masukan jabatan" value="{{ auth()->user()->JabatanKaryawan->nama_level ?? old('jabatan') }}">
                                        @error('jabatan')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="w-full">
                                        <label for="HP">Nomor Telepon</label>
                                        <input id="HP" type="text" name="HP"
                                            class="form-control @error('HP') is-invalid @enderror"
                                            placeholder="Contoh: 6285269805413" value="{{ auth()->user()->Karyawan->HP ?? old('HP') }}">
                                        @error('HP')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="d-grid mt-4">
                                        <button class="btn btn-primary" type="submit">Update Data</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="/assets/vendor/jquery/jquery.min.js"></script>
    <script>
        $(document).ready(function() {
            // Toggle password visibility
            $('.toggle-btn').on('click', function() {
                let passwordField = $(this).prev('input[type="password"], input[type="text"]');
                let fieldType = passwordField.attr('type') === 'password' ? 'text' : 'password';
                passwordField.attr('type', fieldType);

                let icon = $(this).find('i');
                icon.toggleClass('bi-eye bi-eye-slash');
            });

            // Form navigation
            $('#reset-password-btn').on('click', function() {
                $('.nav-btn').removeClass('active');
                $(this).addClass('active');
                $('.form-container').removeClass('active');
                $('#reset-password-form').addClass('active');
            });

            $('#change-data-btn').on('click', function() {
                $('.nav-btn').removeClass('active');
                $(this).addClass('active');
                $('.form-container').removeClass('active');
                $('#change-data-form').addClass('active');
            });
        });
    </script>
</section>
@endsection
