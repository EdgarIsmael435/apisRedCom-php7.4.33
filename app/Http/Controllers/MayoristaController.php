<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cliente;
use App\Models\ChipPropuesta;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class MayoristaController extends Controller
{
    /**
     * Buscar clientes por mayorista
     */
    public function buscarClientes(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search'            => 'required|string|min:2',
            'codigo_mayorista'  => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $search    = trim($request->query('search'));
        $mayorista = trim($request->query('codigo_mayorista'));

        $clientes = Cliente::where('siStatus', 1)
            ->where('mayorista', $mayorista)
            ->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('apellido', 'like', "%{$search}%");
            })
            ->limit(20)
            ->get(['id', 'nombre', 'apellido']);

        return response()->json([
            'status' => 'success',
            'total'  => $clientes->count(),
            'data'   => $clientes
        ]);
    }

    /**
     * Validar chip para flujo mayorista
     * Se puede validar por ICC o por DN
     * No bloquea, no asigna, no toca simbot
     */
    public function validateChipMayorista(Request $request)
    {
        $request->validate([
            'codigo_mayorista' => 'required|string|max:100',
            'icc'              => 'nullable|string|max:30',
            'dn'               => 'nullable|string|max:30',
        ]);

        $mayorista = trim($request->codigo_mayorista);
        $icc       = $request->filled('icc') ? trim($request->icc) : null;
        $dn        = $request->filled('dn')  ? trim($request->dn)  : null;

        if (!$icc && !$dn) {
            return response()->json([
                'status'  => 'error',
                'code'    => 'ICC_O_DN_REQUERIDO',
                'message' => 'Debes enviar ICC o DN'
            ], 422);
        }

        // 1. Coincidencia exacta
        $by   = 'ICC & DN';
        $chip = ChipPropuesta::where('icc', $icc)->where('dn', $dn)->first();

        // 2. Solo por ICC (DN puede estar mal por OCR)
        if (!$chip) {
            $by   = 'ICC';
            $chip = ChipPropuesta::where('icc', $icc)->first();
        }

        // 3. Solo por DN (ICC puede estar mal por OCR)
        if (!$chip) {
            $by   = 'DN';
            $chip = ChipPropuesta::where('dn', $dn)->first();
        }

        if (!$chip) {
            return response()->json([
                'status'  => 'error',
                'code'    => 'CHIP_NO_EXISTE',
                'message' => 'El chip no existe en el sistema'
            ], 404);
        }

        $error = $this->validarChipMayoristaInterno($chip, $mayorista);
        if ($error) {
            return $error;
        }

        return response()->json([
            'status' => 'success',
            'by'     => $by,
            'data'   => [
                'icc'       => $chip->icc,
                'dn'        => $chip->dn,
                'compania'  => $chip->compania,
                'mayorista' => $chip->responsable,
                'estado'    => 'VALIDO_PARA_ASIGNACION'
            ]
        ]);
    }


    /**
     * Asignar vendedor a un chip
     */
    public function asignarVendedor(Request $request)
    {
        $request->validate([
            'icc'              => 'required|string|max:30',
            'id_cliente'       => 'required|integer',
            'codigo_mayorista' => 'required|string|max:100',
            'reasignar'        => 'sometimes|boolean'
        ]);

        $reasignar = $request->boolean('reasignar', false);

        // Buscar cliente
        $cliente = Cliente::where('id', $request->id_cliente)
            ->where('mayorista', $request->codigo_mayorista)
            ->where('siStatus', 1)
            ->first();

        if (!$cliente) {
            return response()->json([
                'status'  => 'error',
                'code'    => 'CLIENTE_INVALIDO',
                'message' => 'Cliente no existe o no pertenece al mayorista'
            ], 404);
        }

        // Buscar chip
        $chip = ChipPropuesta::where('icc', $request->icc)->first();

        if (!$chip) {
            return response()->json([
                'status'  => 'error',
                'code'    => 'CHIP_NO_EXISTE',
                'message' => 'El chip no existe'
            ], 404);
        }

        /**
         * VALIDACIONES CRITICAS
         * Solo bloqueamos vendedor si NO es reasignacion
         */
        $error = $this->validarChipMayoristaInterno(
            $chip,
            $request->codigo_mayorista,
            $reasignar
        );

        if ($error) {
            return $error;
        }

        $nombreVendedor = strtoupper(
            trim($cliente->nombre)
        );

        $chip->update([
            'vendedor' => $nombreVendedor,
            'fecha_hora_asignacion' => now()
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => $reasignar
                ? 'Vendedor reasignado correctamente'
                : 'Vendedor asignado correctamente',
            'data'    => [
                'icc'        => $chip->icc,
                'vendedor'   => $chip->vendedor,
                'id_cliente' => $cliente->id,
                'reasignado' => $reasignar
            ]
        ]);
    }


    /**
     * Validaciones internas reutilizables
     */
    private function validarChipMayoristaInterno($chip, $mayorista, $permitirReasignacion = false)
    {
        // Verificar que el chip pertenece al mayorista
        if (
            empty($chip->responsable) ||
            strtoupper(trim($chip->responsable)) !== strtoupper($mayorista)
        ) {
            return response()->json([
                'status'  => 'error',
                'code'    => 'CHIP_NO_PERTENECE_MAYORISTA',
                'message' => 'El chip no pertenece al mayorista'
            ], 409);
        }

        // Verificar que no tenga recarga
        if (!empty($chip->folio_recarga) || !empty($chip->fecha_recarga)) {
            return response()->json([
                'status'  => 'error',
                'code'    => 'CHIP_RECARGADO',
                'message' => 'El chip ya tiene recarga registrada'
            ], 409);
        }

        /**
         * CAMBIO IMPORTANTE: Ahora retorna 'warning' en lugar de 'success'
         * Esto es más semántico: el chip YA está asignado, no es un error
         * pero tampoco es un éxito en el contexto de "validar para asignar"
         * 
         * Solo bloqueamos si NO es reasignación
         */
        if (!empty($chip->vendedor) && !$permitirReasignacion) {
            return response()->json([
                'status'  => 'warning',  // Cambiado de 'success' a 'warning'
                'code'    => 'CHIP_YA_ASIGNADO',
                'message' => 'El chip ya tiene vendedor asignado',
                'data' => [
                    'icc'      => $chip->icc,
                    'dn'       => $chip->dn,
                    'vendedor' => $chip->vendedor
                ]
            ], 200);
        }

        // Verificar caducidad
        return $this->checkCaducidadMayorista($chip);
    }


    /**
     * Validar caducidad del chip para mayorista
     */
    private function checkCaducidadMayorista($chip)
    {
        $compania = strtoupper(trim($chip->compania));

        // Determinar días de vigencia según compañía
        switch ($compania) {
            case 'MOVISTAR':
            case 'BAIT':
            case 'ATT':
            case 'UNEFON':
                $diasVigencia = 179;
                break;
            case 'VIRGIN':
                $diasVigencia = 89;
                break;
            case 'TELCEL':
                $diasVigencia = 180;
                break;
            default:
                $diasVigencia = 150;
                break;
        }

        // Misma lógica que validateChipState
        if ($chip->fecha_caducidad) {
            $fechaExpira      = Carbon::parse($chip->fecha_caducidad);
            $fechaBaseMostrar = $chip->fecha_activacion
                ? Carbon::parse($chip->fecha_activacion)->format('d/m/Y')
                : null;
        } else {
            if ($chip->fecha_activacion) {
                $fechaBase = Carbon::parse($chip->fecha_activacion);
            } elseif ($chip->fecha_entrega) {
                $fechaBase = Carbon::parse($chip->fecha_entrega);
            } else {
                return response()->json([
                    'status'  => 'error',
                    'code'    => 'FECHA_ENTREGA_INVALIDA',
                    'message' => 'El chip no tiene fecha válida para calcular vigencia'
                ], 409);
            }

            $fechaExpira      = $fechaBase->copy()->addDays($diasVigencia);
            $fechaBaseMostrar = $fechaBase->format('d/m/Y');
        }

        if ($fechaExpira->lt(now())) {
            return response()->json([
                'status'       => 'error',
                'code'         => 'CHIP_CADUCADO',
                'message'      => "Chip caducado (vigencia {$diasVigencia} días)",
                'fechaBase'    => $fechaBaseMostrar,
                'fechaExpira'  => $fechaExpira->format('d/m/Y')
            ], 410);
        }

        return null;
    }
}
