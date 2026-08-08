<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Persistence\Results;

/**
 * The typed outcome of a binding write — LT-2.
 *
 * Layer: Persistence / Result. Typed outcomes, never magic strings.
 */
enum BindingWriteOutcome: string
{
    case Created = 'created';

    case AlreadyExists = 'already_exists';

    case Updated = 'updated';

    case Conflict = 'conflict';

    case InvalidIdentity = 'invalid_identity';

    case InvalidReference = 'invalid_reference';

    case Failed = 'failed';
}
