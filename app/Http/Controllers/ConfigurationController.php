<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use App\Models\Entity;
use App\Models\ParticipationCollection;
use App\Models\SepaPaymentOrder;
use App\Models\SepaPaymentBeneficiary;
use App\Models\Administration;
use App\Models\PrintConfiguration;
use App\Models\PrintOrder;
use App\Models\ParticipationDonation;
use App\Models\Manager;
use App\Models\Seller;
use App\Models\User;
use App\Models\EmailCommunicationLog;
use App\Models\PartilotBillingSetting;
use App\Models\BillingDirectDebitOrder;
use App\Services\AdministrationBillingService;
use App\Support\ContactEmailRegistry;
use App\Support\PanelSelectionResolver;
use Illuminate\Support\Facades\Hash;

class ConfigurationController extends Controller
{
    use \App\Http\Controllers\Concerns\AutoSelectsPanelScope;

    /**
     * Mostrar la vista principal de configuración
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $configurationEntityScoped = false;
        $configurationAdministrationScoped = false;
        $scopedEntityId = null;
        $scopedAdministrationId = null;
        $settingsEntity = null;
        $settingsAdministration = null;
        $settingsAdministrationEntities = collect();
        $settingsLogEntityId = null;
        $settingsManagers = collect();
        $settingsPanelUser = null;
        $entityEmailLogs = collect();

        $defaultSection = 'datos-partilot';
        if ($user && $user->isEntityPanelAccount()) {
            $defaultSection = 'datos-entidad';
        } elseif ($user && $user->isAdministrationPanelAccount()) {
            $defaultSection = 'datos-administracion';
        }
        $section = $request->get('section', $defaultSection);
        $step = (int) $request->get('step', 1);
        $entityId = $request->get('entity_id');

        if ($user && $user->isPrintShop()) {
            return redirect()->route('print-shop.index');
        }

        if ($user && ! $user->canAccessConfigurationSection($section)) {
            $allowed = $user->allowedConfigurationSections();
            $section = $allowed[0] ?? $defaultSection;
        }

        $configurationEntityScoped = (bool) ($user && $user->isEntityPanelAccount());
        $configurationAdministrationScoped = (bool) ($user && $user->isAdministrationPanelAccount());
        $scopedEntityId = $user?->scopedConfigurationEntityId();
        $scopedAdministrationId = $user?->scopedConfigurationAdministrationId();

        if ($configurationAdministrationScoped && $scopedAdministrationId) {
            $settingsAdministrationEntities = Entity::forUser($user)->orderBy('name')->get();
            if ($request->filled('entity_id')) {
                $candidateEntityId = (int) $request->get('entity_id');
                if ($settingsAdministrationEntities->contains('id', $candidateEntityId)) {
                    $settingsLogEntityId = $candidateEntityId;
                }
            }
            if (! $settingsLogEntityId && $settingsAdministrationEntities->count() === 1) {
                $settingsLogEntityId = (int) $settingsAdministrationEntities->first()->id;
            }
        }

        if ($redirect = $this->redirectConfigurationIfImplicitEntity($request, 'codigos-recarga', 2, $entityId ? (int) $entityId : null, $step)) {
            if ($section === 'codigos-recarga') {
                return $redirect;
            }
        }

        if ($redirect = $this->redirectConfigurationIfImplicitEntity($request, 'ordenes-pago-entidades', 2, $entityId ? (int) $entityId : null, $step)) {
            if ($section === 'ordenes-pago-entidades') {
                return $redirect;
            }
        }

        if ($configurationEntityScoped && $scopedEntityId) {
            if (in_array($section, ['codigos-recarga', 'ordenes-pago-entidades'], true)) {
                $entityId = $scopedEntityId;
                if ($section === 'codigos-recarga' && ((int) $step === 1 || ! $request->get('entity_id'))) {
                    $step = 2;
                } elseif ($section === 'ordenes-pago-entidades' && ((int) $step === 1 || ! $request->get('entity_id'))) {
                    $step = 2;
                }
            }
            if ($section === 'datos-partilot') {
                $section = 'datos-entidad';
            }
        }

        // Para gestores de entidad (sin cuenta panel), Ajustes solo permite secciones de pagos.
        if ($user && $user->isEntityManagerWithoutPanelAccount()) {
            if (!$user->hasEntityManagerPermission('payments')) {
                abort(403, 'No tienes permisos para acceder a Ajustes.');
            }
            $allowedManagerSections = ['ordenes-pago-entidades', 'codigos-recarga'];
            if (! in_array($section, $allowedManagerSections, true)) {
                $section = 'ordenes-pago-entidades';
            }
        }

        $entities = collect();
        $entity = null;
        $collections = collect();
        $unverifiedCollections = collect();
        $collectionTab = 'pending';
        $sepaOrders = collect();
        $sepaOrder = null;
        $provincias = collect();
        $localidades = collect();
        $printConfiguration = null;
        $printConfigurations = collect();
        $printShopPanelUser = null;
        $printOrders = collect();
        $printOrderAuditsByOrderId = collect();
        $printOrderIssuesById = [];
        $printOrdersReconciliationFilter = 'all';
        $provinces = [];
        $provinceCityMap = [];
        $participationDonations = collect();

        $logTab = 'partilot';
        $logsProvincias = collect();
        $logsLocalidades = collect();
        $logsEntities = collect();
        $logsAdministrations = collect();
        $logsManagers = collect();
        $logsSellers = collect();
        $logsUsersPicker = collect();
        $selectedLogAdministration = null;
        $selectedLogEntity = null;
        $selectedLogManager = null;
        $selectedLogSeller = null;
        $selectedLogUser = null;

        if ($section === 'logs-actividad') {
            $allowedTabs = ['partilot', 'administracion', 'entidades', 'vendedores', 'usuarios'];
            $logTab = $request->get('log_tab', 'partilot');
            if (! in_array($logTab, $allowedTabs, true)) {
                $logTab = 'partilot';
            }

            $logsProvincias = Entity::forUser($user)->whereNotNull('province')->where('province', '!=', '')->distinct()->pluck('province')->sort()->values();
            $logsLocalidades = Entity::forUser($user)->whereNotNull('city')->where('city', '!=', '')->distinct()->pluck('city')->sort()->values();

            $entQ = Entity::with('administration')->forUser($user);
            if ($request->filled('provincia')) {
                $entQ->where('province', $request->provincia);
            }
            if ($request->filled('localidad')) {
                $entQ->where('city', $request->localidad);
            }
            if ($request->filled('busqueda')) {
                $q = $request->busqueda;
                $entQ->where(function ($qry) use ($q) {
                    $qry->where('name', 'like', '%'.$q.'%')
                        ->orWhere('province', 'like', '%'.$q.'%')
                        ->orWhere('city', 'like', '%'.$q.'%');
                });
            }
            $logsEntities = $entQ->orderBy('name')->get();

            $logsAdministrations = Administration::forUser($user)->orderBy('name')->get();

            $adminId = $request->get('administration_id');
            if ($adminId && $user->canAccessAdministration((int) $adminId)) {
                $selectedLogAdministration = Administration::query()->find((int) $adminId);
            }

            $leId = $request->get('entity_id');
            if ($leId) {
                $selectedLogEntity = Entity::with('administration')->forUser($user)->find((int) $leId);
            }

            $lmId = $request->get('manager_id');
            if ($selectedLogEntity && $lmId) {
                $selectedLogManager = Manager::with('user')
                    ->where('entity_id', $selectedLogEntity->id)
                    ->find((int) $lmId);
            }

            $lsId = $request->get('seller_id');
            if ($selectedLogEntity && $lsId) {
                $selectedLogSeller = Seller::query()
                    ->whereHas('entities', fn ($q) => $q->where('entities.id', $selectedLogEntity->id))
                    ->find((int) $lsId);
            }

            $luId = $request->get('target_user_id');
            if ($luId) {
                $candidateUser = User::query()->find((int) $luId);
                if ($candidateUser && ! $candidateUser->isSuperAdmin() && ! $candidateUser->isPanelAccount()) {
                    $selectedLogUser = $candidateUser;
                }
            }

            if ($selectedLogEntity) {
                $logsManagers = Manager::with('user')
                    ->where('entity_id', $selectedLogEntity->id)
                    ->orderByDesc('is_primary')
                    ->orderBy('id')
                    ->get();

                $logsSellers = Seller::with('user')
                    ->whereHas('entities', fn ($q) => $q->where('entities.id', $selectedLogEntity->id))
                    ->orderBy('name')
                    ->orderBy('last_name')
                    ->get();
            }

            // Solo usuarios “normales”: sin cuenta panel de administración/entidad ni rol super administrador.
            $logsUsersPicker = User::query()
                ->withoutPanelAccount()
                ->where(function ($q) {
                    $q->whereNull('role')->orWhere('role', '!=', User::ROLE_SUPER_ADMIN);
                })
                ->orderBy('name')
                ->limit(500)
                ->get(['id', 'name', 'last_name', 'last_name2', 'email', 'phone', 'status']);
        }

        if ($section === 'codigos-recarga') {
            if ((int) $step === 2 && ! $entityId) {
                return redirect()->route('configuration.index', ['section' => 'codigos-recarga', 'step' => 1]);
            }
            if ($step === 1 || ! $entityId) {
                $provincias = Entity::forUser($user)->whereNotNull('province')->where('province', '!=', '')->distinct()->pluck('province')->sort()->values();
                $localidades = Entity::forUser($user)->whereNotNull('city')->where('city', '!=', '')->distinct()->pluck('city')->sort()->values();
                $query = Entity::with('administration')->forUser($user);
                if ($request->filled('provincia')) {
                    $query->where('province', $request->provincia);
                }
                if ($request->filled('localidad')) {
                    $query->where('city', $request->localidad);
                }
                if ($request->filled('busqueda')) {
                    $q = $request->busqueda;
                    $query->where(function ($qry) use ($q) {
                        $qry->where('name', 'like', '%'.$q.'%')
                            ->orWhere('province', 'like', '%'.$q.'%')
                            ->orWhere('city', 'like', '%'.$q.'%');
                    });
                }
                $entities = $query->orderBy('name')->get();
            }

            if ($entityId && (int) $step === 2) {
                $entity = Entity::with('administration')->forUser($request->user())->find($entityId);
                if (! $entity) {
                    return redirect()->route('configuration.index', ['section' => 'codigos-recarga', 'step' => 1]);
                }
                $participationDonations = $this->participationDonationsForEntity((int) $entity->id);
            }
        }

        if ($section === 'ordenes-pago-entidades') {
            if ($step === 1 || !$entityId) {
                $provincias = Entity::forUser($user)->whereNotNull('province')->where('province', '!=', '')->distinct()->pluck('province')->sort()->values();
                $localidades = Entity::forUser($user)->whereNotNull('city')->where('city', '!=', '')->distinct()->pluck('city')->sort()->values();
                $query = Entity::with('administration')->forUser($user);
                if ($request->filled('provincia')) {
                    $query->where('province', $request->provincia);
                }
                if ($request->filled('localidad')) {
                    $query->where('city', $request->localidad);
                }
                if ($request->filled('busqueda')) {
                    $q = $request->busqueda;
                    $query->where(function ($qry) use ($q) {
                        $qry->where('name', 'like', '%' . $q . '%')
                            ->orWhere('province', 'like', '%' . $q . '%')
                            ->orWhere('city', 'like', '%' . $q . '%');
                    });
                }
                $entities = $query->orderBy('name')->get();
            }

            if ($entityId && in_array($step, [2, 3], true)) {
                $entity = Entity::with('administration')->forUser($request->user())->find($entityId);
                if (!$entity) {
                    return redirect()->route('configuration.index', ['section' => 'ordenes-pago-entidades', 'step' => 1]);
                }
            }

            if ($entity && $step === 2) {
                $sepaOrders = SepaPaymentOrder::where('administration_id', $entity->administration_id)
                    ->with('beneficiaries')
                    ->orderBy('created_at', 'desc')
                    ->get();
            }

            if ($entity && $step === 3) {
                $orderId = $request->get('order_id');
                if ($orderId && $entity->administration_id) {
                    $sepaOrder = SepaPaymentOrder::where('administration_id', $entity->administration_id)
                        ->with([
                            'beneficiaries' => fn ($q) => $q->with([
                                'participationCollection.items.participation.set.reserve.lottery',
                            ]),
                        ])
                        ->find($orderId);
                }
                if (!$sepaOrder) {
                    $collectionTab = $request->get('tab', 'pending');
                    $baseQuery = ParticipationCollection::whereHas('items.participation', function ($q) use ($entityId) {
                        $q->where('entity_id', $entityId);
                    });

                    $unverifiedCollections = (clone $baseQuery)->pendingVerification()
                        ->with(['user', 'items'])
                        ->orderBy('created_at', 'desc')
                        ->get();

                    if ($collectionTab === 'unverified') {
                        $collections = $unverifiedCollections;
                    } else {
                        $collectionsQuery = (clone $baseQuery);
                        if (Schema::hasColumn('participation_collections', 'sepa_payment_order_id')) {
                            $collectionsQuery = $collectionsQuery->pending();
                        } else {
                            $collectionsQuery = $collectionsQuery->verified();
                        }
                        $collections = $collectionsQuery->with(['user', 'items'])->orderBy('created_at', 'desc')->get();
                    }
                }
            }
        }

        if ($section === 'imprenta') {
            $printConfigurations = PrintConfiguration::query()
                ->orderedOldestFirst()
                ->get();
            $printConfiguration = null;
            $printShopPanelUser = null;
            $printConfigParam = $request->query('print_config_id');
            if ($printConfigParam === 'new') {
                $hasDefault = PrintConfiguration::query()->default()->exists();
                $printConfiguration = new PrintConfiguration([
                    'status' => $hasDefault
                        ? PrintConfiguration::STATUS_NOT_DEFAULT
                        : PrintConfiguration::STATUS_DEFAULT,
                ]);
                [$provinces, $provinceCityMap] = $this->getProvinceCityData();
            } elseif (is_numeric($printConfigParam) && (int) $printConfigParam > 0) {
                $printConfiguration = PrintConfiguration::findOrFail((int) $printConfigParam);
                $printShopPanelUser = app(\App\Services\PrintShopPanelUserService::class)->panelUser($printConfiguration);
                [$provinces, $provinceCityMap] = $this->getProvinceCityData();
            }
        }

        if ($section === 'ordenes-imprenta') {
            $printOrders = PrintOrder::query()
                ->whereIn('entity_id', $user->accessibleEntityIds())
                ->with(['entity', 'set', 'lottery', 'printConfiguration'])
                ->orderByDesc('id')
                ->limit(200)
                ->get();

            $reconciliationService = app(\App\Services\PrintOrderPaymentReconciliationService::class);
            $printOrderIssuesById = [];
            foreach ($printOrders as $order) {
                $issue = $reconciliationService->detectIssue($order);
                if ($issue) {
                    $printOrderIssuesById[$order->id] = $issue;
                }
            }
            $printOrdersReconciliationFilter = request()->query('reconciliation', 'all');

            $orderIds = $printOrders->pluck('id')->all();
            if (!empty($orderIds)) {
                $audits = DB::table('print_order_status_audits')
                    ->whereIn('print_order_id', $orderIds)
                    ->orderByDesc('id')
                    ->get();
                $userIds = $audits->pluck('user_id')->filter()->unique()->values()->all();
                $usersById = empty($userIds)
                    ? collect()
                    : DB::table('users')->whereIn('id', $userIds)->pluck('name', 'id');

                $printOrderAuditsByOrderId = $audits
                    ->map(function ($row) use ($usersById) {
                        $row->user_name = $row->user_id ? ($usersById[$row->user_id] ?? ('Usuario #' . $row->user_id)) : 'Sistema';
                        return $row;
                    })
                    ->groupBy('print_order_id');
            }
        }

        if ($section === 'datos-entidad' && $scopedEntityId) {
            $settingsEntity = Entity::with(['managers.user', 'administration'])
                ->forUser($user)
                ->findOrFail($scopedEntityId);
            $settingsPanelUser = User::query()
                ->where('panel_account_type', 'entity')
                ->where('panel_account_id', $scopedEntityId)
                ->first();
            $settingsManagers = $settingsEntity->managers
                ->filter(fn (Manager $manager) => ! ($manager->user && $manager->user->isPanelAccount()))
                ->values();
            [$provinces, $provinceCityMap] = $this->getProvinceCityData();
        }

        if ($section === 'datos-administracion' && $scopedAdministrationId) {
            $settingsAdministration = Administration::forUser($user)->findOrFail($scopedAdministrationId);
            $settingsPanelUser = User::query()
                ->where('panel_account_type', 'administration')
                ->where('panel_account_id', $scopedAdministrationId)
                ->first();
            [$provinces, $provinceCityMap] = $this->getProvinceCityData();
        }

        if ($section === 'facturacion-cobros' && $scopedEntityId) {
            $settingsEntity = Entity::forUser($user)->findOrFail($scopedEntityId);
        }

        if ($section === 'facturacion-cobros' && $scopedAdministrationId) {
            $settingsAdministration = Administration::forUser($user)->findOrFail($scopedAdministrationId);
        }

        $showBillingRemittancePanel = false;
        $billingAdministrations = collect();
        $billingAdministrationId = null;
        $billingAdministration = null;
        $billingPendingCharges = collect();
        $billingAllCharges = collect();
        $billingDirectDebitOrders = collect();
        $billingDirectDebitOrder = null;
        $billingView = 'charges';
        $billingChargeStatus = '';

        if (
            $section === 'facturacion-cobros'
            && $user?->isSuperAdmin()
            && ! $configurationEntityScoped
            && ! $configurationAdministrationScoped
        ) {
            $showBillingRemittancePanel = true;
            $billingAdministrations = Administration::query()
                ->orderBy('name')
                ->get();

            $billingView = $request->get('billing_view') === 'order' ? 'order' : 'charges';
            $billingChargeStatus = (string) $request->get('billing_charge_status', '');

            if ($request->filled('billing_administration_id')) {
                $billingAdministrationId = (int) $request->get('billing_administration_id');
            }

            if ($request->filled('billing_order_id')) {
                $billingDirectDebitOrder = BillingDirectDebitOrder::query()
                    ->with(['administration', 'charges.entity', 'createdByUser'])
                    ->find((int) $request->get('billing_order_id'));

                if ($billingDirectDebitOrder) {
                    $billingAdministrationId = (int) $billingDirectDebitOrder->administration_id;
                    $billingView = 'order';
                }
            }

            if ($billingAdministrationId) {
                $billingAdministration = $billingAdministrations->firstWhere('id', $billingAdministrationId)
                    ?? Administration::query()->find($billingAdministrationId);

                if ($billingAdministration) {
                    $billingService = app(AdministrationBillingService::class);
                    $billingPendingCharges = $billingService->pendingChargesForAdministration($billingAdministration->id);
                    $billingAllCharges = $billingService->chargesForAdministration(
                        $billingAdministration->id,
                        $billingChargeStatus !== '' ? $billingChargeStatus : null
                    );
                    $billingDirectDebitOrders = BillingDirectDebitOrder::query()
                        ->withCount('charges')
                        ->where('administration_id', $billingAdministration->id)
                        ->orderByDesc('id')
                        ->get();
                }
            }
        }

        if ($section === 'logs-emails' && $scopedEntityId) {
            $entityEmailLogs = EmailCommunicationLog::query()
                ->orderByDesc('created_at')
                ->limit(500)
                ->get()
                ->filter(fn (EmailCommunicationLog $log) => (int) data_get($log->context, 'entity_id') === (int) $scopedEntityId)
                ->take(200)
                ->values();
        }

        if (in_array($section, ['logs-emails', 'logs-notificaciones'], true) && $settingsLogEntityId) {
            $entityEmailLogs = EmailCommunicationLog::query()
                ->orderByDesc('created_at')
                ->limit(500)
                ->get()
                ->filter(fn (EmailCommunicationLog $log) => (int) data_get($log->context, 'entity_id') === (int) $settingsLogEntityId)
                ->take(200)
                ->values();
        }

        $partilotBillingSettings = null;
        if ($section === 'config-factura-auto') {
            $partilotBillingSettings = PartilotBillingSetting::current();
        }

        return view('configuration.index', compact(
            'section',
            'step',
            'entityId',
            'entities',
            'entity',
            'collections',
            'unverifiedCollections',
            'collectionTab',
            'sepaOrders',
            'sepaOrder',
            'provincias',
            'localidades',
            'printConfigurations',
            'printConfiguration',
            'printShopPanelUser',
            'printOrders',
            'printOrderAuditsByOrderId',
            'printOrderIssuesById',
            'printOrdersReconciliationFilter',
            'provinces',
            'provinceCityMap',
            'participationDonations',
            'logTab',
            'logsProvincias',
            'logsLocalidades',
            'logsEntities',
            'logsAdministrations',
            'logsManagers',
            'logsSellers',
            'logsUsersPicker',
            'selectedLogAdministration',
            'selectedLogEntity',
            'selectedLogManager',
            'selectedLogSeller',
            'selectedLogUser',
            'configurationEntityScoped',
            'configurationAdministrationScoped',
            'scopedEntityId',
            'scopedAdministrationId',
            'settingsEntity',
            'settingsAdministration',
            'settingsAdministrationEntities',
            'settingsLogEntityId',
            'settingsManagers',
            'settingsPanelUser',
            'entityEmailLogs',
            'partilotBillingSettings',
            'showBillingRemittancePanel',
            'billingAdministrations',
            'billingAdministrationId',
            'billingAdministration',
            'billingPendingCharges',
            'billingAllCharges',
            'billingDirectDebitOrders',
            'billingDirectDebitOrder',
            'billingView',
            'billingChargeStatus'
        ));
    }

    /**
     * Donaciones cuya primera fila en participation_donation_items (por id) apunta a una participación de la entidad dada.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ParticipationDonation>
     */
    private function participationDonationsForEntity(int $entityId)
    {
        return ParticipationDonation::query()
            ->whereIn('id', function ($q) use ($entityId) {
                $q->select('pdi.donation_id')
                    ->from('participation_donation_items as pdi')
                    ->join('participations as p', 'p.id', '=', 'pdi.participation_id')
                    ->where('p.entity_id', $entityId)
                    ->whereRaw('pdi.id = (SELECT MIN(pdi2.id) FROM participation_donation_items pdi2 WHERE pdi2.donation_id = pdi.donation_id)');
            })
            ->orderByDesc('donated_at')
            ->orderByDesc('id')
            ->get();
    }

