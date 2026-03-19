<x-filament-panels::page.simple>

    {{ \Filament\Support\Facades\FilamentView::renderHook(
          \Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
          scopes: $this->getRenderHookScopes(),
      ) }}

    <form wire:submit.prevent="authenticate" class="space-y-6">
        {{ $this->form }}
        <div class="flex justify-center">
            {{ $this->getAuthenticateFormAction() }}
        </div>
    </form>

    {{ \Filament\Support\Facades\FilamentView::renderHook(
        \Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
        scopes: $this->getRenderHookScopes(),
    ) }}
</x-filament-panels::page.simple>
