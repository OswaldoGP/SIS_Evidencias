<?php

namespace App\Http\Controllers;

use App\Models\AsignacionMateria;
use App\Models\Materia;
use App\Models\Semestre;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class Semestres extends Controller
{
    public function index()
    {
        $this->autoActivarSemestre();

        $titulo = 'Semestres';

        $semestres = Semestre::withCount([

            'materias as materias_activas_count' => function ($q) {
                $q->where('materias.activo', 1);
            },

            'materias as materias_asignadas_count' => function ($q) {
                $q->where('materias_semestres.asignada', 1)
                    ->where('materias.activo', 1);
            },

            'materias as materias_por_asignar_count' => function ($q) {
                $q->where('materias_semestres.asignada', 0)
                    ->where('materias.activo', 1);
            }

        ])->get();

        return view('modules.semestres.index', compact(
            'titulo',
            'semestres'
        ));
    }

    private function autoActivarSemestre(): void
    {
        if (Semestre::where('activo', 1)->exists()) {
            return;
        }

        $hoy = now()->toDateString();

        $candidato = Semestre::where('activo', 0)
            ->whereDate('fecha_inicio', '<=', $hoy)
            ->whereDate('fecha_fin', '>=', $hoy)
            ->first();

        if ($candidato) {
            $candidato->update(['activo' => 1]);
        }
    }

    public function create()
    {
        $titulo = 'Crear Semestre';
        $semestres = Semestre::all();
        return view('modules.semestres.create', compact('titulo', 'semestres'));
    }

    public function verificar(Request $request)
    {
        $existe = Semestre::where('anio', $request->anio)
            ->where('periodo', $request->periodo)
            ->exists();

        return response()->json([
            'existe' => $existe
        ]);
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'anio' => 'required|integer',
                'periodo' => 'required|in:1,2',
            ]);

            $existe = Semestre::where('anio', $request->anio)
                ->where('periodo', $request->periodo)
                ->exists();

            if ($existe) {
                return back()->with('error', 'Ya existe un semestre con ese año y periodo');
            }

            $periodoTexto = $request->periodo == 1 ? 'ENE - JUN' : 'JUL - DIC';
            $nombre = $request->anio . '-' . $request->periodo . ' ' . $periodoTexto;

            if ($request->periodo == 1) {
                $fecha_inicio = $request->anio . '-01-01';
                $fecha_fin    = $request->anio . '-06-30';
            } else {
                $fecha_inicio = $request->anio . '-07-01';
                $fecha_fin    = $request->anio . '-12-31';
            }

            Semestre::create([
                'nombre'       => $nombre,
                'anio'         => $request->anio,
                'periodo'      => $request->periodo,
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin'    => $fecha_fin,
            ]);

            return redirect()->route('semestres')->with('success', 'Semestre creado correctamente');
        } catch (Exception $e) {
            return back()->with('error', 'Error al crear el semestre: ' . $e->getMessage());
        }
    }

    public function cambiarEstado($id)
    {
        $semestre = Semestre::findOrFail($id);
        $hoy = now()->toDateString();

        if (!$semestre->activo) {

            if (Semestre::where('activo', 1)->exists()) {
                return response()->json([
                    'success' => false,
                    'confirmar' => false,
                    'error' => 'Ya existe un semestre activo. Desactívalo antes de activar otro.'
                ], 400);
            }

            if ($hoy < $semestre->fecha_inicio || $hoy > $semestre->fecha_fin) {
                return response()->json([
                    'success' => false,
                    'confirmar' => false,
                    'error' => "Solo se puede activar dentro del rango: {$semestre->fecha_inicio} al {$semestre->fecha_fin}."
                ], 400);
            }
        }

        return response()->json([
            'success' => false,
            'confirmar' => true,
            'semestre_id' => $semestre->id,
            'message' => 'Se requiere contraseña para cambiar el estado de este semestre.'
        ]);
    }

    public function cambiarEstadoConfirmar(Request $request, $id)
    {
        try {
            $request->validate([
                'password' => 'required|string'
            ]);

            if (!Hash::check($request->password, Auth::user()->password)) {
                return response()->json([
                    'success' => false,
                    'error' => 'La contraseña es incorrecta'
                ], 401);
            }

            DB::beginTransaction();

            $semestre = Semestre::findOrFail($id);

            $nuevoEstado = $semestre->activo ? 0 : 1;

            if ($nuevoEstado == 1) {

                Semestre::where('activo', 1)->update([
                    'activo' => 0
                ]);

                $semestre->activo = 1;
                $semestre->save();
            } else {

                $semestre->activo = 0;
                $semestre->save();

                $idsMaterias = $semestre->materias()->pluck('materias.id');

                Materia::whereIn('id', $idsMaterias)->update([
                    'activo' => 0
                ]);

                AsignacionMateria::where('semestre_id', $semestre->id)->update([
                    'activo' => 0,
                    'asignada' => 0
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Estado del semestre actualizado correctamente'
            ]);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function cards()
    {
        $items = Semestre::all();
        return view('modules.semestres.cards', compact('items'));
    }

    public function show(string $id)
    {
        $titulo = 'Eliminar Semestre';

        $item = Semestre::select('id', 'nombre', 'anio', 'periodo', 'fecha_inicio', 'fecha_fin', 'activo')
            ->where('id', $id)
            ->firstOrFail();

        return view('modules.semestres.show', compact('titulo', 'item'));
    }

    public function edit(string $id)
    {
        $titulo = 'Editar Semestre';
        $item = Semestre::findOrFail($id);
        $semestres = Semestre::all();

        return view('modules.semestres.edit', compact('titulo', 'item', 'semestres'));
    }

    public function update(Request $request, $id)
    {
        $semestre = Semestre::findOrFail($id);

        $request->validate([
            'nombre' => 'required|string',
            'anio' => 'required|integer',
            'periodo' => 'required|in:1,2',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        DB::beginTransaction();

        try {
            $semestre->nombre = $request->nombre;
            $semestre->anio = $request->anio;
            $semestre->periodo = $request->periodo;
            $semestre->fecha_inicio = $request->fecha_inicio;
            $semestre->fecha_fin = $request->fecha_fin;

            $semestre->activo = $request->has('activo') ? 1 : $semestre->activo;

            $semestre->save();

            if ($semestre->activo == 0) {
                DB::table('materias_semestres')
                    ->where('semestre_id', $semestre->id)
                    ->update([
                        'asignada' => 0,
                        'updated_at' => now()
                    ]);
            }

            DB::commit();

            return redirect()
                ->route('semestres')
                ->with('success', 'Semestre actualizado correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al actualizar: ' . $e->getMessage());
        }
    }

    public function destroy(Request $request, string $id)
    {
        $request->validate([
            'password' => 'required|string'
        ]);

        $user = Auth::user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'La contraseña es incorrecta'], 401);
        }

        try {
            $semestre = Semestre::findOrFail($id);

            $semestre->delete();

            return response()->json([
                'success' => true,
                'message' => 'El semestre ha sido eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'No se pudo eliminar el semestre: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateConfirmar(Request $request, $id)
    {
        $request->validate([
            'password'     => 'required|string',
            'nombre'       => 'required|string',
            'anio'         => 'required|integer',
            'periodo'      => 'required|in:1,2',
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
        ]);

        if (!Hash::check($request->password, Auth::user()->password)) {
            return response()->json([
                'error' => 'La contraseña es incorrecta'
            ], 401);
        }

        DB::beginTransaction();

        try {
            $semestre = Semestre::findOrFail($id);

            $semestre->nombre       = $request->nombre;
            $semestre->anio         = $request->anio;
            $semestre->periodo      = $request->periodo;
            $semestre->fecha_inicio = $request->fecha_inicio;
            $semestre->fecha_fin    = $request->fecha_fin;

            $semestre->save();

            if ($semestre->activo == 0) {
                DB::table('materias_semestres')
                    ->where('semestre_id', $semestre->id)
                    ->update([
                        'asignada'   => 0,
                        'updated_at' => now()
                    ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Semestre actualizado correctamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'No se pudo actualizar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function listarMaterias($id)
    {
        $semestre = Semestre::with('materias')->findOrFail($id);

        return response()->json($semestre->materias);
    }
}
