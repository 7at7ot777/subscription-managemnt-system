<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Moved off the panel root deliberately.
 *
 * Filament only auto-registers its `home` route when nothing already occupies the
 * group's root URI. The stock Dashboard has $routePath = '/', which takes that slot,
 * and Panel::getUrl() then falls back to url($this->getPath()) — emitting a literal
 * "/{tenant}/app" with the parameter unsubstituted, because that path is a raw string
 * concatenation rather than a route() call that URL::defaults could fill.
 *
 * Freeing the root restores the home route and with it every post-login redirect.
 */
class Dashboard extends BaseDashboard
{
    protected static string $routePath = '/dashboard';
}
