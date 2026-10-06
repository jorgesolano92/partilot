// Pegar en app.component.ts (app Ionic) tras init nativo.
// Fase 5 simplificada: reutilizar flujos existentes; sin guardar ref post-registro.

import { App } from '@capacitor/app';
import { Capacitor } from '@capacitor/core';

private async registrarDeepLinks(): Promise<void> {
  if (!Capacitor.isNativePlatform()) return;

  await App.addListener('appUrlOpen', ({ url }) => {
    const ref = this.extraerRefDeUrl(url);
    if (!ref) return;

    if (this.authService.isLoggedIn()) {
      // Reutilizar flujo existente (escáner / digitalizar / cartera)
      this.router.navigate(['/tabs/tab2'], { queryParams: { ref } });
      return;
    }

    // Sin sesión: registro simple; el usuario consulta después por su cuenta
    this.router.navigate(['/registro']);
  });
}

private extraerRefDeUrl(url: string): string | null {
  try {
    const parsed = new URL(url);
    const ref = parsed.searchParams.get('ref');
    return ref?.trim() || null;
  } catch {
    const match = url.match(/[?&]ref=([^&#]+)/);
    return match ? decodeURIComponent(match[1]).trim() : null;
  }
}

// Llamar desde ngOnInit: await this.registrarDeepLinks();

// App package stores: com.partilot.app
