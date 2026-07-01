@if(app(\App\Services\CookieConsentService::class)->needsBanner(request()))
<div id="partilot-cookie-banner" class="partilot-cookie-banner" role="dialog" aria-label="Consentimiento de cookies">
    <div class="partilot-cookie-banner__inner">
        <p class="partilot-cookie-banner__text">
            Usamos cookies técnicas (necesarias) y analíticas. Las técnicas no necesitan tu permiso.
            <a href="{{ route('legal.politica-de-cookies') }}" target="_blank" rel="noopener">Ver política de cookies</a>
        </p>
        <div class="partilot-cookie-banner__actions">
            <button type="button" class="partilot-cookie-btn partilot-cookie-btn--primary" data-choice="all">Aceptar todas</button>
            <button type="button" class="partilot-cookie-btn partilot-cookie-btn--secondary" data-choice="necessary">Solo las necesarias</button>
            <button type="button" class="partilot-cookie-btn partilot-cookie-btn--secondary" data-choice="custom" data-analytics="0" id="partilot-cookie-configure-btn">Configurar</button>
        </div>
        <div id="partilot-cookie-config-panel" class="partilot-cookie-config" hidden>
            <label class="partilot-cookie-config__item">
                <input type="checkbox" checked disabled> Cookies técnicas (obligatorias)
            </label>
            <label class="partilot-cookie-config__item">
                <input type="checkbox" id="partilot-cookie-analytics"> Cookies analíticas
            </label>
            <button type="button" class="partilot-cookie-btn partilot-cookie-btn--primary" id="partilot-cookie-save-custom">Guardar preferencias</button>
        </div>
    </div>
</div>
<style>
.partilot-cookie-banner {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 100000;
    background: #1f2430;
    color: #f5f6fa;
    padding: 16px 20px;
    box-shadow: 0 -4px 24px rgba(0,0,0,.2);
}
.partilot-cookie-banner__inner { max-width: 1100px; margin: 0 auto; }
.partilot-cookie-banner__text { margin: 0 0 12px; font-size: 0.92rem; line-height: 1.45; }
.partilot-cookie-banner__text a { color: #9ec5ff; }
.partilot-cookie-banner__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
.partilot-cookie-btn {
    flex: 1 1 160px;
    min-height: 44px;
    border-radius: 8px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    font-size: 0.9rem;
}
.partilot-cookie-btn--primary { background: #e85d04; color: #fff; }
.partilot-cookie-btn--secondary { background: #3a4050; color: #fff; }
.partilot-cookie-config { margin-top: 12px; padding-top: 12px; border-top: 1px solid #3a4050; }
.partilot-cookie-config__item { display: block; margin-bottom: 8px; font-size: 0.9rem; }
</style>
<script>
(function () {
    var banner = document.getElementById('partilot-cookie-banner');
    if (!banner) return;

    var csrf = document.querySelector('meta[name="csrf-token"]');
    var token = csrf ? csrf.getAttribute('content') : '';

    function sendChoice(choice, analytics) {
        fetch('{{ url('/api/legal/cookies') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                choice: choice,
                cookies_analiticas: !!analytics
            })
        }).then(function () {
            banner.remove();
        }).catch(function () {
            banner.remove();
        });
    }

    banner.querySelectorAll('[data-choice]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var choice = btn.getAttribute('data-choice');
            if (choice === 'custom') {
                document.getElementById('partilot-cookie-config-panel').hidden = false;
                return;
            }
            sendChoice(choice, choice === 'all');
        });
    });

    var saveCustom = document.getElementById('partilot-cookie-save-custom');
    if (saveCustom) {
        saveCustom.addEventListener('click', function () {
            var analytics = document.getElementById('partilot-cookie-analytics').checked;
            sendChoice('custom', analytics);
        });
    }
})();
</script>
@endif
