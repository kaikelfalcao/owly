<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            // {"mon": ["08:00", "18:00"], ..., "sun": null}; vazio = o padrão de Organization::DEFAULT_HOURS.
            $table->json('business_hours')->nullable()->after('timezone');
            // Feriados nacionais contam como loja fechada; os da empresa (cidade, recesso) vão na lista.
            $table->boolean('national_holidays')->default(true)->after('business_hours');
            $table->json('holidays')->nullable()->after('national_holidays');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['business_hours', 'national_holidays', 'holidays']);
        });
    }
};
