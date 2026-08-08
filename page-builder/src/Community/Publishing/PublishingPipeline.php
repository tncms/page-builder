<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Publishing;

use Closure;
use Plugins\PageBuilder\Community\Manifest\ManifestValidator;
use Plugins\PageBuilder\Community\Manifest\ResourceManifest;
use Plugins\PageBuilder\Repository\Rid;

/**
 * The deterministic publishing pipeline (Phase 26.50B, Rules 3 & 5).
 *
 * Publishing ALWAYS executes the same ordered, fail-fast steps:
 *
 *   Validate Manifest → Validate Payload → Validate Dependencies →
 *   Validate Compatibility → Generate Package Metadata
 *
 * Each step is deterministic and nothing is auto-corrected: the first failing step
 * short-circuits and its field errors are returned verbatim. It reuses the frozen
 * {@see ManifestValidator} (no second validator) and NEVER renders a preview,
 * downloads, uploads, installs or resolves dependencies (Rules 12, 18). The pipeline
 * is pure: it computes and returns metadata; persistence/history are the service's
 * job. The recording of a publish (history, status) belongs to
 * {@see CommunityPublishingService}.
 *
 * @see docs/publishing-workflow.md
 */
final class PublishingPipeline
{
    /** Manifest-step fields, in the frozen validator's vocabulary. */
    private const MANIFEST_FIELDS = ['schema', 'rid', 'fingerprint', 'resource_type', 'provider', 'title', 'license', 'assets', 'author'];

    private const COMPATIBILITY_FIELDS = ['compatibility', 'version'];

    /** @var Closure(): int */
    private readonly Closure $clock;

    /**
     * @param  (Closure(): int)|null  $clock  publish-time source; defaults to time()
     */
    public function __construct(
        private readonly ManifestValidator $validator = new ManifestValidator,
        ?Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    /**
     * Run only the validation steps (no packaging). Success ⇒ status Validated.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $preview
     */
    public function validate(ResourceManifest $manifest, array $payload, array $preview = []): PublishingResult
    {
        $byField = $this->errorsByField($manifest);

        $manifestErrors = $this->collect($byField, self::MANIFEST_FIELDS);
        if ($manifestErrors !== []) {
            return PublishingResult::fail(PublishingResult::STEP_MANIFEST, $manifestErrors);
        }

        $payloadErrors = [...$this->checkPayload($manifest, $payload), ...$this->collect($byField, ['preview'])];
        if ($payloadErrors !== []) {
            return PublishingResult::fail(PublishingResult::STEP_PAYLOAD, $payloadErrors);
        }

        $dependencyErrors = $this->collect($byField, ['dependencies']);
        if ($dependencyErrors !== []) {
            return PublishingResult::fail(PublishingResult::STEP_DEPENDENCIES, $dependencyErrors);
        }

        $compatibilityErrors = $this->collect($byField, self::COMPATIBILITY_FIELDS);
        if ($compatibilityErrors !== []) {
            return PublishingResult::fail(PublishingResult::STEP_COMPATIBILITY, $compatibilityErrors);
        }

        return PublishingResult::ok(ResourceStatus::Validated);
    }

    /**
     * Validate, then generate immutable package metadata. Success ⇒ status Packaged.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $preview
     */
    public function run(ResourceManifest $manifest, array $payload, array $preview = []): PublishingResult
    {
        $validation = $this->validate($manifest, $payload, $preview);
        if ($validation->failed()) {
            return $validation;
        }

        $package = PackageMetadata::forManifest($manifest, ($this->clock)());

        return PublishingResult::ok(ResourceStatus::Packaged, $package);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function checkPayload(ResourceManifest $manifest, array $payload): array
    {
        if ($payload === []) {
            return ['payload is empty — nothing to publish.'];
        }

        // A kit is composed from member RIDs, not a section tree; the primitive
        // kinds must carry a non-empty renderable section list.
        if ($manifest->resourceType === Rid::KIND_KIT) {
            return [];
        }

        $sections = $payload['sections'] ?? null;
        if (! is_array($sections) || $sections === []) {
            return ['payload has no sections — the layout is broken.'];
        }

        return [];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function errorsByField(ResourceManifest $manifest): array
    {
        $grouped = [];
        foreach ($this->validator->validate($manifest)->errors() as $error) {
            $grouped[$error['field']][] = $error['message'];
        }

        return $grouped;
    }

    /**
     * @param  array<string, array<int, string>>  $byField
     * @param  array<int, string>  $fields
     * @return array<int, string>
     */
    private function collect(array $byField, array $fields): array
    {
        $out = [];
        foreach ($fields as $field) {
            foreach ($byField[$field] ?? [] as $message) {
                $out[] = $message;
            }
        }

        return $out;
    }
}
