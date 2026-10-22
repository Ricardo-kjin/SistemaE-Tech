@extends('layouts.sbadmin')

@section('title', 'Tipo Cambio')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-4">
        <h1 class="mb-0">Tipo Cambio</h1>
        @can('actualizar tipo cambio')
            <form method="POST" action="{{ route('tipo-cambio.actualizar') }}" id="form-actualizar">
                @csrf
                <button type="submit" class="btn btn-primary" id="btn-actualizar">
                    <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                    <span class="icono"><i class="fas fa-sync-alt me-1"></i></span>
                    <span class="texto">Actualizar</span>
                </button>
            </form>
        @endcan
    </div>
    <ol class="breadcrumb mb-4 mt-2">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li class="breadcrumb-item">Administración</li>
        <li class="breadcrumb-item active">Tipo Cambio</li>
    </ol>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            Tipo de cambio oficial del Banco Central de Bolivia
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Moneda</th>
                            <th class="text-end">Tipo de cambio</th>
                            <th>Fuente</th>
                            <th>Fecha de registro</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($registros as $registro)
                            <tr>
                                <td class="text-nowrap">{{ $registro->fecha->format('d/m/Y') }}</td>
                                <td>{{ $registro->moneda }}</td>
                                <td class="text-end text-nowrap">{{ number_format((float) $registro->tipo_cambio, 5, ',', '.') }}</td>
                                <td>{{ $registro->fuente }}</td>
                                <td class="text-nowrap">{{ $registro->fecha_registro->format('d/m/Y H:i:s') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No hay registros de tipo de cambio.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($registros->hasPages())
                <div class="mt-3">
                    {{ $registros->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('form-actualizar')?.addEventListener('submit', function (evento) {
            const boton = document.getElementById('btn-actualizar');
            if (boton.disabled) {
                evento.preventDefault();
                return;
            }
            boton.disabled = true;
            boton.querySelector('.spinner-border').classList.remove('d-none');
            boton.querySelector('.icono').classList.add('d-none');
            boton.querySelector('.texto').textContent = 'Consultando BCB...';
        });
    </script>
@endpush
