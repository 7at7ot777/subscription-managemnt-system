<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CENTRAL database only.
 *
 * There is deliberately NO `users` table here. Tenant users live in each tenant's
 * own database (see database/migrations/tenant/). Keeping a central `users` table
 * would mean that any failure to initialise tenancy silently authenticates against
 * the wrong database; without it, such a bug fails loudly instead.
 *
 * `sessions` is central because Livewire's /livewire/update route runs StartSession
 * from the plain `web` group before tenancy is initialised. Tenant isolation of the
 * session is enforced by App\Http\Middleware\EnsureSessionBelongsToTenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
