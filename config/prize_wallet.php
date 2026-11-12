<?php

return [
  /*
  | Límite máximo por operación de cobro/donación en cartera (SEC-041).
  */
  'max_operation_amount' => (float) env('PRIZE_WALLET_MAX_OPERATION_AMOUNT', 50000),

  /*
  | Límite máximo para generación de un código prepago (SEC-054).
  */
  'max_prepago_code_amount' => (float) env('PRIZE_WALLET_MAX_PREPAGO_CODE_AMOUNT', 10000),

  /*
  | Si el premio por décimo (escrutinio) supera este umbral, el cobro online
  | queda bloqueado y debe gestionarse con la entidad (presencial / banco).
  */
  'max_online_prize_per_decimo' => (float) env('PRIZE_WALLET_MAX_ONLINE_PRIZE_PER_DECIMO', 2000),
];
