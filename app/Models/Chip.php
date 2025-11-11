<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chip extends Model
{
    protected $table = 'chip';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'icc',
        'dn',
        'compania',
        'entrega',
        'folio',
        'usuario',
        'fecha',
        'recarga',
        'observaciones',
        'estatus_sim_bot',
        'fecha_consulta_sim_bot'
    ];
}
