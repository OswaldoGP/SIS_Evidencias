<?php

namespace App\Http\Controllers;

use App\Models\AsignacionMateria;
use App\Models\Materia;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Docente;

class AsignarMaterias extends Controller
{
    public function index()
    {
        $titulo = "Asignar Materias";

        $items = AsignacionMateria::with([
            'materia',
            'docente',
            'semestre',
            'docente.user',
            'evidencias' // Agregado para que puedas validarlo visualmente en tu Blade
        ])
            ->orderBy('id', 'desc')
            ->get();

        return view('modules.asignar-materias.index', compact(
            'titulo',
            'items'
        ));
    }

    public function create()
    {
        $titulo = "Asignar Materia";

        $docentes = Docente::with('user')
            ->join('users', 'docentes.user_id', '=', 'users.id')
            ->where('docentes.activo', 1)
            ->whereIn('users.rol', ['admin', 'docente'])
            ->select('docentes.*')
            ->orderBy('users.name', 'asc')
            ->get();

        $semestreActivo = Semestre::where('activo', 1)->first();

        if ($semestreActivo) {
            $materias = Materia::where('activo', 1)
                ->withCount([
                    'asignaciones as asignaciones_activas_count' => function ($q) use ($semestreActivo) {
                        $q->where('semestre_id', $semestreActivo->id)
                            ->where('activo', 1);
                    }
                ])
                ->orderBy('nombre')
                ->get()
                ->filter(function ($materia) {
                    $numGrupos = (int) ($materia->num_grupos ?? 1);
                    return $materia->asignaciones_activas_count < $numGrupos;
                })
                ->values();
        } else {
            $materias = collect();
        }

        return view('modules.asignar-materias.create', compact(
            'titulo',
            'docentes',
            'materias',
            'semestreActivo'
        ));
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'semestre_id' => 'required|exists:semestres,id',
                'materia_id'  => 'required|exists:materias,id',
                'docente_id'  => 'required|exists:users,id',
                'grupo'       => 'required',
                'letra'       => 'nullable|string|max:1',
                'alumnos'     => 'required|integer|min:1'
            ]);

            $materia = Materia::findOrFail($request->materia_id);
            $numGrupos = (int) ($materia->num_grupos ?? 1);

            $grupoFinal = $request->grupo;

            if ($numGrupos > 1) {
                if (!$request->filled('letra')) {
                    DB::rollBack();
                    return back()->with('error', 'Debes seleccionar una letra de grupo.');
                }
                $grupoFinal = $request->grupo . '-' . strtoupper($request->letra);
            }

            $yaExiste = AsignacionMateria::where('semestre_id', $request->semestre_id)
                ->where('materia_id', $request->materia_id)
                ->where('grupo', $grupoFinal)
                ->first();

            if ($yaExiste) {
                DB::rollBack();

                if (!$yaExiste->activo) {
                    return back()->with('warning_existente', 'Existe una asignación inactiva para el grupo ' . $grupoFinal . '. Por favor, actívala desde la lista principal.');
                }
                return back()->with('error', 'El grupo ' . $grupoFinal . ' ya está asignado y activo para esta materia.');
            }

            AsignacionMateria::create([
                'semestre_id' => $request->semestre_id,
                'materia_id'  => $request->materia_id,
                'docente_id'  => $request->docente_id,
                'grupo'       => $grupoFinal,
                'alumnos'     => $request->alumnos,
                'activo'      => 1
            ]);

            DB::table('materias_semestres')
                ->where('semestre_id', $request->semestre_id)
                ->where('materia_id', $request->materia_id)
                ->update([
                    'asignada' => 1,
                    'updated_at' => now()
                ]);

            DB::commit();

            return redirect()->route('asignar-materias')
                ->with('success', 'Grupo ' . $grupoFinal . ' asignado correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    public function gruposDisponibles(Request $request)
    {
        try {
            $request->validate([
                'materia_id'  => 'required|exists:materias,id',
                'semestre_id' => 'required|exists:semestres,id',
            ]);

            $materia = Materia::findOrFail($request->materia_id);
            $numGrupos = (int) ($materia->num_grupos ?? 1);

            $base = $this->calcularBaseGrupo($materia);

            $gruposOcupados = AsignacionMateria::where('materia_id', $materia->id)
                ->where('semestre_id', $request->semestre_id)
                ->where('activo', 1)
                ->pluck('grupo')
                ->toArray();

            $abecedario = range('A', 'Z');
            $disponibles = [];

            if ($numGrupos <= 1) {

                if (!in_array($base, $gruposOcupados, true)) {
                    $disponibles[] = $base;
                }
            } else {

                for ($i = 0; $i < $numGrupos; $i++) {
                    $grupo = $base . '-' . $abecedario[$i];

                    if (!in_array($grupo, $gruposOcupados, true)) {
                        $disponibles[] = $grupo;
                    }
                }
            }

            return response()->json([
                'success'     => true,
                'grupos'      => $disponibles,
                'base'        => $base,
                'num_grupos'  => $numGrupos,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'mensaje' => $e->getMessage(),
            ], 500);
        }
    }

    public function edit($id)
    {
        $titulo = "Editar Asignación";
        
        $item = AsignacionMateria::with('evidencias')->findOrFail($id);

        // Validación: No permitir editar si ya hay evidencias
        if ($item->evidencias->count() > 0) {
            return redirect()->route('asignar-materias')
                ->with('error', 'No puedes editar esta asignación porque el docente ya ha subido evidencias.');
        }

        $docentes = Docente::with('user')
            ->join('users', 'docentes.user_id', '=', 'users.id')
            ->where('docentes.activo', 1)
            ->whereIn('users.rol', ['admin', 'docente'])
            ->select('docentes.*')
            ->orderBy('users.name', 'asc')
            ->get();

        return view('modules.asignar-materias.edit', compact(
            'titulo',
            'item',
            'docentes'
        ));
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'docente_id' => 'required|exists:users,id'
            ]);

            $item = AsignacionMateria::findOrFail($id);
            $item->alumnos = $request->alumnos;
            $item->docente_id = $request->docente_id;
            $item->save();

            return redirect()->route('asignar-materias')
                ->with('success', 'Asignación actualizada correctamente');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function show($id)
    {
        $titulo = "Eliminar Asignación";

        $item = AsignacionMateria::with([
            'materia',
            'semestre',
            'evidencias'
        ])->findOrFail($id);
        if ($item->evidencias->count() > 0) {
            return redirect()->route('asignar-materias')
                ->with('error', 'No puedes eliminar esta asignación porque el docente ya ha subido evidencias.');
        }

        return view('modules.asignar-materias.show', compact(
            'titulo',
            'item'
        ));
    }

    public function destroy(Request $request, $id)
    {
        try {
            if (!Hash::check($request->password, Auth::user()->password)) {
                return response()->json([
                    'error' => 'Contraseña incorrecta'
                ], 401);
            }

            $item = AsignacionMateria::findOrFail($id);
            if ($item->evidencias()->exists()) {
                return response()->json([
                    'error' => 'No puedes eliminar esta asignación porque el docente ya ha subido evidencias.'
                ], 403);
            }

            DB::beginTransaction();

            DB::table('materias_semestres')
                ->where('semestre_id', $item->semestre_id)
                ->where('materia_id', $item->materia_id)
                ->update([
                    'asignada' => 0,
                    'updated_at' => now()
                ]);

            $item->delete();

            DB::commit();

            return response()->json([
                'success' => true
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function estado(Request $request)
    {
        try {
            $request->validate([
                'id'     => 'required|integer|exists:asignacion_materias,id',
                'estado' => 'required|in:0,1',
            ]);

            $item = AsignacionMateria::with(['materia', 'docente', 'evidencias'])->findOrFail($request->id);
            $estado = (int) $request->estado;
            
            if ($estado === 0) {
                if ($item->evidencias()->exists()) {
                    return response()->json([
                        'success' => false,
                        'mensaje' => 'No se puede desactivar: Esta asignación ya cuenta con evidencias registradas.'
                    ]);
                }
            }
            if ($estado === 1) {
                if (!$item->materia || $item->materia->activo == 0) {
                    return response()->json([
                        'success' => false,
                        'mensaje' => 'No se puede activar: La materia asociada está desactivada.'
                    ]);
                }

                if (!$item->docente || $item->docente->activo == 0) {
                    return response()->json([
                        'success' => false,
                        'mensaje' => 'No se puede activar: El docente asociado está desactivado.'
                    ]);
                }

                $activas = AsignacionMateria::where('materia_id', $item->materia_id)
                    ->where('semestre_id', $item->semestre_id)
                    ->where('activo', 1)
                    ->where('id', '!=', $item->id)
                    ->count();

                $numGrupos = (int) ($item->materia->num_grupos ?? 1);

                if ($activas >= $numGrupos) {
                    return response()->json([
                        'success' => false,
                        'mensaje' => "No puedes activar más grupos. La materia permite {$numGrupos} grupo(s)."
                    ]);
                }
            }

            $item->activo = $estado;
            $item->save();

            return response()->json([
                'success' => true,
                'mensaje' => $estado ? 'Asignación activada correctamente' : 'Asignación desactivada correctamente'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'mensaje' => 'Datos inválidos: ' . implode(' ', $e->validator->errors()->all())
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'mensaje' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function tbody()
    {
        $items = AsignacionMateria::with([
            'materia',
            'docente',
            'semestre',
            'evidencias' 
        ])->get();

        return view('modules.asignar-materias.tbody', compact('items'));
    }

    private function calcularBaseGrupo(Materia $materia)
    {
        $siglas = [
            'INGENIERIA EN SISTEMAS COMPUTACIONALES' => 'SIS',
            'INGENIERIA INDUSTRIAL' => 'IG',
            'INGENIERIA EN GESTION EMPRESARIAL' => 'IGE',
            'INGENIERIA EN GESTIÓN EMPRESARIAL' => 'IGE',
            'LICENCIATURA EN TURISMO' => 'LT',
        ];

        $carrera = $materia->carrera;
        if ($carrera) {
            $carrera = mb_strtoupper($carrera, 'UTF-8');
            $carrera = iconv('UTF-8', 'ASCII//TRANSLIT', $carrera);
            $carrera = trim($carrera);
        }

        $sigla = $siglas[$carrera] ?? 'GEN';

        return $sigla . '-' . $materia->semestre;
    }
}