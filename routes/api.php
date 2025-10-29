<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChipController;
use App\Http\Middleware\VerifyChipToken;

Route::post('/updateRecharge', [ChipController::class, 'updateRechargeChip'])->middleware(VerifyChipToken::class);

Route::get('/getDataChip', [ChipController::class, 'getDataChip']);
