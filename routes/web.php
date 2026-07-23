<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\BackController;
use App\Http\Controllers\AdministratorController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\LotteryController;
use App\Http\Controllers\LotteryTypeController;
use App\Http\Controllers\LotteryScrutinyController;
use App\Http\Controllers\ScrutinyController;
use App\Http\Controllers\ReserveController;
use App\Http\Controllers\SetController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\PanelMagicLinkController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\ParticipationController;
use App\Http\Controllers\ParticipationActivityLogController;
use App\Http\Controllers\DevolutionsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\CommunicationEmailController;
use App\Http\Controllers\ContextController;
use App\Http\Controllers\SepaPaymentOrderController;
use App\Http\Controllers\BillingDirectDebitController;
use App\Http\Controllers\PrizePaymentSuperAdminController;
use App\Http\Controllers\EntityLotteryPrizeSettingsController;
use App\Http\Controllers\BackgroundTaskController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\AdministrationContractController;
use App\Http\Controllers\EntityContractController;
use App\Models\Administration;
use App\Models\User;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Ruta para servir archivos de storage (necesario para Nginx cuando el symlink no funciona)
Route::get('/storage/{path}', function ($path) {
    // Prevenir path traversal attacks
    $path = str_replace('..', '', $path);
    $filePath = storage_path('app/public/' . $path);
    
    // Verificar que el archivo existe y está dentro del directorio permitido
    $realPath = realpath($filePath);
    $allowedPath = realpath(storage_path('app/public'));
    
    if (!$realPath || strpos($realPath, $allowedPath) !== 0) {
        abort(404, 'Archivo no encontrado');
    }
    
    if (!file_exists($realPath) || is_dir($realPath)) {
        abort(404, 'Archivo no encontrado');
    }
    
    // Obtener el tipo MIME
    $type = mime_content_type($realPath);
    if (!$type) {
        $extension = pathinfo($realPath, PATHINFO_EXTENSION);
        $types = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
        ];
        $type = $types[strtolower($extension)] ?? 'application/octet-stream';
    }
    
    return response()->file($realPath, [
        'Content-Type' => $type,
        'Cache-Control' => 'public, max-age=31536000',
    ]);
})->where('path', '.*');

Route::get('comprobar-participacion', [App\Http\Controllers\ApiController::class, 'showParticipationTicket']);
Route::get('comprobar-participaciones', [App\Http\Controllers\ApiController::class, 'showParticipationTicket']);
Route::get('/participation-ticket', [ApiController::class, 'showParticipationTicket']);

// Rutas de autenticación
Route::get('/', function () {
    return redirect('/dashboard');
});

