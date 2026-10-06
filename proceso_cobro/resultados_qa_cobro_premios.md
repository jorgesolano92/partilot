# Resultados QA — Cobro de premios

| Campo | Valor |
|-------|-------|
| Fecha | 2026-06-19 20:30:50 |
| Entorno | local |
| Comando | `php artisan qa:prize-payments --bootstrap --with-phpunit` |
| Bootstrap | sí |
| Resumen | **20/20 OK**, 0 fallos |
| Estado global | **PASS** |

## Detalle por sección

| ID | Sección | Check | Resultado | Detalle |
|----|---------|-------|-----------|---------|
| A.1 | A | Tabla entity_lottery_prize_settings existe | OK | — |
| A.2 | A | Tabla entity_lottery_prize_activation_logs existe | OK | — |
| A.3 | A | Tabla participation_collection_items existe | OK | — |
| A.4 | A | Columna online_payer en settings | OK | — |
| A.5 | A | Columna wallet_mode en participations | OK | — |
| B.1 | B | lockModeFromDevolution online PARTILOT | OK | Modo online PARTILOT bloqueado en settings |
| B.3 | B | Online sin activar → funds pending | OK | funds_status=pending, online_enabled=0 |
| B.3b | B | Presencial habilitado permite pago físico | OK | — |
| B.C | B | Online entidad (legacy) cobrable sin fondos PARTILOT | OK | Enhorabuena!! Tu participación tiene un premio de 50,00€. |
| B.4.1 | B | GET /api/wallet/participations | OK | HTTP 200 |
| B.4.2 | B | Participación premiada con payment_blocked (sin activación) | OK | block_reason=not_activated |
| B.4.3 | B | GET cobrables excluye bloqueadas | OK | Cobrables: 0 |
| B.4.4 | B | POST /api/wallet/cobro rechaza si bloqueado | OK | HTTP 422: Enhorabuena!! Tu participación tiene un premio de 50,00€. Estamos en contacto con la entidad para habilitar el cobro lo antes posible. |
| B.4.5 | B | POST /api/wallet/donacion rechaza si bloqueado | OK | HTTP 422: Enhorabuena!! Tu participación tiene un premio de 50,00€. Estamos en contacto con la entidad para habilitar el cobro lo antes posible. |
| D.3.1 | D | Nativa digital rechazada en presencial | OK | Las participaciones digitales solo se cobran online. |
| D.3.2 | D | Participación ya cobrada → LOPD en presencial | OK | Esta participación ya ha sido gestionada. |
| ANX.1 | Anexo | GET check → can_digitalize / can_store_in_warehouse | OK | status=can_link |
| ANX.2 | Anexo | POST store-warehouse | OK | Participación guardada en almacén. |
| ANX.3 | Anexo | wallet_mode=storage en BD | OK | wallet_mode=storage |
| ANX.4 | Anexo | Almacén excluido de cobrables | OK | En cobrables: no (bien) |

## PHPUnit (testsuite PrizePayment)

Estado: **PASS**

```
PHPUnit 10.5.46 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.2.12
Configuration: H:\xampp3\htdocs\sipart\phpunit.xml

...........                                                       11 / 11 (100%)

Time: 00:02.638, Memory: 44.00 MB

OK (11 tests, 23 assertions)
```

_Generado automáticamente. Ver `proceso_cobro/guia_pruebas_cobro_premios.md` sección J._
