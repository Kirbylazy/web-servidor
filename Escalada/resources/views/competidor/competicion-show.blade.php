{{--
    Vista detallada de competición para el competidor — Flujo de inscripción.

    Esta es la vista más importante para el competidor: aquí puede ver los
    detalles de una competición y completar el proceso de inscripción formal
    (subir licencia + subir pago + confirmar inscripción).

    Recibe datos del controlador (ruta competiciones.show en web.php):
      - $competicion → modelo Competicion con relaciones copa, ubicacion
      - $inscripcion → la inscripción del usuario para esta competición (o null)
      - $licenciaAnual → LicenciaValidacion anual vigente del usuario (o null)
                          Si existe, no necesita subir licencia de nuevo
      - $notifInscripcion → notificación de inscripción no leída para esta competición
                             (InscripcionActualizadaNotification)

    Flujo de inscripción (3 pasos):
      1. Subir licencia federativa → POST a inscripciones.upload_licencia
         (InscripcionController@uploadLicencia)
         - Si tiene licencia anual vigente → se omite este paso
      2. Subir justificante de pago → POST a inscripciones.upload_pago
         (InscripcionController@uploadPago)
      3. Confirmar inscripción → POST a inscripciones.store
         (InscripcionController@store)
         - Solo habilitado si ambos documentos están subidos
         - Cambia el estado de borrador → pendiente

    Estados de inscripción y su efecto en la UI:
      - sin_inscripcion → muestra los 3 pasos completos
      - borrador → muestra los pasos (mismo que sin_inscripcion)
      - pendiente → solo muestra estado, no permite modificar
      - aprobada → alerta verde, no muestra formularios
      - rechazada → alerta roja con motivo, permite reinscripción

    Extiende: layouts/app.blade.php

    Relacionado con:
      - dashboard/competidor.blade.php → grid de competiciones con botones que enlazan aquí
      - InscripcionController → uploadLicencia(), uploadPago(), store()
      - Inscripcion (modelo) → estados, rutas de documentos
      - LicenciaValidacion (modelo) → licencias anuales verificadas por el árbitro
      - InscripcionActualizadaNotification → notificación que se muestra al abrir esta vista
--}}
@extends('layouts.app')

@section('title', $competicion->name)

@section('content')

{{-- ── NOTIFICACIÓN DEL ÁRBITRO ───────────────────────────────────────────
     Si hay una notificación no leída para esta competición, se muestra como
     alerta (verde=aprobada, roja=rechazada con motivo).
──────────────────────────────────────────────────────────────────────── --}}
@if($notifInscripcion)
    @if($notifInscripcion->data['estado'] === 'aprobada')
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong>¡Inscripción aprobada!</strong> Tu documentación para
            <strong>{{ $notifInscripcion->data['competicion'] }}</strong> ha sido verificada y tu inscripción está confirmada.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @else
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Inscripción rechazada.</strong>
            @if(!empty($notifInscripcion->data['motivo']))
                Motivo: {{ $notifInscripcion->data['motivo'] }}
            @endif
            Puedes volver a subir los documentos y reinscribirte.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
@endif

{{-- Mensajes flash y errores de validación --}}
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Cabecera: botón volver + nombre + badge campeonato --}}
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">&larr; Volver al dashboard</a>
    <h3 class="mb-0">{{ $competicion->name }}</h3>
    @if($competicion->campeonato)
        <span class="badge bg-danger">Campeonato</span>
    @endif
</div>

