<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('face_id_ok')->default(false)->after('avatar');
            $table->string('ocupacion')->nullable()->after('face_id_ok');
            $table->text('notas_admin')->nullable()->after('ocupacion');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['face_id_ok', 'ocupacion', 'notas_admin']);
        });
    }
};
