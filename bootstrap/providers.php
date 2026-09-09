<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\TenantPanelProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    // Without this the tenant routes never load and the TenantCreated pipeline
    // (create database + run tenant migrations) never fires.
    TenancyServiceProvider::class,
    // Order matters: /admin must be registered before the /{tenant} catch-all.
    AdminPanelProvider::class,
    TenantPanelProvider::class,
];
