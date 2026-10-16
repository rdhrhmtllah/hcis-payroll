@extends('layouts.master')
@push('vueapp')
      @vite('resources/js/app.js')
      @inertiaHead
@endpush
@section('content')
@inertia
@endsection