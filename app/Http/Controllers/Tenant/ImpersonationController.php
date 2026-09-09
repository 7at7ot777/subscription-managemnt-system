<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\ImpersonationLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stancl\Tenancy\Features\UserImpersonation;
use Stancl\Tenancy\Middleware\ScopeSessions;

class ImpersonationController extends Controller
{
    /**
     * Consume an impersonation token and enter the tenant as the selected user.
     *
     * No tenant password is read or copied: stancl's makeResponse() verifies the token
     * belongs to THIS tenant, enforces its 60-second TTL, calls loginUsingId(), and
     * deletes the token so it cannot be replayed.
     */
    public function enter(Request $request, string $tenant, string $token): RedirectResponse
    {
        // Drop any pre-existing tenant session before logging in. This defeats session
        // fixation and stops EnsureSessionBelongsToTenant from rejecting the request
        // because the session is still bound to a previously visited tenant.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $response = UserImpersonation::makeResponse($token);

        $request->session()->put(ScopeSessions::$tenantIdKey, tenant()->getTenantKey());
        $request->session()->put('impersonation', [
            'log_id' => (int) $request->query('log'),
            'started_at' => now()->toIso8601String(),
        ]);

        return $response;
    }

    /**
     * End impersonation and return to the central admin panel.
     *
     * The super admin's own session is a different guard (`super_admin`) and is
     * untouched by this, so they arrive back at /admin still authenticated.
     */
    public function leave(Request $request): RedirectResponse
    {
        $logId = $request->session()->get('impersonation.log_id');

        if ($logId) {
            ImpersonationLog::query()
                ->whereKey($logId)
                ->whereNull('ended_at')
                ->update(['ended_at' => now()]);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to('/admin');
    }
}
