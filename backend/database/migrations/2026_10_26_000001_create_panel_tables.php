<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Esquema del panel según docs/spec.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 16)->default('client')->after('email');
            $table->foreignId('client_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('plan')->nullable();
            $table->decimal('fx_rate', 12, 4);
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
            $table->string('key', 32)->unique();
            $table->string('name');
            $table->string('description')->default('');
            $table->decimal('price_usd_impl', 10, 2);
            $table->decimal('price_usd_month', 10, 2);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('project_widgets', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('widget_id')->constrained()->restrictOnDelete();
            $table->primary(['project_id', 'widget_id']);
        });

        // Secciones de requisitos. widget_key nulo = sección base (la piden todos los proyectos).
        Schema::create('requirement_groups', function (Blueprint $table) {
            $table->id();
            $table->string('key', 32)->unique();
            $table->string('widget_key', 32)->nullable()->index();
            $table->string('name');
            $table->string('description')->default('');
            $table->unsignedSmallInteger('sort')->default(0);
        });

        Schema::create('requirement_templates', function (Blueprint $table) {
            $table->id();
            $table->string('group', 32)->index();
            $table->string('key', 32)->unique();
            $table->string('subgroup')->nullable();
            $table->string('label');
            $table->string('type', 8);
            $table->string('hint')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->foreign('group')->references('key')->on('requirement_groups')->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::create('project_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('template_key', 32);
            $table->text('value')->nullable();
            $table->boolean('done')->default(false);
            $table->string('source', 8)->default('manual');
            $table->timestamp('updated_at')->nullable();
            $table->unique(['project_id', 'template_key']);
            $table->foreign('template_key')->references('key')->on('requirement_templates')->cascadeOnUpdate()->restrictOnDelete();
        });

        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });

        Schema::create('signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('key', 32);
            $table->boolean('ok');
            $table->string('detail')->default('');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['site_id', 'key', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signals');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('project_requirements');
        Schema::dropIfExists('requirement_templates');
        Schema::dropIfExists('requirement_groups');
        Schema::dropIfExists('project_widgets');
        Schema::dropIfExists('widgets');
        Schema::dropIfExists('projects');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn('role');
        });
        Schema::dropIfExists('clients');
    }
};
