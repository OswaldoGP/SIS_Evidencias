<?php

namespace App\Http\Controllers;

use App\Models\Docente;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class Docentes extends Controller
{
    public function index()
    {
        $titulo = 'Docentes';
        return view('modules.docentes.index', compact('titulo'));
    }

    public function data()
    {
        $query = User::query()
            ->leftJoin('docentes', 'docentes.user_id', '=', 'users.id')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.rol',
                'docentes.celular as celular',
                'docentes.departamento as departamento',
                'docentes.cargo as cargo',
                'docentes.activo as docente_activo',
            ])
            ->orderBy('users.id', 'ASC');

        return DataTables::of($query)

            ->filter(function ($query) {
                $search = request('search.value');

                if (!empty($search)) {
                    $search = strtolower($search);

                    $query->where(function ($q) use ($search) {
                        $q->whereRaw('LOWER(users.name) LIKE ?', ["%{$search}%"])
                            ->orWhereRaw('LOWER(users.email) LIKE ?', ["%{$search}%"])
                            ->orWhereRaw('LOWER(users.rol) LIKE ?', ["%{$search}%"])
                            ->orWhereRaw('LOWER(docentes.celular) LIKE ?', ["%{$search}%"])
                            ->orWhereRaw('LOWER(docentes.departamento) LIKE ?', ["%{$search}%"])
                            ->orWhereRaw('LOWER(docentes.cargo) LIKE ?', ["%{$search}%"]);
                    });
                }
            })

            ->addColumn('nombre', function ($row) {
                return strtoupper($row->name);
            })

            ->editColumn('celular', function ($row) {
                return $row->celular ?: 'Sin número';
            })

            ->addColumn('departamento', function ($row) {
                return $row->departamento ?: 'Sin departamento';
            })

            ->editColumn('cargo', function ($row) {
                $cargo = $row->cargo ?: 'SIN CARGO';

                if ($row->rol === 'admin') {
                    return '<span class="badge bg-danger-subtle text-danger border border-danger">'
                        . strtoupper($cargo) .
                        '</span>';
                }

                if ($row->rol === 'encargado') {
                    return '<span class="badge bg-warning-subtle text-warning border border-warning">'
                        . strtoupper($cargo ?: 'ENCARGADO') .
                        '</span>';
                }

                return '<span class="badge bg-primary-subtle text-primary border border-primary">'
                    . strtoupper($cargo) .
                    '</span>';
            })

            ->editColumn('rol', function ($row) {
                if ($row->rol === 'admin') {
                    return '<span class="badge bg-danger">ADMIN</span>';
                }

                if ($row->rol === 'encargado') {
                    return '<span class="badge bg-warning text-dark">ENCARGADO</span>';
                }

                return '<span class="badge bg-info text-dark">DOCENTE</span>';
            })

            ->addColumn('password_btn', function ($row) {
                return '<button class="btn btn-outline-secondary btn-sm reset-btn" 
                        data-id="' . $row->id . '" 
                        data-rol="' . $row->rol . '">
                        <i class="fa-solid fa-user-lock"></i>
                    </button>';
            })

            ->addColumn('activo_switch', function ($row) {
                $checked = $row->docente_activo ? 'checked' : '';

                return '<div class="form-check form-switch d-flex justify-content-center">
                        <input class="form-check-input cambiar-estado" 
                            type="checkbox" 
                            data-id="' . $row->id . '" 
                            data-rol="' . $row->rol . '" 
                            ' . $checked . '>
                    </div>';
            })

            ->addColumn('editar_btn', function ($row) {
                return '<a href="' . route('docentes.edit', $row->id) . '" 
                        class="btn btn-outline-warning btn-sm">
                        <i class="fa-solid fa-user-pen"></i>
                    </a>';
            })

            ->orderColumn('nombre', 'users.name $1')
            ->orderColumn('email', 'users.email $1')
            ->orderColumn('celular', 'docentes.celular $1')
            ->orderColumn('departamento', 'docentes.departamento $1')
            ->orderColumn('cargo', 'docentes.cargo $1')
            ->orderColumn('rol', 'users.rol $1')

            ->rawColumns(['cargo', 'rol', 'password_btn', 'activo_switch', 'editar_btn'])
            ->make(true);
    }

    public function create()
    {
        $titulo = 'Crear Docente';

        return view('modules.docentes.create', compact('titulo'));
    }

    public function store(Request $request)
    {
        try {
            $usuarioActual = Auth::user();
            $rolActual = strtolower($usuarioActual->rol ?? '');

            // Encargado no puede crear admins ni encargados
            if ($rolActual === 'encargado' && in_array($request->rol, ['admin', 'encargado'], true)) {
                return back()->withInput()->with('error', 'No tienes permiso para crear usuarios con ese rol.');
            }

            $nombre = strtoupper(trim($request->name));
            $apellidoP = strtoupper(trim($request->apellido_p));
            $apellidoM = strtoupper(trim($request->apellido_m));
            $nombreCompleto = trim("$nombre $apellidoP $apellidoM");
            $email = strtolower(trim($request->email));

            if (User::where('email', $email)->exists()) {
                return back()->withInput()->with('error', 'El correo ya existe en el sistema.');
            }

            $partes = explode(' ', strtolower($nombreCompleto));
            $nombreFormateado = '';
            foreach ($partes as $p) {
                $nombreFormateado .= ucfirst($p);
            }
            $passwordTemporal = "Sistema" . $nombreFormateado;

            DB::transaction(function () use ($request, $nombreCompleto, $email, $passwordTemporal) {

                $user = new User();
                $user->name = $nombreCompleto;
                $user->email = $email;
                $user->password = Hash::make($passwordTemporal);
                $user->rol = $request->rol;
                $user->save();

                // Encargado no tiene perfil docente
                if ($request->rol === 'encargado') {
                    $docente = new Docente();
                    $docente->user_id = $user->id;
                    $docente->cargo = 'ENCARGADO';
                    $docente->celular = null;
                    $docente->departamento = null;
                    $docente->activo = 1;
                    $docente->save();
                    return;
                }

                $docente = new Docente();
                $docente->user_id = $user->id;

                if ($request->rol === 'admin') {
                    $docente->cargo = strtoupper($request->cargo ?? 'ADMINISTRADOR');
                    $docente->activo = 1;

                    if ($request->has('perfil_docente')) {
                        $docente->celular = $request->celular;
                        $docente->departamento = $request->dpto;
                    } else {
                        $docente->celular = null;
                        $docente->departamento = null;
                    }
                } else {
                    $docente->celular = $request->celular;
                    $docente->departamento = $request->dpto;
                    $docente->cargo = 'DOCENTE';
                    $docente->activo = 1;
                }

                $docente->save();
            });

            $urlPdf = route('pdf.descargar', [
                'nombre' => (string)$nombreCompleto,
                'email'  => (string)$email,
                'pass'   => (string)$passwordTemporal
            ]);

            return redirect()->route('docentes')
                ->with('success', 'Usuario guardado con éxito!')
                ->with('pdf', $urlPdf);
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Error al registrar: ' . $e->getMessage());
        }
    }

    public function resetPassword(Request $request, $id)
    {
        try {
            $admin = Auth::user();

            if (!Hash::check($request->password, $admin->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contraseña incorrecta'
                ]);
            }

            $user = User::findOrFail($id);

            $rolActual = strtolower($admin->rol ?? '');
            $rolObjetivo = strtolower($user->rol ?? '');

            // Encargado no puede resetear password de admins
            if ($rolObjetivo === 'admin' && $rolActual !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para resetear la contraseña de un administrador.'
                ], 403);
            }

            $partes = explode(' ', strtolower($user->name));
            $nombreFormateado = '';
            foreach ($partes as $p) {
                $nombreFormateado .= ucfirst($p);
            }
            $nuevaPassword = "Sistema" . $nombreFormateado;

            $user->password = Hash::make($nuevaPassword);
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Contraseña restablecida',
                'pdf' => route('pdf.descargar', [
                    'nombre' => $user->name,
                    'email' => $user->email,
                    'pass' => $nuevaPassword
                ])
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function downloadPdf(Request $request)
    {
        $data = [
            'titulo' => 'COMPROBANTE DE CREDENCIALES',
            'nombre' => $request->query('nombre'),
            'email'  => $request->query('email'),
            'pass'   => $request->query('pass'),
            'fecha'  => now()->format('d/m/Y H:i A'),
            'leyenda' => $request->query('leyenda', 'Guarde sus credenciales en un lugar seguro y cambie su contraseña al iniciar sesión.')
        ];

        $pdf = Pdf::loadView('pdfs.comprobante_registro', $data);
        return $pdf->stream("Comprobante_{$request->nombre}.pdf");
    }

    public function tbody()
    {
        $items = User::all();
        return view('modules.docentes.tbody', compact('items'));
    }

    public function estado($id, $estado)
    {
        $docente = Docente::find($id);

        if (!$docente) {
            return response()->json([
                'success' => false,
                'message' => 'Docente no encontrado'
            ], 404);
        }

        $user = User::find($docente->user_id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        $usuarioActual = Auth::user();
        $rolActual = strtolower($usuarioActual->rol ?? '');
        $rolObjetivo = strtolower($user->rol ?? '');

        if ($rolObjetivo === 'admin') {
            if ($rolActual !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo un administrador puede modificar el estado de otro administrador.'
                ], 403);
            }

            if ((int) $estado === 0) {
                $adminsActivos = Docente::whereHas('user', function ($q) {
                    $q->where('rol', 'admin');
                })->where('activo', 1)->count();

                if ($adminsActivos <= 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No puedes desactivar al último administrador activo.'
                    ], 400);
                }
            }
        }

        $docente->activo = $estado;
        $docente->save();

        return response()->json([
            'success' => true,
            'message' => $estado ? 'Usuario activado' : 'Usuario desactivado'
        ]);
    }

    public function edit(string $id)
    {
        $titulo = 'Editar Docente';

        $item = User::with('docente')->findOrFail($id);

        $usuarioActual = Auth::user();
        $rolActual = strtolower($usuarioActual->rol ?? '');
        $rolObjetivo = strtolower($item->rol ?? '');

        // Encargado no puede editar admins
        if ($rolObjetivo === 'admin' && $rolActual !== 'admin') {
            return redirect()
                ->route('docentes')
                ->with('error', 'No tienes permiso para editar a un administrador.');
        }

        return view('modules.docentes.edit', compact('item', 'titulo'));
    }

    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $usuarioActual = Auth::user();
            $rolActual = strtolower($usuarioActual->rol ?? '');
            $rolObjetivo = strtolower($user->rol ?? '');

            // Encargado no puede editar admins
            if ($rolObjetivo === 'admin' && $rolActual !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para modificar a un administrador.'
                ], 403);
            }

            // Encargado no puede asignar roles admin ni encargado
            if ($rolActual === 'encargado' && in_array($request->rol, ['admin', 'encargado'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para asignar ese rol.'
                ], 403);
            }

            $email = strtolower(trim($request->email));

            if (User::where('email', $email)->where('id', '!=', $id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'El correo ya existe en el sistema.'
                ], 422);
            }

            $nombre = strtoupper(trim($request->name));

            if ($request->filled('apellido_p') || $request->filled('apellido_m')) {
                $apellidoP = strtoupper(trim($request->apellido_p));
                $apellidoM = strtoupper(trim($request->apellido_m));
                $nombreCompleto = trim("$nombre $apellidoP $apellidoM");
            } else {
                $nombreCompleto = $nombre;
            }

            $departamento = $request->input('dpto', $request->input('departamento'));

            DB::transaction(function () use ($request, $user, $nombreCompleto, $email, $departamento) {

                $user->name = $nombreCompleto;
                $user->email = $email;
                $user->rol = $request->rol;
                $user->save();

                if ($request->rol === 'encargado') {
                    $docente = $user->docente;

                    if (!$docente) {
                        $docente = new Docente();
                        $docente->user_id = $user->id;
                    }

                    $docente->cargo = 'ENCARGADO';
                    $docente->celular = null;
                    $docente->departamento = null;
                    $docente->activo = 1;
                    $docente->save();

                    return;
                }

                $docente = $user->docente;

                if (!$docente) {
                    $docente = new Docente();
                    $docente->user_id = $user->id;
                    $docente->activo = 1;
                }

                $docente->celular = $request->celular;
                $docente->departamento = $departamento;
                $docente->cargo = ($request->rol == 'admin')
                    ? strtoupper($request->cargo ?? 'ADMINISTRADOR')
                    : 'DOCENTE';

                $docente->save();
            });

            return redirect()->route('docentes')
                ->with('success', 'Usuario actualizado con éxito!');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo actualizar: ' . $e->getMessage()
            ], 500);
        }
    }
}
