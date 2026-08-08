<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Schema\Fields;

/** Media (image/file) picker field, storing a media id or URL. */
class MediaField extends Field
{
    protected string $type = 'media';

    public function multiple(bool $multiple = true): static
    {
        return $this->editor(['multiple' => $multiple]);
    }

    /**
     * Restrict the accepted MIME/type hints for the picker.
     *
     * @param  array<int, string>  $accept
     */
    public function accept(array $accept): static
    {
        return $this->editor(['accept' => $accept]);
    }
}
