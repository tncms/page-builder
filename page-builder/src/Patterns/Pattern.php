<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns;

use Closure;
use Plugins\PageBuilder\Patterns\Contracts\PatternInterface;

/**
 * A discovered pattern: its manifest, the directory it lives in, its source id +
 * priority (for duplicate-id resolution), and a lazy payload loader. The payload
 * is only materialised when {@see payload()} is first called (§20 performance).
 */
final class Pattern implements PatternInterface
{
    /** @var array<string, mixed>|null */
    private ?array $payload = null;

    /**
     * @param  Closure(): array<string, mixed>  $payloadLoader
     */
    public function __construct(
        private readonly PatternManifest $manifest,
        private readonly Closure $payloadLoader,
        private readonly string $directory = '',
        private readonly string $sourceId = '',
        private readonly int $priority = 0,
        private readonly string $packId = '',
    ) {}

    public function id(): string
    {
        return $this->manifest->id;
    }

    public function manifest(): PatternManifest
    {
        return $this->manifest;
    }

    public function payload(): array
    {
        return $this->payload ??= ($this->payloadLoader)();
    }

    public function directory(): string
    {
        return $this->directory;
    }

    public function sourceId(): string
    {
        return $this->sourceId;
    }

    /** The pack this pattern belongs to (empty when it is not part of a pack). */
    public function packId(): string
    {
        return $this->packId;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    /** Absolute path to the thumbnail file (may not exist). */
    public function thumbnailPath(): string
    {
        if ($this->directory === '') {
            return '';
        }

        return $this->directory.DIRECTORY_SEPARATOR.$this->manifest->thumbnail;
    }
}
