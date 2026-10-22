@extends('layouts.sbadmin')

@section('title', 'Dashboard')

@section('content')
    <h1 class="mt-4">Dashboard</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item active">Dashboard</li>
    </ol>
    <div class="row">
        @can('ver tipo cambio')
            <div class="col-xl-4 col-md-6">
                <div class="card bg-primary text-white mb-4">
                    <div class="card-body">
                        <div class="small text-white-50">Último tipo de cambio registrado</div>
                        @if ($ultimo)
                            <div class="fs-4 fw-bold">{{ number_format((float) $ultimo->CAMBIO, 5, ',', '.') }}</div>
                            <div class="small">Vigencia: {{ $ultimo->FECHA->format('d/m/Y') }}</div>
                        @else
                            <div class="fs-5">Sin registros</div>
                        @endif
                    </div>
                    <div class="card-footer d-flex align-items-center justify-content-between">
                        <a class="small text-white stretched-link" href="{{ route('tipo-cambio.index') }}">Administración → Tipo Cambio</a>
                        <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                    </div>
                </div>
            </div>
        @endcan
    </div>
@endsection
