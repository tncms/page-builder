<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Preview;

/**
 * Immutable description of what to preview.
 *
 * A PreviewContext carries a fully-formed, id-free LayoutPayload (the resource
 * rendered as a page/section/block), the target device, and rendering options.
 * It NEVER references the editor's BuilderState, the current page's content, or
 * any mutable runtime — a preview is a pure projection of the resource itself.
 *
 * The object is read-only; "mutations" return new instances.
 */
final class PreviewContext
{
    public const KIND_BLOCK = 'block';
    public const KIND_SECTION = 'section';
    public const KIND_PAGE = 'page';

    public const KINDS = [self::KIND_BLOCK, self::KIND_SECTION, self::KIND_PAGE];

    /**
     * @param  array<string, mixed>  $payload  A LayoutPayload: {schema?, sections:[...]}
     */
    public function __construct(
        public readonly string $kind,
        public readonly array $payload,
        public readonly string $device = 'desktop',
        public readonly string $locale = 'en',
        public readonly bool $sampleContent = true,
        public readonly string $label = '',
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function page(array $payload, string $label = ''): self
    {
        return new self(self::KIND_PAGE, $payload, label: $label);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function section(array $payload, string $label = ''): self
    {
        return new self(self::KIND_SECTION, $payload, label: $label);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function block(array $payload, string $label = ''): self
    {
        return new self(self::KIND_BLOCK, $payload, label: $label);
    }

    public function withDevice(string $device): self
    {
        return new self($this->kind, $this->payload, $device, $this->locale, $this->sampleContent, $this->label);
    }

    public function withLocale(string $locale): self
    {
        return new self($this->kind, $this->payload, $this->device, $locale, $this->sampleContent, $this->label);
    }

    public function withSampleContent(bool $sampleContent): self
    {
        return new self($this->kind, $this->payload, $this->device, $this->locale, $sampleContent, $this->label);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function withPayload(array $payload): self
    {
        return new self($this->kind, $payload, $this->device, $this->locale, $this->sampleContent, $this->label);
    }

    public function isValidKind(): bool
    {
        return in_array($this->kind, self::KINDS, true);
    }

    /**
     * A deterministic cache key: identical contexts always produce the same key,
     * so preview HTML can be reused without any persistent storage.
     */
    public function cacheKey(): string
    {
        return 'pb-preview:'.sha1((string) json_encode([
            'kind' => $this->kind,
            'device' => $this->device,
            'locale' => $this->locale,
            'sample' => $this->sampleContent,
            'payload' => $this->payload,
        ], JSON_UNESCAPED_SLASHES));
    }
}
