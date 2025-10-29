<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chip extends Model
{
    protected $table = 'chip_ia';
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
        'statusTkBot',
        'fechaConsultaTkBot'
    ];
}
