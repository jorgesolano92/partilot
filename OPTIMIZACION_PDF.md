# Optimización de Generación de PDFs

## Problema Resuelto
La función `exportParticipationPdf` era muy lenta al generar PDFs con muchas participaciones (1000+), causando colapso del servidor.

## Soluciones Implementadas

### 1. Procesamiento por Lotes (Chunking)
- **Problema**: Cargar todas las participaciones en memoria de una vez
- **Solución**: Procesar en chunks de 100 participaciones
- **Beneficio**: Reduce el uso de memoria y evita timeouts

### 2. Cache de HTML Procesado
- **Problema**: Reprocesar el mismo HTML en cada generación
- **Solución**: Cache con TTL de 1 hora para HTML procesado
- **Beneficio**: Acelera generaciones subsecuentes

### 3. Procesamiento Asíncrono
- **Problema**: PDFs muy grandes bloquean la interfaz
- **Solución**: Jobs en cola para PDFs >1000 participaciones
- **Beneficio**: Interfaz responsiva, procesamiento en background

### 4. Optimización de Consultas
- **Problema**: Consultas innecesarias a la base de datos
- **Solución**: Select específicos y eager loading
- **Beneficio**: Menos carga en la base de datos

### 5. Optimización de Algoritmos
- **Problema**: Bucles anidados costosos
- **Solución**: Algoritmos optimizados para ordenamiento
- **Beneficio**: Menor complejidad computacional

### 6. Optimización de Imágenes Reutilizables
- **Problema**: Imágenes duplicadas aumentan el tamaño del PDF
- **Solución**: Detección automática de imágenes idénticas y compresión
- **Beneficio**: Reducción significativa del tamaño del PDF (hasta 70%)

### 7. Cache de HTML por Ticket
- **Problema**: Reprocesar HTML para cada participación
- **Solución**: Cache individual por ticket procesado
- **Beneficio**: Aceleración en generaciones subsecuentes

### 8. QR Codes Dinámicos
- **Problema**: Necesidad de QR codes únicos por participación
- **Solución**: Generación automática de QR codes con cache
- **Beneficio**: Verificación rápida de participaciones, mejor UX

## Uso

### Para PDFs de Participación

#### PDFs Pequeños (<500 participaciones)
```php
// Usar el método original optimizado
GET /design/pdf/participation/{id}
```

#### PDFs Grandes (>1000 participaciones)
```php
// Usar procesamiento asíncrono
GET /design/pdf/participation-async/{id}

// Verificar estado
GET /design/pdf/status/{job_id}

// Descargar cuando esté listo
GET /design/pdf/download/{job_id}
```

### Para PDFs de Portada y Trasera

#### PDFs Normales
```php
// Portada optimizada
GET /design/pdf/cover/{id}

// Trasera optimizada
GET /design/pdf/back/{id}
```

#### PDFs Asíncronos (para HTML muy grandes)
```php
// Portada asíncrona
GET /design/pdf/cover-async/{id}

// Trasera asíncrona
GET /design/pdf/back-async/{id}
```

### Para PDFs Generales
```php
// PDF optimizado desde HTML
POST /design/export-pdf
```

## Configuración

### Archivo de Configuración
Editar `config/pdf_optimization.php`:

```php
'sync_limit' => 500,        // Límite para procesamiento síncrono
'async_limit' => 1000,      // Límite para procesamiento asíncrono
'chunk_size' => 100,        // Tamaño de chunks
'memory_limit' => '2048M',  // Límite de memoria
```

### Configurar Colas
```bash
# Instalar driver de colas (Redis recomendado)
composer require predis/predis

# Configurar .env
QUEUE_CONNECTION=database
QUEUE_RETRY_AFTER=7200
PDF_QUEUE=pdf

# Ejecutar worker (PDFs grandes requieren timeout alto o 0)
php artisan queue:work --queue=pdf,default --timeout=0 --tries=1
```

