<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\BackController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ParticipationController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\LotteryController;
use App\Http\Controllers\DevolutionsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\EntityLotteryPrizeSettingsController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\ScrutinyController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\BackgroundTaskController;
use App\Http\Controllers\ReserveController;
use App\Http\Controllers\SetController;
use App\Http\Controllers\LegalApiController;
use App\Http\Controllers\AccountDeletionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


Route::get('test', [ApiController::class,'test']);

Route::prefix('legal')->group(function () {
    Route::get('/config', [LegalApiController::class, 'config']);
    Route::get('/documents', [LegalApiController::class, 'documents']);
    Route::get('/documents/{slug}', [LegalApiController::class, 'document']);
    Route::get('/cookies/status', [LegalApiController::class, 'cookieStatus']);
    Route::post('/cookies', [LegalApiController::class, 'storeCookieConsent']);
    Route::middleware('auth.api')->group(function () {
        Route::get('/pending-acceptances', [LegalApiController::class, 'pendingAcceptances']);
        Route::get('/role-invitations/{key}', [LegalApiController::class, 'showRoleInvitation']);
        Route::post('/role-invitations/{key}/respond', [LegalApiController::class, 'respondRoleInvitation']);
    });
});

Route::middleware('auth.api')->prefix('account')->group(function () {
    Route::get('/deletion/status', [AccountDeletionController::class, 'status']);
    Route::post('/deletion/request', [AccountDeletionController::class, 'request']);
});

Route::get('/check-delete/{type}/{id}', [ApiController::class, 'checkDelete']);
Route::delete('/delete/{type}/{id}', [ApiController::class, 'deleteItem']);

Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])->name('api.stripe.webhook');

// ============================================================================
// RUTAS PÚBLICAS (Sin autenticación)
// ============================================================================

// Verificar participación por referencia (pública)
Route::get('/participation/check', [ApiController::class, 'checkParticipation']);
Route::get('/participation-ticket', [ApiController::class, 'showParticipationTicket']);
Route::get('/public/participation-check', [ApiController::class, 'publicParticipationCheckJson']);

// Configuración de Firebase (pública para inicialización)
Route::get('/notifications/firebase-config', [NotificationController::class, 'getFirebaseConfig']);

// Resultados de lotería (públicos, para pestaña Sorteos en app)
Route::get('/lottery/results', [LotteryController::class, 'apiGetAllResults']);

// Comprobar número (app móvil: escrutinio de un número)
Route::post('/scrutiny/generate', [ScrutinyController::class, 'generateScrutiny']);

// ============================================================================
// RUTAS DE AUTENTICACIÓN
// ============================================================================

Route::prefix('auth')->group(function () {
    // Login vendedor (solo cuentas con rol seller)
    Route::post('/login', [AuthController::class, 'apiLogin']);
    // Login usuario (solo cuentas con rol client; rechaza seller y gestores)
    Route::post('/login-usuario', [AuthController::class, 'apiLoginUsuario']);
    
    // Registro (si aplica)
    Route::post('/register', [AuthController::class, 'apiRegister']);
    Route::get('/sms/config', [\App\Http\Controllers\PhoneVerificationController::class, 'config']);
    Route::post('/sms/send-code', [\App\Http\Controllers\PhoneVerificationController::class, 'sendCode'])
        ->middleware('throttle:6,1');
    
    // Obtener usuario autenticado
    Route::middleware('auth.api')->get('/user', function (Request $request) {
        return response()->json([
            'user' => $request->user()
        ]);
    });
    
    // Logout
    Route::middleware('auth.api')->post('/logout', [AuthController::class, 'apiLogout']);
    
    // Refresh token
    Route::middleware('auth.api')->post('/refresh', [AuthController::class, 'apiRefresh']);
    
    // Verificar token
    Route::middleware('auth.api')->get('/verify', function (Request $request) {
        return response()->json(['valid' => true, 'user' => $request->user()]);
    });
});

// ============================================================================
// RUTAS PROTEGIDAS (Requieren autenticación)
// ============================================================================

