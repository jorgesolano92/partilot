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
];
