<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Plugins\PageBuilder\State\BuilderState;
use Plugins\PageBuilder\State\CommandBus;
use Plugins\PageBuilder\State\CommandFactory;
use Plugins\PageBuilder\State\Transactions\Transaction;
use Plugins\PageBuilder\State\Transactions\TransactionManager;

/**
 * Stateless command execution endpoints for the future Vue editor (Phase 8).
 *
 * The editor never edits raw layout JSON: it sends the current payload plus a
 * command (or a transaction of commands), the server runs it through the same
 * CommandBus / BuilderState the internal API uses, and returns the updated state
 * array. There is no server-side session — the payload round-trips each call, so
 * the editor stays the source of truth. Gated by `page-builder.edit`.
 */
final class StateCommandController
{
    public function __construct(
        private readonly CommandFactory $factory,
        private readonly CommandBus $bus,
        private readonly TransactionManager $transactions,
    ) {}

    public function command(Request $request): JsonResponse
    {
        $this->authorize();

        $state = BuilderState::make($this->payload($request));

        try {
            $command = $this->factory->make(
                (string) $request->input('command', ''),
                (array) $request->input('command_payload', []),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        try {
            $state = $this->bus->dispatch($state, $command);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $state->toArray()]);
    }

    public function transaction(Request $request): JsonResponse
    {
        $this->authorize();

        $state = BuilderState::make($this->payload($request));
        $transaction = Transaction::make((string) $request->input('label', 'Transaction'));

        try {
            foreach ((array) $request->input('commands', []) as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $transaction->add($this->factory->make(
                    (string) ($entry['command'] ?? $entry['name'] ?? ''),
                    (array) ($entry['command_payload'] ?? $entry['payload'] ?? []),
                ));
            }

            $state = $this->transactions->run($state, $transaction);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $state->toArray()]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function payload(Request $request): ?array
    {
        $payload = $request->input('payload');

        return is_array($payload) ? $payload : null;
    }

    private function authorize(): void
    {
        abort_unless(function_exists('cms_can') && cms_can('page-builder.edit'), 403);
    }
}
