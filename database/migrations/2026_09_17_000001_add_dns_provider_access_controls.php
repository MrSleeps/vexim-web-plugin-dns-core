<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vw_dns_providers', function (Blueprint $table) {
            $table->foreignId('owner_user_id')
                ->nullable()
                ->after('id')
                ->constrained('users_web')
                ->restrictOnDelete();
        });

        Schema::table('vw_dns_domains', function (Blueprint $table) {
            $table->string('service_control', 32)
                ->default('system_only')
                ->after('is_active');
            $table->string('record_control', 32)
                ->default('system_only')
                ->after('service_control');

            $table->dropForeign(['provider_id']);
            $table->foreign('provider_id')
                ->references('id')
                ->on('vw_dns_providers')
                ->restrictOnDelete();
        });

        if (Schema::hasTable('vw_settings')
            && ! DB::table('vw_settings')->where('key', 'domain_admin_dns_access')->exists()) {
            DB::table('vw_settings')->insert([
                'key' => 'domain_admin_dns_access',
                'value' => 'disabled',
                'type' => 'string',
                'description' => 'DNS access for domain admins: disabled, global providers only, or global plus their own providers',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Cache::forget('settings.all');
        }
    }

    public function down(): void
    {
        Schema::table('vw_dns_domains', function (Blueprint $table) {
            $table->dropForeign(['provider_id']);
            $table->foreign('provider_id')
                ->references('id')
                ->on('vw_dns_providers')
                ->cascadeOnDelete();

            $table->dropColumn(['service_control', 'record_control']);
        });

        Schema::table('vw_dns_providers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_user_id');
        });
    }
};
