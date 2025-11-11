<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Chip;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ChipController extends Controller
{
    public function getDataChip(Request $request)
    {
        $iccid = $request->query('ICCID');
        $dn    = $request->query('DN');

        if (!$iccid || !$dn) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Debes enviar ICCID y DN'
            ], 400);
        }

        $query = Chip::select(
            'id',
            'icc',
            'dn',
            'compania',
            'entrega',
            'folio',
            'usuario',
            'fecha',
            'estatus_sim_bot',
            'fecha_consulta_sim_bot'
        );

        // Coincidencia exacta
        $chipExact = (clone $query)->where('icc', $iccid)->where('dn', $dn)->first();
        if ($chipExact) {
            if ($error = $this->checkStatusConsulta($chipExact)) return $error;
            if ($error = $this->checkCaducidad($chipExact)) return $error;
            $this->updateStatusTicket($chipExact);
            return response()->json(['status' => 'success', 'by' => 'ICCID & DN', 'reliability' => 100, 'data' => $chipExact]);
        }

        // Por ICCID
        $chipIcc = (clone $query)->where('icc', $iccid)->first();
        if ($chipIcc) {
            if ($error = $this->checkStatusConsulta($chipIcc)) return $error;
            if ($error = $this->checkCaducidad($chipIcc)) return $error;
            $this->updateStatusTicket($chipIcc);
            return response()->json(['status' => 'warning', 'by' => 'ICCID', 'reliability' => 50, 'message' => 'El DN no coincide', 'data' => $chipIcc]);
        }

        // Por DN
        $chipDn = (clone $query)->where('dn', $dn)->first();
        if ($chipDn) {
            if ($error = $this->checkStatusConsulta($chipDn)) return $error;
            if ($error = $this->checkCaducidad($chipDn)) return $error;
            $this->updateStatusTicket($chipDn);
            return response()->json(['status' => 'warning', 'by' => 'DN', 'reliability' => 50, 'message' => 'El ICCID no coincide', 'data' => $chipDn]);
        }

        return response()->json(['status' => 'error', 'message' => 'Chip no encontrado'], 404);
    }

    public function updateRechargeChip(Request $request)
    {
        // Validar parámetros requeridos
        $request->validate([
            'id'            => 'required|integer|exists:chip_ia,id',
            'recarga'       => 'required|numeric|min:1',
            'fechaRecarga'  => 'required|date',
            'folio'         => 'required|string|max:50',
            'usuario'       => 'required|string|max:100',
            'observaciones' => 'nullable|string|max:255',
        ]);

        try {
            // Buscar chip
            $chip = Chip::find($request->id);

            if (!$chip) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Chip no encontrado'
                ], 404);
            }

            // Actualizar campos
            $chip->update([
                'recarga'       => $request->recarga,
                'fecha'         => \Carbon\Carbon::parse($request->fechaRecarga)->format('Y-m-d H:i:s'),
                'folio'         => $request->folio,
                'usuario'       => $request->usuario,
                'observaciones' => $request->observaciones,
            ]);

            // Confirmar respuesta
            return response()->json([
                'status'  => 'success',
                'message' => 'Chip actualizado correctamente',
                'data'    => $chip
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al actualizar chip',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function revertDataSim(Request $request)
    {
        $iccid = $request->input('iccid');
        $dn    = $request->input('dn');

        if (!$iccid && !$dn) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Debes enviar al menos ICCID o DN'
            ], 400);
        }

        try {
            // Buscar chip por ICCID o DN
            $chipQuery = Chip::query()
                ->where('estatus_sim_bot', 1)
                ->whereNull('folio')
                ->where(function ($q) use ($iccid, $dn) {
                    if ($iccid) $q->orWhere('icc', $iccid);
                    if ($dn)    $q->orWhere('dn', $dn);
                });

            $chip = $chipQuery->first();

            if (!$chip) {
                return response()->json([
                    'status'  => 'no_action',
                    'message' => 'No se encontró chip bloqueado para liberar'
                ]);
            }

            // Liberar chip
            $chip->update([
                'estatus_sim_bot' => null,
                'fecha_consulta_sim_bot' => null
            ]);

            Log::info("Chip liberado automáticamente por API: ICCID {$chip->icc} / DN {$chip->dn}");

            return response()->json([
                'status'  => 'success',
                'message' => 'Chip liberado correctamente',
                'data'    => [
                    'icc' => $chip->icc,
                    'dn'  => $chip->dn
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al liberar chip: ' . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Error al liberar chip',
                'error'   => $e->getMessage()
            ], 500);
        }
    }


    private function updateStatusTicket($chip)
    {
        Chip::where('id', $chip->id)->update([
            'estatus_sim_bot' => 1,
            'fecha_consulta_sim_bot' => now()
        ]);
    }

    private function checkCaducidad($chip)
    {
        if (!$chip) return null;
        switch ($chip->compania) {
            case 'MOVISTAR':
                $diasVigencia = 179;
                break;
            case 'BAIT':
                $diasVigencia = 179;
                break;
            case 'VIRGIN':
                $diasVigencia = 89;
                break;
            case 'TELCEL':
                $diasVigencia = 180;
                break;
            case 'ATT':
            case 'UNEFON':
                $diasVigencia = 179;
                break;
            default:
                $diasVigencia = 150;
                break;
        }
        $fechaEntrega = Carbon::parse($chip->entrega);
        $fechaExpira = $fechaEntrega->copy()->addDays($diasVigencia);
        if ($fechaExpira->lt(now())) {
            return response()->json([
                'status' => 'error',
                'expired' => true,
                'dateDelivery' => $fechaEntrega->format('d/m/Y'),
                'dateExpired' => $fechaExpira->format('d/m/Y'),
                'message' => "Chip caducado (vigencia {$diasVigencia} días)"
            ], 410);
        }
        if (!empty($chip->fecha) || !empty($chip->folio)) {
            $fechaFormateada = null;

            if (!empty($chip->fecha)) {
                $fechaFormateada = \Carbon\Carbon::parse($chip->fecha)->format('d/m/Y');
            }
            return response()->json([
                'status' => 'error',
                'used' => true,
                'message' => "Chip ya tiene recarga registrada",
                'data' => ['folio' => $chip->folio, 'fechaRecarga' => $fechaFormateada]
            ], 409);
        }
        return null;
    }

    private function checkStatusConsulta($chip)
    {
        if ($chip->estatus_sim_bot == 1) {
            return response()->json([
                'status' => 'error',
                'blocked' => true,
                'message' => 'Chip ya fue consultado previamente',
                'lastConsulta' => $chip->fecha_consulta_sim_bot
            ], 409);
        }
        return null;
    }
}
