<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Cloning\Adapters;

use Closure;
use Illuminate\Support\Facades\DB;
use Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\TransactionRunnerInterface;

/**
 * The Laravel/database implementation of the transaction boundary (LT-3).
 *
 * Layer: Translation (adapter). Keeps the framework transaction API at the
 * application-service boundary; the domain value objects never depend on it.
 */
final class IlluminateTransactionRunner implements TransactionRunnerInterface
{
    public function run(Closure $work): mixed
    {
        return DB::transaction($work);
    }
}
