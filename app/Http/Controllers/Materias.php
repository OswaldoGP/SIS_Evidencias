<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\Materia;
use App\Models\Semestre;
use App\Models\AsignacionMateria;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Materias extends Controller
{
    public function index()
    {
        $titulo = 'Materias';

        $semestreActivo = Semestre::where('activo', 1)->first();

        $items = Materia::withCount([
            'asignaciones as asignaciones_activas_count' => function ($q) use ($semestreActivo) {
                if ($semestreActivo) {
                    $q->where('semestre_id', $semestreActivo->id);
                }
                $q->where('activo', 1);
            }
        ])->get();

        return view('modules.materias.index', compact('titulo', 'items'));
    }

    public function create()
    {
        $titulo = 'Crear Materia';
        return view('modules.materias.create', compact('titulo'));
    }

    public function store(Request $request)
    {
        try {
            $item = new Materia();
            $item->nombre = $request->nombre;
            $item->clave = $request->clave;
            $item->unidades = $request->unidades;
            $item->carrera = $request->carrera;
            $item->semestre = $request->semestre;
            $item->num_grupos = 0;
            $item->activo = 0;
            $item->save();

            return to_route('materias')
                ->with('success', 'Materia guardada con éxito!');
        } catch (Exception $e) {
            return to_route('materias')
                ->with('error', $e->getMessage());
        }
    }

    public function edit(string $id)
    {
        $titulo = 'Editar Materia';
        $item = Materia::findOrFail($id);

        return view('modules.materias.edit', compact('item', 'titulo'));
    }

    public function update(Request $request, string $id)
    {
        try {
            $item = Materia::findOrFail($id);

            $item->nombre = $request->nombre;
            $item->clave = $request->clave;
            $item->unidades = $request->unidades;
            $item->carrera = $request->carrera;
            $item->semestre = $request->semestre;

            $item->save();

            return to_route('materias')
                ->with('success', 'Materia actualizada correctamente');
        } catch (Exception $e) {
            return to_route('materias')
                ->with('error', $e->getMessage());
        }
    }

    public function show(string $id)
    {
        $titulo = 'Eliminar Materia';
        $items = Materia::findOrFail($id);

        $semestreActivo = Semestre::where('activo', 1)->first();

        $tieneAsignaciones = false;
        if ($semestreActivo) {
            $tieneAsignaciones = AsignacionMateria::where('materia_id', $items->id)
                ->where('semestre_id', $semestreActivo->id)
                ->exists();
        }

        return view('modules.materias.show', compact('items', 'titulo', 'tieneAsignaciones'));
    }

    public function destroy(string $id)
    {
        try {
            $item = Materia::findOrFail($id);

            $semestreActivo = Semestre::where('activo', 1)->first();

            if ($semestreActivo) {
                $tieneAsignaciones = AsignacionMateria::where('materia_id', $item->id)
                    ->where('semestre_id', $semestreActivo->id)
                    ->exists();

                if ($tieneAsignaciones) {
                    return to_route('materias')
                        ->with('error', 'No se puede eliminar: la materia tiene asignaciones registradas en el semestre activo.');
                }
            }

            $item->semestres()->detach();
            $item->delete();

            return to_route('materias')
                ->with('success', 'Materia eliminada correctamente');
        } catch (Exception $e) {
            return to_route('materias')
                ->with('error', $e->getMessage());
        }
    }

    public function tbody()
    {
        $semestreActivo = Semestre::where('activo', 1)->first();

        $items = Materia::withCount([
            'asignaciones as asignaciones_activas_count' => function ($q) use ($semestreActivo) {
                if ($semestreActivo) {
                    $q->where('semestre_id', $semestreActivo->id);
                }
                $q->where('activo', 1);
            }
        ])->get();

        return view('modules.materias.tbody', compact('items'));
    }

    public function misMaterias()
    {
        $titulo = 'Mis Materias';

        $user = Auth::user();
        $asignaciones = collect();

        $semestreActivo = Semestre::where('activo', 1)->first();

        if (!$semestreActivo) {
            return view('modules.materias.misMaterias', compact(
                'titulo',
                'asignaciones',
                'semestreActivo'
            ));
        }

        $docente = $user->docente;

        if ($docente && $docente->activo) {
            $asignaciones = AsignacionMateria::with([
                'materia',
                'semestre',
            ])
                ->where('docente_id', $docente->id)
                ->where('semestre_id', $semestreActivo->id)
                ->where('activo', 1)
                ->whereHas('materia', function ($q) {
                    $q->where('activo', 1);
                })
                ->get()
                ->sortBy([
                    fn($a, $b) => strcmp($a->materia?->nombre ?? '', $b->materia?->nombre ?? ''),
                    fn($a, $b) => strcmp($a->grupo ?? '', $b->grupo ?? ''),
                ])
                ->values();
        }

        return view('modules.materias.misMaterias', compact(
            'titulo',
            'asignaciones',
            'semestreActivo'
        ));
    }

    public function estado(Request $request)
    {
        try {
            // 1. Quitamos 'min:1' de la validación inicial para que acepte 0 al desactivar
            $request->validate([
                'id'     => 'required|integer|exists:materias,id',
                'estado' => 'required|in:0,1',
                'grupos' => 'nullable|integer|max:20',
            ], [
                'id.required'     => 'El identificador de la materia es obligatorio.',
                'id.exists'       => 'La materia seleccionada no existe.',
                'estado.required' => 'El estado es obligatorio.',
                'estado.in'       => 'El estado debe ser activo o inactivo.',
                'grupos.integer'  => 'El número de grupos debe ser un valor entero.',
                'grupos.max'      => 'No se permiten más de 20 grupos por materia.',
            ]);

            DB::beginTransaction();

            $materia = Materia::findOrFail($request->id);
            $semestreActivo = Semestre::where('activo', 1)->first();

            if (!$semestreActivo) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'mensaje' => 'No hay un semestre activo configurado en el sistema.'
                ]);
            }

            // 2. VALIDACIÓN AL DESACTIVAR (estado === 0)
            if ((int) $request->estado === 0) {
                $tieneAsignaciones = AsignacionMateria::where('materia_id', $materia->id)
                    ->where('semestre_id', $semestreActivo->id)
                    ->exists();

                if ($tieneAsignaciones) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'mensaje' => 'No se puede desactivar: La materia ya tiene grupos o docentes asignados en el semestre activo.'
                    ]);
                }
            }

            // 3. ACTIVAR O DESACTIVAR
            if ((int) $request->estado === 1) {
                $grupos = (int) ($request->grupos ?? 1);
                if ($grupos < 1) $grupos = 1;
                if ($grupos > 20) $grupos = 20;

                $materia->activo = 1;
                $materia->num_grupos = $grupos;
                $materia->save();

                $semestreActivo
                    ->materias()
                    ->syncWithoutDetaching([$materia->id]);
            } else {
                $materia->activo = 0;
                $materia->num_grupos = 0;
                $materia->save();

                $materia
                    ->semestres()
                    ->detach($semestreActivo->id);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'mensaje' => $request->estado
                    ? "Materia activada correctamente con {$materia->num_grupos} grupo(s)."
                    : 'Materia desactivada correctamente.',
                'grupos' => $materia->num_grupos,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'mensaje' => 'Datos inválidos: ' . implode(' ', $e->validator->errors()->all())
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'mensaje' => 'Error en el servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    public function gruposAjax(Request $request)
    {
        try {
            $request->validate([
                'id'     => 'required|integer|exists:materias,id',
                'grupos' => 'required|integer|min:1|max:20',
            ]);

            $materia = Materia::findOrFail($request->id);

            if (!$materia->activo) {
                return response()->json([
                    'success' => false,
                    'mensaje' => 'Activa la materia primero.',
                ], 400);
            }

            $semestreActivo = Semestre::where('activo', 1)->first();

            if (!$semestreActivo) {
                return response()->json([
                    'success' => false,
                    'mensaje' => 'No hay semestre activo.',
                ], 400);
            }

            $asignacionesActivas = AsignacionMateria::where('materia_id', $materia->id)
                ->where('semestre_id', $semestreActivo->id)
                ->where('activo', 1)
                ->count();

            if ((int) $request->grupos < $asignacionesActivas) {
                return response()->json([
                    'success' => false,
                    'mensaje' => "No puedes reducir a {$request->grupos} grupos porque ya hay {$asignacionesActivas} asignaciones activas."
                ], 400);
            }

            $materia->num_grupos = (int) $request->grupos;
            $materia->save();

            return response()->json([
                'success' => true,
                'mensaje' => "Grupos actualizados a {$materia->num_grupos}.",
                'grupos'  => $materia->num_grupos,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'mensaje' => 'Datos inválidos: ' . implode(' ', $e->validator->errors()->all())
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'mensaje' => $e->getMessage()
            ], 500);
        }
    }
}
