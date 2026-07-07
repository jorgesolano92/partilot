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
        $redirect = $redirect->withErrors($errors)->withInput();

        $message = match (true) {
            $errors instanceof Validator => $errors->errors()->first(),
            $errors instanceof MessageBag => $errors->first(),
            default => collect($errors)->flatten()->filter()->first(),
        };

        if (is_string($message) && $message !== '') {
            $redirect->with('error', $message);
        }

        return $redirect;
    }
}
