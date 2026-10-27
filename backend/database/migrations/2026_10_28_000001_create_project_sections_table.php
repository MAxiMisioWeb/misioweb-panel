<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado interno del trabajo de la agencia en cada sección de un proyecto
 * (Sin empezar, En curso, Listo). El cliente no lo ve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_sections', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('group', 32);
            $table->string('work_status', 16)->default('todo');
            $table->timestamp('updated_at')->nullable();
            $table->primary(['project_id', 'group']);
            $table->foreign('group')->references('key')->on('requirement_groups')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_sections');
    }
};