{{-- ── DETALLES DE LA COMPETICIÓN ─────────────────────────────────────────
     Tarjeta con información de la competición: fecha, tipo, provincia, copa,
     ubicación y dirección del rocódromo.
──────────────────────────────────────────────────────────────────────── --}}
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-sm-6 col-lg-3">
                <div class="text-muted small">Fecha</div>
                <div class="fw-semibold">{{ $competicion->fecha_realizacion?->format('d/m/Y H:i') ?? '—' }}</div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="text-muted small">Tipo</div>
                <div><span class="badge bg-secondary">{{ ucfirst($competicion->tipo) }}</span></div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="text-muted small">Provincia</div>
                <div class="fw-semibold">{{ $competicion->provincia }}</div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="text-muted small">Copa</div>
                <div>{{ $competicion->copa?->name ?? '—' }}</div>
            </div>
            {{-- Ubicación y dirección (si hay rocódromo asociado) --}}
            @if($competicion->ubicacion)
                <div class="col-sm-6 col-lg-3">
                    <div class="text-muted small">Ubicación</div>
                    <div>{{ $competicion->ubicacion->name }}</div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="text-muted small">Dirección</div>
                    <div>{{ $competicion->ubicacion->direccion ?? '—' }}</div>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     SECCIÓN DE INSCRIPCIÓN (solo para competidores)
     ══════════════════════════════════════════════════════════════════════
     Solo visible para usuarios con rol 'competidor'.
     Muestra el estado actual de la inscripción y los formularios de upload
     según el estado en que se encuentre.
