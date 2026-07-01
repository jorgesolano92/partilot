<div class="role-legal-block mb-4">
    <p class="small text-muted">{{ config('legal.role_intro_sentence') }}</p>

    @if(!empty($invitation['context']['entity_name']))
        <p class="mb-2"><strong>Entidad:</strong> {{ $invitation['context']['entity_name'] }}</p>
    @endif
    @if(!empty($invitation['context']['administration_name']))
        <p class="mb-2"><strong>Administración:</strong> {{ $invitation['context']['administration_name'] }}</p>
    @endif

    <ul class="small mb-3">
        @foreach(($invitation['summary_bullets'] ?? []) as $bullet)
            <li>{{ $bullet }}</li>
        @endforeach
    </ul>

    <p class="small mb-0">
        <a href="{{ route('legal.terminos-y-condiciones') }}" target="_blank" rel="noopener">Ver condiciones legales completas</a>
    </p>
</div>
