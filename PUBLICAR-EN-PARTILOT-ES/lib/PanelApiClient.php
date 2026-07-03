<?php

declare(strict_types=1);

final class PanelApiClient
{
    public function __construct(private string $apiUrl)
    {
    }

    /**
     * @return array{success: bool, error: ?string, ticket: ?array, http_status: int}
     */
    public function check(string $ref, ?string $sig): array
    {
        $query = http_build_query(array_filter([
            'ref' => $ref,
            'sig' => $sig,
        ], static fn ($v) => $v !== null && $v !== ''));

        $url = $this->apiUrl . (str_contains($this->apiUrl, '?') ? '&' : '?') . $query;

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 15,
                'header' => "Accept: application/json\r\nUser-Agent: PartilotPublicCheck/1.0\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }

        if ($body === false) {
            return [
                'success' => false,
                'error' => 'No se pudo contactar con el servicio de verificación. Inténtalo más tarde.',
                'ticket' => null,
                'http_status' => $status ?: 502,
            ];
        }

        $data = json_decode($body, true);
        if (! is_array($data)) {
            return [
                'success' => false,
                'error' => 'Respuesta inválida del servicio de verificación.',
                'ticket' => null,
                'http_status' => $status ?: 500,
            ];
        }

        return [
            'success' => (bool) ($data['success'] ?? false),
            'error' => isset($data['error']) ? (string) $data['error'] : null,
            'ticket' => is_array($data['ticket'] ?? null) ? $data['ticket'] : null,
            'http_status' => $status ?: 200,
        ];
    }

    public function isCountableFailure(?string $error): bool
    {
        if ($error === null || trim($error) === '') {
            return false;
        }

        $needles = [
            'No se encontró ninguna participación',
            'No se encontró la participación correspondiente',
            'referencia no es válida',
            'firma del código QR no es válida',
            'requiere firma criptográfica',
            'sin firma no permitido',
        ];

        foreach ($needles as $needle) {
            if (stripos($error, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
