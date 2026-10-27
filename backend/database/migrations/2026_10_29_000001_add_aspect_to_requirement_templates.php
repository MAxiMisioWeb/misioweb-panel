<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Formato que pide cada campo de imagen (16:9, 7:5, 4:5); el panel recorta a ese formato. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requirement_templates', function (Blueprint $table) {
            $table->string('aspect', 8)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('requirement_templates', function (Blueprint $table) {
            $table->dropColumn('aspect');
        });
    }
};
