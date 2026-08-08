<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns;

/**
 * Turns a payload TEMPLATE (a normal Builder payload that may contain `{{id}}`
 * placeholders) into a plain, ready-to-insert Builder payload by substituting the
 * pattern's variables (Phase 25.1, §3/§4).
 *
 * Rules — deliberately minimal (§3): simple variable interpolation only. No
 * expressions, no scripting, no eval. A whole-string placeholder (`"{{n}}"`)
 * yields the variable's native value (so a number/toggle stays typed); a partial
 * placeholder (`"Hi {{name}}"`) is string-substituted. Unknown placeholders are
 * left untouched. A pattern WITHOUT variables returns its payload unchanged, so
 * every existing pattern keeps working (§1).
 */
final class PatternVariableResolver
{
    /** Matches `{{ id }}` anywhere in a string. */
    private const PLACEHOLDER = '/\{\{\s*([A-Za-z0-9_.-]+)\s*\}\}/';

    /** Matches a string that is EXACTLY one placeholder (whole-value substitution). */
    private const WHOLE = '/^\{\{\s*([A-Za-z0-9_.-]+)\s*\}\}$/';

    /**
     * @param  array<string, mixed>  $payload  The pattern's (template) payload.
     * @param  array<string, mixed>  $input    User-supplied values keyed by variable id.
     * @return array<string, mixed>
     */
    public function resolve(PatternManifest $manifest, array $payload, array $input = []): array
    {
        if ($manifest->variables === []) {
            return $payload;
        }

        [$typed, $strings] = $this->valueMaps($manifest->variables, $input);

        /** @var array<string, mixed> $resolved */
        $resolved = $this->interpolate($payload, $typed, $strings);

        return $resolved;
    }

    /**
     * The resolved value map (id => native value), merging defaults with user input.
     * Public so callers/tests can inspect what will be substituted.
     *
     * @param  array<int, PatternVariable>  $variables
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function values(array $variables, array $input = []): array
    {
        return $this->valueMaps($variables, $input)[0];
    }

    /**
     * Every distinct `{{id}}` referenced anywhere in a payload (§15 validation).
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    public function placeholders(array $payload): array
    {
        $found = [];
        $this->walkStrings($payload, function (string $s) use (&$found): void {
            if (preg_match_all(self::PLACEHOLDER, $s, $m) > 0) {
                foreach ($m[1] as $id) {
                    $found[$id] = true;
                }
            }
        });

        return array_keys($found);
    }

    /**
     * @param  array<int, PatternVariable>  $variables
     * @param  array<string, mixed>  $input
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function valueMaps(array $variables, array $input): array
    {
        $typed = [];
        $strings = [];
        foreach ($variables as $variable) {
            $value = array_key_exists($variable->id, $input) && $input[$variable->id] !== null
                ? $input[$variable->id]
                : $variable->default;
            $typed[$variable->id] = $value;
            $strings[$variable->id] = $this->stringify($value);
        }

        return [$typed, $strings];
    }

    /**
     * @param  array<string, mixed>  $typed
     * @param  array<string, string>  $strings
     */
    private function interpolate(mixed $node, array $typed, array $strings): mixed
    {
        if (is_array($node)) {
            $out = [];
            foreach ($node as $key => $value) {
                $out[$key] = $this->interpolate($value, $typed, $strings);
            }

            return $out;
        }

        if (is_string($node)) {
            return $this->replace($node, $typed, $strings);
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>  $typed
     * @param  array<string, string>  $strings
     */
    private function replace(string $value, array $typed, array $strings): mixed
    {
        if (preg_match(self::WHOLE, $value, $m) === 1 && array_key_exists($m[1], $typed)) {
            return $typed[$m[1]];
        }

        return preg_replace_callback(
            self::PLACEHOLDER,
            static fn (array $m): string => array_key_exists($m[1], $strings) ? $strings[$m[1]] : $m[0],
            $value,
        );
    }

    private function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }

    /**
     * @param  mixed  $node
     * @param  callable(string): void  $onString
     */
    private function walkStrings(mixed $node, callable $onString): void
    {
        if (is_array($node)) {
            foreach ($node as $value) {
                $this->walkStrings($value, $onString);
            }

            return;
        }
        if (is_string($node)) {
            $onString($node);
        }
    }
}
