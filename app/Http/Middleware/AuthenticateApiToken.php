<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autenticación por token cifrado (sin tabla personal_access_tokens).
 * El token contiene user_id y expiración, firmado con APP_KEY.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token no proporcionado.'
            ], 401);
        }

        try {
            $data = Crypt::decrypt($token);

            if (!is_array($data) || empty($data['user_id'])) {
                throw new \Exception('Token inválido');
            }

            if (!empty($data['exp']) && $data['exp'] < time()) {
                return response()->json([
                    'success' => false,
                'message' => 'Token expirado.'
                ], 401);
            }

            $user = User::find($data['user_id']);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado.'
                ], 401);
            }

            if ($user->deletion_requested_at || $user->deletion_status === 'executed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta cuenta está desactivada por solicitud de baja.',
                ], 401);
            }

            auth()->setUser($user);
            $request->setUserResolver(fn () => $user);

            return $next($request);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token inválido o corrupto.'
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token inválido.'
            ], 401);
        }
    }
}
