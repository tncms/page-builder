<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

/**
 * Per-breakpoint + audience visibility value — Phase 6.
 *
 * Breakpoints default to visible (true). Audience flags (`loggedIn`/
 * `loggedOut`) are nullable = "don't care". Optional role allow-list. Tolerant
 * `from()`; pure value object — it only describes intent, it does not enforce it.
 */
final class VisibilityValue
{
    /**
     * @param  array<int, string>  $roles
     */
    public function __construct(
        private readonly bool $desktop = true,
        private readonly bool $tablet = true,
        private readonly bool $mobile = true,
        private readonly ?bool $loggedIn = null,
        private readonly ?bool $loggedOut = null,
        private readonly array $roles = [],
    ) {}

    public static function from(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (! is_array($value)) {
            return new self;
        }

        return new self(
            (bool) ($value['desktop'] ?? true),
            (bool) ($value['tablet'] ?? true),
            (bool) ($value['mobile'] ?? true),
            array_key_exists('loggedIn', $value) ? (bool) $value['loggedIn'] : null,
            array_key_exists('loggedOut', $value) ? (bool) $value['loggedOut'] : null,
            is_array($value['roles'] ?? null) ? array_values(array_map('strval', $value['roles'])) : [],
        );
    }

    public function visibleOn(string $device): bool
    {
        return match ($device) {
            'desktop' => $this->desktop,
            'tablet' => $this->tablet,
            'mobile' => $this->mobile,
            default => true,
        };
    }

    /**
     * @return array{desktop: bool, tablet: bool, mobile: bool, loggedIn: ?bool, loggedOut: ?bool, roles: array<int, string>}
     */
    public function toArray(): array
    {
        return [
            'desktop' => $this->desktop,
            'tablet' => $this->tablet,
            'mobile' => $this->mobile,
            'loggedIn' => $this->loggedIn,
            'loggedOut' => $this->loggedOut,
            'roles' => $this->roles,
        ];
    }
}
