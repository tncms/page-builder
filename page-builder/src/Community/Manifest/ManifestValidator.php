<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Manifest;

use Plugins\PageBuilder\Community\Manifest\Contracts\ManifestContract;
use Plugins\PageBuilder\Community\Manifest\Contracts\ManifestValidatorContract;
use Plugins\PageBuilder\Repository\Rid;

/**
 * The official Resource Manifest validator (Phase 26.50A, Rule 15).
 *
 * It rejects malformed manifests field-by-field: schema, rid, fingerprint,
 * classification, compatibility, dependencies, license, assets, author and preview.
 * It NEVER infers compatibility (Rule 5) and NEVER touches commerce (Rule 18) — it
 * only confirms the declaration is well-formed and internally consistent (e.g. the
 * rid's source/kind agree with the provider/resource_type).
 *
 * A future Marketplace / Enterprise validator EXTENDS this contract with license /
 * purchase checks; the shared manifest validation here stays the single baseline
 * every provider passes (Rule 12).
 *
 * @see docs/manifest-validator.md
 */
final class ManifestValidator implements ManifestValidatorContract
{
    private const FINGERPRINT = '/^[0-9a-f]{16,}$/i';

    private const VERSION = '/^\d+\.\d+\.\d+/';

    private const SLUG = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    public function __construct(
        private readonly ManifestParser $parser = new ManifestParser,
    ) {}

    /**
     * @param  ManifestContract|array<string, mixed>  $manifest
     */
    public function validate(ManifestContract|array $manifest): ManifestValidationResult
    {
        $m = $manifest instanceof ResourceManifest
            ? $manifest
            : ($manifest instanceof ManifestContract ? $this->parser->fromArray($manifest->toArray()) : $this->parser->fromArray($manifest));

        $errors = [];

        $this->checkSchema($m, $errors);
        $this->checkIdentity($m, $errors);
        $this->checkClassification($m, $errors);
        $this->checkVersion($m, $errors);
        $this->checkCompatibility($m, $errors);
        $this->checkDependencies($m, $errors);
        $this->checkLicense($m, $errors);
        $this->checkAssets($m, $errors);
        $this->checkAuthor($m, $errors);
        $this->checkPreview($m, $errors);

        return new ManifestValidationResult($errors);
    }

    public function isValid(ManifestContract|array $manifest): bool
    {
        return $this->validate($manifest)->passed();
    }

    /**
     * @param  array<int, array{field: string, message: string}>  $errors
     */
    private function checkSchema(ResourceManifest $m, array &$errors): void
    {
        if ($m->schema !== ResourceManifest::SCHEMA) {
            $errors[] = ['field' => 'schema', 'message' => "unsupported schema '{$m->schema}' (expected '".ResourceManifest::SCHEMA."')."];
        }
    }

    /**
     * @param  array<int, array{field: string, message: string}>  $errors
     */
    private function checkIdentity(ResourceManifest $m, array &$errors): void
    {
        if (! Rid::isValid($m->rid)) {
            $errors[] = ['field' => 'rid', 'message' => "'{$m->rid}' is not a valid resource identifier."];
        } else {
            $parsed = Rid::parse($m->rid);
            if ($m->provider !== '' && $parsed->source !== $m->provider) {
                $errors[] = ['field' => 'rid', 'message' => "rid source '{$parsed->source}' must equal provider '{$m->provider}'."];
            }
            if ($m->resourceType !== '' && $parsed->kind !== $m->resourceType) {
                $errors[] = ['field' => 'rid', 'message' => "rid kind '{$parsed->kind}' must equal resource_type '{$m->resourceType}'."];
            }
        }

        if (! preg_match(self::FINGERPRINT, $m->fingerprint)) {
            $errors[] = ['field' => 'fingerprint', 'message' => 'fingerprint must be a non-empty hex content digest.'];
        }
    }

