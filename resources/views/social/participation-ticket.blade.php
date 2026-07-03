<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Participación - SIPART</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .ticket-container {
            max-width: 600px;
            margin: 2rem auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .ticket-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .ticket-body {
            padding: 2rem;
        }
        
        .lottery-info {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .numbers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
            gap: 10px;
            margin: 1rem 0;
        }
        
        .number-box {
            background: #667eea;
            color: white;
            padding: 10px;
            border-radius: 10px;
            text-align: center;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .prize-info {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 10px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .prize-amount {
            font-size: 2rem;
            font-weight: bold;
            color: #155724;
        }
        
        .error-container {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 10px;
            padding: 1rem;
            color: #721c24;
        }
        
        .btn-verify {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .btn-verify:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            color: white;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .status-winner {
            background: #d4edda;
            color: #155724;
        }
        
        .status-no-prize {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .verify-input-group {
            display: flex;
            align-items: stretch;
            max-width: 100%;
            margin: 0 auto;
        }

        .verify-input {
            flex: 1;
            min-width: 0;
            border: 2px solid #dee2e6;
            border-right: none;
            border-radius: 25px 0 0 25px;
            padding: 12px 20px;
            font-size: 1rem;
            line-height: 1.5;
            color: #212529;
            background-color: #fff;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .verify-input::placeholder {
            color: #adb5bd;
        }

        .verify-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }

        .verify-input-group .btn-verify {
            border-radius: 0 25px 25px 0;
            white-space: nowrap;
        }

        @media (max-width: 576px) {
            .verify-input-group {
                flex-direction: column;
                gap: 12px;
            }

            .verify-input {
                border-right: 2px solid #dee2e6;
                border-radius: 25px;
            }

            .verify-input-group .btn-verify {
                border-radius: 25px;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="ticket-container">
            <div class="ticket-header">
                <h1><i class="ri-ticket-line me-2"></i>Verificación de Participación</h1>
                <p class="mb-0">Sistema de Verificación PARTILOT</p>
            </div>
            
            <div class="ticket-body">
                @if($error)
                    <div class="error-container">
                        <h4><i class="ri-error-warning-line me-2"></i>Error</h4>
                        <p>{{ $error }}</p>
                        <a href="{{ url('/comprobar-participaciones') }}" class="btn btn-outline-danger">
                            <i class="ri-refresh-line me-2"></i>Intentar de nuevo
                        </a>
                    </div>
                @elseif($ticket)
                    <div class="lottery-info">
                        <h4><i class="ri-calendar-line me-2"></i>{{ $ticket['lottery']['name'] ?? 'Sorteo' }}</h4>
                         <p class="mb-1"><strong>Fecha del Sorteo:</strong> {{ $ticket['lottery']['draw_date'] ? \Carbon\Carbon::parse($ticket['lottery']['draw_date'])->format('d-m-Y') : 'N/A' }}</p>
                        <p class="mb-0"><strong>Entidad:</strong> {{ $ticket['reserve']['entity']['name'] ?? 'N/A' }}</p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5><i class="ri-hashtag me-2"></i>Números de la Participación</h5>
                             <div class="numbers-grid">
                                 @if(isset($ticket['reserve']['reservation_numbers']))
                                     @foreach($ticket['reserve']['reservation_numbers'] as $number)
                                         <div class="number-box">{{ str_pad($number, 5, '0', STR_PAD_LEFT) }}</div>
                                     @endforeach
                                 @endif
                             </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h5><i class="ri-information-line me-2"></i>Información del Ticket</h5>
                            <p><strong>Referencia:</strong> {{ $ticket['data']['participation_number'] ?? 'N/A' }}</p>
                            <p><strong>Participación:</strong> {{ $ticket['data']['participation_code'] ?? 'N/A' }}</p>
                            <p><strong>Precio:</strong> {{ number_format($ticket['set']['played_amount'] ?? 0, 2) }}€</p>
                        </div>
                    </div>
                    
                    @if($ticket['prize_info'])
                        <div class="prize-info">
                            @if($ticket['prize_info']['has_won'])
                                <div class="text-center">
                                    <h3><i class="ri-trophy-line me-2"></i>¡FELICIDADES!</h3>
                                    <div class="prize-amount">{{ number_format($ticket['prize_info']['prize_amount'], 2) }}€</div>
                                    <p class="mb-2"><strong>Premio por Participación</strong></p>
                                    
                                    @if(isset($ticket['prize_info']['winning_categories']) && is_array($ticket['prize_info']['winning_categories']))
                                        <div class="mt-3">
                                            <h6><strong>Detalle de Premios:</strong></h6>
                                            @php
                                                // Calcular la proporción del premio según lo jugado
                                                $importeJugado = $ticket['set']['played_amount'] ?? 0;
                                                $precioDecimo = $ticket['lottery']['ticket_price'] ?? 6; // Precio del décimo del sorteo
                                                $proporcion = $importeJugado / $precioDecimo; // Proporción (ej: 5/6 = 0.8333)
                                            @endphp
                                            @foreach($ticket['prize_info']['winning_categories'] as $category)
                                                @php
                                                    $premioCompleto = $category['premio_decimo'] ?? 0;
                                                    $premioProporcional = $premioCompleto * $proporcion; // Premio proporcional
                                                @endphp
                                                <div class="row mb-2">
                                                    <div class="col-8 text-start">
                                                        <strong>{{ $category['categoria'] ?? 'Premio' }}</strong>
                                                        <small class="text-muted d-block">({{ number_format($proporcion * 100, 1) }}% del décimo)</small>
                                                    </div>
                                                    <div class="col-4 text-end">
                                                        <strong class="text-success">{{ number_format($premioProporcional, 2) }}€</strong>
                                                        <small class="text-muted d-block">({{ number_format($premioCompleto, 2) }}€)</small>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="mb-0">Categoría: <strong>{{ $ticket['prize_info']['prize_category'] }}</strong></p>
                                    @endif
                                    
                                    <span class="status-badge status-winner">GANADOR</span>
                                </div>
                            @else
                                <div class="text-center">
                                    <h4><i class="ri-information-line me-2"></i>Sin Premio</h4>
                                    <p>Esta participación no ha resultado premiada en este sorteo.</p>
                                    <span class="status-badge status-no-prize">SIN PREMIO</span>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="text-center">
                            <h4><i class="ri-time-line me-2"></i>Resultados Pendientes</h4>
                            <p>Los resultados de este sorteo aún no han sido publicados.</p>
                            <span class="status-badge status-pending">PENDIENTE</span>
                        </div>
                    @endif
                    
                    <div class="text-center mt-4">
                        <a href="{{ url('/comprobar-participaciones') }}" class="btn btn-verify">
                            <i class="ri-refresh-line me-2"></i>Verificar Otra Participación
                        </a>
                    </div>
                @else
                    <div class="text-center">
                        <h4><i class="ri-search-line me-2"></i>Verificar Participación</h4>
                        <p>Ingrese la referencia de su participación para verificar si ha resultado premiada.</p>
                        
                        <form method="GET" action="{{ url('/comprobar-participaciones') }}" class="mt-4">
                            <div class="verify-input-group mb-3">
                                <input type="text" name="ref" class="verify-input"
                                       placeholder="Ingrese la referencia de su participación"
                                       value="{{ request('ref') }}" required autocomplete="off">
                                <button class="btn btn-verify" type="submit">
                                    <i class="ri-search-line me-2"></i>Verificar
                                </button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
