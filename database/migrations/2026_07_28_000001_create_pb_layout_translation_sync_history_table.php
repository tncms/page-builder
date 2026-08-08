<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Page Builder — LT-7C (Synchronization History).
 *
 * Additive, append-only table recording one row per committed synchronization execution. It is a
 * DECISION reconstruction record (INV-LT-7-EXEC-02), not a log: the plan fingerprint, the baseline
 * before and after, and every applied/skipped item with its reviewer decision — enough to reconstruct
 * and reverse an execution WITHOUT any payload snapshot. Payload-safe by construction: the item columns
 * store origins + enums + decisions only.
 *
 * Separate from the frozen LT-2 binding and LT-4 baseline tables (neither is modified). Deliberately NO
 * foreign key to `pb_layouts` (content-addressed provenance; the source stays independently deletable —
 * consistent with the LT-4 baseline store). Reversible: `down()` drops only this additive table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pb_layout_translation_sync_history', function (Blueprint $table): void {
            $table->id();

            $table->string('page_translation_key');
            $table->string('actor')->nullable();

            // Provenance: the exact plan, and the baseline moved from → to.
            $table->string('plan_fingerprint');
            $table->string('baseline_before');
            $table->string('baseline_after');

            $table->unsignedBigInteger('source_layout_id');

            // Decision reconstruction (payload-safe: origins + enums + decisions only).
            $table->unsignedInteger('applied_count');
            $table->unsignedInteger('skipped_count');
            $table->json('applied_items');
            $table->json('skipped_items');

            $table->string('executed_at');

            $table->timestamps();

            $table->index('page_translation_key', 'pb_lt_sync_history_page_translation_index');
            $table->index('executed_at', 'pb_lt_sync_history_executed_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pb_layout_translation_sync_history');
    }
};