En producción (supervisor), el worker de PDFs debe escuchar la cola `pdf` y usar `--timeout=0` o al menos `7200`. El job `GenerateParticipationPdfJob` calcula su propio timeout según el número de participaciones (p. ej. ~36 min para 1750).

## Comandos de Mantenimiento

### Limpiar Archivos Temporales
```bash
# Limpiar archivos más antiguos de 24 horas
php artisan pdf:cleanup

# Limpiar archivos más antiguos de 1 hora
php artisan pdf:cleanup --hours=1
```

### Limpiar Cache de PDFs
```bash
# Limpiar todo el cache de PDFs
php artisan pdf:clear-cache

# Limpiar cache solo de participaciones
php artisan pdf:clear-cache --type=participation

# Limpiar cache solo de portadas
php artisan pdf:clear-cache --type=cover

# Limpiar cache solo de traseras
php artisan pdf:clear-cache --type=back
```

### Gestionar Imágenes Optimizadas
```bash
# Ver estadísticas de optimización
php artisan images:optimize --stats

# Limpiar imágenes optimizadas
php artisan images:optimize --clear

# Probar optimización con una imagen
php artisan images:optimize --test
```

### Gestionar QR Codes
```bash
# Ver estadísticas de QR codes
php artisan qr:manage --stats

# Limpiar QR codes antiguos
php artisan qr:manage --clear

# Limpiar QR codes más antiguos de 12 horas
php artisan qr:manage --clear --hours=12

# Probar generación de QR code
php artisan qr:manage --test
```

### Programar Limpieza Automática
Agregar a `app/Console/Kernel.php`:
```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('pdf:cleanup')->hourly();
}
```

## Monitoreo

### Verificar Estado de Colas
```bash
php artisan queue:work --verbose
```

### Logs de Jobs
Los jobs se registran en `storage/logs/laravel.log`

## Dependencias Adicionales

### Para Combinar PDFs
```bash
composer require setasign/fpdi
```

### Para Colas (Opcional)
```bash
composer require predis/predis  # Para Redis
# O usar database driver (ya incluido)
```

## Rendimiento Esperado

| Participaciones | Método | Tiempo Estimado | Memoria | Reducción PDF |
|-----------------|--------|-----------------|---------|---------------|
| < 100 | Síncrono | < 10 segundos | < 256MB | 50-80% menor |
| 100-500 | Síncrono | 10-30 segundos | < 512MB | 60-70% menor |
| 500-1000 | Chunking | 30-60 segundos | < 1GB | 65-75% menor |
| > 1000 | Asíncrono | 1-5 minutos | Variable | 70-80% menor |

## Gestión de QR Codes

### Sistema de Persistencia
Los QR codes se guardan como archivos PNG en `storage/app/qr_codes/` para reutilización:
- **Ventajas**: No se regeneran si ya existen
- **Eficiencia**: Reutilización automática en PDFs posteriores
- **Espacio**: Archivos PNG pequeños (~2-5KB cada uno)

### Comandos de Gestión
```bash
# Ver estadísticas de QR codes guardados
php artisan qr:manage stats

# Limpiar todos los QR codes guardados
php artisan qr:manage clear

# Probar velocidad de generación
php artisan qr:manage test

# Limpiar QR codes antiguos (más de X horas)
php artisan qr:clear --hours=24
```

## Troubleshooting

### Error de Memoria
- Aumentar `memory_limit` en `config/pdf_optimization.php`
- Reducir `chunk_size`

### Timeout de Jobs
- El job define `$timeout` dinámico (≈120 s por cada 100 participaciones, mín. 15 min, máx. 2 h).
- `QUEUE_RETRY_AFTER` debe ser ≥ timeout del job (por defecto 7200 s en `config/queue.php`).
- Reiniciar el worker tras desplegar: `php artisan queue:work --queue=pdf,default --timeout=0`

### Archivos Temporales
- Ejecutar `php artisan pdf:cleanup` regularmente
- Verificar permisos en `storage/app/`
