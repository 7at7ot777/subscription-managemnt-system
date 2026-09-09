@php($impersonatedUser = auth()->guard('web')->user())
<div style="position:sticky;top:0;z-index:50;display:flex;align-items:center;justify-content:center;
            gap:1rem;flex-wrap:wrap;padding:.625rem 1rem;background:#b45309;color:#fff;
            font:600 .875rem/1.4 ui-sans-serif,system-ui,sans-serif;">
    <span>
        Impersonating
        <strong>{{ $impersonatedUser?->name ?? 'a user' }}</strong>
        in <strong>{{ tenant()?->name }}</strong>
    </span>

    <form method="POST" action="{{ route('tenant.impersonation.leave') }}" style="margin:0;">
        @csrf
        <button type="submit"
                style="padding:.3rem .75rem;border:0;border-radius:.375rem;background:#fff;
                       color:#b45309;font-weight:700;cursor:pointer;">
            Leave impersonation
        </button>
    </form>
</div>
