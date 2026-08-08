<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Persistence\Repositories;

use Illuminate\Database\QueryException;
use Plugins\PageBuilder\LayoutTranslation\Identity\LayoutKey;
use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Identity\TranslationGroupKey;
use Plugins\PageBuilder\LayoutTranslation\Persistence\Contracts\LayoutTranslationBindingRepositoryInterface;
use Plugins\PageBuilder\LayoutTranslation\Persistence\Models\LayoutTranslationBinding;
use Plugins\PageBuilder\LayoutTranslation\Persistence\Results\BindingWriteResult;
use Plugins\PageBuilder\LayoutTranslation\Persistence\Results\PersistedLayoutTranslationBinding;
use Plugins\PageBuilder\LayoutTranslation\State\LayoutSynchronizationState;
use Plugins\PageBuilder\LayoutTranslation\State\LayoutTranslationExistence;
use Plugins\PageBuilder\LayoutTranslation\State\LayoutTranslationLifecycle;
use Plugins\PageBuilder\LayoutTranslation\State\LayoutTranslationStatus;
use Throwable;

/**
 * The Eloquent-backed binding repository — LT-2.
 *
 * Layer: Persistence / Repository. It reads and writes the binding table and
 * ALWAYS hydrates domain contracts ({@see PersistedLayoutTranslationBinding}) /
 * typed results ({@see BindingWriteResult}); an Eloquent model never crosses this
 * boundary. It rehydrates identity only through the frozen LT-1 factories and
 * rehydrates status by branching on the persisted existence dimension through the
 * existing status constructors — it never adds convenience factories to the frozen
 * value objects. It performs no clone/diff/sync/baseline and never derives
 * capability. Corrupt persisted state fails closed to an invalid-reference status.
 */
final class EloquentLayoutTranslationBindingRepository implements LayoutTranslationBindingRepositoryInterface
{
    public function findByPageTranslationKey(PageTranslationKey $key): ?PersistedLayoutTranslationBinding
    {
        return $this->hydrate(
            LayoutTranslationBinding::query()->where('page_translation_key', $key->value())->first(),
        );
    }

    public function findByLayoutKey(LayoutKey $key): ?PersistedLayoutTranslationBinding
    {
        return $this->hydrate(
            LayoutTranslationBinding::query()->where('layout_key', $key->value())->first(),
        );
    }

    public function findByTranslationGroupKey(TranslationGroupKey $key): array
    {
        $bindings = [];

        foreach (LayoutTranslationBinding::query()->where('translation_group_key', $key->value())->get() as $model) {
            $binding = $this->hydrate($model);
            if ($binding !== null) {
                $bindings[] = $binding;
            }
        }

        return $bindings;
    }

    public function createBinding(PersistedLayoutTranslationBinding $binding): BindingWriteResult
    {
        $existing = $this->findByPageTranslationKey($binding->translation());
        if ($existing !== null) {
            return BindingWriteResult::alreadyExists($existing);
        }

        $byLayout = $this->findByLayoutKey($binding->layout());
        if ($byLayout !== null) {
            return BindingWriteResult::conflict('Layout key is already bound.');
        }

        try {
            LayoutTranslationBinding::query()->create($this->toRow($binding));
        } catch (QueryException $e) {
            // A unique-constraint race resolves to a conflict, not a fatal error.
            return BindingWriteResult::conflict($e->getMessage());
        } catch (Throwable $e) {
            return BindingWriteResult::failed($e->getMessage());
        }

        return BindingWriteResult::created($binding);
    }

    public function updatePersistedStatus(PageTranslationKey $key, LayoutTranslationStatus $status): BindingWriteResult
    {
        $model = LayoutTranslationBinding::query()->where('page_translation_key', $key->value())->first();
        if ($model === null) {
            return BindingWriteResult::invalidReference('No binding exists for the page translation.');
        }

        $model->fill($this->statusColumns($status));

        try {
            $model->save();
        } catch (Throwable $e) {
            return BindingWriteResult::failed($e->getMessage());
        }

        $hydrated = $this->hydrate($model);

        return $hydrated === null
            ? BindingWriteResult::failed('Updated binding could not be rehydrated.')
            : BindingWriteResult::updated($hydrated);
    }

