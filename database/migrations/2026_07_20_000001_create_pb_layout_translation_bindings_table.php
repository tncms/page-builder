<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Page Builder — LT-2 (Persistent Translation Binding).
 *
 * Additive relationship table. `pb_layouts` remains the canonical layout-document
 * store and is NOT modified (no locale column). This table owns ONLY the
 * translation relationship — Translation Group → Page Translation → Layout →
 * pb_layouts.id — and the persisted, three-dimensional translation status.
 *
 * Identity is the canonical serialized value of the frozen LT-1 identity value
 * objects. `locale` is indexed relationship metadata and coherence data — it is
 * NOT the canonical identity. Uniqueness is on `page_translation_key` and
 * `layout_key` (never on group + locale), preserving the future additive
 * LayoutKey-variant path.
 *
 * Deletion behaviour is conservative: `layout_id` restricts deletion of a bound
 * pb_layouts row (no cascade) so a translated layout can never be silently
 * destroyed. Reversible: `down()` drops only this additive table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pb_layout_translation_bindings', function (Blueprint $table): void {
            $table->id();

            // Canonical serialized LT-1 identities (never reinvented from metadata).
            $table->string('translation_group_key');
            $table->string('page_translation_key');
            $table->string('layout_key');

            // The bound canonical layout document.
            $table->unsignedBigInteger('layout_id');

            // Relationship metadata + coherence only — NOT identity.
            $table->string('locale', 20);

            // Three independent, orthogonal state dimensions — never one flat field.
            $table->string('existence_state');
            $table->string('lifecycle_state')->nullable();
            $table->string('synchronization_state');

            $table->timestamps();

            // A page translation owns at most one default layout; a layout key
            // resolves to exactly one binding. NOT keyed on (group, locale).
            $table->unique('page_translation_key', 'pb_lt_bindings_page_translation_unique');
            $table->unique('layout_key', 'pb_lt_bindings_layout_key_unique');

            $table->index('translation_group_key', 'pb_lt_bindings_group_index');
            $table->index('locale', 'pb_lt_bindings_locale_index');
            $table->index('layout_id', 'pb_lt_bindings_layout_id_index');

            // Conservative: restrict deletion of a bound layout document (no cascade).
            $table->foreign('layout_id', 'pb_lt_bindings_layout_fk')
                ->references('id')
                ->on('pb_layouts')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pb_layout_translation_bindings');
    }
};
