<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Authority;

use RuntimeException;

/**
 * A Page Builder authority refusal (PB-CORE37-R1): the actor may not perform the
 * operation on this content, the content belongs to another extension, or the
 * operation lacks the Core revision it was planned against.
 *
 * Carries a bounded machine reason and the HTTP status the boundary maps it to.
 * The message is a safe, user-facing sentence (never a permission dump).
 */
final class PageBuilderAuthorityException extends RuntimeException
{
    /** @param  array<string, mixed>  $current  reconciliation metadata (never payloads) */
    private function __construct(
        public readonly string $reason,
        public readonly int $statusCode,
        string $message,
        public readonly array $current = [],
    ) {
        parent::__construct($message);
    }

    public static function unauthenticated(): self
    {
        return new self('unauthenticated', 403, 'You must be signed in to change this content.');
    }

    public static function forbidden(string $reason, string $message): self
    {
        return new self($reason, 403, $message);
    }

    public static function managed(string $owner): self
    {
        return new self(
            'managed_content',
            403,
            'This content is managed by another extension, so Page Builder cannot change it.',
            ['owner' => $owner],
        );
    }

    public static function unsupportedType(string $type): self
    {
        return new self('unsupported_type', 404, "Page Builder does not edit content of type [{$type}].");
    }

    public static function revisionRequired(int $currentRevision): self
    {
        return new self(
            'core_revision_required',
            409,
            'This change is missing the content revision it was based on. Reload the editor and try again.',
            ['content_revision' => $currentRevision],
        );
    }
}
