<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    protected $table = 'configuraciones';
    protected $fillable = ['nombre_empresa', 'nit', 'direccion', 'telefono', 'logo', 'margen_activo', 'margen_porcentaje'];
    protected $casts = ['margen_activo' => 'boolean', 'margen_porcentaje' => 'float'];
}
