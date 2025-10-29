<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Chip;
use Carbon\Carbon;

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
            'statusTkBot',
            'fechaConsultaTkBot'
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

    private function updateStatusTicket($chip)
    {
        Chip::where('id', $chip->id)->update([
            'statusTkBot' => 1,
            'fechaConsultaTkBot' => now()
        ]);
    }

    private function checkCaducidad($chip)
    {
        if (!$chip) return null;
        switch ($chip->compania) {
            case 'MOVISTAR':
                $diasVigencia = 119;
                break;
            case 'BAIT':
                $diasVigencia = 170;
                break;
            case 'ATT':
            case 'UNEFON':
                $diasVigencia = 179;
                break;
            default:
                $diasVigencia = 30;
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
            return response()->json([
                'status' => 'error',
                'used' => true,
                'message' => "Chip ya tiene recarga registrada",
                'data' => ['folio' => $chip->folio, 'fechaRecarga' => $chip->fecha]
            ], 409);
        }
        return null;
    }

    private function checkStatusConsulta($chip)
    {
        if ($chip->statusTkBot == 1 && 2 == 3) {
            return response()->json([
                'status' => 'error',
                'blocked' => true,
                'message' => 'Chip ya fue consultado previamente',
                'lastConsulta' => $chip->fechaConsultaTkBot
            ], 409);
        }
        return null;
    }
}
