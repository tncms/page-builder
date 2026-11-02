<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Themes;

use Illuminate\Support\Facades\Log;
use Plugins\PageBuilder\Components\ComponentProviderRegistry;
use Plugins\PageBuilder\Components\Exceptions\ComponentNamespaceViolationException;
use Plugins\PageBuilder\Components\Exceptions\ComponentRegistryFrozenException;
use Plugins\PageBuilder\Components\Exceptions\DuplicateComponentIdentifierException;
use Plugins\PageBuilder\Components\Exceptions\NonCompliantComponentProviderException;
use TheNguyen\CMS\Services\ThemeManager;
use Throwable;

/**
 * Registers the active theme chain's declarative components (PB-XREG-4), once per request, from Page Builder's
 * `booted` callback — after every plugin's boot() and immediately before the single registry freeze.
 *
 * Failure isolation: each theme in the chain is one atomic batch. A rejected batch never throws out of boot,
 * never mutates the registry, and never affects built-ins, plugins, theme design resources, or another theme in
 * the chain. The outcome is a bounded diagnostic code per theme (no paths, classes or messages).
 */
final class ThemeComponentRegistration
{
    /** @var list<array{theme: string, code: string}> */
    private array $diagnostics = [];

    private bool $ran = false;

    public function __construct(
        private readonly ComponentProviderRegistry $registry,
        private readonly ThemeOriginIdentity $identity,
        private readonly ThemeComponentManifestReader $reader,
        private readonly ThemeComponentFactory $factory,
        private readonly ?ThemeManager $themes = null,
    ) {}

    public function registerActiveThemes(): void
    {
        if ($this->ran) {
            return;
        }
        $this->ran = true;

        foreach ($this->identity->activeChain() as $slug) {
            $this->registerTheme($slug);
        }
    }

    /** @return list<array{theme: string, code: string}> */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    private function registerTheme(string $slug): void
    {
        try {
            $provider = ThemeComponentProvider::fromTheme($slug, $this->versionOf($slug), $this->reader, $this->factory);

            if ($provider->components() === []) {
                return;
            }

            $code = $this->identity->ineligibility($slug);
            if ($code !== null) {
                $this->diagnose($slug, $code);

                return;
            }

            $this->registry->register($provider);
        } catch (ThemeComponentException $e) {
            $this->diagnose($slug, $e->diagnostic);
        } catch (NonCompliantComponentProviderException $e) {
            $this->diagnose($slug, 'theme.noncompliant.'.($e->report->failures()[0]->name ?? 'unknown'));
        } catch (ComponentNamespaceViolationException) {
            $this->diagnose($slug, 'theme.namespace_violation');
        } catch (DuplicateComponentIdentifierException) {
            $this->diagnose($slug, 'theme.duplicate_identifier');
        } catch (ComponentRegistryFrozenException) {
            $this->diagnose($slug, 'theme.registry_frozen');
        } catch (Throwable) {
            $this->diagnose($slug, 'theme.registration_failed');
        }
    }

    private function versionOf(string $slug): string
    {
        try {
            return (string) ($this->themes()->find($slug)?->version ?? '');
        } catch (Throwable) {
            return '';
        }
    }

    private function diagnose(string $slug, string $code): void
    {
        $this->diagnostics[] = ['theme' => $slug, 'code' => $code];

        try {
            Log::warning('Page Builder: theme components were not registered.', ['theme' => $slug, 'code' => $code]);
        } catch (Throwable) {
            // Logging must never break boot.
        }
    }

    private function themes(): ThemeManager
    {
        return $this->themes ?? app('cms.theme');
    }
}
