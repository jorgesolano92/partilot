@include('entities.partials.entity_commercial_settings_card', [
    'entity' => $entity,
    'readonly' => $readonly ?? true,
    'defaults' => $defaults ?? [],
    'formId' => $formId ?? null,
])