──────────────────────────────────────────────────────────────────────── --}}
@if(auth()->user()->rol === 'competidor')
    @php
        $esPasada = $competicion->fecha_realizacion?->isPast() ?? false;
        $estado   = $inscripcion?->estado ?? 'sin_inscripcion';
    @endphp

    <div class="card">
        <div class="card-header fw-semibold">Mi inscripción</div>
        <div class="card-body">

            {{-- Alertas de estado según la inscripción --}}
            @if($estado === 'aprobada')
                <div class="alert alert-success mb-4">
                    <strong>Inscripción aprobada.</strong> Estás inscrito en esta competición.
                </div>
            @elseif($estado === 'rechazada')
                <div class="alert alert-danger mb-4">
                    <strong>Inscripción rechazada.</strong>
                    @if($inscripcion->motivo_rechazo)
                        Motivo: {{ $inscripcion->motivo_rechazo }}
                    @endif
                    Vuelve a subir los documentos y reinscríbete.
                </div>
            @elseif($estado === 'pendiente')
                <div class="alert alert-warning mb-4">
                    <strong>Pendiente de revisión.</strong> El árbitro está revisando tu documentación.
                </div>
            @endif

            {{-- Formularios de upload: visibles si la competición no ha pasado
                 o si el estado permite modificación --}}
            @if(!$esPasada || in_array($estado, ['sin_inscripcion', 'borrador', 'rechazada']))

                {{-- ── PASO 1: LICENCIA FEDERATIVA ────────────────────────── --}}
                <h6 class="mb-2">
                    1. Licencia federativa
                    {{-- Badge de estado del paso --}}
                    @if($licenciaAnual)
                        <span class="badge bg-success ms-1">Verificada este año</span>
                    @elseif($inscripcion?->licencia_path)
                        <span class="badge bg-success ms-1">Subida</span>
                    @else
                        <span class="badge bg-secondary ms-1">Pendiente</span>
                    @endif
                </h6>

                @if($licenciaAnual)
                    {{-- Licencia anual vigente: no necesita subirla de nuevo --}}
                    <div class="alert alert-success py-2 mb-3 small">
                        Tu licencia federativa fue verificada en una competición anterior y es válida
                        hasta el <strong>{{ $licenciaAnual->valida_hasta->format('d/m/Y') }}</strong>.
                        No necesitas adjuntarla de nuevo.
                    </div>
                @else
                    {{-- Si ya subió el archivo, mostrar enlace para verlo --}}
                    @if($inscripcion?->licencia_path)
                        <div class="mb-3 d-flex align-items-center gap-2">
                            <span class="text-success small">Archivo subido correctamente.</span>
                            <a href="{{ Storage::url($inscripcion->licencia_path) }}" target="_blank" class="btn btn-sm btn-outline-secondary">Ver</a>
                        </div>
                    @endif

                    {{-- Formulario de upload: solo si no está pendiente ni aprobada --}}
                    @if($estado !== 'pendiente' && $estado !== 'aprobada')
                        <form method="POST" action="{{ route('inscripciones.upload_licencia', $competicion->id) }}"
                              enctype="multipart/form-data" class="mb-4">
                            @csrf
                            <div class="input-group" style="max-width: 480px">
                                <input type="file" name="licencia" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                                <button class="btn btn-outline-primary">Subir licencia</button>
                            </div>
                            <div class="text-muted small mt-1">Formatos: JPG, PNG, PDF — Máx. 5 MB</div>
                        </form>
                    @endif
                @endif

                {{-- ── PASO 2: JUSTIFICANTE DE PAGO ───────────────────────── --}}
                <h6 class="mb-2">
                    2. Justificante de pago
                    @if($inscripcion?->pago_path)
                        <span class="badge bg-success ms-1">Subido</span>
                    @else
                        <span class="badge bg-secondary ms-1">Pendiente</span>
                    @endif
                </h6>

                @if($inscripcion?->pago_path)
                    <div class="mb-3 d-flex align-items-center gap-2">
                        <span class="text-success small">Archivo subido correctamente.</span>
                        <a href="{{ Storage::url($inscripcion->pago_path) }}" target="_blank" class="btn btn-sm btn-outline-secondary">Ver</a>
                    </div>
                @endif

                @if($estado !== 'pendiente' && $estado !== 'aprobada')
                    <form method="POST" action="{{ route('inscripciones.upload_pago', $competicion->id) }}"
                          enctype="multipart/form-data" class="mb-4">
                        @csrf
                        <div class="input-group" style="max-width: 480px">
                            <input type="file" name="pago" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                            <button class="btn btn-outline-primary">Subir justificante</button>
                        </div>
                        <div class="text-muted small mt-1">Formatos: JPG, PNG, PDF — Máx. 5 MB</div>
                    </form>
                @endif

                {{-- ── PASO 3: CONFIRMAR INSCRIPCIÓN ──────────────────────── --}}
                @if($estado !== 'pendiente' && $estado !== 'aprobada')
                    @php
                        // La licencia está OK si tiene anual vigente O si subió el archivo
                        $licenciaOk       = $licenciaAnual || $inscripcion?->licencia_path;
                        // Puede inscribirse solo si ambos documentos están subidos
                        $puedeInscribirse = $licenciaOk && $inscripcion?->pago_path;
                    @endphp
                    <hr>
                    {{-- POST a inscripciones.store: cambia estado a 'pendiente' --}}
                    <form method="POST" action="{{ route('inscripciones.store', $competicion->id) }}">
                        @csrf
                        <button class="btn btn-success btn-lg {{ $puedeInscribirse ? '' : 'disabled' }}"
                                {{ $puedeInscribirse ? '' : 'disabled' }}>
                            {{-- Texto diferente si es reinscripción (tras rechazo) --}}
                            {{ $estado === 'rechazada' ? 'Confirmar reinscripción' : 'Confirmar inscripción' }}
                        </button>
                        {{-- Mensaje informativo si falta documentación --}}
                        @if(!$puedeInscribirse)
                            <div class="text-muted small mt-2">
                                {{ $licenciaOk ? 'Sube el justificante de pago para continuar.' : 'Sube la licencia y el justificante de pago para continuar.' }}
                            </div>
                        @endif
                    </form>
                @endif

            {{-- Casos especiales para competiciones pasadas --}}
            @elseif($esPasada && $estado === 'sin_inscripcion')
                <p class="text-muted">Esta competición ya ha finalizado y no estabas inscrito.</p>
            @elseif($esPasada && $estado === 'aprobada')
                <div class="alert alert-success">Participaste en esta competición.</div>
            @endif

        </div>
    </div>
@endif

@endsection
