<?php

namespace App\Support;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\MessageBag;

class FormRedirectNotify
{
    /**
     * @param  Validator|MessageBag|array<string, string>  $errors
     */
    public static function withErrors(RedirectResponse $redirect, Validator|MessageBag|array $errors): RedirectResponse
    {
        $message = match (true) {
            $errors instanceof Validator => $errors->errors()->first(),
            $errors instanceof MessageBag => $errors->first(),
            default => collect($errors)->flatten()->filter()->first(),
        };

        $message = is_string($message) ? trim($message) : '';

        return $redirect
            ->withErrors($errors)
            ->withInput()
            ->with('partilot_notify', [
                'type' => 'error',
                'title' => 'No se pudo guardar',
                'text' => $message !== '' ? $message : 'Revisa los datos del formulario.',
            ]);
    }
}
