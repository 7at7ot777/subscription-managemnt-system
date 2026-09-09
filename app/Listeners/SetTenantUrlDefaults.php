<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Support\Facades\URL;
use Stancl\Tenancy\Events\TenancyInitialized;

/**
 * The path resolver calls $route->forgetParameter('tenant') once the tenant has been
 * resolved, so by the time any URL is generated the parameter is gone and route()
 * would throw UrlGenerationException. Registering it as a URL default fixes every
 * generated URL at once: Filament login/logout redirects, resource links, breadcrumbs,
 * pagination and table sort links.
 *
 * This is bound to the event rather than the middleware so it also applies to the
 * Livewire persistent-middleware replay on /livewire/update.
 */
class SetTenantUrlDefaults
{
    public function handle(TenancyInitialized $event): void
    {
        URL::defaults(['tenant' => $event->tenancy->tenant->slug]);
    }
}