Route::get('login', [AuthController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('login', [AuthController::class, 'login']);
Route::post('logout', [AuthController::class, 'logout'])->name('logout');

Route::get('panel/acceso/{token}', [PanelMagicLinkController::class, 'show'])->name('panel.access');
Route::post('panel/acceso/{token}', [PanelMagicLinkController::class, 'update'])->name('panel.access.submit');

// Ruta para crear usuario administrador por defecto (solo en desarrollo)
Route::get('create-admin', [AuthController::class, 'createDefaultAdmin']);

// Rutas públicas de Firebase (necesarias para inicializar notificaciones)
Route::get('notifications/firebase-config', [NotificationController::class, 'getFirebaseConfig'])->name('notifications.firebase-config-public');

// Ruta para servir el service worker de Firebase
Route::get('firebase-messaging-sw.js', function () {
    $path = public_path('firebase-messaging-sw.js');
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path, [
        'Content-Type' => 'application/javascript',
        'Service-Worker-Allowed' => '/'
    ]);
})->name('firebase-service-worker');

// Rutas públicas de confirmación de vendedores (sin autenticación)
Route::get('/sellers/confirm/accept/{token}', [SellerController::class, 'confirmAccept'])->name('sellers.confirm-accept');
Route::post('/sellers/confirm/accept/{token}', [SellerController::class, 'confirmAcceptStore'])->name('sellers.confirm-accept.store');
Route::get('/sellers/confirm/reject/{token}', [SellerController::class, 'confirmReject'])->name('sellers.confirm-reject');
Route::post('/sellers/confirm/reject/{token}', [SellerController::class, 'confirmRejectStore'])->name('sellers.confirm-reject.store');
Route::get('/asignacion-participaciones/aceptar/{token}', [\App\Http\Controllers\ParticipationAssignmentReceiptController::class, 'accept'])->name('participation-assignment.accept');
Route::get('/asignacion-participaciones/rechazar/{token}', [\App\Http\Controllers\ParticipationAssignmentReceiptController::class, 'reject'])->name('participation-assignment.reject');
Route::get('/entity-managers/confirm/accept/{token}', [EntityController::class, 'confirmManagerAccept'])->name('entity-managers.confirm-accept');
Route::post('/entity-managers/confirm/accept/{token}', [EntityController::class, 'confirmManagerAcceptStore'])->name('entity-managers.confirm-accept.store');
Route::post('/entity-managers/confirm/respond/{token}', [EntityController::class, 'confirmManagerRespond'])->name('entity-managers.confirm-respond');
Route::get('/entity-managers/confirm/reject/{token}', [EntityController::class, 'confirmManagerReject'])->name('entity-managers.confirm-reject');

Route::get('/entity-managers/pending/register/{token}', [\App\Http\Controllers\EntityManagerPendingInvitationController::class, 'showRegister'])->name('entity-managers.pending.register');
Route::post('/entity-managers/pending/register/{token}', [\App\Http\Controllers\EntityManagerPendingInvitationController::class, 'storeRegister'])->name('entity-managers.pending.register.store');
Route::get('/entity-managers/pending/reject/{token}', [\App\Http\Controllers\EntityManagerPendingInvitationController::class, 'reject'])->name('entity-managers.pending.reject');

// Confirmación doble opt-in cobro por transferencia (sin autenticación)
Route::middleware(['throttle:20,1'])->group(function () {
    Route::get('/cobro-transferencia/confirmar/{token}', [\App\Http\Controllers\TransferCollectionVerificationController::class, 'confirm'])->name('transfer-collection.confirm');
    Route::get('/cobro-transferencia/cancelar/{token}', [\App\Http\Controllers\TransferCollectionVerificationController::class, 'cancel'])->name('transfer-collection.cancel');
});
Route::get('/contrato-premio/firmar/{token}', [\App\Http\Controllers\PrizePaymentContractController::class, 'show'])->name('prize-contract.sign');
Route::post('/contrato-premio/firmar/{token}', [\App\Http\Controllers\PrizePaymentContractController::class, 'store'])->name('prize-contract.sign.submit');
Route::get('/contrato-administracion/firmar/{token}', [AdministrationContractController::class, 'show'])->name('administration-contract.sign');
Route::post('/contrato-administracion/firmar/{token}', [AdministrationContractController::class, 'store'])->name('administration-contract.sign.submit');
Route::get('/contrato-entidad/aceptar/{token}', [EntityContractController::class, 'acceptPrimaryManager'])->name('entity-contract.accept-primary');
Route::post('/contrato-entidad/aceptar/{token}', [EntityContractController::class, 'storePrimaryManagerAcceptance'])->name('entity-contract.accept-primary.store');

// Registro web comprador (venta digital pendiente)
Route::get('/registro-comprador/{token}', [\App\Http\Controllers\DigitalBuyerRegistrationController::class, 'show'])->name('digital-buyer.register');
Route::post('/registro-comprador/{token}', [\App\Http\Controllers\DigitalBuyerRegistrationController::class, 'store'])->name('digital-buyer.register.store');
Route::post('/registro-comprador/sms-code', [\App\Http\Controllers\PhoneVerificationController::class, 'sendCode'])->name('digital-buyer.sms-code');

// Registro web destinatario de regalo (email no registrado)
Route::get('/registro-regalo/{token}', [\App\Http\Controllers\GiftRecipientRegistrationController::class, 'show'])->name('gift-recipient.register');
Route::post('/registro-regalo/{token}', [\App\Http\Controllers\GiftRecipientRegistrationController::class, 'store'])->name('gift-recipient.register.store');
Route::post('/registro-regalo/sms-code', [\App\Http\Controllers\PhoneVerificationController::class, 'sendCode'])->name('gift-recipient.sms-code');

// Páginas legales (públicas, sin autenticación)
Route::get('/aviso-legal', [LegalController::class, 'avisoLegal'])->name('legal.aviso-legal');
Route::get('/politica-de-privacidad', [LegalController::class, 'politicaDePrivacidad'])->name('legal.politica-de-privacidad');
Route::get('/politica-de-cookies', [LegalController::class, 'politicaDeCookies'])->name('legal.politica-de-cookies');
Route::get('/terminos-y-condiciones', [LegalController::class, 'terminosYCondiciones'])->name('legal.terminos-y-condiciones');

// Diseño externo por invitación (público, sin login; acceso solo por enlace)
Route::get('/design/external/invite/{token}', [\App\Http\Controllers\DesignController::class, 'externalInviteByToken'])->name('design.external.invite');
Route::get('/design/external/editor', [\App\Http\Controllers\DesignController::class, 'externalEditor'])->name('design.external.editor');
Route::post('/design/external/save-format', [\App\Http\Controllers\DesignController::class, 'externalSaveFormat'])->name('design.external.saveFormat');
Route::post('/design/external/upload-image', [\App\Http\Controllers\DesignController::class, 'uploadImage'])->name('design.external.uploadImage');
Route::post('/design/external/save-snapshot', [\App\Http\Controllers\DesignController::class, 'saveSnapshot'])->name('design.external.saveSnapshot');
Route::post('/design/external/generate-qr', [\App\Http\Controllers\BackController::class, 'generarQr'])->name('design.external.generateQr');
Route::get('/design/external/thank-you', [\App\Http\Controllers\DesignController::class, 'externalThankYou'])->name('design.external.thankYou');
Route::get('/design/external/file/{id}/download', [\App\Http\Controllers\DesignController::class, 'externalDownloadFileSession'])->name('design.external.downloadFile');

// Rutas protegidas por autenticación (cuenta panel entidad: solo lectura vía entity_panel.readonly)
Route::middleware(['auth', 'administration_saas_contract', 'entity_framework_contract', 'panel_legal_accepted', 'active_entity.context', 'entity_panel.readonly', 'entity_manager.legacy_password', 'print_shop.scope'])->group(function () {

    Route::get('/contrato-administracion/pendiente', [AdministrationContractController::class, 'pending'])->name('administration-contract.pending');
    Route::post('/contrato-administracion/reenviar', [AdministrationContractController::class, 'resend'])->name('administration-contract.resend');
    Route::get('/contrato-entidad/pendiente', [EntityContractController::class, 'pending'])->name('entity-contract.pending');
    Route::get('/contrato-entidad/{entity}/preview', [EntityContractController::class, 'preview'])->name('entity-contract.preview');
    Route::get('/contrato-entidad/{entity}/download', [EntityContractController::class, 'download'])->name('entity-contract.download');
    Route::post('/panel/aceptacion-legal', [\App\Http\Controllers\PanelLegalAcceptanceController::class, 'store'])->name('panel-legal.submit');
    
    Route::get('dashboard', [AuthController::class, 'dashboard'])->name('dashboard');
    Route::post('panel/switch-entity', [\App\Http\Controllers\PanelEntitySwitchController::class, 'switch'])
        ->name('panel.switch-entity');
    Route::post('lottery-deadline-reminders/dismiss', [\App\Http\Controllers\LotteryDeadlineReminderController::class, 'dismiss'])
        ->name('lottery-deadline-reminders.dismiss');
    Route::post('lottery-deadline-decisions/assume-debt', [\App\Http\Controllers\LotteryDeadlineAdminDecisionController::class, 'assumeDebt'])
        ->name('lottery-deadline-decisions.assume-debt');
    Route::post('lottery-deadline-decisions/annul', [\App\Http\Controllers\LotteryDeadlineAdminDecisionController::class, 'annul'])
        ->name('lottery-deadline-decisions.annul');

    Route::post('/design-editor/upload-image', [\App\Http\Controllers\DesignController::class, 'uploadImage'])->name('design.uploadImage');
    Route::post('/design-editor/save-snapshot', [\App\Http\Controllers\DesignController::class, 'saveSnapshot'])->name('design.saveSnapshot');
    Route::post('/design-editor/generate-qr', [\App\Http\Controllers\BackController::class, 'generarQr'])->name('design.generateQr');

    Route::prefix('print-shop')->middleware('role:super_admin,print_shop')->group(function () {
        Route::get('/', [\App\Http\Controllers\PrintShopController::class, 'index'])->name('print-shop.index');
        Route::get('/orders/{printOrder}', [\App\Http\Controllers\PrintShopController::class, 'show'])->name('print-shop.orders.show');
        Route::get('/orders/{printOrder}/design', [\App\Http\Controllers\PrintShopController::class, 'editDesign'])->name('print-shop.orders.design');
        Route::get('/orders/{printOrder}/briefing-files/{file}', [\App\Http\Controllers\PrintShopController::class, 'downloadBriefingFile'])->name('print-shop.orders.briefing-file');
        Route::post('/orders/{printOrder}/save-format', [\App\Http\Controllers\DesignController::class, 'printShopSaveFormat'])->name('print-shop.orders.save-design');
        Route::put('/orders/{printOrder}/update-format', [\App\Http\Controllers\DesignController::class, 'printShopUpdateFormat'])->name('print-shop.orders.update-design');
        Route::post('/orders/{printOrder}/submit-approval', [\App\Http\Controllers\PrintShopController::class, 'submitDesignForApproval'])->name('print-shop.orders.submit-approval');
        Route::post('/orders/{printOrder}/status', [\App\Http\Controllers\PrintShopController::class, 'updateStatus'])->name('print-shop.orders.status');
    });

    Route::get('gestor/establecer-contrasena', [AuthController::class, 'showEntityManagerLegacyPassword'])->name('entity-manager.legacy-password.show');
    Route::post('gestor/establecer-contrasena', [AuthController::class, 'updateEntityManagerLegacyPassword'])->name('entity-manager.legacy-password.update');

    Route::get('panel/contrasena-provisional', [AuthController::class, 'showProvisionalPassword'])->name('provisional-password.show');
    Route::post('panel/contrasena-provisional', [AuthController::class, 'updateProvisionalPassword'])->name('provisional-password.update');
    Route::post('panel/contrasena-provisional/omitir', [AuthController::class, 'skipProvisionalPassword'])->name('provisional-password.skip');

    Route::get('cuenta/mis-datos', [AccountController::class, 'myData'])->name('account.my-data');
    Route::post('cuenta/contrasena', [AccountController::class, 'updatePassword'])->name('account.update-password');

    // Selector de contexto de gestor (administración / entidad)
    Route::post('context/set-role', [ContextController::class, 'setRole'])->name('context.set-role');

    Route::prefix('background-tasks')->group(function () {
        Route::get('/', [BackgroundTaskController::class, 'index'])->name('background-tasks.index');
        Route::post('/', [BackgroundTaskController::class, 'store'])->name('background-tasks.store');
        Route::get('/{uuid}', [BackgroundTaskController::class, 'show'])->name('background-tasks.show');
        Route::post('/{uuid}/cancel', [BackgroundTaskController::class, 'cancel'])->name('background-tasks.cancel');
    });

Route::group(['prefix' => 'administrations', 'middleware' => 'role:super_admin'], function() {
    //
    Route::get('/', function() {return view('admins.index');})->name('administrations.index');
    Route::get('/add', [AdministratorController::class, 'create'])->name('administrations.create');
    Route::get('/add/manager', function() {return view('admins.add_manager');})->name('administrations.add-manager');
    Route::post('/add/manager', [AdministratorController::class, 'store_information']);
    Route::post('/store', [AdministratorController::class, 'store']);

    Route::get('/view/{id}', function ($id) {
        $administration = Administration::with(['manager.user'])
            ->forUser(auth()->user())
            ->findOrFail($id);
        $panelUser = User::query()
            ->where('panel_account_type', 'administration')
            ->where('panel_account_id', $administration->id)
            ->first();

        $billingService = app(\App\Services\AdministrationBillingService::class);
        $pendingBillingCharges = $billingService->pendingChargesForAdministration($administration->id);
        $pendingBillingTotal = $pendingBillingCharges->sum('amount');

        return view('admins.show', compact('administration', 'panelUser', 'pendingBillingCharges', 'pendingBillingTotal'));
    })->name('administrations.show');
    Route::post('/{administration}/send-panel-access', [AdministratorController::class, 'sendPanelAccessEmail'])
        ->name('administrations.send-panel-access');
    Route::post('/{administration}/send-contract', [AdministratorController::class, 'sendContract'])
        ->name('administrations.send-contract');
    Route::get('/{administration}/contract-preview', [AdministrationContractController::class, 'preview'])
        ->name('administrations.contract-preview');
    Route::get('/{administration}/contract-download', [AdministrationContractController::class, 'download'])
        ->name('administrations.contract-download');
    Route::get('/edit/{id}', [AdministratorController::class, 'edit'])->name('administrations.edit');
    Route::put('/update/{id}', [AdministratorController::class, 'update'])->name('administrations.update');
    Route::post('/{administration}/toggle-status', [AdministratorController::class, 'toggleStatus'])->name('administrations.toggle-status');
    Route::post('/{administration}/billing-payment', [AdministratorController::class, 'updateBillingPayment'])->name('administrations.update-billing-payment');
    Route::post('/check-email', [AdministratorController::class, 'checkEmail'])->name('administrations.check-email');
    Route::post('/assign-primary-manager/{id}', [AdministratorController::class, 'assignPrimaryManager'])->name('administrations.assign-primary-manager');
    Route::get('/edit/manager/{id}', function($id) {
        $administration = Administration::with(['manager.user'])
            ->forUser(auth()->user())
            ->findOrFail($id);

        return view('admins.edit_manager', compact('administration'));
    })->name('administrations.edit-manager');
    Route::get('/edit/api/{id}', [AdministratorController::class, 'editApi'])->name('administrations.edit-api');
    Route::put('/update/api/{id}', [AdministratorController::class, 'updateApi'])->name('administrations.update-api');
});

Route::group(['prefix' => 'billing-direct-debits', 'middleware' => 'role:super_admin'], function () {
    Route::get('/', [BillingDirectDebitController::class, 'index'])->name('billing-direct-debits.index');
    Route::get('/{billingDirectDebit}', [BillingDirectDebitController::class, 'show'])->name('billing-direct-debits.show');
    Route::get('/{billingDirectDebit}/generate-xml', [BillingDirectDebitController::class, 'generateXml'])->name('billing-direct-debits.generate-xml');
});

Route::group(['prefix' => 'prize-payments', 'middleware' => 'role:super_admin', 'as' => 'prize-payments.'], function () {
    Route::get('/', [PrizePaymentSuperAdminController::class, 'index'])->name('index');
    Route::get('/{prizePayment}', [PrizePaymentSuperAdminController::class, 'show'])->name('show');
    Route::post('/{prizePayment}/confirm-funds', [PrizePaymentSuperAdminController::class, 'confirmFunds'])->name('confirm-funds');
    Route::post('/{prizePayment}/mark-contract-signed', [PrizePaymentSuperAdminController::class, 'markContractSigned'])->name('mark-contract-signed');
    Route::post('/{prizePayment}/activate-online', [PrizePaymentSuperAdminController::class, 'activateOnline'])->name('activate-online');
    Route::post('/{prizePayment}/activate-presencial', [PrizePaymentSuperAdminController::class, 'activatePresencial'])->name('activate-presencial');
    Route::put('/{prizePayment}/messages', [PrizePaymentSuperAdminController::class, 'updateMessages'])->name('update-messages');
    Route::post('/{prizePayment}/send-contract', [PrizePaymentSuperAdminController::class, 'sendContract'])->name('send-contract');
    Route::put('/{prizePayment}/change-mode', [PrizePaymentSuperAdminController::class, 'changeMode'])->name('change-mode');
    Route::post('/{prizePayment}/block-payments', [PrizePaymentSuperAdminController::class, 'blockPayments'])->name('block-payments');
});

Route::group(['prefix' => 'entities'], function() {
    //
    Route::get('/', [EntityController::class, 'index'])->name('entities.index');
    Route::get('/add', [EntityController::class, 'create'])->name('entities.create');
    Route::post('/store-administration', [EntityController::class, 'store_administration']);
    Route::get('/add/information', [EntityController::class, 'create_information'])->name('entities.add-information');
    Route::post('/store-information', [EntityController::class, 'store_information']);
    Route::get('/add/manager', [EntityController::class, 'create_manager'])->name('entities.add-manager');
    Route::post('/store-manager', [EntityController::class, 'store_manager']);
    Route::post('/save-manager-draft', [EntityController::class, 'save_manager_draft'])->name('entities.save-manager-draft');

    // Nuevas rutas para invitación de gestores
    Route::post('/check-manager-email', [EntityController::class, 'check_manager_email'])->name('entities.check-manager-email');
    Route::post('/invite-manager', [EntityController::class, 'invite_manager'])->name('entities.invite-manager');
    Route::post('/register-manager/{id}', [EntityController::class, 'register_manager'])->name('entities.register-manager');
    Route::post('/create-pending-entity', [EntityController::class, 'create_pending_entity'])->name('entities.create-pending-entity');
    
    // Ruta temporal para crear gestor de prueba
    Route::get('/create-test-manager', [EntityController::class, 'create_test_manager'])->name('entities.create-test-manager');

    Route::get('/view/{id}', [EntityController::class, 'show'])->name('entities.show');
    Route::get('/edit/{id}', [EntityController::class, 'edit'])->name('entities.edit');
    Route::put('/update/{id}', [EntityController::class, 'update'])->name('entities.update');
    Route::post('/dismiss-billing-switches-modal', [EntityController::class, 'dismissBillingSwitchesModal'])->name('entities.dismiss-billing-switches-modal');
    Route::post('/{entity}/toggle-status', [EntityController::class, 'toggleStatus'])->name('entities.toggle-status');
    Route::post('/check-email', [EntityController::class, 'checkEmail'])->name('entities.check-email');
    Route::delete('/destroy/{id}', [EntityController::class, 'destroy']);
    Route::get('/delete/{id}', [EntityController::class, 'destroy']);
    
    // Rutas para editar manager
    Route::get('/edit/manager/{id}', [EntityController::class, 'edit_manager'])->name('entities.edit-manager');
    Route::put('/update/manager/{id}', [EntityController::class, 'update_manager'])->name('entities.update-manager');
    Route::get('/edit/manager-permissions/{entity_id}/{manager_id}', [EntityController::class, 'edit_manager_permissions'])->name('entities.edit-manager-permissions');
    Route::put('/update/manager-permissions/{entity_id}/{manager_id}', [EntityController::class, 'update_manager_permissions'])->name('entities.update-manager-permissions');
    Route::post('/set-primary-manager', [EntityController::class, 'set_primary_manager'])->name('entities.set-primary-manager');
    Route::post('/toggle-manager-status', [EntityController::class, 'toggle_manager_status'])->name('entities.toggle-manager-status');
    Route::post('/resend-manager-invitation', [EntityController::class, 'resend_manager_invitation'])->name('entities.resend-manager-invitation');
    Route::delete('/destroy/manager/{entity_id}/{manager_id}', [EntityController::class, 'destroy_manager'])->name('entities.destroy-manager');
    
});

Route::group(['prefix' => 'managers', 'middleware' => 'role:super_admin,administration'], function() {
    //
    Route::get('/edit/{id}', [ManagerController::class, 'edit'])->name('managers.edit');
    Route::put('/update/{id}', [ManagerController::class, 'update'])->name('managers.update');
    Route::delete('/destroy/{id}', [ManagerController::class, 'destroy'])->name('managers.destroy');
    Route::get('/delete/{id}', [ManagerController::class, 'destroy'])->name('managers.delete');
});

/*Route::get('entities',function() {
    return view('entities.index');
});*/
Route::group(['prefix' => 'sellers', 'middleware' => ['role:super_admin,administration,entity', 'entity.permission:sellers']], function() {
    Route::get('/', [SellerController::class, 'index'])->name('sellers.index');
    Route::get('/add', [SellerController::class, 'create'])->name('sellers.create');
    Route::post('/store-entity', [SellerController::class, 'store_entity'])->name('sellers.store-entity');
    Route::get('/add-information', [SellerController::class, 'add_information'])->name('sellers.add-information');
    Route::post('/store-existing-user', [SellerController::class, 'store_existing_user'])->name('sellers.store-existing-user');
    Route::post('/store-new-user', [SellerController::class, 'store_new_user'])->name('sellers.store-new-user');
    Route::get('/view/{id}', [SellerController::class, 'show'])->name('sellers.show');
    Route::post('/view/{id}/comment', [SellerController::class, 'updateComment'])->name('sellers.update-comment');
    Route::get('/edit/{id}', [SellerController::class, 'edit'])->name('sellers.edit');
    Route::put('/update/{id}', [SellerController::class, 'update'])->name('sellers.update');
    Route::post('/{seller}/toggle-status', [SellerController::class, 'toggleStatus'])->name('sellers.toggle-status');
    Route::delete('/destroy/{id}', [SellerController::class, 'destroy'])->name('sellers.destroy');
    Route::get('/delete/{id}', [SellerController::class, 'destroy'])->name('sellers.delete');
    Route::post('/check-user-email', [SellerController::class, 'check_user_email'])->name('sellers.check-user-email');
    Route::post('/check-email', [SellerController::class, 'checkEmail'])->name('sellers.check-email');
    Route::post('/get-sets-by-reserve', [SellerController::class, 'getSetsByReserve'])->name('sellers.get-sets-by-reserve');
    Route::post('/validate-participations', [SellerController::class, 'validateParticipations'])->name('sellers.validate-participations');
    Route::post('/save-assignments', [SellerController::class, 'saveAssignments'])->name('sellers.save-assignments');
    Route::post('/get-assigned-participations', [SellerController::class, 'getAssignedParticipations'])->name('sellers.get-assigned-participations');
    Route::post('/remove-assignment', [SellerController::class, 'removeAssignment'])->name('sellers.remove-assignment');
    Route::post('/get-participations-by-book', [SellerController::class, 'getParticipationsByBook'])->name('sellers.get-participations-by-book');
    
    // Rutas para grupos de vendedores
    Route::post('/{id}/update-group', [SellerController::class, 'updateGroup'])->name('sellers.update-group');
    Route::get('/by-group', [SellerController::class, 'getByGroup'])->name('sellers.by-group');
    Route::get('/group-stats', [SellerController::class, 'getGroupStats'])->name('sellers.group-stats');
    
    // Rutas de liquidación de vendedores
    Route::get('/settlement-summary', [SellerController::class, 'getSettlementSummary'])->name('sellers.settlement-summary');
    Route::post('/settlement', [SellerController::class, 'storeSettlement'])->name('sellers.settlement.store');
    Route::get('/settlement-history', [SellerController::class, 'getSettlementHistory'])->name('sellers.settlement-history');
});

Route::group(['prefix' => 'groups'], function() {
    Route::get('/', [\App\Http\Controllers\GroupController::class, 'index'])->name('groups.index');
    Route::get('/add', [\App\Http\Controllers\GroupController::class, 'create'])->name('groups.create');
    Route::post('/store-entity', [\App\Http\Controllers\GroupController::class, 'storeEntity'])->name('groups.storeEntity');
    Route::get('/add-information', [\App\Http\Controllers\GroupController::class, 'addInformation'])->name('groups.add-information');
    Route::post('/store', [\App\Http\Controllers\GroupController::class, 'store'])->name('groups.store');
    Route::get('/edit/{id}', [\App\Http\Controllers\GroupController::class, 'edit'])->name('groups.edit');
    Route::put('/update/{id}', [\App\Http\Controllers\GroupController::class, 'update'])->name('groups.update');
    Route::delete('/destroy/{id}', [\App\Http\Controllers\GroupController::class, 'destroy'])->name('groups.destroy');
});

Route::get('users', [UserController::class, 'index'])->name('users.index');

Route::group(['prefix' => 'lottery'], function() {
    //
    Route::get('/', [LotteryController::class, 'index'])->name('lotteries.index');
    Route::get('/add', [LotteryController::class, 'create'])->name('lotteries.create');
    Route::post('/store', [LotteryController::class, 'store'])->name('lotteries.store');
    Route::get('/view/{lottery}', [LotteryController::class, 'show'])->name('lotteries.show');
    Route::put('/view/{lottery}/prize-presencial-contact', [EntityLotteryPrizeSettingsController::class, 'updatePresencialContactWeb'])
        ->name('lotteries.update-prize-presencial-contact');
    Route::get('/edit/{lottery}', [LotteryController::class, 'edit'])->name('lotteries.edit');
    Route::put('/update/{lottery}', [LotteryController::class, 'update'])->name('lotteries.update');
    Route::delete('/destroy/{lottery}', [LotteryController::class, 'destroy'])->name('lotteries.destroy');
    Route::get('/delete/{lottery}', [LotteryController::class, 'destroy'])->name('lotteries.delete');
    Route::post('/change-status/{lottery}', [LotteryController::class, 'changeStatus'])->name('lotteries.change-status');
    Route::delete('/delete-image/{lottery}', [LotteryController::class, 'deleteImage'])->name('lotteries.delete-image');
    Route::post('/generate', [LotteryController::class, 'generate'])->name('lotteries.generate');
    
    // Rutas adicionales para funcionalidades específicas
    Route::get('/administrations', [LotteryController::class, 'showAdministrations'])->name('lottery.administrations');
    Route::post('/select-administration', [LotteryController::class, 'selectAdministration'])->name('lottery.select-administration');
    Route::get('/results', [LotteryController::class, 'showLotteryResults'])->name('lottery.results');
    // Rutas de escrutinio
    Route::get('/scrutiny/{lottery}', [LotteryScrutinyController::class, 'show'])->name('lottery.scrutiny');
    Route::post('/scrutiny/{lottery}/process', [LotteryScrutinyController::class, 'process'])->name('lottery.process-scrutiny');
    Route::post('/scrutiny/{lottery}/save', [LotteryScrutinyController::class, 'save'])->name('lottery.save-scrutiny');
    Route::get('/scrutiny/{lottery}/administration/{administration}', [LotteryScrutinyController::class, 'showResults'])->name('lottery.show-administration-scrutiny');
    Route::delete('/scrutiny/{lottery}/administration/{administration}', [LotteryScrutinyController::class, 'delete'])->name('lottery.delete-scrutiny');
    // Ruta para escrutinio por categoría (números individuales)
    Route::get('/scrutiny/{lottery}/category', [LotteryScrutinyController::class, 'showByCategory'])->name('lottery.scrutiny-category');
    Route::get('/results/edit/{id}', [LotteryController::class, 'editLotteryResults'])->name('lottery.edit-results');
    
    // Rutas para resultados de lotería
    Route::post('/fetch-results', [LotteryController::class, 'fetchAndSaveResults'])->name('lottery.fetch-results');
    Route::post('/fetch-specific-results', [LotteryController::class, 'fetchSpecificResults'])->name('lottery.fetch-specific-results');
    Route::post('/save-results', [LotteryController::class, 'saveResults'])->name('lottery.save-results');
    Route::get('/results/{lottery}', [LotteryController::class, 'showResults'])->name('lottery.show-results');
    Route::get('/results-table', [LotteryController::class, 'resultsTable'])->name('lottery.results-table');
});

Route::group(['prefix' => 'lottery_types'], function() {
    //
    Route::get('/', [LotteryTypeController::class, 'index'])->name('lottery-types.index');
    Route::get('/add', [LotteryTypeController::class, 'create'])->name('lottery-types.create');
    Route::post('/store', [LotteryTypeController::class, 'store'])->name('lottery-types.store');
    Route::get('/view/{lotteryType}', [LotteryTypeController::class, 'show'])->name('lottery-types.show');
    Route::get('/edit/{lotteryType}', [LotteryTypeController::class, 'edit'])->name('lottery-types.edit');
    Route::put('/update/{lotteryType}', [LotteryTypeController::class, 'update'])->name('lottery-types.update');
    Route::delete('/destroy/{lotteryType}', [LotteryTypeController::class, 'destroy'])->name('lottery-types.destroy');
    Route::get('/delete/{lotteryType}', [LotteryTypeController::class, 'destroy'])->name('lottery-types.delete');
    Route::post('/change-status/{lotteryType}', [LotteryTypeController::class, 'changeStatus'])->name('lottery-types.change-status');
    
    // Ruta para obtener categorías disponibles
    Route::get('/available-categories', [LotteryTypeController::class, 'getAvailablePrizeCategories'])->name('lottery-types.available-categories');
});

Route::group(['prefix' => 'reserves', 'middleware' => 'role:super_admin,administration,entity'], function() {
    //
    Route::get('/', [ReserveController::class, 'index'])->name('reserves.index');
    Route::get('/add', [ReserveController::class, 'create'])->name('reserves.create');
    Route::post('/store-entity', [ReserveController::class, 'store_entity'])->name('reserves.store-entity');
    Route::post('/store-entity-ajax', [ReserveController::class, 'store_entity_ajax'])->name('reserves.store-entity-ajax');
    Route::get('/add/lottery', [ReserveController::class, 'add_lottery'])->name('reserves.add-lottery');
    Route::post('/store-lottery', [ReserveController::class, 'store_lottery'])->name('reserves.store-lottery');
    Route::post('/store-lottery-ajax', [ReserveController::class, 'store_lottery_ajax'])->name('reserves.store-lottery-ajax');
    Route::get('/add/information', [ReserveController::class, 'add_information'])->name('reserves.add-information');
    Route::post('/store-information', [ReserveController::class, 'store_information'])->name('reserves.store-information');
    Route::get('/view/{reserve}', [ReserveController::class, 'show'])->name('reserves.show');
    Route::get('/edit/{reserve}', [ReserveController::class, 'edit'])->name('reserves.edit');
    Route::put('/update/{reserve}', [ReserveController::class, 'update'])->name('reserves.update');
    Route::delete('/destroy/{reserve}', [ReserveController::class, 'destroy'])->name('reserves.destroy');
    Route::get('/delete/{reserve}', [ReserveController::class, 'destroy'])->name('reserves.delete');
    Route::post('/change-status/{reserve}', [ReserveController::class, 'changeStatus'])->name('reserves.change-status');
    Route::get('/lotteries-by-entity', [ReserveController::class, 'getLotteriesByEntity'])->name('reserves.lotteries-by-entity');
});

Route::group(['prefix' => 'sets', 'middleware' => 'role:super_admin,administration,entity'], function() {
    //
    Route::get('/', [SetController::class, 'index'])->name('sets.index');
    Route::get('/add', [SetController::class, 'create'])->name('sets.create');
    Route::post('/store-entity', [SetController::class, 'store_entity'])->name('sets.store-entity');
    Route::post('/store-entity-ajax', [SetController::class, 'store_entity_ajax'])->name('sets.store-entity-ajax');
    Route::get('/add/reserve', [SetController::class, 'add_reserve'])->name('sets.add-reserve');
    Route::post('/store-reserve', [SetController::class, 'store_reserve'])->name('sets.store-reserve');
    Route::post('/store-reserve-ajax', [SetController::class, 'store_reserve_ajax'])->name('sets.store-reserve-ajax');
    Route::get('/add/information', [SetController::class, 'add_information'])->name('sets.add-information');
    Route::post('/store-information', [SetController::class, 'store_information'])->name('sets.store-information');
    Route::get('/view/{set}', [SetController::class, 'show'])->name('sets.show');
    Route::get('/edit/{set}', [SetController::class, 'edit'])->name('sets.edit');
    Route::put('/update/{set}', [SetController::class, 'update'])->name('sets.update');
    Route::delete('/destroy/{set}', [SetController::class, 'destroy'])->name('sets.destroy');
    Route::get('/delete/{set}', [SetController::class, 'destroy'])->name('sets.delete');
    Route::post('/change-status/{set}', [SetController::class, 'changeStatus'])->name('sets.change-status');
    Route::get('/reserves-by-entity', [SetController::class, 'getReservesByEntity'])->name('sets.reserves-by-entity');
    Route::get('/download-xml/{set}', [SetController::class, 'downloadXml'])->name('sets.download-xml');
    Route::post('sets/{set}/import-xml', [App\Http\Controllers\SetController::class, 'importXml'])->name('sets.importXml');
    Route::get('/get-price', [SetController::class, 'getPrice'])->name('sets.get-price');
});

Route::group(['prefix' => 'participations'], function() {
    //
    Route::get('/', [ParticipationController::class, 'index'])->name('participations.index');
    Route::get('/search-by-reference', [ParticipationController::class, 'searchByReference'])->name('participations.search-by-reference');
    Route::get('/add', [ParticipationController::class, 'create'])->name('participations.create');
    Route::post('/view-entity', [ParticipationController::class, 'store_entity'])->name('participations.view-entity');
    Route::get('/view/{id}', [ParticipationController::class, 'show'])->name('participations.show');
    Route::patch('/view/{id}/notes', [ParticipationController::class, 'updateNotes'])->name('participations.update-notes');
    Route::get('/view/{id}/seller', [ParticipationController::class, 'show_seller'])->name('participations.show-seller');
    Route::get('/book/{set_id}/{book_number}/participations', [ParticipationController::class, 'getBookParticipations'])->name('participations.book-participations');
    
    // Rutas para historial de actividades
    Route::get('/{id}/activity-log', [ParticipationActivityLogController::class, 'show'])->name('participations.activity-log');
    Route::get('/{id}/history', [ParticipationActivityLogController::class, 'getParticipationHistory'])->name('participations.history');
});

// Rutas para historial de actividades
Route::group(['prefix' => 'activity-logs'], function() {
    Route::get('/seller/{id}', [ParticipationActivityLogController::class, 'getSellerHistory'])->name('activity-logs.seller');
    Route::get('/entity/{id}', [ParticipationActivityLogController::class, 'getEntityHistory'])->name('activity-logs.entity');
    Route::get('/stats', [ParticipationActivityLogController::class, 'getActivityStats'])->name('activity-logs.stats');
    Route::get('/recent', [ParticipationActivityLogController::class, 'getRecentActivities'])->name('activity-logs.recent');
});

Route::group(['prefix' => 'design', 'middleware' => 'entity.permission:design'], function() {
    //
    Route::get('/', [\App\Http\Controllers\DesignController::class, 'index'])->name('design.index');
    // Nuevo flujo con controlador
    Route::get('/add', [\App\Http\Controllers\DesignController::class, 'selectEntity'])->name('design.selectEntity');
    
    Route::post('/store-entity', [\App\Http\Controllers\DesignController::class, 'storeEntity'])->name('design.storeEntity');
    Route::post('design/add/lottery', [\App\Http\Controllers\DesignController::class, 'storeLottery'])->name('design.storeLottery');

    Route::get('/add/lottery/{entity_id?}', [\App\Http\Controllers\DesignController::class, 'selectLottery'])->name('design.selectLottery');
    Route::get('/add/set', [\App\Http\Controllers\DesignController::class, 'selectSet'])->name('design.selectSet');
    Route::post('/choose-type', [\App\Http\Controllers\DesignController::class, 'chooseType'])->name('design.chooseType');
    Route::get('/choose-type', [\App\Http\Controllers\DesignController::class, 'showChooseType'])->name('design.showChooseType');
    Route::post('/add/format', [\App\Http\Controllers\DesignController::class, 'format'])->name('design.format');
    Route::post('/save-format', [\App\Http\Controllers\DesignController::class, 'saveFormat'])->name('design.saveFormat');
    Route::get('/list-formats', [\App\Http\Controllers\DesignController::class, 'listFormats'])->name('design.listFormats');
    Route::get('/digital/participation-image/{id}', [\App\Http\Controllers\DesignController::class, 'digitalParticipationImage'])->name('design.digitalParticipationImage');
    Route::get('/marketing/participation-image/{id}', [\App\Http\Controllers\DesignController::class, 'marketingParticipationImage'])->name('design.marketingParticipationImage');

    // Diseño e impresión externo (tarea 9)
    Route::get('/external/step1', [\App\Http\Controllers\DesignController::class, 'externalStep1'])->name('design.external.step1');
    Route::get('/external/step2', [\App\Http\Controllers\DesignController::class, 'externalStep2'])->name('design.external.step2');
    Route::get('/external/step3', [\App\Http\Controllers\DesignController::class, 'externalStep3'])->name('design.external.step3');
    Route::post('/external/create-payment-intent', [\App\Http\Controllers\DesignController::class, 'externalCreatePaymentIntent'])
        ->middleware('throttle:15,1')
        ->name('design.external.createPaymentIntent');
    Route::post('/external/preview-quote', [\App\Http\Controllers\DesignController::class, 'externalPreviewQuote'])->name('design.external.previewQuote');
    Route::post('/external/accept-summary', [\App\Http\Controllers\DesignController::class, 'externalAcceptSummary'])->name('design.external.acceptSummary');
    Route::post('/external/store-step1', [\App\Http\Controllers\DesignController::class, 'externalStoreStep1'])->name('design.external.storeStep1');
    Route::post('/external/send-invitation', [\App\Http\Controllers\DesignController::class, 'externalSendInvitation'])->name('design.external.sendInvitation');
    Route::get('/external/list', [\App\Http\Controllers\DesignController::class, 'externalList'])->name('design.external.list');
    Route::get('/external/{invitation}/file/{file}/download', [\App\Http\Controllers\DesignController::class, 'externalDownloadFileAuth'])->name('design.external.downloadFileAuth');
    Route::delete('/external/{id}', [\App\Http\Controllers\DesignController::class, 'externalDestroy'])->name('design.external.destroy');
    Route::get('/send-to-print/{id}', [\App\Http\Controllers\DesignController::class, 'sendToPrint'])->name('design.sendToPrint');
    Route::post('/send-to-print/{id}/quote', [\App\Http\Controllers\DesignController::class, 'previewPrintOrderQuote'])->name('design.previewPrintOrderQuote');
    Route::post('/send-to-print/{id}/payment-intent', [\App\Http\Controllers\DesignController::class, 'createPrintOrderPaymentIntent'])
        ->middleware('throttle:15,1')
        ->name('design.createPrintOrderPaymentIntent');
    Route::post('/send-to-print/{id}', [\App\Http\Controllers\DesignController::class, 'submitPrintOrder'])->name('design.submitPrintOrder');
    Route::post('/sets/{set}/mark-management-fee-paid', [\App\Http\Controllers\DesignController::class, 'markManagementFeePaid'])->name('design.markManagementFeePaid');
    Route::get('/management-fee/pay/{set}', [\App\Http\Controllers\DesignController::class, 'payManagementFee'])->name('design.managementFee.pay');
    Route::get('/sets/{set}/start', [\App\Http\Controllers\DesignController::class, 'openChooseType'])->name('design.openChooseType');
    Route::get('/editor/{set}', [\App\Http\Controllers\DesignController::class, 'openEditor'])->name('design.openEditor');
    Route::post('/sets/{set}/management-fee/payment-intent', [\App\Http\Controllers\DesignController::class, 'createManagementFeePaymentIntent'])
        ->middleware('throttle:15,1')
        ->name('design.managementFee.paymentIntent');
    Route::post('/sets/{set}/management-fee/confirm-stripe', [\App\Http\Controllers\DesignController::class, 'confirmManagementFeeStripe'])->name('design.managementFee.confirmStripe');
    Route::post('/sets/{set}/management-fee/confirm-remittance', [\App\Http\Controllers\DesignController::class, 'confirmManagementFeeRemittance'])->name('design.managementFee.confirmRemittance');
    Route::get('/approvals', [\App\Http\Controllers\DesignController::class, 'approvalsIndex'])->name('design.approvals.index');
    Route::get('/{id}/preview', [\App\Http\Controllers\DesignController::class, 'participationPreview'])->name('design.participationPreview');
    Route::get('/{id}/approval', [\App\Http\Controllers\DesignController::class, 'approvalReview'])->name('design.approval.review');
    Route::post('/{id}/submit-for-approval', [\App\Http\Controllers\DesignController::class, 'submitForApproval'])->name('design.submitForApproval');
    Route::post('/{id}/approve', [\App\Http\Controllers\DesignController::class, 'approveDesign'])->name('design.approve');
    Route::post('/{id}/reject', [\App\Http\Controllers\DesignController::class, 'rejectDesign'])->name('design.reject');
    // Route::post('design/format', [App\Http\Controllers\DesignController::class, 'storeFormat'])->name('design.storeFormat');

});

Route::get('/design/pdf/participation/{id}', [App\Http\Controllers\DesignController::class, 'exportParticipationPdf'])->name('design.exportParticipationPdf');
Route::get('/design/pdf/participation-async/{id}', [App\Http\Controllers\DesignController::class, 'exportParticipationPdfAsync'])->name('design.exportParticipationPdfAsync');
Route::get('/design/pdf/participation-sample/{id}', [App\Http\Controllers\DesignController::class, 'exportParticipationSamplePdf'])->name('design.exportParticipationSamplePdf');
Route::get('/design/pdf/status/{job_id}', [App\Http\Controllers\DesignController::class, 'checkPdfStatus'])->name('design.checkPdfStatus');
Route::get('/design/pdf/download/{job_id}', [App\Http\Controllers\DesignController::class, 'downloadPdf'])->name('design.downloadPdf');
Route::get('/design/pdf/cover/{id}', [App\Http\Controllers\DesignController::class, 'exportCoverPdf'])->name('design.exportCoverPdf');
Route::get('/design/pdf/cover-async/{id}', [App\Http\Controllers\DesignController::class, 'exportCoverPdfAsync'])->name('design.exportCoverPdfAsync');
Route::get('/design/pdf/back/{id}', [App\Http\Controllers\DesignController::class, 'exportBackPdf'])->name('design.exportBackPdf');
Route::get('/design/pdf/back-async/{id}', [App\Http\Controllers\DesignController::class, 'exportBackPdfAsync'])->name('design.exportBackPdfAsync');
Route::get('/design/pdf/cover-back/{id}', [App\Http\Controllers\DesignController::class, 'exportCoverAndBackPdf'])->name('design.exportCoverAndBackPdf');
Route::get('/design/pdf/cover-back-async/{id}', [App\Http\Controllers\DesignController::class, 'exportCoverBackPdfAsync'])->name('design.exportCoverBackPdfAsync');
Route::post('/design/pdf/preview-step', [App\Http\Controllers\DesignController::class, 'previewDesignStepPdf'])->name('design.previewStepPdf');
Route::post('/design/external/pdf/preview-step', [App\Http\Controllers\DesignController::class, 'previewDesignStepPdf'])->name('design.external.previewStepPdf');
Route::get('/design/pdf/export-async', [App\Http\Controllers\DesignController::class, 'exportPdf'])->name('design.exportPdfAsync');
Route::post('/design/export-pdf', [App\Http\Controllers\DesignController::class, 'exportPdf']);
Route::get('/design/format/edit/{id}', [App\Http\Controllers\DesignController::class, 'editFormat'])->name('design.editFormat');
Route::put('/design/format/update/{id}', [App\Http\Controllers\DesignController::class, 'updateFormat'])->name('design.updateFormat');
Route::get('/design/summary/{id}', [App\Http\Controllers\DesignController::class, 'summary'])->name('design.summary');
Route::delete('/design/format/{id}', [App\Http\Controllers\DesignController::class, 'destroy'])->name('design.destroy');

Route::group(['prefix' => 'social'], function() {
    Route::get('/', [App\Http\Controllers\SocialWebController::class, 'index'])->name('social.index');
    Route::get('/add', [App\Http\Controllers\SocialWebController::class, 'create'])->name('social.create');
    Route::post('/add/design', [App\Http\Controllers\SocialWebController::class, 'storeEntity'])->name('social.store-entity');
    Route::get('/add/design/{entity_id}', [App\Http\Controllers\SocialWebController::class, 'addDesign'])->name('social.add-design');
    Route::post('/', [App\Http\Controllers\SocialWebController::class, 'store'])->name('social.store');
    Route::get('/edit/{id}', [App\Http\Controllers\SocialWebController::class, 'edit'])->name('social.edit');
    Route::put('/{id}', [App\Http\Controllers\SocialWebController::class, 'update'])->name('social.update');
    Route::delete('/{id}', [App\Http\Controllers\SocialWebController::class, 'destroy'])->name('social.destroy');
    Route::post('/{id}/change-status', [App\Http\Controllers\SocialWebController::class, 'changeStatus'])->name('social.change-status');
});
Route::get('requests',function() {
    return view('requests.index');
});

// Rutas de configuración/ajustes
Route::group(['prefix' => 'configuration', 'middleware' => 'entity.permission:payments'], function() {
    Route::get('/', [App\Http\Controllers\ConfigurationController::class, 'index'])->name('configuration.index');
    Route::post('/entity-settings', [App\Http\Controllers\ConfigurationController::class, 'updateEntitySettings'])->name('configuration.entity-settings.update');
    Route::post('/entity-settings/billing', [App\Http\Controllers\ConfigurationController::class, 'updateEntityBilling'])->name('configuration.entity-billing.update');
    Route::post('/entity-settings/managers/{manager}', [App\Http\Controllers\ConfigurationController::class, 'updateEntityManager'])->name('configuration.entity-manager.update');
    Route::post('/administration-settings', [App\Http\Controllers\ConfigurationController::class, 'updateAdministrationSettings'])->name('configuration.administration-settings.update');
    Route::post('/administration-settings/billing', [App\Http\Controllers\ConfigurationController::class, 'updateAdministrationBilling'])->name('configuration.administration-billing.update');
    Route::post('/imprenta', [App\Http\Controllers\ConfigurationController::class, 'updateImprenta'])->name('configuration.imprenta.update');
    Route::post('/partilot-billing', [App\Http\Controllers\ConfigurationController::class, 'updatePartilotBilling'])->name('configuration.partilot-billing.update');
    Route::post('/partilot-profile', [App\Http\Controllers\ConfigurationController::class, 'updatePartilotProfile'])->name('configuration.partilot-profile.update');
    Route::post('/imprenta/panel-access', [App\Http\Controllers\ConfigurationController::class, 'updatePrintShopPanelAccess'])->name('configuration.imprenta.panel-access');
    Route::post('/print-orders/{printOrder}/status', [App\Http\Controllers\ConfigurationController::class, 'updatePrintOrderStatus'])->name('configuration.print-orders.status');
    Route::post('/print-orders/{printOrder}/reconcile-payment', [App\Http\Controllers\ConfigurationController::class, 'reconcilePrintOrderPayment'])->name('configuration.print-orders.reconcile-payment');
    Route::post('/billing-remittance/{administration}', [BillingDirectDebitController::class, 'store'])->name('configuration.billing-remittance.store');
    Route::get('/billing-remittance/orders/{billingDirectDebit}/xml', [BillingDirectDebitController::class, 'generateXml'])->name('configuration.billing-remittance.generate-xml');
    Route::post('/billing-remittance/orders/{billingDirectDebit}/mark-collected', [BillingDirectDebitController::class, 'markCollected'])->name('configuration.billing-remittance.mark-collected');
    Route::post('/billing-remittance/orders/{billingDirectDebit}/cancel', [BillingDirectDebitController::class, 'cancel'])->name('configuration.billing-remittance.cancel');
    Route::delete('ordenes-pago-entidades/collections/{participationCollection}', [App\Http\Controllers\ConfigurationController::class, 'destroyCollection'])->name('ordenes-pago-entidades.collections.destroy');
    Route::post('ordenes-pago-entidades/crear-sepa', [App\Http\Controllers\ConfigurationController::class, 'crearSepa'])->name('ordenes-pago-entidades.crear-sepa');
    Route::get('ordenes-pago-entidades/nueva-orden', [App\Http\Controllers\ConfigurationController::class, 'nuevaOrdenSepa'])->name('ordenes-pago-entidades.nueva-orden');
    Route::post('ordenes-pago-entidades/store-sepa', [App\Http\Controllers\ConfigurationController::class, 'storeOrdenSepa'])->name('ordenes-pago-entidades.store-sepa');
    Route::delete('ordenes-pago-entidades/beneficiaries/{sepaPaymentBeneficiary}', [App\Http\Controllers\ConfigurationController::class, 'destroyBeneficiary'])->name('ordenes-pago-entidades.beneficiaries.destroy');
});
Route::group(['prefix' => 'communications', 'middleware' => 'role:super_admin,administration,entity'], function() {
    Route::get('/', [CommunicationEmailController::class, 'index'])->name('communications.index');
    Route::get('/{id}/preview', [CommunicationEmailController::class, 'preview'])->name('communications.preview');
    Route::post('/{id}/resend', [CommunicationEmailController::class, 'resend'])->name('communications.resend');
    Route::delete('/{id}', [CommunicationEmailController::class, 'destroy'])->name('communications.destroy');
});

// Rutas de Escrutinio
Route::get('scrutiny', [ScrutinyController::class, 'index'])->name('scrutiny.index');
Route::post('scrutiny/generate', [ScrutinyController::class, 'generateScrutiny'])->name('scrutiny.generate');
Route::post('scrutiny/export', [ScrutinyController::class, 'exportScrutiny'])->name('scrutiny.export');

// Rutas de Devoluciones
// Rutas específicas ANTES del resource para evitar conflictos
Route::get('devolutions-data', [DevolutionsController::class, 'data'])->name('devolutions.data');
Route::get('devolutions/entities', [DevolutionsController::class, 'getEntities'])->name('devolutions.entities');
Route::get('devolutions/lotteries', [DevolutionsController::class, 'getLotteriesByEntity'])->name('devolutions.lotteries');
Route::get('devolutions/sellers', [DevolutionsController::class, 'getSellersByEntity'])->name('devolutions.sellers');
Route::get('devolutions/sets', [DevolutionsController::class, 'getSetsBySellerAndLottery'])->name('devolutions.sets');
Route::get('devolutions/sets-by-entity', [DevolutionsController::class, 'getSetsByEntityAndLottery'])->name('devolutions.sets-by-entity');
Route::get('devolutions/reserves-by-entity', [DevolutionsController::class, 'getReservesByEntityAndLottery'])->name('devolutions.reserves-by-entity');
Route::get('devolutions/participations', [DevolutionsController::class, 'getParticipationsBySellerAndLottery'])->name('devolutions.participations');
Route::post('devolutions/validate', [DevolutionsController::class, 'validateParticipations'])->name('devolutions.validate');
Route::get('devolutions/liquidation-summary', [DevolutionsController::class, 'getLiquidationSummary'])->name('devolutions.liquidation-summary');

// Rutas para gestión de pagos de devoluciones
Route::get('devolutions/{id}/payments', [DevolutionsController::class, 'getPayments'])->name('devolutions.payments');
Route::post('devolutions/{id}/payments', [DevolutionsController::class, 'addPayment'])->name('devolutions.payments.store');
Route::put('devolutions/{devolutionId}/payments/{paymentId}', [DevolutionsController::class, 'updatePayment'])->name('devolutions.payments.update');
Route::delete('devolutions/{devolutionId}/payments/{paymentId}', [DevolutionsController::class, 'deletePayment'])->name('devolutions.payments.delete');

// Resource routes (deben ir AL FINAL para evitar conflictos)
Route::resource('devolutions', DevolutionsController::class);

// Rutas de Usuarios
Route::get('users-data', [UserController::class, 'data'])->name('users.data');
Route::post('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
Route::post('users/check-email', [UserController::class, 'checkEmail'])->name('users.check-email');
Route::get('users/{user}/wallet', [UserController::class, 'wallet'])->name('users.wallet');
Route::get('users/{user}/history', [UserController::class, 'history'])->name('users.history');
Route::resource('users', UserController::class);

// Rutas de Notificaciones
Route::group(['prefix' => 'notifications'], function() {
    Route::get('/', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/dashboard', [NotificationController::class, 'dashboard'])->name('notifications.dashboard');
    Route::get('/create', [NotificationController::class, 'create'])->name('notifications.create');
    Route::post('/store-type', [NotificationController::class, 'storeType'])->name('notifications.store-type');

    Route::get('/push-to-user', [NotificationController::class, 'pushToUserForm'])->name('notifications.push-to-user');
    Route::post('/push-to-user', [NotificationController::class, 'storeUserPush'])->name('notifications.store-user-push');

    // Rutas para selección de entidad
    Route::get('/select-entity', [NotificationController::class, 'selectEntity'])->name('notifications.select-entity');
    Route::post('/store-entity', [NotificationController::class, 'storeEntity'])->name('notifications.store-entity');
    
    // Rutas para selección de administración
    Route::get('/select-administration', [NotificationController::class, 'selectAdministration'])->name('notifications.select-administration');
    Route::post('/store-administration', [NotificationController::class, 'storeAdministration'])->name('notifications.store-administration');
    
    // Rutas para selección de entidades de administración
    Route::get('/select-administration-entities', [NotificationController::class, 'selectAdministrationEntities'])->name('notifications.select-administration-entities');
    Route::post('/store-administration-entities', [NotificationController::class, 'storeAdministrationEntities'])->name('notifications.store-administration-entities');
    
    // Ruta para formulario de mensaje
    Route::get('/message', [NotificationController::class, 'message'])->name('notifications.message');
    Route::post('/store', [NotificationController::class, 'store'])->name('notifications.store');
    
    // Ruta para mensaje de éxito
    Route::get('/success', [NotificationController::class, 'success'])->name('notifications.success');
    
    // Ruta AJAX para obtener entidades por administración
    Route::get('/entities-by-administration', [NotificationController::class, 'getEntitiesByAdministration'])->name('notifications.entities-by-administration');
    
    // Ruta para registrar token FCM (requiere autenticación)
    Route::post('/register-token', [NotificationController::class, 'registerToken'])->name('notifications.register-token');

    Route::post('/unregister-token', [NotificationController::class, 'unregisterToken'])->name('notifications.unregister-token');

    // Ruta para enviar notificación de prueba
    Route::post('/send-test', [NotificationController::class, 'sendTest'])->name('notifications.send-test');
    
    // Ruta para eliminar notificación (al final para evitar conflictos)
    Route::delete('/delete/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
});

// Rutas de Órdenes de Pago SEPA
Route::group(['prefix' => 'sepa-payments', 'middleware' => 'entity.permission:payments'], function() {
    Route::get('/', [SepaPaymentOrderController::class, 'index'])->name('sepa-payments.index');
    Route::get('/create', [SepaPaymentOrderController::class, 'create'])->name('sepa-payments.create');
    Route::post('/', [SepaPaymentOrderController::class, 'store'])->name('sepa-payments.store');
    Route::get('/{sepaPaymentOrder}', [SepaPaymentOrderController::class, 'show'])->name('sepa-payments.show');
    Route::get('/{sepaPaymentOrder}/generate-xml', [SepaPaymentOrderController::class, 'generateXml'])->name('sepa-payments.generate-xml');
    Route::post('/{sepaPaymentOrder}/mark-ready', [SepaPaymentOrderController::class, 'markAsReady'])->name('sepa-payments.mark-ready');
    Route::post('/{sepaPaymentOrder}/mark-beneficiaries-paid', [SepaPaymentOrderController::class, 'markBeneficiariesPaid'])->name('sepa-payments.mark-beneficiaries-paid');
    Route::post('/{sepaPaymentOrder}/revert-beneficiaries', [SepaPaymentOrderController::class, 'revertBeneficiariesToCobrable'])->name('sepa-payments.revert-beneficiaries');
    Route::delete('/{sepaPaymentOrder}', [SepaPaymentOrderController::class, 'destroy'])->name('sepa-payments.destroy');
});

}); // Cierre del middleware auth