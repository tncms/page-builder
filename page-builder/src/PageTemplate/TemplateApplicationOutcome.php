<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\PageTemplate;

/**
 * The immutable result of applying a Page Template to a destination (Phase 26.47D).
 *
 * A success carries the owner it was applied to and the Builder editor URL the
 * caller should redirect to. A failure carries the issues that prevented the
 * application (reusing the {code, message, severity} shape emitted by the Full
 * Page import layer) and no redirect. The application only ever touches the
 * Builder draft — see TemplateApplicationService.
 */
final class TemplateApplicationOutcome
{
    /**
     * @param  array<int, array{code: string, message: string, severity: string}>  $issues
     */
    private function __construct(
        public readonly bool $ok,
        public readonly string $ownerType,
        public readonly int|string|null $ownerId,
        public readonly ?string $redirectUrl,
        public readonly array $issues,
        public readonly bool $created,
    ) {
    }

    public static function success(
        string $ownerType,
        int|string $ownerId,
        ?string $redirectUrl,
        bool $created,
    ): self {
        return new self($ok = true, $ownerType, $ownerId, $redirectUrl, [], $created);
    }

    /**
     * @param  array<int, array{code: string, message: string, severity: string}>  $issues
     */
    public static function failure(array $issues, string $ownerType = '', int|string|null $ownerId = null): self
    {
        return new self(false, $ownerType, $ownerId, null, array_values($issues), false);
    }

    /**
     * A single-issue failure convenience.
     */
    public static function reject(string $code, string $message, string $ownerType = ''): self
    {
        return self::failure([['code' => $code, 'message' => $message, 'severity' => 'blocker']], $ownerType);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'ownerType' => $this->ownerType,
            'ownerId' => $this->ownerId,
            'redirect' => $this->redirectUrl,
            'created' => $this->created,
            'issues' => $this->issues,
        ];
    }
}
