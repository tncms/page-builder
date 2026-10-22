<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources;

use Plugins\PageBuilder\Contracts\ComponentInstance;

/**
 * Resolves a component's configured data sources for one render (PB-XREG-2).
 *
 * Called by the {@see \Plugins\PageBuilder\Runtime\ComponentRuntime} right before
 * a component's trusted view renders. For every `data-source` field its
 * definition declares, the saved value is re-validated (never trusted from the
 * save) and executed under a server-built context: the current render locale
 * (frontend public locale, or the editing locale scoped around an editor render)
 * and `preview` vs `public` mode. The view receives ONLY plain result arrays as
 * `$dataSources[<field>]`; nothing is written back into the layout.
 *
 * The component's own `data` is left untouched, so the saved configuration stays
 * visible and recoverable even while its source is unavailable. Non-ok outcomes
 * are collected as bounded diagnostics for the editor preview response.
 */
final class DataSourceRenderResolver
{
    /** @var list<array{component_id: ?string, field: string, code: string}> */
    private array $diagnostics = [];

    public function __construct(
        private readonly DataSourceConfigValidator $validator,
        private readonly DataSourceExecutor $executor,
    ) {}

    /**
     * Null when the component declares no data-source field.
     *
     * @return array<string, array{status: string, code: ?string, records: list<array<string, mixed>>}>|null
     */
    public function resolve(ComponentInstance $instance, bool $preview): ?array
    {
        $fields = DataSourceFields::in($instance->definition());

        if ($fields === []) {
            return null;
        }

        $context = new DataSourceContext(
            $this->locale(),
            $preview ? DataSourceContext::MODE_PREVIEW : DataSourceContext::MODE_PUBLIC,
        );
        $data = $instance->data();

        $out = [];
        foreach ($fields as $name => $accepts) {
            $result = $this->executor->execute($this->validator->inspect($data[$name] ?? null, $accepts), $context);

            if ($result->code !== null && $result->code !== DataSourceResult::CODE_NOT_CONFIGURED) {
                $id = $instance->payload()['id'] ?? null;
                $this->diagnostics[] = ['component_id' => is_string($id) ? $id : null, 'field' => $name, 'code' => $result->code];
            }

            $out[$name] = $result->toArray();
        }

        return $out;
    }

    /**
     * Return and clear the diagnostics collected since the last drain.
     *
     * @return list<array{component_id: ?string, field: string, code: string}>
     */
    public function drainDiagnostics(): array
    {
        $out = $this->diagnostics;
        $this->diagnostics = [];

        return $out;
    }

    private function locale(): string
    {
        $locale = (string) app()->getLocale();

        return $locale !== '' ? $locale : (string) config('app.fallback_locale', 'en');
    }
}
