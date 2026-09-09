<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TENANT database. Cache isolation comes from this table living in the tenant's own
 * database, not from cache tags: Illuminate\Cache\DatabaseStore does not extend
 * TaggableStore, so stancl's tag-based CacheTenancyBootstrapper would throw on every
 * Cache call. See App\Tenancy\Bootstrappers\DatabaseCacheTenancyBootstrapper.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};
