<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('tipo', ['admin', 'miembro'])->default('miembro')->after('email');
            $table->string('telefono')->nullable()->after('tipo');
            $table->string('empresa')->nullable()->after('telefono');
            $table->string('avatar')->nullable()->after('empresa');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'telefono', 'empresa', 'avatar']);
        });
    }
};
