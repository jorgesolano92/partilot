<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Vendedor - Partilot</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #007bff;
        }
        .header h1 {
            color: #007bff;
            margin: 0;
        }
        .content {
            margin-bottom: 30px;
        }
        .content p {
            margin-bottom: 15px;
        }
        .buttons {
            text-align: center;
            margin: 30px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            margin: 10px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .btn-accept {
            background-color: #28a745;
            color: #ffffff;
        }
        .btn-accept:hover {
            background-color: #218838;
        }
        .btn-reject {
            background-color: #dc3545;
            color: #ffffff;
        }
        .btn-reject:hover {
            background-color: #c82333;
        }
        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎯 Solicitud de Vendedor - Partilot</h1>
        </div>
        
        <div class="content">
            <p>Hola,</p>
            
            <p><strong>{{ $seller->entities->first()->name ?? 'Una entidad' }}</strong> te invita a colaborar como <strong>vendedor</strong> en la plataforma Partilot.</p>
            
            <div class="info-box">
                <p><strong>Antes de aceptar, lee las responsabilidades del cargo:</strong></p>
                <ul>
                    @foreach(config('legal_roles.vendedor.summary_bullets', []) as $bullet)
                        <li>{{ $bullet }}</li>
                    @endforeach
                </ul>
            </div>
            
            <p>Para completar el proceso, necesitas <strong>aceptar o rechazar</strong> esta solicitud:</p>
        </div>
        
        <div class="buttons">
            <a href="{{ $acceptUrl }}" class="btn btn-accept">Ver invitación y decidir</a>
            <a href="{{ $rejectUrl }}" class="btn btn-reject">Rechazar</a>
        </div>
        
        <div class="content">
            <p><em>Si no reconoces esta invitación, puedes ignorar este correo o rechazarla directamente.</em></p>
        </div>
        
        <div class="footer">
            <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
            <p>&copy; {{ date('Y') }} Partilot. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
