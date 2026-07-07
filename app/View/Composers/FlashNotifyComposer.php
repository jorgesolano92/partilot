<?php

namespace App\View\Composers;

use Illuminate\Support\ViewErrorBag;
use Illuminate\View\View;

class FlashNotifyComposer
{
    public function compose(View $view): void
    {
        $flashSuccess = session()->pull('success');
        $flashWarning = session()->pull('warning');
        $flashInfo = session()->pull('info');
        $flashError = session()->pull('error');

        $errors = $view->getData()['errors'] ?? null;
        $validationText = null;

        if ($errors instanceof ViewErrorBag && $errors->any()) {
            $validationText = implode("\n", $errors->all());
        }

        if (! $flashError && $validationText) {
            $flashError = $validationText;
            $validationText = null;
        } elseif ($flashError && $validationText && trim($flashError) === trim($validationText)) {
            $validationText = null;
        }

        $view->with([
            'flashSuccess' => $flashSuccess,
            'flashWarning' => $flashWarning,
            'flashInfo' => $flashInfo,
            'flashError' => $flashError,
            'flashValidationErrors' => $validationText,
        ]);
    }
}
