<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Materia extends Model
{
    use Searchable;

    protected $table = 'materias';

    protected $fillable = [
        'nombre',
        'clave',
        'unidades',
        'semestre',
        'num_grupos',
        'carrera',
        'activo',
    ];

    public function semestres()
    {
        return $this->belongsToMany(Semestre::class, 'materias_semestres')
            ->withPivot('asignada')
            ->withTimestamps();
    }

    public function asignaciones()
    {
        return $this->hasMany(AsignacionMateria::class, 'materia_id');
    }

    public function evidencias()
    {
        return $this->hasMany(Evidencia::class, 'materia_id');
    }

    public function toSearchableArray()
    {
        return [
            'nombre' => $this->nombre,
            'clave' => $this->clave,
            'carrera' => $this->carrera,
            'semestre' => $this->semestre,
        ];
    }
}
