<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Components;

use Plugins\PageBuilder\Components\Contracts\ComponentProvider;
use Plugins\PageBuilder\Components\Contracts\ExternalComponentDefinition;
use Plugins\PageBuilder\Components\Contracts\InternalComponentProvider;
use Plugins\PageBuilder\Contracts\BlockDefinition;
use Plugins\PageBuilder\Contracts\ComponentDefinition;
use Plugins\PageBuilder\Contracts\SectionDefinition;
use Throwable;

/**
 * Certifies that a {@see ComponentProvider} satisfies the PB-XREG-1 public
 * contract before it may register. One validator for every provider, deliberately
 * comparable to the Resource Provider SDK's ProviderComplianceValidator.
 *
 * Checks (each a named obligation): namespace grammar/bounds, provider version,
 * well-formed component set, canonical identifiers, section/block kinds, no
 * duplicate identifiers in the batch, safe trusted renderer view, deterministic
 * output, no executable persisted metadata, and provider/registry isolation.
 *
 * Structural only: it never touches the database, the network, or Core. The
 * server-derived identity check (declared namespace == canonical plugin slug)
 * belongs to {@see ComponentProviderRegistry}, which alone can resolve it.
 */
final class ComponentProviderComplianceValidator
{
    /** A provider-declared view must target the provider's own namespace, dot-notated, no traversal. */
    private const VIEW_TAIL_PATTERN = '/^[a-z0-9_-]+(\.[a-z0-9_-]+)*$/i';

    public function validate(ComponentProvider $provider): ComponentComplianceReport
    {
        $internal = $provider instanceof InternalComponentProvider;

        return new ComponentComplianceReport($this->safeNamespace($provider), [
            $this->checkNamespace($provider, $internal),
            $this->checkVersion($provider),
            $this->checkComponents($provider),
            $this->checkIdentifiers($provider, $internal),
            $this->checkKinds($provider),
            $this->checkDuplicates($provider),
            $this->checkRenderer($provider, $internal),
            $this->checkDeterminism($provider),
            $this->checkMetadata($provider),
            $this->checkIsolation($provider),
        ]);
    }

    public function isCompliant(ComponentProvider $provider): bool
    {
        return $this->validate($provider)->passed();
    }

    private function checkNamespace(ComponentProvider $provider, bool $internal): ComponentComplianceCheck
    {
        $ns = $this->safeNamespace($provider);

        if ($ns === '') {
            return ComponentComplianceCheck::fail('namespace', 'namespace() must be a non-empty string.');
        }

        if ($internal) {
            // The internal provider owns the reserved namespace and bare built-ins.
            return ComponentComplianceCheck::pass('namespace');
        }

        if ($ns === ComponentIdentifier::RESERVED_NAMESPACE) {
            return ComponentComplianceCheck::fail('namespace', 'The "page-builder" namespace is reserved.');
        }

        if (strlen($ns) > ComponentIdentifier::MAX_SEGMENT_LENGTH
            || preg_match(ComponentIdentifier::SEGMENT_PATTERN, $ns) !== 1) {
            return ComponentComplianceCheck::fail('namespace', 'namespace() must be a valid lowercase plugin slug.');
        }

        return ComponentComplianceCheck::pass('namespace');
    }

    private function checkVersion(ComponentProvider $provider): ComponentComplianceCheck
    {
        try {
            $version = $provider->version();
        } catch (Throwable $e) {
            return ComponentComplianceCheck::fail('version', 'version() threw: '.$e->getMessage());
        }

        return trim($version) === ''
            ? ComponentComplianceCheck::fail('version', 'version() must be a non-empty string.')
            : ComponentComplianceCheck::pass('version');
    }

    private function checkComponents(ComponentProvider $provider): ComponentComplianceCheck
    {
        try {
            foreach ($provider->components() as $component) {
                if (! $component instanceof ComponentDefinition) {
                    return ComponentComplianceCheck::fail(
                        'components',
                        'Every component must implement ComponentDefinition.'
                    );
                }
            }
        } catch (Throwable $e) {
            return ComponentComplianceCheck::fail('components', 'components() threw: '.$e->getMessage());
        }

        return ComponentComplianceCheck::pass('components');
    }

    private function checkIdentifiers(ComponentProvider $provider, bool $internal): ComponentComplianceCheck
    {
        $ns = $this->safeNamespace($provider);

        foreach ($this->definitions($provider) as $definition) {
            $type = $this->safeType($definition);

            if ($internal) {
                // Built-ins: a bare reserved id, or an explicit reserved-namespace id.
                if (ComponentIdentifier::isBuiltin($type) || $this->matchesNamespace($type, $ns)) {
                    continue;
                }

                return ComponentComplianceCheck::fail(
                    'identifiers',
                    'Internal identifier "'.$type.'" is not a valid built-in id.'
                );
            }

            if (ComponentIdentifier::classify($type) !== ComponentIdentifier::KIND_EXTERNAL) {
                return ComponentComplianceCheck::fail(
                    'identifiers',
                    'Identifier "'.$type.'" must be a valid "'.$ns.':<local>" id (bare ids are reserved).'
                );
            }

            if (! $this->matchesNamespace($type, $ns)) {
                return ComponentComplianceCheck::fail(
                    'identifiers',
                    'Identifier "'.$type.'" must use the provider namespace "'.$ns.'".'
                );
            }
        }

        return ComponentComplianceCheck::pass('identifiers');
    }

