<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Page Builder — LT-4 (Baseline & Structural Correspondence).
 *
 * Additive table, separate from the frozen LT-2 binding table (which is NOT
 * modified). One immutable source baseline per target page translation: the
 * content-addressed source fingerprint at baseline time + the source layout it
 * referenced + when it was captured. Structural node correspondence itself lives in
 * the layout payload (`data['_origin']`), not here.
 *
 * Deliberately NO foreign key to `pb_layouts`: the baseline is a content-addressed
 * snapshot (the fingerprint is the authoritative provenance), and the source must
 * remain independently deletable/editable without coupling to the target
 * (LT-3-BINDING-LIFECYCLE-GOVERNANCE §3.7). Reversible: `down()` drops only this
 * additive table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pb_layout_translation_baselines', function (Blueprint $table): void {
            $table->id();

            // One baseline per target page translation (canonical key value).
            $table->string('page_translation_key');

            // The source document referenced, and its content-addressed fingerprint.
            $table->unsignedBigInteger('source_layout_id');
            $table->string('fingerprint');

            // When the baseline was captured (ISO-8601).
            $table->string('captured_at');

            $table->timestamps();

            $table->unique('page_translation_key', 'pb_lt_baselines_page_translation_unique');
            $table->index('source_layout_id', 'pb_lt_baselines_source_layout_index');
            $table->index('fingerprint', 'pb_lt_baselines_fingerprint_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pb_layout_translation_baselines');
    }
};