    private function getProvinceCityData(): array
    {
        try {
            return Cache::rememberForever('es_official_province_city_catalog', function () {
                $cachePath = 'catalogs/es_province_city_catalog.json';
                if (Storage::disk('local')->exists($cachePath)) {
                    $stored = json_decode((string) Storage::disk('local')->get($cachePath), true);
                    if (
                        is_array($stored)
                        && isset($stored['provinces'], $stored['provinceCityMap'])
                        && is_array($stored['provinces'])
                        && is_array($stored['provinceCityMap'])
                    ) {
                        return [$stored['provinces'], $stored['provinceCityMap']];
                    }
                }

                $provincesUrl = 'https://raw.githubusercontent.com/codeforspain/ds-organizacion-administrativa/master/data/provincias.json';
                $citiesUrl = 'https://raw.githubusercontent.com/codeforspain/ds-organizacion-administrativa/master/data/municipios.json';

                $provincesResponse = Http::timeout(20)->get($provincesUrl);
                $citiesResponse = Http::timeout(25)->get($citiesUrl);
                if (! $provincesResponse->ok() || ! $citiesResponse->ok()) {
                    throw new \RuntimeException('No se pudo descargar catálogo de provincias/municipios.');
                }

                $provincesData = $provincesResponse->json();
                $citiesData = $citiesResponse->json();
                if (! is_array($provincesData) || ! is_array($citiesData)) {
                    throw new \RuntimeException('Catálogo de provincias/municipios inválido.');
                }

                $provinceNamesById = [];
                foreach ($provincesData as $province) {
                    $provinceId = trim((string) ($province['provincia_id'] ?? ''));
                    $name = trim((string) ($province['nombre'] ?? ''));
                    if ($provinceId !== '' && $name !== '') {
                        $provinceNamesById[$provinceId] = $name;
                    }
                }

                $provinceCityMap = [];
                foreach ($citiesData as $city) {
                    $provinceId = trim((string) ($city['provincia_id'] ?? ''));
                    $cityName = trim((string) ($city['nombre'] ?? ''));
                    if ($provinceId === '' || $cityName === '' || ! isset($provinceNamesById[$provinceId])) {
                        continue;
                    }
                    $provinceName = $provinceNamesById[$provinceId];
                    $provinceCityMap[$provinceName] ??= [];
                    $provinceCityMap[$provinceName][$cityName] = true;
                }

                ksort($provinceCityMap, SORT_NATURAL | SORT_FLAG_CASE);
                foreach ($provinceCityMap as $province => $citiesSet) {
                    $cities = array_keys($citiesSet);
                    sort($cities, SORT_NATURAL | SORT_FLAG_CASE);
                    $provinceCityMap[$province] = $cities;
                }

                $provinces = array_keys($provinceCityMap);
                Storage::disk('local')->put($cachePath, json_encode([
                    'provinces' => $provinces,
                    'provinceCityMap' => $provinceCityMap,
                ], JSON_UNESCAPED_UNICODE));

                return [$provinces, $provinceCityMap];
            });
        } catch (\Throwable $e) {
            Log::warning('No se pudo cargar catálogo oficial provincias/localidades para imprenta, usando fallback local.', [
                'error' => $e->getMessage(),
            ]);

            $rows = PrintConfiguration::query()
                ->select('province', 'city')
                ->whereNotNull('province')
                ->where('province', '!=', '')
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->get();

            $provinceCityMap = [];
            foreach ($rows as $row) {
                $province = trim((string) $row->province);
                $city = trim((string) $row->city);
                if ($province === '' || $city === '') {
                    continue;
                }
                $provinceCityMap[$province] ??= [];
                $provinceCityMap[$province][$city] = true;
            }

            ksort($provinceCityMap, SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($provinceCityMap as $province => $citiesSet) {
                $cities = array_keys($citiesSet);
                sort($cities, SORT_NATURAL | SORT_FLAG_CASE);
                $provinceCityMap[$province] = $cities;
            }

            return [array_keys($provinceCityMap), $provinceCityMap];
        }
    }

    public function updateImprenta(Request $request)
    {
        if (! $request->user()?->isSuperAdmin()) {
            abort(403, 'Solo super administrador puede modificar la configuración de imprenta.');
        }

        $isNew = $request->input('print_configuration_id') === 'new'
            || ! $request->filled('print_configuration_id');

        $rules = [
            'company_name' => 'nullable|string|max:255',
            'nif_cif' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'province' => 'nullable|string|max:120',
            'city' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'price_design' => 'nullable|numeric|min:0',
            'price_participation' => 'nullable|numeric|min:0',
            'price_back_bw' => 'nullable|numeric|min:0',
            'price_back_color' => 'nullable|numeric|min:0',
            'price_taco_25' => 'nullable|numeric|min:0',
            'price_taco_50' => 'nullable|numeric|min:0',
            'price_taco_100' => 'nullable|numeric|min:0',
            'bank_account' => 'nullable|string|max:80',
            'stripe_publishable_key' => 'nullable|string|max:255',
            'stripe_secret_key' => 'nullable|string|max:2000',
            'stripe_webhook_secret' => 'nullable|string|max:2000',
        ];
        if (! $isNew) {
            $rules['print_configuration_id'] = 'required|integer|exists:print_configurations,id';
        } else {
            $rules['print_configuration_id'] = 'nullable';
        }

        $data = $request->validate($rules);

        if ($isNew) {
            $config = new PrintConfiguration();
        } else {
            $config = PrintConfiguration::findOrFail((int) $data['print_configuration_id']);
        }
        unset($data['print_configuration_id']);
        $config->fill($data);
        $config->save();

        $makeDefault = $request->boolean('status');
        if (! $makeDefault && $isNew && ! PrintConfiguration::query()->default()->exists()) {
            $makeDefault = true;
        }

        if ($makeDefault) {
            PrintConfiguration::assignDefault($config);
        } else {
            $config->status = PrintConfiguration::STATUS_NOT_DEFAULT;
            $config->save();

            if (! PrintConfiguration::query()->default()->exists()) {
                $fallback = PrintConfiguration::query()
                    ->where('id', '!=', $config->id)
                    ->orderedOldestFirst()
                    ->first();
                if ($fallback) {
                    PrintConfiguration::assignDefault($fallback);
                }
            }
        }

        $message = $isNew
            ? 'Imprenta creada correctamente.'
            : 'Configuración de imprenta actualizada correctamente.';

        return redirect()->route('configuration.index', ['section' => 'imprenta', 'print_config_id' => $config->id])
            ->with('success', $message);
    }

    public function updatePartilotBilling(Request $request)
    {
        if (! $request->user()?->isSuperAdmin()) {
            abort(403, 'Solo super administrador puede modificar la configuración de facturación PARTILOT.');
        }

        $data = $request->validate([
            'company_name' => 'required|string|max:255',
            'nif_cif' => 'required|string|max:50',
            'address' => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'province' => 'required|string|max:120',
            'city' => 'required|string|max:120',
            'phone' => 'required|string|max:50',
            'email' => 'required|email|max:255',
            'fee_per_participation_1000' => 'required|numeric|min:0',
            'fee_per_participation_5000' => 'required|numeric|min:0',
            'fee_per_participation_10000' => 'required|numeric|min:0',
            'fee_administration_per_participation' => 'required|numeric|min:0',
            'payment_management_commission' => 'required|numeric|min:0',
            'bank_account' => 'required|string|max:80',
            'sepa_creditor_id' => 'nullable|string|max:35',
            'stripe_publishable_key' => 'nullable|string|max:255',
            'stripe_secret_key' => 'nullable|string|max:2000',
            'stripe_webhook_secret' => 'nullable|string|max:2000',
        ]);

        $settings = PartilotBillingSetting::current();
        $settings->fill($data);
        $settings->save();

        return redirect()->route('configuration.index', ['section' => 'config-factura-auto'])
            ->with('success', 'Configuración de facturación PARTILOT guardada correctamente.');
    }

    public function updatePrintShopPanelAccess(Request $request)
    {
        if (! $request->user()?->isSuperAdmin()) {
            abort(403, 'Solo super administrador puede gestionar el acceso de imprenta.');
        }

        $data = $request->validate([
            'print_configuration_id' => 'required|integer|exists:print_configurations,id',
            'panel_email' => 'required|email|max:255',
            'panel_password' => 'nullable|string|min:8|confirmed',
        ], [
            'panel_email.required' => 'Indica el email de acceso al panel de imprenta.',
            'panel_password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'panel_password.confirmed' => 'La confirmación de contraseña no coincide.',
        ]);

        $config = PrintConfiguration::findOrFail((int) $data['print_configuration_id']);
        unset($data['print_configuration_id']);

        try {
            $user = app(\App\Services\PrintShopPanelUserService::class)->upsertPanelUser($config, $data);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('configuration.index', [
                'section' => 'imprenta',
                'print_config_id' => $config->id,
            ])->with('error', $e->getMessage());
        }

        return redirect()->route('configuration.index', [
            'section' => 'imprenta',
            'print_config_id' => $config->id,
        ])->with('success', 'Acceso al panel de imprenta actualizado. Usuario: '.($user->panel_login_username ?? '—'));
    }

    public function updatePrintOrderStatus(Request $request, PrintOrder $printOrder)
    {
        if (! in_array((int) $printOrder->entity_id, $request->user()->accessibleEntityIds(), true)) {
            abort(403, 'No tienes permisos para modificar esta orden de imprenta.');
        }
        if (! $this->canManagePrintOrderWorkflow($request->user())) {
            return redirect()->route('configuration.index', ['section' => 'ordenes-imprenta'])
                ->with('error', 'No tienes permisos operativos para cambiar el estado de órdenes de imprenta.');
        }

        $data = $request->validate([
            'target_status' => 'required|string|in:pendiente_revision,en_produccion,enviada,rechazada',
        ]);

        $target = $data['target_status'];
        if (! $printOrder->canTransitionTo($target)) {
            $reason = $printOrder->paymentTransitionBlockReason();

            return redirect()->route('configuration.index', ['section' => 'ordenes-imprenta'])
                ->with('error', $reason ?? 'Transición de estado no permitida para esta orden.');
        }

        $from = (string) $printOrder->status;
        $printOrder->status = $target;
        if ($target === PrintOrder::STATUS_SENT && ! $printOrder->sent_at) {
            $printOrder->sent_at = now();
        }
        $printOrder->save();
        $this->logPrintOrderStatusAudit(
            printOrder: $printOrder,
            action: 'status_change',
            fromStatus: $from,
            toStatus: $target,
            message: 'Cambio de estado de orden de imprenta',
        );

        return redirect()->route('configuration.index', ['section' => 'ordenes-imprenta'])
            ->with('success', 'Estado de la orden actualizado a: ' . PrintOrder::statusLabel($target) . '.');
    }

    public function reconcilePrintOrderPayment(Request $request, PrintOrder $printOrder)
    {
        if (! in_array((int) $printOrder->entity_id, $request->user()->accessibleEntityIds(), true)) {
            abort(403, 'No tienes permisos para esta orden de imprenta.');
        }
        if (! $this->canManagePrintOrderWorkflow($request->user())) {
            return redirect()->route('configuration.index', ['section' => 'ordenes-imprenta'])
                ->with('error', 'No tienes permisos para conciliar pagos de imprenta.');
        }

        $result = app(\App\Services\PrintOrderPaymentReconciliationService::class)
            ->reconcile($printOrder, dryRun: false);

        $flash = ($result['ok'] ?? false) ? 'success' : 'error';

        return redirect()->route('configuration.index', ['section' => 'ordenes-imprenta', 'reconciliation' => 'issues'])
            ->with($flash, $result['message'] ?? 'Conciliación finalizada.');
    }

    private function canManagePrintOrderWorkflow($user): bool
    {
        return $user && ($user->isSuperAdmin() || $user->isAdministration() || $user->isPrintShop());
    }

    private function logPrintOrderStatusAudit(
        PrintOrder $printOrder,
        string $action,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $message = null
    ): void {
        try {
            DB::table('print_order_status_audits')->insert([
                'print_order_id' => $printOrder->id,
                'entity_id' => $printOrder->entity_id,
                'set_id' => $printOrder->set_id,
                'design_format_id' => $printOrder->design_format_id,
                'user_id' => auth()->id(),
                'action' => $action,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'message' => $message,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('No se pudo registrar auditoría de estado de imprenta: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar un beneficiario de una orden SEPA (cuenta vinculada).
     * Si el beneficiario está vinculado a una participation_collection, se borra dicha solicitud de cobro
     * para que desde la app el usuario pueda volver a cobrarla creando una nueva (llenando los datos de nuevo).
     */
    public function destroyBeneficiary(Request $request, SepaPaymentBeneficiary $sepaPaymentBeneficiary)
    {
        $request->validate([
            'entity_id' => 'required|exists:entities,id',
            'order_id' => 'required|exists:sepa_payment_orders,id',
        ]);
        $entity = Entity::with('administration')->forUser($request->user())->findOrFail($request->entity_id);
        $order = $sepaPaymentBeneficiary->paymentOrder;
        if (!$order || (int) $order->id !== (int) $request->order_id || $order->administration_id != $entity->administration_id) {
            return redirect()->route('configuration.index', ['section' => 'ordenes-pago-entidades', 'step' => 1])
                ->with('error', 'No tiene permiso para modificar esta orden.');
        }

        try {
            DB::beginTransaction();
            $collection = $sepaPaymentBeneficiary->participationCollection;
            $sepaPaymentBeneficiary->delete();
            if ($collection) {
                $collection->delete();
            }
            $remaining = SepaPaymentBeneficiary::where('sepa_payment_order_id', $order->id)->get();
            $order->update([
                'number_of_transactions' => $remaining->count(),
                'control_sum' => $remaining->sum('amount'),
            ]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('configuration.index', [
                'section' => 'ordenes-pago-entidades', 'step' => 3,
                'entity_id' => $entity->id, 'order_id' => $order->id,
            ])->with('error', 'Error al eliminar: ' . $e->getMessage());
        }

        return redirect()->route('configuration.index', [
            'section' => 'ordenes-pago-entidades', 'step' => 3,
            'entity_id' => $entity->id, 'order_id' => $order->id,
        ])->with('success', 'Cuenta eliminada de la orden. La solicitud de cobro se ha borrado; desde la app se puede volver a cobrar llenando los datos de nuevo.');
    }

    /**
     * Eliminar una participation_collection (orden de transferencia)
     */
    public function destroyCollection(Request $request, ParticipationCollection $participationCollection)
    {
        $participationCollection->delete();
        $entityId = $request->query('entity_id') ?: $request->input('entity_id');
        $redirect = $entityId
            ? redirect()->route('configuration.index', ['section' => 'ordenes-pago-entidades', 'step' => 3, 'entity_id' => $entityId])
            : redirect()->route('configuration.index', ['section' => 'ordenes-pago-entidades', 'step' => 1]);
        return $redirect->with('success', 'Orden de transferencia eliminada.');
    }

    /**
     * Crear orden SEPA desde las participation_collections de la entidad y ofrecer descarga XML
     */
    public function crearSepa(Request $request)
    {
        $request->validate(['entity_id' => 'required|exists:entities,id']);
        $entity = Entity::with('administration')->forUser($request->user())->findOrFail($request->entity_id);
        $collections = ParticipationCollection::whereHas('items.participation', function ($q) use ($entity) {
            $q->where('entity_id', $entity->id);
        });
        if (Schema::hasColumn('participation_collections', 'sepa_payment_order_id')) {
            $collections = $collections->pending();
        }
        $collections = $collections->with('user')->get();

        if ($collections->isEmpty()) {
            return redirect()->route('configuration.index', ['section' => 'ordenes-pago-entidades', 'step' => 3, 'entity_id' => $entity->id])
                ->with('error', 'No hay peticiones de cobro para generar la orden SEPA.');
        }

        $administration = $entity->administration;
        $debtorName = $administration ? $administration->name : $entity->name;
        $debtorNif = $administration ? $administration->nif_cif : $entity->nif_cif ?? null;
        $debtorAddress = $administration ? $administration->address : $entity->address ?? null;
        $debtorIban = $administration && $administration->account ? $this->normalizeIban($administration->account) : null;
        if (!$debtorIban) {
            return redirect()->route('configuration.index', ['section' => 'ordenes-pago-entidades', 'step' => 3, 'entity_id' => $entity->id])
                ->with('error', 'La administración o entidad no tiene cuenta bancaria configurada.');
        }

        try {
            DB::beginTransaction();
            $totalAmount = $collections->sum('importe_total');
            $order = SepaPaymentOrder::create([
                'administration_id' => $entity->administration_id,
                'message_id' => SepaPaymentOrder::generateMessageId(),
                'creation_date' => now(),
                'execution_date' => now()->addDays(1),
                'number_of_transactions' => $collections->count(),
                'control_sum' => $totalAmount,
                'payment_info_id' => SepaPaymentOrder::generateMessageId(),
                'batch_booking' => true,
                'charge_bearer' => 'SLEV',
                'debtor_name' => $debtorName,
                'debtor_nif_cif' => $debtorNif,
                'debtor_iban' => $debtorIban,
                'debtor_address' => $debtorAddress,
                'status' => 'draft',
                'notes' => 'Generado desde Ordenes Pago Entidades - ' . $entity->name,
            ]);

            foreach ($collections as $col) {
                $iban = $this->normalizeIban($col->iban);
                $creditorName = trim($col->nombre . ' ' . $col->apellidos);
                SepaPaymentBeneficiary::create([
                    'sepa_payment_order_id' => $order->id,
                    'participation_collection_id' => $col->id,
                    'end_to_end_id' => SepaPaymentBeneficiary::generateEndToEndId(),
                    'amount' => $col->importe_total,
                    'currency' => 'EUR',
                    'creditor_name' => $creditorName ?: 'Beneficiario',
                    'creditor_nif_cif' => $col->nif ?? null,
                    'creditor_iban' => $iban,
                    'purpose_code' => 'CASH',
                    'remittance_info' => 'Cobro participaciones',
                ]);
                if (Schema::hasColumn('participation_collections', 'sepa_payment_order_id')) {
                    $col->update(['sepa_payment_order_id' => $order->id]);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('configuration.index', ['section' => 'ordenes-pago-entidades', 'step' => 3, 'entity_id' => $entity->id])
                ->with('error', 'Error al crear la orden SEPA: ' . $e->getMessage());
        }

        return redirect()->route('sepa-payments.generate-xml', $order->id)
            ->with('success', 'Orden SEPA creada. Descargando XML.');
    }

    private function normalizeIban(string $iban): string
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban));
        if (!str_starts_with($iban, 'ES')) {
            $iban = 'ES' . $iban;
        }
        return $iban;
    }

    /**
     * Formulario para crear nueva orden SEPA desde Ordenes Pago Entidades.
     * Los beneficiarios salen de participation_collections pendientes (no vinculadas a ninguna orden SEPA) de la entidad elegida.
     */
    public function nuevaOrdenSepa(Request $request)
    {
        $request->validate(['entity_id' => 'required|exists:entities,id']);
        $entity = Entity::with('administration')->forUser($request->user())->findOrFail($request->entity_id);

        $query = ParticipationCollection::whereHas('items.participation', function ($q) use ($entity) {
            $q->where('entity_id', $entity->id);
        });
        if (Schema::hasColumn('participation_collections', 'sepa_payment_order_id')) {
            $query->pending();
        }
        $collections = $query->with('user')->orderBy('created_at', 'desc')->get();

        $administrations = Administration::all();
        $debtorName = $entity->administration->name ?? $entity->name;
        $debtorNif = $entity->administration->nif_cif ?? $entity->nif_cif ?? '';
        $debtorAddress = $entity->administration->address ?? $entity->address ?? '';
        $debtorIban = '';
        if ($entity->administration && $entity->administration->account) {
            $debtorIban = preg_replace('/\s+/', '', $entity->administration->account);
            $debtorIban = str_starts_with(strtoupper($debtorIban), 'ES') ? substr($debtorIban, 2) : $debtorIban;
        }

        return view('configuration.ordenes-pago-entidades-nueva-orden', compact(
            'entity', 'collections', 'administrations', 'debtorName', 'debtorNif', 'debtorAddress', 'debtorIban'
        ));
    }

    /**
     * Guardar orden SEPA desde el formulario de Ordenes Pago Entidades (con opción de vincular participation_collections).
     */
    public function storeOrdenSepa(Request $request)
    {
        $validated = $request->validate([
            'entity_id' => 'required|exists:entities,id',
            'administration_id' => 'nullable|exists:administrations,id',
            'execution_date' => 'required|date|after_or_equal:today',
            'debtor_name' => 'required|string|max:255',
            'debtor_nif_cif' => ['nullable', 'string', 'max:50', new \App\Rules\SpanishDocument],
            'debtor_iban' => ['required', 'string', 'max:22', 'regex:/^[0-9]{22}$/'],
            'debtor_address' => 'nullable|string|max:500',
            'batch_booking' => 'nullable|boolean',
            'beneficiaries' => 'required|array|min:1',
            'beneficiaries.*.creditor_name' => 'required|string|max:255',
            'beneficiaries.*.creditor_nif_cif' => ['nullable', 'string', 'max:50', new \App\Rules\SpanishDocument],
            'beneficiaries.*.creditor_iban' => ['required', 'string', 'max:22', 'regex:/^[0-9]{22}$/'],
            'beneficiaries.*.amount' => 'required|numeric|min:0.01',
            'beneficiaries.*.currency' => 'required|string|size:3',
            'beneficiaries.*.purpose_code' => 'nullable|string|max:10',
            'beneficiaries.*.remittance_info' => 'nullable|string|max:500',
            'beneficiaries.*.collection_id' => 'nullable|integer|exists:participation_collections,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        $entity = Entity::with('administration')->forUser($request->user())->findOrFail($validated['entity_id']);

        $debtorIban = $validated['debtor_iban'];
        if (!str_starts_with(strtoupper($debtorIban), 'ES')) {
            $debtorIban = 'ES' . $debtorIban;
        }
        $validator = \Validator::make(['iban' => $debtorIban], ['iban' => [new \App\Rules\SpanishIban]]);
        if ($validator->fails()) {
            return back()->withErrors(['debtor_iban' => 'El IBAN del deudor no es válido.'])->withInput();
        }
        foreach ($validated['beneficiaries'] as $index => $beneficiary) {
            $creditorIban = 'ES' . $beneficiary['creditor_iban'];
            $v = \Validator::make(['iban' => $creditorIban], ['iban' => [new \App\Rules\SpanishIban]]);
            if ($v->fails()) {
                return back()->withErrors(["beneficiaries.{$index}.creditor_iban" => 'El IBAN del beneficiario no es válido.'])->withInput();
            }
        }

        try {
            DB::beginTransaction();
            $totalAmount = collect($validated['beneficiaries'])->sum('amount');
            $order = SepaPaymentOrder::create([
                'administration_id' => $validated['administration_id'] ?? $entity->administration_id,
                'message_id' => SepaPaymentOrder::generateMessageId(),
                'creation_date' => now(),
                'execution_date' => $validated['execution_date'],
                'number_of_transactions' => count($validated['beneficiaries']),
                'control_sum' => $totalAmount,
                'payment_info_id' => SepaPaymentOrder::generateMessageId(),
                'batch_booking' => (bool) ($validated['batch_booking'] ?? false),
                'charge_bearer' => 'SLEV',
                'debtor_name' => $validated['debtor_name'],
                'debtor_nif_cif' => $validated['debtor_nif_cif'] ?? null,
                'debtor_iban' => $debtorIban,
                'debtor_address' => $validated['debtor_address'] ?? null,
                'status' => 'draft',
                'notes' => ($validated['notes'] ?? null) ?: 'Orden desde Ordenes Pago Entidades - ' . $entity->name,
            ]);

            foreach ($validated['beneficiaries'] as $beneficiary) {
                $creditorIban = $beneficiary['creditor_iban'];
                if (!str_starts_with(strtoupper($creditorIban), 'ES')) {
                    $creditorIban = 'ES' . $creditorIban;
                }
                $participationCollectionId = !empty($beneficiary['collection_id']) ? (int) $beneficiary['collection_id'] : null;
                SepaPaymentBeneficiary::create([
                    'sepa_payment_order_id' => $order->id,
                    'participation_collection_id' => $participationCollectionId,
                    'end_to_end_id' => SepaPaymentBeneficiary::generateEndToEndId(),
                    'amount' => $beneficiary['amount'],
                    'currency' => $beneficiary['currency'] ?? 'EUR',
                    'creditor_name' => $beneficiary['creditor_name'],
                    'creditor_nif_cif' => $beneficiary['creditor_nif_cif'] ?? null,
                    'creditor_iban' => $creditorIban,
                    'purpose_code' => $beneficiary['purpose_code'] ?? 'CASH',
                    'remittance_info' => $beneficiary['remittance_info'] ?? null,
                ]);
                if ($participationCollectionId && Schema::hasColumn('participation_collections', 'sepa_payment_order_id')) {
                    ParticipationCollection::where('id', $participationCollectionId)
                        ->whereNull('sepa_payment_order_id')
                        ->update(['sepa_payment_order_id' => $order->id]);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->withErrors(['error' => 'Error al crear la orden: ' . $e->getMessage()]);
        }

        $redirectTo = $request->input('redirect_to', 'step2');
        if ($redirectTo === 'show') {
            return redirect()->route('sepa-payments.show', $order->id)->with('success', 'Orden de pago creada.');
        }
        return redirect()->route('configuration.index', [
            'section' => 'ordenes-pago-entidades',
            'step' => 2,
            'entity_id' => $entity->id,
        ])->with('success', 'Orden de pago creada. Seleccione la orden en la lista y pulse "Ver detalle" para ver los beneficiarios o generar el XML.');
    }

    public function updateEntitySettings(Request $request)
    {
        $user = $request->user();
        $entityId = $user?->scopedConfigurationEntityId();
        abort_unless($entityId && $user?->canMutateEntityConfiguration(), 403);

        $entity = Entity::forUser($user)->findOrFail($entityId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'province' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:10',
            'address' => 'nullable|string|max:500',
            'nif_cif' => ['nullable', 'string', 'max:20', new \App\Rules\EntityDocument],
            'phone' => 'nullable|string|max:20',
            'email' => 'required|email|max:255',
            'comments' => 'nullable|string|max:1000',
            'current_password' => 'nullable|required_with:panel_password|string',
            'panel_password' => 'nullable|string|min:8|confirmed',
        ]);

        $panelUser = User::query()
            ->where('panel_account_type', 'entity')
            ->where('panel_account_id', $entity->id)
            ->first();

        $newEntityEmail = trim((string) ($validated['email'] ?? ''));

        if ($panelUser && $newEntityEmail !== '' && $newEntityEmail !== $panelUser->email) {
            if (ContactEmailRegistry::isTaken($newEntityEmail, $panelUser->id, null, $entity->id)) {
                return back()->withInput()
                    ->withErrors(['email' => 'Este correo ya está en uso en otra administración, entidad o cuenta de usuario.']);
            }
        }

        if ($request->filled('panel_password')) {
            if (! $panelUser || ! Hash::check((string) $request->input('current_password'), $panelUser->password)) {
                return back()->withInput()->withErrors([
                    'current_password' => 'La contraseña actual no es correcta.',
                ]);
            }
        }

        $entity->update(collect($validated)->only([
            'name', 'province', 'city', 'postal_code', 'address', 'nif_cif', 'phone', 'email', 'comments',
        ])->all());

        $entity->refresh();

        if ($panelUser) {
            $panelUser->update([
                'email' => $entity->email,
                'name' => trim((string) $entity->name) ?: 'Entidad',
                'phone' => $entity->phone,
                'nif_cif' => $entity->nif_cif,
            ]);
            if ($request->filled('panel_password')) {
                $panelUser->password = $request->input('panel_password');
                $panelUser->save();
            }
        }

        return redirect()->route('configuration.index', ['section' => 'datos-entidad'])
            ->with('success', 'Datos de la entidad actualizados correctamente.');
    }

    public function updateEntityBilling(Request $request)
    {
        $user = $request->user();
        $entityId = $user?->scopedConfigurationEntityId();
        abort_unless($entityId && $user?->canMutateEntityConfiguration(), 403);

        $entity = Entity::forUser($user)->findOrFail($entityId);

        $validated = $request->validate([
            'billing_iban' => 'nullable|string|max:34',
        ]);

        $iban = preg_replace('/\s+/', '', strtoupper((string) ($validated['billing_iban'] ?? '')));
        $entity->update(['billing_iban' => $iban !== '' ? $iban : null]);

        return redirect()->route('configuration.index', ['section' => 'facturacion-cobros'])
            ->with('success', 'Datos de facturación actualizados correctamente.');
    }

    public function updateEntityManager(Request $request, Manager $manager)
    {
        $user = $request->user();
        $entityId = $user?->scopedConfigurationEntityId();
        abort_unless($entityId && $user?->canMutateEntityConfiguration(), 403);
        abort_unless((int) $manager->entity_id === $entityId, 403);

        $manager->load('user');
        if ($manager->user && $manager->user->isPanelAccount()) {
            abort(403, 'La cuenta de acceso al panel no se edita como gestor.');
        }

        $managerUser = $manager->user;
        $userId = $managerUser?->id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'last_name2' => 'nullable|string|max:255',
            'nif_cif' => ['nullable', 'string', 'max:20', 'unique:users,nif_cif'.($userId ? ','.$userId : '')],
            'birthday' => ['nullable', 'date', new \App\Rules\MinimumAge(18)],
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'comment' => 'nullable|string|max:1000',
        ]);

        if (! $managerUser) {
            $managerUser = new User;
            $managerUser->name = $validated['name'].' '.$validated['last_name'];
            $managerUser->email = $validated['email'];
            $managerUser->password = User::ENTITY_MANAGER_LEGACY_DEFAULT_PASSWORD;
            $managerUser->role = User::ROLE_ENTITY;
            $managerUser->save();
            $manager->update(['user_id' => $managerUser->id]);
        }

        $managerUser->update([
            'name' => $validated['name'],
            'last_name' => $validated['last_name'],
            'last_name2' => $validated['last_name2'],
            'nif_cif' => $validated['nif_cif'],
            'birthday' => $validated['birthday'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'comment' => $validated['comment'],
            'role' => User::ROLE_ENTITY,
        ]);

        return redirect()->route('configuration.index', ['section' => 'datos-entidad', 'tab' => 'gestores'])
            ->with('success', 'Datos del gestor actualizados correctamente.');
    }

    public function updateAdministrationSettings(Request $request)
    {
        $user = $request->user();
        $administrationId = $user?->scopedConfigurationAdministrationId();
        abort_unless($administrationId && $user?->canMutateAdministrationConfiguration(), 403);

        $administration = Administration::forUser($user)->findOrFail($administrationId);

        $validated = $request->validate([
            'web' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'society' => 'required|string|max:255',
            'nif_cif' => ['required', 'string', 'max:255', new \App\Rules\SpanishDocument],
            'province' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'postal_code' => 'required|string|max:10',
            'address' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:255',
            'current_password' => 'nullable|required_with:panel_password|string',
            'panel_password' => 'nullable|string|min:8|confirmed',
        ]);

        $panelUser = User::query()
            ->where('panel_account_type', 'administration')
            ->where('panel_account_id', $administration->id)
            ->first();

        $newEmail = trim((string) $validated['email']);

        if ($panelUser) {
            if (strcasecmp((string) $panelUser->email, $newEmail) !== 0
                && ContactEmailRegistry::isTaken($newEmail, $panelUser->id, $administration->id, null)) {
                return back()->withInput()
                    ->withErrors(['email' => 'Este correo ya está en uso en otra administración, entidad o cuenta de usuario.']);
            }
        } elseif (strcasecmp((string) $administration->email, $newEmail) !== 0
            && ContactEmailRegistry::isTaken($newEmail, null, $administration->id, null)) {
            return back()->withInput()
                ->withErrors(['email' => 'Este correo ya está en uso en otra administración, entidad o cuenta de usuario.']);
        }

        if ($request->filled('panel_password')) {
            if (! $panelUser || ! Hash::check((string) $request->input('current_password'), $panelUser->password)) {
                return back()->withInput()->withErrors([
                    'current_password' => 'La contraseña actual no es correcta.',
                ]);
            }
        }

        $administration->update([
            'web' => $validated['web'] ?? '',
            'name' => $validated['name'],
            'society' => $validated['society'],
            'nif_cif' => $validated['nif_cif'],
            'province' => $validated['province'],
            'city' => $validated['city'],
            'postal_code' => $validated['postal_code'],
            'address' => $validated['address'],
            'email' => $newEmail,
            'phone' => $validated['phone'],
        ]);

        if ($panelUser) {
            $panelUser->update([
                'email' => $newEmail,
                'name' => Administration::panelDisplayNameFromParts($validated['name'], $validated['society']),
                'phone' => $validated['phone'],
                'nif_cif' => $validated['nif_cif'],
            ]);
            if ($request->filled('panel_password')) {
                $panelUser->password = $request->input('panel_password');
                $panelUser->save();
            }
        }

        if ($request->filled('panel_password')) {
            $administration->refresh();
            if ($administration->status === null || $administration->status === -1) {
                $administration->update(['status' => 1]);
            }
        }

        return redirect()->route('configuration.index', ['section' => 'datos-administracion'])
            ->with('success', 'Datos de la administración actualizados correctamente.');
    }

    public function updateAdministrationBilling(Request $request)
    {
        $user = $request->user();
        $administrationId = $user?->scopedConfigurationAdministrationId();
        abort_unless($administrationId && $user?->canMutateAdministrationConfiguration(), 403);

        $administration = Administration::forUser($user)->findOrFail($administrationId);

        $accountValue = $this->sanitizeIbanAccount($request->input('account'));
        $request->merge(['account' => $accountValue ?? '']);

        $request->validate([
            'account' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    $digits = preg_replace('/\D/', '', (string) $value);
                    if ($digits !== '' && strlen($digits) !== 22) {
                        $fail('La cuenta bancaria debe estar vacía o tener exactamente 22 dígitos.');
                    }
                },
            ],
        ]);

        if ($accountValue) {
            $iban = 'ES'.$accountValue;
            $validator = \Validator::make(['iban' => $iban], [
                'iban' => [new \App\Rules\SpanishIban],
            ]);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            }
        }

        $administration->update([
            'account' => $accountValue ? ('ES'.$accountValue) : null,
        ]);

        return redirect()->route('configuration.index', ['section' => 'facturacion-cobros'])
            ->with('success', 'Datos de facturación actualizados correctamente.');
    }

    private function sanitizeIbanAccount($value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $raw = preg_replace('/\s+/', '', trim((string) $value));
        $raw = preg_replace('/^ES/i', '', $raw);
        $digits = preg_replace('/\D/', '', $raw);

        return $digits !== '' ? $digits : null;
    }
}