    public function removeBinding(PageTranslationKey $key): BindingWriteResult
    {
        $model = LayoutTranslationBinding::query()->where('page_translation_key', $key->value())->first();
        if ($model === null) {
            return BindingWriteResult::invalidReference('No binding exists for the page translation.');
        }

        $hydrated = $this->hydrate($model);

        try {
            $model->delete();
        } catch (Throwable $e) {
            return BindingWriteResult::failed($e->getMessage());
        }

        return $hydrated === null
            ? BindingWriteResult::failed('Removed binding could not be rehydrated.')
            : BindingWriteResult::updated($hydrated);
    }

    /**
     * Hydrate a domain binding from a model, rehydrating identity via the frozen
     * factories and status by branching on the persisted existence dimension.
     * Corrupt/incoherent persisted data fails closed.
     */
    private function hydrate(?LayoutTranslationBinding $model): ?PersistedLayoutTranslationBinding
    {
        if ($model === null) {
            return null;
        }

        try {
            $group = TranslationGroupKey::forOwner(...$this->ownerFromGroupKey($model->translation_group_key));
            $translation = PageTranslationKey::forGroup($group, $model->locale);
            $layout = LayoutKey::forTranslation($translation);

            // Fail closed if the stored canonical values do not match the
            // rehydrated ones (identity must never be reinvented from metadata).
            if ($group->value() !== $model->translation_group_key
                || $translation->value() !== $model->page_translation_key
                || $layout->value() !== $model->layout_key) {
                return null;
            }

            return new PersistedLayoutTranslationBinding(
                $group,
                $translation,
                $layout,
                (int) $model->layout_id,
                $this->hydrateStatus($model->existence_state, $model->lifecycle_state, $model->synchronization_state),
            );
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function ownerFromGroupKey(string $groupKey): array
    {
        // Canonical form: "translation-group:{ownerType}:{ownerId}".
        $parts = explode(':', $groupKey);
        if (count($parts) !== 3 || $parts[0] !== TranslationGroupKey::NAMESPACE) {
            throw new \InvalidArgumentException('Malformed translation group key.');
        }

        return [$parts[1], $parts[2]];
    }

    private function hydrateStatus(string $existence, ?string $lifecycle, string $synchronization): LayoutTranslationStatus
    {
        try {
            $existenceEnum = LayoutTranslationExistence::from($existence);
            $syncEnum = LayoutSynchronizationState::from($synchronization);

            return match ($existenceEnum) {
                LayoutTranslationExistence::NotCreated => $lifecycle === null && $syncEnum === LayoutSynchronizationState::NotApplicable
                    ? LayoutTranslationStatus::notCreated()
                    : LayoutTranslationStatus::invalidReference(),
                LayoutTranslationExistence::InvalidReference => LayoutTranslationStatus::invalidReference(),
                LayoutTranslationExistence::Exists => LayoutTranslationStatus::exists(
                    LayoutTranslationLifecycle::from((string) $lifecycle),
                    $syncEnum,
                ),
            };
        } catch (Throwable) {
            // Corrupt persisted state fails closed rather than throwing.
            return LayoutTranslationStatus::invalidReference();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(PersistedLayoutTranslationBinding $binding): array
    {
        return array_merge([
            'translation_group_key' => $binding->group()->value(),
            'page_translation_key' => $binding->translation()->value(),
            'layout_key' => $binding->layout()->value(),
            'layout_id' => $binding->layoutId(),
            'locale' => $binding->translation()->locale(),
        ], $this->statusColumns($binding->status()));
    }

    /**
     * @return array<string, ?string>
     */
    private function statusColumns(LayoutTranslationStatus $status): array
    {
        return [
            'existence_state' => $status->existence()->value,
            'lifecycle_state' => $status->lifecycle()?->value,
            'synchronization_state' => $status->synchronization()->value,
        ];
    }
}