Route::middleware('auth.api')->group(function () {
    Route::prefix('background-tasks')->group(function () {
        Route::get('/', [BackgroundTaskController::class, 'index']);
        Route::post('/', [BackgroundTaskController::class, 'store']);
        Route::get('/{uuid}', [BackgroundTaskController::class, 'show']);
        Route::post('/{uuid}/cancel', [BackgroundTaskController::class, 'cancel']);
    });
    
    // ========================================================================
    // PERFIL Y USUARIO
    // ========================================================================
    Route::prefix('profile')->group(function () {
        Route::get('/', [UserController::class, 'apiGetProfile']);
        Route::put('/', [UserController::class, 'apiUpdateProfile']);
        Route::post('/change-password', [UserController::class, 'apiChangePassword']);
        Route::post('/upload-avatar', [UserController::class, 'apiUploadAvatar']);
    });
    
    // ========================================================================
    // PARTICIPACIONES
    // ========================================================================
    Route::prefix('participations')->group(function () {
        // Listar participaciones
        Route::get('/', [ParticipationController::class, 'apiIndex']);
        
        // Obtener participación específica
        Route::get('/{id}', [ParticipationController::class, 'apiShow']);
        
        // Crear/Asignar participación
        Route::post('/', [ParticipationController::class, 'apiStore']);
        
        // Vender participación
        Route::post('/{id}/sell', [ParticipationController::class, 'apiSell']);
        
        // Digitalizar participación (escanear QR)
        Route::post('/digitalize', [ParticipationController::class, 'apiDigitalize']);
        
        // Regalar participación
        Route::post('/{id}/gift', [ParticipationController::class, 'apiGift']);
        
        // Obtener participaciones por vendedor
        Route::get('/seller/{sellerId}', [ParticipationController::class, 'apiGetBySeller']);
        
        // Obtener participaciones por set/libro
        Route::get('/set/{setId}/book/{bookNumber}', [ParticipationController::class, 'getBookParticipations']);
        
        // Historial de participación
        Route::get('/{id}/history', [ParticipationController::class, 'apiGetHistory']);
        
        // Buscar participación por código/referencia
        Route::get('/search/{code}', [ParticipationController::class, 'apiSearch']);
    });
    
    // Verificar si existe un usuario por email (para venta digital)
    Route::post('/users/check-exists', [UserController::class, 'apiCheckUserExists']);

    // ========================================================================
    // VENTAS
    // ========================================================================
    Route::prefix('sales')->group(function () {
        // Venta por QR
        Route::post('/qr', [ParticipationController::class, 'apiSellByQr']);
        
        // Venta manual
        Route::post('/manual', [ParticipationController::class, 'apiSellManual']);

        // Venta digital (usuario existente) o pendiente (email no registrado + invitación)
        Route::post('/digital', [ParticipationController::class, 'apiSellDigital']);
        Route::post('/digital/pending', [ParticipationController::class, 'apiSellDigitalPending']);
        Route::post('/digital/pending/{pendingId}/notify', [ParticipationController::class, 'apiSendPendingDigitalNotify']);
        Route::post('/digital/pending/{pendingId}/resend-email', [ParticipationController::class, 'apiResendPendingDigitalEmail']);
        Route::get('/digital/pending/{pendingId}/whatsapp-link', [ParticipationController::class, 'apiGetPendingDigitalWhatsAppLink']);
        Route::post('/digital/pending/{pendingId}/whatsapp', [ParticipationController::class, 'apiSendPendingDigitalWhatsApp']);

        // Historial de ventas del vendedor autenticado (para app móvil)
        Route::get('/me', [ParticipationController::class, 'apiGetMySales']);
        Route::get('/whatsapp/config', [ParticipationController::class, 'apiWhatsAppConfig']);
        Route::get('/notify/config', [ParticipationController::class, 'apiWhatsAppConfig']);
        
        // Obtener ventas del vendedor (por ID)
        Route::get('/seller/{sellerId}', [ParticipationController::class, 'apiGetSalesBySeller']);
        
        // Estadísticas de ventas
        Route::get('/stats', [ParticipationController::class, 'apiGetSalesStats']);
    });
    
    // ========================================================================
    // VENDEDORES
    // ========================================================================
    Route::prefix('sellers')->group(function () {
        // Reservas y sets del vendedor autenticado (para app móvil)
        Route::get('/me/reserves', [SellerController::class, 'apiGetMyReserves']);
        Route::get('/me/digital-available', [SellerController::class, 'apiGetTotalDigitalAvailable']);
        Route::post('/me/validate-sale', [SellerController::class, 'apiValidateSale']);
        
        // Participaciones asignadas del vendedor autenticado
        Route::get('/me/entities', [SellerController::class, 'apiGetMyEntities']);
        Route::get('/me/lotteries', [SellerController::class, 'apiGetMyLotteries']);
        Route::get('/me/tacos', [SellerController::class, 'apiGetMyTacos']);
        Route::get('/me/taco-by-qr', [SellerController::class, 'apiTacoByQr']);
        Route::get('/me/tacos/{setId}/{bookNumber}/participations', [SellerController::class, 'apiGetTacoParticipations']);

        // Listar vendedores
        Route::get('/', [SellerController::class, 'apiIndex']);
        
        // Rangos disponibles en un set (debe ir antes de /{id} para no capturar como id)
        Route::get('/available-ranges-set', [SellerController::class, 'getAvailableRangesForSet']);
        
        // Obtener vendedor específico
        Route::get('/{id}', [SellerController::class, 'apiShow']);
        
        // Asignar participaciones a vendedor
        Route::post('/{id}/assign-participations', [SellerController::class, 'apiAssignParticipations']);
        
        // Obtener participaciones asignadas
        Route::get('/{id}/participations', [SellerController::class, 'apiGetParticipations']);
        
        // Obtener participaciones por libro
        Route::post('/get-participations-by-book', [SellerController::class, 'getParticipationsByBook']);
        
        // Remover asignación
        Route::post('/remove-assignment', [SellerController::class, 'removeAssignment']);
        
        // Validar participaciones
        Route::post('/validate-participations', [SellerController::class, 'validateParticipations']);
        
        // Guardar asignaciones
        Route::post('/save-assignments', [SellerController::class, 'saveAssignments']);
        
        // Obtener sets por reserva
        Route::post('/get-sets-by-reserve', [SellerController::class, 'getSetsByReserve']);
        
        // Liquidación de vendedor
        Route::get('/{id}/settlement-summary', [SellerController::class, 'getSettlementSummary']);
        Route::post('/{id}/settlement', [SellerController::class, 'storeSettlement']);
        Route::get('/{id}/settlement-history', [SellerController::class, 'getSettlementHistory']);
        
        // Estadísticas del vendedor
        Route::get('/{id}/stats', [SellerController::class, 'apiGetStats']);
        
        // Grupos de vendedores
        Route::get('/by-group', [SellerController::class, 'getByGroup']);
        Route::get('/group-stats', [SellerController::class, 'getGroupStats']);
        Route::post('/{id}/update-group', [SellerController::class, 'updateGroup']);
    });

    // ========================================================================
    // GESTOR (participaciones de vendedores de sus entidades; tabla managers)
    // ========================================================================
    Route::prefix('managers')->group(function () {
        Route::get('/me/entities', [SellerController::class, 'apiGetManagerEntities']);
        Route::get('/me/entities/{entityId}/assignment/lotteries', [SellerController::class, 'apiManagerAssignmentLotteries']);
        Route::get('/me/entities/{entityId}/assignment/sets', [SellerController::class, 'apiManagerAssignmentSets']);
        Route::post('/me/entities/{entityId}/assignment/validate-reference', [SellerController::class, 'apiManagerAssignmentValidateReference']);
        Route::get('/me/entities/{entityId}/sellers', [SellerController::class, 'apiGetManagerEntitySellers']);
        Route::get('/me/entities/{entityId}/sellers/{sellerId}/detail', [SellerController::class, 'apiGetManagerSellerDetail']);
        Route::post('/me/entities/{entityId}/sellers/{sellerId}/settlement', [SellerController::class, 'apiManagerStoreSettlement']);
        Route::get('/me/tacos', [SellerController::class, 'apiGetManagerTacos']);
        Route::get('/me/tacos/{setId}/{bookNumber}/participations', [SellerController::class, 'apiGetManagerTacoParticipations']);
        Route::get('/me/taco-for-assign', [SellerController::class, 'apiManagerTacoForAssign']);
        Route::post('/me/check-user-email', [SellerController::class, 'apiManagerCheckUserEmail']);
        Route::post('/me/store-existing-user', [SellerController::class, 'apiManagerStoreExistingUser']);
        Route::post('/me/store-new-user', [SellerController::class, 'apiManagerStoreNewUser']);
        Route::post('/me/store-external-seller', [SellerController::class, 'apiManagerStoreExternalSeller']);
    });
    
    // ========================================================================
    // NOTIFICACIONES
    // ========================================================================
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'apiIndex']);

        Route::post('/mark-all-read', [NotificationController::class, 'apiMarkAllAsRead']);

        Route::get('/unread/count', [NotificationController::class, 'apiUnreadCount']);

        Route::post('/register-token', [NotificationController::class, 'registerToken']);

        Route::post('/unregister-token', [NotificationController::class, 'unregisterToken']);

        Route::get('/{id}', [NotificationController::class, 'apiShow'])->whereNumber('id');

        Route::put('/{id}/read', [NotificationController::class, 'apiMarkAsRead'])->whereNumber('id');

        Route::delete('/{id}', [NotificationController::class, 'apiDestroy'])->whereNumber('id');
    });
    
    // ========================================================================
    // LOTERÍAS Y SORTEOS
    // ========================================================================
    Route::prefix('lotteries')->group(function () {
        // Listar loterías
        Route::get('/', [LotteryController::class, 'apiIndex']);
        
        // Obtener lotería específica
        Route::get('/{id}', [LotteryController::class, 'apiShow']);
        
        // Obtener resultados de lotería
        Route::get('/{id}/results', [LotteryController::class, 'apiGetResults']);
        
        // Obtener resultados por administración
        Route::get('/{id}/results/administration/{administrationId}', [LotteryController::class, 'apiGetResultsByAdministration']);
        
        // Loterías disponibles para venta
        Route::get('/available', [LotteryController::class, 'apiGetAvailable']);
        
        // Tipos de lotería
        Route::get('/types', [LotteryController::class, 'apiGetTypes']);
    });
    
    // ========================================================================
    // RESULTADOS Y ESCRUTINIO
    // ========================================================================
    Route::prefix('results')->group(function () {
        // Verificar si participación ganó
        Route::post('/check-winning', [ApiController::class, 'apiCheckWinning']);
        
        // Obtener resultados de participación
        Route::get('/participation/{participationId}', [ApiController::class, 'apiGetParticipationResults']);
        
        // Obtener resultados de sorteo
        Route::get('/lottery/{lotteryId}', [LotteryController::class, 'apiGetResults']);
    });
    
    // ========================================================================
    // CARTERA Y MOVIMIENTOS
    // ========================================================================
    Route::prefix('wallet')->group(function () {
        // Obtener cartera del usuario
        Route::get('/', [UserController::class, 'apiGetWallet']);
        
        // Obtener movimientos
        Route::get('/movements', [UserController::class, 'apiGetMovements']);
        
        // Obtener historial (digitalizaciones, regalos; cobros pendiente)
        Route::get('/historial', [ParticipationController::class, 'apiGetUserHistorial']);
        
        // Obtener participaciones en cartera
        Route::get('/participations', [ParticipationController::class, 'apiGetWalletParticipations']);
        // Participaciones cobrables (con premio, no regaladas, no cobradas)
        Route::get('/participations/cobrables', [ParticipationController::class, 'apiGetCobrables']);
        // Registrar cobro (marca como cobradas)
        Route::post('/cobro', [ParticipationController::class, 'apiRegistrarCobro']);
        // Registrar donación (marca como donadas y genera código de recarga)
        Route::post('/donacion', [ParticipationController::class, 'apiRegistrarDonacion']);
        // Consultar participación por referencia (antes de vincular)
        Route::get('/participations/check', [ParticipationController::class, 'apiCheckByReference']);
        // Vincular participación a la cartera (guardar user id en buyer_name)
        Route::post('/participations/link', [ParticipationController::class, 'apiLinkToWallet']);
        Route::post('/participations/store-warehouse', [ParticipationController::class, 'apiStoreInWarehouse']);
        // Vincular venta digital pendiente por código (email erróneo o registro tardío)
        Route::post('/digital-pending/claim', [ParticipationController::class, 'apiClaimPendingDigitalByCode']);
        // Regalar participación a otro usuario (por email)
        Route::post('/participations/gift', [ParticipationController::class, 'apiGiftToUser']);
        Route::get('/gifts/pending', [ParticipationController::class, 'apiPendingGifts']);
        Route::post('/gifts/{giftId}/accept', [ParticipationController::class, 'apiAcceptGift'])->whereNumber('giftId');
        Route::post('/gifts/{giftId}/reject', [ParticipationController::class, 'apiRejectGift'])->whereNumber('giftId');
    });
    
    // ========================================================================
    // COBROS Y PAGOS
    // ========================================================================
    Route::prefix('payments')->group(function () {
        // Listar cobros disponibles
        Route::get('/available', [UserController::class, 'apiGetAvailablePayments']);
        
        // Solicitar cobro
        Route::post('/request', [UserController::class, 'apiRequestPayment']);
        
        // Historial de cobros
        Route::get('/history', [UserController::class, 'apiGetPaymentHistory']);
        
        // Obtener detalles de cobro
        Route::get('/{id}', [UserController::class, 'apiGetPaymentDetails']);
    });
    
    // ========================================================================
    // GESTIÓN (Para gestores)
    // ========================================================================
    Route::prefix('management')->middleware('role:super_admin,administration,entity,manager')->group(function () {
        
        // Participaciones
        Route::prefix('participations')->group(function () {
            Route::get('/', [ParticipationController::class, 'apiManagementIndex']);
            Route::get('/stats', [ParticipationController::class, 'apiGetManagementStats']);
            Route::post('/bulk-assign', [ParticipationController::class, 'apiBulkAssign']);
            // Pago de premios (gestor): validar participaciones con premio y registrar pago
            Route::post('/validate-for-payment', [ParticipationController::class, 'apiValidateParticipationsForPayment']);
            Route::post('/register-payment', [ParticipationController::class, 'apiRegisterPayment']);
        });
        
        // Vendedores
        Route::prefix('sellers')->group(function () {
            Route::get('/', [SellerController::class, 'apiManagementIndex']);
            Route::post('/', [SellerController::class, 'apiStore']);
            Route::put('/{id}', [SellerController::class, 'apiUpdate']);
            Route::delete('/{id}', [SellerController::class, 'apiDestroy']);
            Route::post('/{id}/toggle-status', [SellerController::class, 'toggleStatus']);
        });
        
        // Devoluciones (rutas específicas ANTES de /{id} para no capturar "entities", "lotteries", etc.)
        Route::prefix('devolutions')->group(function () {
            Route::get('/entities', [DevolutionsController::class, 'getEntities']);
            Route::get('/lotteries', [DevolutionsController::class, 'getLotteriesByEntity']);
            Route::get('/sellers', [DevolutionsController::class, 'getSellersByEntity']);
            Route::get('/sets', [DevolutionsController::class, 'getSetsBySellerAndLottery']);
            Route::get('/sets-by-entity', [DevolutionsController::class, 'getSetsByEntityAndLottery']);
            // Nuevo: reservas por entidad y sorteo (igual que en la web) para permitir devoluciones por reserva en la app
            Route::get('/reserves-by-entity', [DevolutionsController::class, 'getReservesByEntityAndLottery']);
            Route::get('/available-ranges-reserve', [DevolutionsController::class, 'getAvailableRangesForReserve']);
            Route::get('/participations', [DevolutionsController::class, 'getParticipationsBySellerAndLottery']);
            Route::post('/validate', [DevolutionsController::class, 'validateParticipations']);
            Route::get('/liquidation-summary', [DevolutionsController::class, 'getLiquidationSummary']);

            Route::get('/', [DevolutionsController::class, 'apiIndex']);
            Route::post('/', [DevolutionsController::class, 'apiStore']);
            Route::get('/{id}/payments', [DevolutionsController::class, 'getPayments']);
            Route::post('/{id}/payments', [DevolutionsController::class, 'addPayment']);
            Route::put('/{devolutionId}/payments/{paymentId}', [DevolutionsController::class, 'updatePayment']);
            Route::delete('/{devolutionId}/payments/{paymentId}', [DevolutionsController::class, 'deletePayment']);
            Route::get('/{id}', [DevolutionsController::class, 'apiShow']);
            Route::put('/{id}', [DevolutionsController::class, 'apiUpdate']);
            Route::delete('/{id}', [DevolutionsController::class, 'apiDestroy']);
        });
        
        // Pagos de gestor (premios)
        Route::prefix('payments')->group(function () {
            Route::get('/entities', [ParticipationController::class, 'apiGetEntitiesForPayment']);
            Route::get('/', [ManagerController::class, 'apiGetPayments']);
            Route::post('/', [ManagerController::class, 'apiCreatePayment']);
            Route::get('/{id}', [ManagerController::class, 'apiGetPaymentDetails']);
        });
    });
    
    // ========================================================================
    // ENTIDADES Y ADMINISTRACIONES
    // ========================================================================
    Route::prefix('entities')->group(function () {
        Route::get('/', [EntityController::class, 'apiIndex']);
        Route::get('/{entity}/lottery/{lottery}/prize-settings', [EntityLotteryPrizeSettingsController::class, 'apiShow']);
        Route::put('/{entity}/lottery/{lottery}/prize-settings/contact', [EntityLotteryPrizeSettingsController::class, 'apiUpdatePresencialContact']);
        Route::get('/{id}', [EntityController::class, 'apiShow']);
        Route::get('/{id}/lotteries', [EntityController::class, 'apiGetLotteries']);
        Route::get('/{id}/sellers', [EntityController::class, 'apiGetSellers']);
    });
    
    // ========================================================================
    // RESERVAS Y SETS
    // ========================================================================
    Route::prefix('reserves')->group(function () {
        Route::get('/', [ReserveController::class, 'apiIndex']);
        Route::get('/{id}', [ReserveController::class, 'apiShow']);
        Route::get('/{id}/sets', [ReserveController::class, 'apiGetSets']);
    });
    
    Route::prefix('sets')->group(function () {
        Route::get('/', [SetController::class, 'apiIndex']);
        Route::get('/{id}', [SetController::class, 'apiShow']);
        Route::get('/{id}/participations', [SetController::class, 'apiGetParticipations']);
        Route::get('/{id}/price', [SetController::class, 'getPrice']);
    });
    
    // ========================================================================
    // UTILIDADES Y ARCHIVOS (Versiones para app móvil con autenticación)
    // ========================================================================
    Route::prefix('utils')->group(function () {
        // Subir imagen (versión para app móvil)
        Route::post('/upload-image', [\App\Http\Controllers\DesignController::class, 'uploadImage']);
        
        // Generar QR (versión para app móvil)
        Route::post('/generate-qr', [BackController::class, 'generarQr']);
        
        // Verificar si se puede eliminar (versión para app móvil)
        Route::get('/check-delete/{type}/{id}', [ApiController::class, 'checkDelete']);
        
        // Eliminar item (versión para app móvil)
        Route::delete('/delete/{type}/{id}', [ApiController::class, 'deleteItem']);
    });
});