<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyChipToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->header('X-CHIP-TOKEN');
        if ($token !== env('CHIP_API_TOKEN')) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Token inválido o no autorizado'
            ], 401);
        }
        return $next($request);
    }
}
