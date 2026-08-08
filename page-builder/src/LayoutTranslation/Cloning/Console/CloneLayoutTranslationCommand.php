<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Cloning\Console;

use Illuminate\Console\Command;
use Plugins\PageBuilder\LayoutTranslation\Cloning\Commands\CloneLayoutTranslationCommand as CloneCommand;
use Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\LayoutTranslationCloneServiceInterface;
use Plugins\PageBuilder\LayoutTranslation\Cloning\Results\LayoutTranslationCloneOutcome;
use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Identity\TranslationGroupKey;

/**
 * Administrative clone entry point (LT-3).
 *
 * Layer: Translation (console). An OPERATOR command: it resolves canonical
 * identities, shows a summary before mutation, invokes the application clone
 * SERVICE (never repositories directly), never overwrites an existing target, and
 * returns a meaningful exit code. Authorization is the operator's responsibility
 * (an admin CLI); the service itself is authorization-neutral.
 */
final class CloneLayoutTranslationCommand extends Command
{
    protected $signature = 'page-builder:layout-translation:clone
        {ownerType : The content owner type (page|post)}
        {ownerId : The content id (the translation group anchor)}
        {sourceLocale : The source page translation locale}
        {targetLocale : The target page translation locale}';

    protected $description = 'Clone a source page translation layout to a target page translation (draft, atomic, never overwrites).';

    public function handle(LayoutTranslationCloneServiceInterface $service): int
    {
        $group = TranslationGroupKey::forOwner((string) $this->argument('ownerType'), (string) $this->argument('ownerId'));
        $source = PageTranslationKey::forGroup($group, (string) $this->argument('sourceLocale'));
        $target = PageTranslationKey::forGroup($group, (string) $this->argument('targetLocale'));

        $this->line('Clone layout translation:');
        $this->table(['field', 'value'], [
            ['translation_group', $group->value()],
            ['source', $source->value()],
            ['target', $target->value()],
        ]);

        $result = $service->cloneTranslation(new CloneCommand($group, $source, $target, 'cli'));

        $this->line('Outcome: '.$result->outcome()->value);

        return match ($result->outcome()) {
            LayoutTranslationCloneOutcome::Created, LayoutTranslationCloneOutcome::AlreadyCloned => self::SUCCESS,
            default => self::FAILURE,
        };
    }
}
