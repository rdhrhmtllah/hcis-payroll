@extends('layouts.master')

@section('title', 'Dashboard Super User')

@section('content')
<div class="container mt-5">
    <div class="row">
        <!-- Ticket Masuk -->
        <a href="{{ route('takeTicket') }}" class="col-md-4 mb-3">
            <div>
                <div class="card text-white" style="background-color: #3f51b5; border-radius: 12px;">
                    <div class="card-body d-flex align-items-center justify-content-around">
                        <i class="fas fa-envelope-open-text fa-3x mr-4"></i> <!-- spacing di sini -->
                        <div>
                            <h5 class="card-title mb-0">Ticket Masuk</h5>
                            <p class="card-text display-4 mb-0">{{ $ticket_masuk }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </a>

        <!-- Ticket On Process -->
        <a href="{{ route('displayTicket') }}" class="col-md-4 mb-3">
            <div class="card text-white" style="background-color: #ffc107; border-radius: 12px;">
                <div class="card-body d-flex align-items-center justify-content-around">
                    <i class="fas fa-spinner fa-3x mr-4 fa-spin"></i>
                    <div>
                        <h5 class="card-title mb-0">Ticket On Process</h5>
                        <p class="card-text display-4 mb-0">{{ $ticket_onprosess }}</p>
                    </div>
                </div>
            </div>
        </a>

        <!-- Ticket Selesai -->
        <a href="{{ route('finishTicket') }}" class="col-md-4 mb-3">
            <div class="card text-white" style="background-color: #2e7d32; border-radius: 12px;">
                <div class="card-body d-flex align-items-center justify-content-around">
                    <i class="fas fa-check-circle fa-3x mr-4"></i>
                    <div>
                        <h5 class="card-title mb-0">Ticket Selesai (Reciepent)</h5>
                        <p class="card-text display-4 mb-0">{{ $ticket_selesai }}</p>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>
@endsection