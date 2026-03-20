<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChipController;
use App\Http\Controllers\MayoristaController;
use App\Http\Middleware\VerifyChipToken;

/*
|--------------------------------------------------------------------------
| Rutas REDi / SIMBOT
|--------------------------------------------------------------------------
*/
Route::get('/getDataChip', [ChipController::class, 'getDataChip']);
Route::post('/updateRecharge', [ChipController::class, 'updateRechargeChip'])->middleware(VerifyChipToken::class);
Route::post('/revertDataSim', [ChipController::class, 'revertDataSim'])->middleware(VerifyChipToken::class);


/*
|--------------------------------------------------------------------------
| Rutas MAYORISTA
|--------------------------------------------------------------------------
*/
Route::get('/mayoristas/buscarClientes', [MayoristaController::class, 'buscarClientes']);
Route::get('/mayoristas/validateChip', [MayoristaController::class, 'validateChipMayorista']);
Route::post('/mayoristas/asignarVendedor', [MayoristaController::class, 'asignarVendedor'])->middleware(VerifyChipToken::class);