    private function checkKinds(ComponentProvider $provider): ComponentComplianceCheck
    {
        foreach ($this->definitions($provider) as $definition) {
            $isSection = $definition instanceof SectionDefinition;
            $isBlock = $definition instanceof BlockDefinition;

            if ($isSection === $isBlock) {
                return ComponentComplianceCheck::fail(
                    'kinds',
                    'Component "'.$this->safeType($definition).'" must be exactly one of SectionDefinition or BlockDefinition.'
                );
            }
        }

        return ComponentComplianceCheck::pass('kinds');
    }

    private function checkDuplicates(ComponentProvider $provider): ComponentComplianceCheck
    {
        $seen = [];

        foreach ($this->definitions($provider) as $definition) {
            $type = $this->safeType($definition);

            if (isset($seen[$type])) {
                return ComponentComplianceCheck::fail(
                    'duplicates',
                    'Duplicate identifier "'.$type.'" in the provider batch.'
                );
            }

            $seen[$type] = true;
        }

        return ComponentComplianceCheck::pass('duplicates');
    }

    private function checkRenderer(ComponentProvider $provider, bool $internal): ComponentComplianceCheck
    {
        if ($internal) {
            // Built-ins resolve by the existing view convention; no declared view.
            return ComponentComplianceCheck::pass('renderer');
        }

        $ns = $this->safeNamespace($provider);

        foreach ($this->definitions($provider) as $definition) {
            if (! $definition instanceof ExternalComponentDefinition) {
                return ComponentComplianceCheck::fail(
                    'renderer',
                    'External component "'.$this->safeType($definition).'" must implement ExternalComponentDefinition.'
                );
            }

            $view = $this->safeView($definition);
            $prefix = $ns.'::';

            if (! str_starts_with($view, $prefix)) {
                return ComponentComplianceCheck::fail(
                    'renderer',
                    'view() "'.$view.'" must live in the provider namespace "'.$prefix.'".'
                );
            }

            $tail = substr($view, strlen($prefix));

            if ($tail === '' || str_contains($tail, '..') || preg_match(self::VIEW_TAIL_PATTERN, $tail) !== 1) {
                return ComponentComplianceCheck::fail(
                    'renderer',
                    'view() "'.$view.'" is not a safe dot-notated view name.'
                );
            }
        }

        return ComponentComplianceCheck::pass('renderer');
    }

    private function checkDeterminism(ComponentProvider $provider): ComponentComplianceCheck
    {
        try {
            $first = $this->typeList($provider);
            $second = $this->typeList($provider);
        } catch (Throwable $e) {
            return ComponentComplianceCheck::fail('determinism', 'components() threw: '.$e->getMessage());
        }

        return $first === $second
            ? ComponentComplianceCheck::pass('determinism')
            : ComponentComplianceCheck::fail('determinism', 'components() must yield the same identifiers in the same order on every call.');
    }

    private function checkMetadata(ComponentProvider $provider): ComponentComplianceCheck
    {
        foreach ($this->definitions($provider) as $definition) {
            try {
                $array = $definition->toArray();
            } catch (Throwable $e) {
                return ComponentComplianceCheck::fail('metadata', 'toArray() threw: '.$e->getMessage());
            }

            if (! $this->isPlainData($array)) {
                return ComponentComplianceCheck::fail(
                    'metadata',
                    'Component "'.$this->safeType($definition).'" toArray() must contain only scalars and arrays (no objects/closures).'
                );
            }
        }

        return ComponentComplianceCheck::pass('metadata');
    }

    private function checkIsolation(ComponentProvider $provider): ComponentComplianceCheck
    {
        if ($provider instanceof ComponentProviderRegistry) {
            return ComponentComplianceCheck::fail('isolation', 'A provider must not also be the registry.');
        }

        return ComponentComplianceCheck::pass('isolation');
    }

    // ---- helpers --------------------------------------------------------

    /**
     * @return array<int, ComponentDefinition>
     */
    private function definitions(ComponentProvider $provider): array
    {
        $out = [];

        try {
            foreach ($provider->components() as $component) {
                if ($component instanceof ComponentDefinition) {
                    $out[] = $component;
                }
            }
        } catch (Throwable) {
            return [];
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    private function typeList(ComponentProvider $provider): array
    {
        return array_map(fn (ComponentDefinition $d): string => $this->safeType($d), $this->definitions($provider));
    }

    private function safeNamespace(ComponentProvider $provider): string
    {
        try {
            return trim($provider->namespace());
        } catch (Throwable) {
            return '';
        }
    }

    private function safeType(ComponentDefinition $definition): string
    {
        try {
            return trim($definition->type());
        } catch (Throwable) {
            return '';
        }
    }

    private function safeView(ExternalComponentDefinition $definition): string
    {
        try {
            return trim($definition->view());
        } catch (Throwable) {
            return '';
        }
    }

    private function matchesNamespace(string $type, string $namespace): bool
    {
        if (ComponentIdentifier::classify($type) !== ComponentIdentifier::KIND_EXTERNAL) {
            return false;
        }

        return ComponentIdentifier::parse($type)->namespace === $namespace;
    }

    /**
     * Recursively true when a value is JSON-plain data: scalars, null, or arrays
     * of plain data. No objects, no closures — nothing executable can persist.
     */
    private function isPlainData(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (! $this->isPlainData($item)) {
                    return false;
                }
            }

            return true;
        }

        return $value === null || is_scalar($value);
    }
}
