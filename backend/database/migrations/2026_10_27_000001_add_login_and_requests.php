<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Login con Google y pedidos de prospectos.
 *
 * Quien se registra queda como prospect: elige widgets y manda un pedido (proyecto en
 * estado requested). Cuando la agencia lo aprueba, pasa a client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change(); // quien entra con Google no tiene contraseña
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('role', 16)->default('prospect')->change();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->text('request_note')->nullable()->after('status');
            $table->timestamp('requested_at')->nullable()->after('request_note');
            $table->timestamp('reviewed_at')->nullable()->after('requested_at');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['request_note', 'requested_at', 'reviewed_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('google_id');
            $table->string('role', 16)->default('client')->change();
        });
    }
};
