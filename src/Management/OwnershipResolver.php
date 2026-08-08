<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Management;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Resolves whether the current actor may manage a "My Designs" resource
 * (Phase 26.49, Rule 16).
 *
 * Ownership is ALWAYS verified server-side: a record stores the display author
 * derived from the authenticated user at capture time (mirrors the Design
 * Capture controller), and this compares that stored author to the same
 * derivation for the current user. A privileged administrator — anyone holding
 * the elevated capability — may manage any design. The client's claimed
 * ownership is never trusted.
 */
final class OwnershipResolver
{
    /** The capability that lets an administrator manage designs they do not own. */
    public const ADMIN_CAPABILITY = 'page-builder.publish';

    /**
     * May the current user manage this record (owner OR authorized admin)?
     *
     * @param  array<string, mixed>  $record
     */
    public function canManage(array $record, ?Authenticatable $user): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $author = $record['author'] ?? null;
        if (! is_string($author) || $author === '') {
            // A design with no recorded owner can only be managed by an admin.
            return false;
        }

        $current = $this->currentAuthor($user);

        return $current !== null && $current === $author;
    }

    /**
     * The display author for the current user — the exact derivation the Design
     * Capture controller stored (so ownership comparisons line up).
     */
    public function currentAuthor(?Authenticatable $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $name = $user->name ?? null;

        return is_string($name) && $name !== '' ? $name : (string) $user->getAuthIdentifier();
    }

    private function isAdmin(): bool
    {
        return function_exists('cms_can') && cms_can(self::ADMIN_CAPABILITY);
    }
}
