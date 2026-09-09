# Multi-Tenancy Architecture

This application serves many isolated customers ("tenants") from one codebase. Each
tenant gets its **own database**; a single **central** database holds the tenant registry,
subscriptions and super admins.

Built on [stancl/tenancy](https://tenancyforlaravel.com) v3 with two
[Filament](https://filamentphp.com) v4 panels.

---

## 1. Architecture

```
                       Central database (sms_central)
                       ├── tenants                          registry, credentials, subscription
                       ├── super_admins                     central operators
                       ├── impersonation_logs               audit trail
                       ├── tenant_user_impersonation_tokens single-use tokens
                       └── sessions, cache, jobs
                                    │
                    resolved from the URL by slug
                                    │
             ┌──────────────────────┴──────────────────────┐
             ▼                                             ▼
   tenant<uuid-of-acme>                        tenant<uuid-of-globex>
   ├── users                                   ├── users
   ├── password_reset_tokens                   ├── password_reset_tokens
   └── cache, cache_locks                      └── cache, cache_locks
             │                                             │
   /acme/app  (Filament tenant panel)          /globex/app (Filament tenant panel)
```

Two panels, deliberately separate:

| Panel | Path | Guard | Model | Database |
|---|---|---|---|---|
| Super admin | `/admin` | `super_admin` | `App\Models\SuperAdmin` | central, always |
| Tenant | `/{tenant}/app` | `web` | `App\Models\User` | the resolved tenant's |

The super-admin panel **never** initialises tenancy, and `SuperAdmin` uses stancl's
`CentralConnection` trait, so it keeps resolving centrally even while tenancy is active.

---

## 2. Tenant URLs

```
https://example.com/acme/app/login
https://example.com/acme/app/dashboard
https://example.com/globex/app/users
```

The first path segment is the tenant's unique **slug**.

**Resolution is by slug only, never by primary key.** Tenant UUIDs never appear in URLs,
and a UUID in the path returns 404. The slug must match `^[a-z0-9]+(?:-[a-z0-9]+)*$`,
which is enforced *both* at creation (validation) and at resolution
(`App\Tenancy\Resolvers\SlugTenantResolver`).

Checking the pattern before querying matters: MySQL's `utf8mb4_unicode_ci` collation is
case-insensitive, so without it `/ACME` would resolve the `acme` tenant and the same
workspace would be reachable at many URLs.

### Reserved slugs

Slugs that would shadow a real route are rejected — `admin`, `api`, `livewire`,
`storage`, `tenancy`, `up`, `filament`, `login`, and others. The list lives in
`config/tenancy.php` under `reserved_slugs`.

---

## 3. Creating a tenant

### From the CLI

```sh
php artisan tenant:create Acme acme \
    --admin-name="Acme Admin" \
    --admin-email=admin@acme.test \
    --admin-password='Sup3rSecret!23'
```

### From the super-admin panel

`/admin` → **Tenants** → **New tenant**.

### What provisioning does

```
validate  →  tenant row (status=inactive, provisioning=provisioning)
          →  CREATE DATABASE                 (stancl Jobs\CreateDatabase)
          →  run database/migrations/tenant  (stancl Jobs\MigrateDatabase)
          →  create the first administrator  (inside the tenant context)
          →  mark ready + active
```

### Failure policy

Provisioning is deliberately **not** wrapped in a database transaction: `CREATE DATABASE`
is DDL, and MySQL implicitly commits on DDL, so a surrounding transaction would be
silently committed mid-flight while appearing to guarantee atomicity.

Instead, on any failure the tenant is marked `provisioning_status = failed`,
`status = inactive`, and the row is **kept** with the error message in `data.provisioning_error`.
Nothing is deleted automatically, because `$tenant->delete()` triggers `DROP DATABASE`,
which itself throws when the database was never created — the most common failure mode.
A failed tenant can never serve traffic, and the operator keeps the evidence.

---

## 4. Migrations

Central and tenant migrations are strictly separated.

| Location | Applies to | Command |
|---|---|---|
| `database/migrations/` | central database | `php artisan migrate` |
| `database/migrations/tenant/` | every tenant database | `php artisan tenants:migrate` |

`php artisan migrate` will not pick up the tenant folder: Laravel's migrator globs
`*_*.php` non-recursively.

```sh
php artisan tenants:migrate                       # all tenants
php artisan tenants:migrate --tenants=<uuid>      # one tenant (primary key, not slug)
php artisan tenants:rollback
php artisan tenants:seed
php artisan tenants:run "some:command"
php artisan tenants:list
```

All of these ship with stancl. The only tenancy commands this application adds are
`tenant:create`, `super-admin:create` and `tenant:prune-test-databases`.

---

## 5. Authentication isolation

Tenant users authenticate against **their own** database. `App\Models\User` declares no
connection; stancl swaps Laravel's default connection during tenancy initialisation, so
the auth provider, the Filament resources and every query follow automatically.

There is **no central `users` table**. That is deliberate: if tenancy ever failed to
initialise, a central `users` table would mean silently authenticating against the wrong
database. Without it, such a bug fails loudly.

### The cross-tenant session problem

Path-based tenancy puts every tenant on one origin, so all tenants share one cookie jar
and one session store. Left alone, this is an account-takeover vector:

> A user logs in at `/acme`. Their session holds `login_web_<hash> = 1`. They then visit
> `/globex`. The database connection has already swapped, so `User::find(1)` now returns
> **Globex's** user #1 — typically its owner. No credentials required, just an edited URL.

The defence is `App\Http\Middleware\EnsureSessionBelongsToTenant`, which records the
tenant id in the session on first use and asserts it on every later request. On mismatch
it logs a warning, logs the user out, invalidates the session and returns a 403 page
asking them to sign in again. `AuthIsolationTest` proves this.

### Why sessions are central

`SESSION_CONNECTION` is pinned to the central connection. Livewire posts every AJAX
interaction to one global `/livewire/update` route, where `StartSession` runs from the
plain `web` group *before* tenancy is initialised. A tenant-scoped session store (or a
per-tenant cookie name) would therefore 419 on every Livewire request. Isolation comes
from binding the session to its tenant, not from splitting the store.

---

## 6. Subscriptions

Each tenant has `subscription_start_at` and `subscription_end_at`. Both are nullable:

- `subscription_start_at = null` → started forever ago.
- `subscription_end_at = null` → **perpetual**, not expired.

State is derived in exactly one place, `Tenant::subscriptionStatus()`:

| State | Meaning |
|---|---|
| `not_started` | now is before the start date |
| `active` | inside the window |
| `expired` | now is after the end date |

`App\Http\Middleware\EnsureSubscriptionIsValid` enforces it server-side. Nothing else in
the codebase compares subscription dates.

**Expired tenants are hard-blocked, but login and logout stay reachable**, along with the
expiry page itself — otherwise the user is locked out by the very middleware meant to
explain the problem. The exempt route list is `tenancy.subscription_exempt_routes`.

---

## 7. Super admin

```sh
php artisan super-admin:create      # prompts for the password
```

`php artisan db:seed` also creates `admin@example.com` / `password` for local
development only.

From `/admin` a super admin can create, edit, view, activate and deactivate tenants;
review subscription state and provisioning state; configure tenant database credentials;
and impersonate a tenant user.

---

## 8. Impersonation

```
Super admin at /admin  →  Tenants  →  Impersonate  →  pick a user
      → single-use token minted in the central database
      → redirect to /{slug}/impersonate/{token}
      → signed in inside the tenant, amber banner shown
      → "Leave impersonation"  →  back at /admin
```

Security properties (all covered by `ImpersonationTest`):

- **No password is ever read, copied or reset.** The token is the only credential.
- The token is 128 random characters, **single use**, and expires after **60 seconds**.
- A token minted for one tenant is rejected with 403 by any other tenant.
- Tokens can only be minted from the central panel behind the `super_admin` guard.
  There is no tenant-facing route that creates one.
- The session is invalidated and regenerated before login, defeating session fixation.
- Every impersonation is written to `impersonation_logs` (who, which tenant, which user,
  IP, user agent, start and end times).
- Impersonation is permitted into suspended and expired tenants **on purpose** — a super
  admin must be able to enter a broken tenant in order to diagnose it.

The super admin's own session uses a different guard (`super_admin`), so it survives
untouched and "leave" simply returns them to `/admin`.

---

## 9. Handling tenant database credentials

### Database naming

Tenant databases are named **`tenant_<slug>`** — `tenant_acme`, `tenant_hathout` — rather
than after the tenant's UUID. That is what appears in `SHOW DATABASES`, backups,
slow-query logs and monitoring, so a readable name is worth having.

The name is produced by `DatabaseConfig::generateDatabaseNamesUsing()`, registered in
`TenancyServiceProvider::register()`, and stancl freezes the result into
`tenancy_db_name` at provisioning time. The slug is immutable after creation, so the
name never drifts from the physical database.

The **Database name field is read-only in the panel, deliberately**. stancl has no rename
operation — only create, delete, migrate and seed — so editing the column would not
rename anything. It would simply repoint the connection at a database that does not
exist, leaving the tenant's real data orphaned and every request returning 503.

`config('tenancy.slug.max')` is capped at 50 because MySQL limits identifiers to 64
characters and the name is prefix + slug; `TenantManagementTest` asserts the two stay
compatible.

### Credentials

Per-tenant credentials are optional; blank fields inherit the central connection.

The column names are **not** arbitrary — stancl scans the tenant's raw attributes for
keys prefixed `tenancy_db_`, strips the prefix and merges the rest into the connection
config. Hence `tenancy_db_name`, `tenancy_db_host`, `tenancy_db_port`,
`tenancy_db_username`, `tenancy_db_password`.

`App\Tenancy\TenantDatabaseConfig` filters out **null** values before that merge. Without
it, a column that exists but is null would overwrite the template connection's working
credentials with null and the connection would fail.

### Keeping the password out of everything

`tenancy_db_password` is `encrypted` at rest and listed in the model's `$hidden`. That
single line is load-bearing: `$hidden` removes it from `attributesToArray()`, which is
what Filament fills forms from, which is what Livewire serialises into the page's
`wire:snapshot`. Layers:

1. Model `$hidden` + `encrypted` cast.
2. Form field is `password()`, never revealable, never pre-filled, and only written when
   actually filled.
3. `EditTenant::mutateFormDataBeforeFill()` strips it again, so removing `$hidden` later
   still cannot leak it.
4. Absent from the table, and global search is pinned to `['name', 'slug']`.
5. Driver failures inside tenancy are caught in `bootstrap/app.php` and rendered as a
   generic 503, with details sent only to the log.

> **Production note:** set `zend.exception_ignore_args = On` in `php.ini`. Laravel 13's
> own debug page does not print argument values, but with that INI setting off the
> decrypted connection array remains in `$e->getTrace()` and would reach any error
> reporter or CLI trace renderer.

---

## 10. Local development

Prerequisites: **PHP 8.4+** (Laravel 13 pulls Symfony 8, which requires `>= 8.4.1`) and
MySQL.

```sh
# 1. databases
mysql -uroot -e "CREATE DATABASE sms_central; CREATE DATABASE sms_testing;"

# 2. app
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan filament:assets

# 3. a super admin, then a tenant
php artisan super-admin:create
php artisan tenant:create Acme acme \
    --admin-name="Acme Admin" --admin-email=admin@acme.test --admin-password='Sup3rSecret!23'

php artisan serve
```

- Super admin: <http://localhost:8000/admin>
- Tenant: <http://localhost:8000/acme/app>

### Tests

```sh
php artisan test
php artisan tenant:prune-test-databases   # after an interrupted run
```

The suite runs against **real MySQL**, because tenant isolation depends on
`CREATE DATABASE`, which SQLite cannot model. `RefreshDatabase` is deliberately unused —
DDL implicitly commits in MySQL, so its transaction would be silently discarded. The
central database is migrated once per process and truncated between tests; tenant
databases are created for real and dropped afterwards. Test tenants use a distinct
`testtenant` prefix so cleanup can never match a production database.

---

## 11. Configuration reference

| Key | Purpose |
|---|---|
| `tenancy.reserved_slugs` | slugs that may not identify a tenant |
| `tenancy.slug.min` / `.max` | slug length bounds |
| `tenancy.subscription_exempt_routes` | routes reachable while expired |
| `tenancy.database.prefix` | tenant database name prefix (`TENANCY_DB_PREFIX`) |
| `tenancy.filesystem.asset_helper_tenancy` | **must stay `false`** — see below |
| `SESSION_CONNECTION` | **must be the central connection** — see §5 |
| `DB_QUEUE_CONNECTION` | **must be the central connection** — see below |

Three settings are load-bearing and easy to break:

- **`asset_helper_tenancy = false`.** stancl otherwise repoints `asset()` at
  `/tenancy/assets/*`, and Filament builds every CSS/JS URL with `asset()`. Enabling it
  renders the tenant panel with no styles and no scripts.
- **`SESSION_CONNECTION = mysql`** (central). See §5.
- **`DB_QUEUE_CONNECTION = mysql`** (central). Otherwise jobs dispatched inside tenancy
  are written to the *tenant's* `jobs` table while the worker only polls the central one,
  so they are queued and never run.

Tenant panel middleware is registered with `isPersistent: true`. That is what makes
Livewire replay tenancy on `/livewire/update`; without it every AJAX interaction in the
tenant panel would silently execute against the central database.
`LivewireTenancyTest` asserts it.
