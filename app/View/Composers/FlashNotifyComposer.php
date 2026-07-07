<?php

namespace App\View\Composers;

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\View;

class FlashNotifyComposer
{
    public function compose(View $view): void
    {
        $messages = [];

        $this->pushIfPresent($messages, 'success', 'Correcto', session()->pull('success'));
        $this->pushIfPresent($messages, 'notice', 'Aviso', session()->pull('warning'));
        $this->pushIfPresent($messages, 'info', 'Información', session()->pull('info'));
        $this->pushIfPresent($messages, 'error', 'Error', session()->pull('error'));

        $notify = session()->pull('partilot_notify');
        if (is_array($notify) && ! empty($notify['text'])) {
            $messages[] = [
                'type' => (string) ($notify['type'] ?? 'error'),
                'title' => (string) ($notify['title'] ?? 'Aviso'),
                'text' => (string) $notify['text'],
            ];
        }

        $validationText = $this->validationTextFromView($view);
        if ($validationText !== null) {
            $alreadyShown = collect($messages)->contains(
                fn (array $item) => trim((string) ($item['text'] ?? '')) === trim($validationText)
            );
            if (! $alreadyShown) {
                $messages[] = [
                    'type' => 'error',
                    'title' => 'No se pudo guardar',
                    'text' => $validationText,
                ];
            }
        }

        $view->with('partilotPageFlashes', $messages);

        // Compatibilidad con código legado del layout.
        $view->with([
            'flashSuccess' => null,
            'flashWarning' => null,
            'flashInfo' => null,
            'flashError' => null,
            'flashValidationErrors' => null,
        ]);
    }

    private function validationTextFromView(View $view): ?string
    {
        $errors = $view->getData()['errors'] ?? session()->get('errors');

        if ($errors instanceof ViewErrorBag && $errors->any()) {
            return implode("\n", $errors->all());
        }

        if ($errors instanceof MessageBag && $errors->any()) {
            return implode("\n", $errors->all());
        }

        return null;
    }

    /**
     * @param  array<int, array{type: string, title: string, text: string}>  $messages
     */
    private function pushIfPresent(array &$messages, string $type, string $title, mixed $text): void
    {
        if (! is_string($text) || trim($text) === '') {
            return;
        }

        $messages[] = [
            'type' => $type,
            'title' => $title,
            'text' => $text,
        ];
    }
}