    /**
     * @param  array<int, array{field: string, message: string}>  $errors
     */
    private function checkClassification(ResourceManifest $m, array &$errors): void
    {
        if (! in_array($m->resourceType, Rid::KINDS, true)) {
            $errors[] = ['field' => 'resource_type', 'message' => "unknown resource_type '{$m->resourceType}'."];
        }
        if (! preg_match(self::SLUG, $m->provider)) {
            $errors[] = ['field' => 'provider', 'message' => 'provider must be a non-empty lowercase slug.'];
        }
        if (trim($m->title) === '') {
            $errors[] = ['field' => 'title', 'message' => 'title is required.'];
        }
    }

    /**
     * @param  array<int, array{field: string, message: string}>  $errors
     */
    private function checkVersion(ResourceManifest $m, array &$errors): void
    {
        if (! preg_match(self::VERSION, $m->version)) {
            $errors[] = ['field' => 'version', 'message' => "version '{$m->version}' must be semantic (e.g. 1.0.0)."];
        }
    }

    /**
     * @param  array<int, array{field: string, message: string}>  $errors
     */
    private function checkCompatibility(ResourceManifest $m, array &$errors): void
    {
        // Declared, never inferred (Rule 5). The value object always supplies the
        // block; we only reject empty declarations and a malformed minimum version.
        if (trim($m->compatibility->pageBuilder) === '') {
            $errors[] = ['field' => 'compatibility', 'message' => 'compatibility.page_builder is required (use "*" for any).'];
        }
        if (trim($m->compatibility->theme) === '') {
            $errors[] = ['field' => 'compatibility', 'message' => 'compatibility.theme is required (use "*" for any).'];
        }
        $min = $m->compatibility->minimumBuilderVersion;
        if ($min !== null && ! preg_match(self::VERSION, $min)) {
            $errors[] = ['field' => 'compatibility', 'message' => "minimum_builder_version '{$min}' must be semantic."];
        }
    }

    /**
     * @param  array<int, array{field: string, message: string}>  $errors
     */
    private function checkDependencies(ResourceManifest $m, array &$errors): void
    {
        foreach ($m->dependencies as $i => $dependency) {
            if (! $dependency->isKnownType()) {
                $errors[] = ['field' => 'dependencies', 'message' => "dependency #{$i} has unknown type '{$dependency->type}'."];
            }
            if ($dependency->id === '') {
                $errors[] = ['field' => 'dependencies', 'message' => "dependency #{$i} is missing an id."];
            } elseif ($dependency->isResource() && ! Rid::isValid($dependency->id)) {
                $errors[] = ['field' => 'dependencies', 'message' => "dependency #{$i} resource id '{$dependency->id}' is not a valid rid."];
            }
        }
    }

    /**
     * @param  array<int, array{field: string, message: string}>  $errors
     */
    private function checkLicense(ResourceManifest $m, array &$errors): void
    {
        if (! $m->license->isKnownType()) {
            $errors[] = ['field' => 'license', 'message' => "unknown license type '{$m->license->type}'."];
        }
    }

    /**
     * @param  array<int, array{field: string, message: string}>  $errors
     */
    private function checkAssets(ResourceManifest $m, array &$errors): void
    {
        foreach ($m->assets as $i => $asset) {
            if (! $asset->isKnownType()) {
                $errors[] = ['field' => 'assets', 'message' => "asset #{$i} has unknown type '{$asset->type}'."];
            }
            if (trim($asset->ref) === '') {
                $errors[] = ['field' => 'assets', 'message' => "asset #{$i} is missing a ref."];
            }
        }
    }

    /**
     * @param  array<int, array{field: string, message: string}>  $errors
     */
    private function checkAuthor(ResourceManifest $m, array &$errors): void
    {
        if (trim($m->author->name) === '') {
            $errors[] = ['field' => 'author', 'message' => 'author.name is required.'];
        }
    }

    /**
     * @param  array<int, array{field: string, message: string}>  $errors
     */
    private function checkPreview(ResourceManifest $m, array &$errors): void
    {
        foreach (['width' => $m->preview->width, 'height' => $m->preview->height] as $key => $value) {
            if ($value !== null && $value < 0) {
                $errors[] = ['field' => 'preview', 'message' => "preview.{$key} must not be negative."];
            }
        }
    }
}
