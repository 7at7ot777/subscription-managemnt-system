<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tenants\Actions;

use App\Filament\Resources\Tenants\TenantResource;
use App\Models\ImpersonationLog;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Mints a single-use impersonation token from the central panel.
 *
 * This action is the ONLY place a token is created, it lives behind the super_admin
 * guard, and there is no tenant-facing route that can mint one. No tenant password is
 * ever read, copied or reset.
 */
class ImpersonateTenantUserAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'impersonate';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Impersonate')
            ->icon(Heroicon::OutlinedUserCircle)
            ->color('warning')
            ->visible(fn (Tenant $record): bool => $record->isImpersonable())
            ->schema([
                Select::make('user_id')
                    ->label('Sign in as')
                    // Queried inside the tenant context: the default connection in the
                    // admin panel is central, which has no users table at all.
                    ->options(fn (Tenant $record): array => $record->run(
                        fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all(),
                    ))
                    ->searchable()
                    ->required(),
            ])
            ->requiresConfirmation()
            ->modalHeading(fn (Tenant $record): string => 'Impersonate a user in '.$record->name)
            ->modalDescription('This is recorded in the impersonation audit log, and your actions will appear as the impersonated user.')
            ->modalSubmitActionLabel('Start impersonation')
            ->action(function (Tenant $record, array $data) {
                $tenantUser = $record->run(
                    fn (): User => User::query()->findOrFail($data['user_id']),
                );

                $log = ImpersonationLog::create([
                    'super_admin_id' => Auth::guard('super_admin')->id(),
                    'tenant_id' => $record->getTenantKey(),
                    'tenant_user_id' => (string) $tenantUser->getKey(),
                    'tenant_user_email' => $tenantUser->email,
                    'ip_address' => request()->ip(),
                    'user_agent' => Str::limit((string) request()->userAgent(), 500),
                    'started_at' => now(),
                ]);

                $token = tenancy()->impersonate(
                    $record,
                    (string) $tenantUser->getKey(),
                    route('filament.tenant.pages.dashboard', ['tenant' => $record->slug]),
                    'web',
                );

                session()->put('impersonator_return_url', TenantResource::getUrl('index'));

                return redirect()->to(route('tenant.impersonation.enter', [
                    'tenant' => $record->slug,
                    'token' => $token->token,
                    'log' => $log->getKey(),
                ]));
            });
    }
}
