<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChipPropuesta extends Model
{
    protected $table = 'chip_propuesta';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'folio_recarga',
        'dn',
        'vendedor',
        'fecha_recarga',
        'monto_recarga',
        'usuario_recarga',
        'usuario_captura',
        'obs_captura',
        'estatus_sim_bot',
        'fecha_hora_consulta_sim_bot'
    ];
}
?>
