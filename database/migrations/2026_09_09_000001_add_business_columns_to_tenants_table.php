<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The `tenancy_db_*` column names are NOT arbitrary. Stancl's DatabaseConfig scans
 * the tenant's raw attributes for keys prefixed `tenancy_db_`, strips the prefix and
 * merges the remainder into the tenant's connection config. Renaming these would mean
 * the credentials silently never reach the connection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('name');
            $table->string('slug', 63)->unique();
            $table->string('status', 32)->default('inactive')->index();
            $table->string('provisioning_status', 32)->default('pending')->index();
            $table->timestamp('subscription_start_at')->nullable();
            $table->timestamp('subscription_end_at')->nullable()->index();

            $table->string('tenancy_db_name')->nullable();
            $table->string('tenancy_db_host')->nullable();
            $table->string('tenancy_db_port')->nullable();
            $table->string('tenancy_db_username')->nullable();
            $table->text('tenancy_db_password')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'name', 'slug', 'status', 'provisioning_status',
                'subscription_start_at', 'subscription_end_at',
                'tenancy_db_name', 'tenancy_db_host', 'tenancy_db_port',
                'tenancy_db_username', 'tenancy_db_password',
            ]);
        });
    }
};
