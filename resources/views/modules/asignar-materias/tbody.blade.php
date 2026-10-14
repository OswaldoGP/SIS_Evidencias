@forelse ($items as $item)
    @php
        $tieneEvidencias = $item->evidencias->count() > 0;
    @endphp
    <tr>
        <td>{{ $item->semestre->nombre ?? '—' }}</td>
        <td>{{ $item->materia->nombre ?? '—' }}</td>
        <td>{{ $item->docente->user->name ?? 'Sin docente' }}</td>
        <td>{{ $item->grupo }}</td>
        <td>{{ $item->alumnos }}</td>
        <td>
            <div class="form-check form-switch d-flex justify-content-center">
                <input class="form-check-input chkToggle" type="checkbox" data-id="{{ $item->id }}"
                    {{ $item->activo ? 'checked' : '' }} 
                    {{ $tieneEvidencias ? 'disabled' : '' }}
                    @if($tieneEvidencias) data-bs-toggle="tooltip" title="No se puede desactivar porque tiene evidencias" @endif>
            </div>
        </td>
        <td>
            <div class="d-flex justify-content-center gap-2">
                @if($tieneEvidencias)
                    <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" title="Bloqueado: El docente ya subió evidencias">
                        <button class="btn btn-outline-secondary btn-sm" disabled style="opacity: 0.5; cursor: not-allowed;">
                            <i class="bi bi-pencil"></i>
                        </button>
                    </span>
                    <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" title="Bloqueado: El docente ya subió evidencias">
                        <button class="btn btn-outline-secondary btn-sm" disabled style="opacity: 0.5; cursor: not-allowed;">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </span>
                @else
                    <a href="{{ route('asignar-materias.edit', $item->id) }}" class="btn btn-outline-warning btn-sm">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <a href="{{ route('asignar-materias.show', $item->id) }}" class="btn btn-outline-danger btn-sm">
                        <i class="fa-solid fa-trash-can"></i>
                    </a>
                @endif
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="7" class="text-center">
            <strong>Sin registros</strong>
        </td>
    </tr>
@endforelse