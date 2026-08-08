<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Page Builder — Phase 2 (Layout Storage).
 *
 * One row per owner (polymorphic: page / post / any content), holding the
 * structured layout tree as JSON — never HTML. Draft and published payloads
 * live side by side on the same row so publishing is a draft → published copy.
 * Both payloads are nullable: a row can exist (e.g. mode toggled to "builder")
 * before any layout is drafted, and can be drafted before it is ever published.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pb_layouts', function (Blueprint $table): void {
            $table->id();

            // Polymorphic owner — attaches to existing content without any core
            // schema change. No FK (owner tables vary); integrity is enforced in
            // application code.
            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');

            // 'classic' (default) or 'builder'. isPageBuilderMode() === 'builder'.
            $table->string('editor_mode')->default('classic');

            // Structured layout trees (sections → blocks), never HTML.
            $table->json('draft_payload')->nullable();
            $table->json('published_payload')->nullable();

            // Lightweight audit stamps (who last drafted / published). Nullable
            // strings so a null/guest actor is valid and no FK is imposed.
            $table->string('draft_updated_by')->nullable();
            $table->string('published_by')->nullable();

            $table->timestamps();

            // Exactly one layout row per owner.
            $table->unique(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pb_layouts');
    }
};
