<?php declare(strict_types=1);

namespace Kuria\ComposerPkgScripts\Script;

use Kuria\ComposerPkgScripts\Exception\ScriptCompilerException;
use Kuria\ComposerPkgScripts\Exception\InvalidScriptVariableException;

class ScriptCompiler
{
    private const VARIABLE_PLACEHOLDER_REGEXP = '{\{\$([^\}]++)\}}';
    private const DIRECT_VARIABLE_REFERENCE_REGEXP = '{^\{\$([^\}]++)\}$}';

    /** @var array */
    private $globalVariables = [];
    /** @var array */
    private $packageVariables = [];
    /** @var array|null */
    private $compiledPackageVariables;
    /** @var callable */
    private $shellArgEscaper = 'escapeshellarg';

    function compile(Script $script): array
    {
        $listeners = [];

        foreach ((array) $script->definition as $listener) {
            $listeners[] = $this->compileScriptListener($script, (string) $listener);
        }

        return $listeners;
    }

    function getGlobalVariables(): array
    {
        return $this->globalVariables;
    }

    function setGlobalVariables(array $globalVariables): void
    {
        $this->globalVariables = $globalVariables;
    }

    function getPackageVariables(): array
    {
        return $this->packageVariables;
    }

    function setPackageVariables(array $packageVariables): void
    {
        $this->packageVariables = $packageVariables;
        $this->compiledPackageVariables = null;
    }

    function setShellArgEscaper(callable $shellArgEscaper): void
    {
        $this->shellArgEscaper = $shellArgEscaper;
    }

    private function compileScriptListener(Script $script, string $listener): string
    {
        return preg_replace_callback(
            self::VARIABLE_PLACEHOLDER_REGEXP,
            function (array $match) use ($script) {
                $value = $this->compilePackageVariable($script->package, $match[1])
                    ?? $this->globalVariables[$match[1]]
                    ?? '';

                return is_array($value)
                    ? implode(' ', array_map($this->shellArgEscaper, $value))
                    : ($this->shellArgEscaper)($value);
            },
            $listener
        );
    }

    private function compilePackageVariable(string $package, string $variable, array $visited = [])
    {
        if (isset($this->compiledPackageVariables[$package][$variable])) {
            // already compiled
            return $this->compiledPackageVariables[$package][$variable];
        }

        if (!isset($this->packageVariables[$package][$variable])) {
            // unknown variable
            return null;
        }

        if (isset($visited[$variable])) {
            // circular reference
            throw new ScriptCompilerException(sprintf(
                'Circular reference to package script variable [%s] detected at [%s][%s]',
                $variable,
                $package,
                implode('][', array_keys($visited))
            ));
        }

        // compile
        $visited[$variable] = true;

        try {
            return $this->compiledPackageVariables[$package][$variable] = $this->resolvePackageVariableValue(
                $package,
                $this->packageVariables[$package][$variable],
                $visited
            );
        } catch (InvalidScriptVariableException $e) {
            throw new ScriptCompilerException(
                sprintf('Failed to compile package script variable [%s][%s] - %s', $package, $variable, $e->getMessage()),
                0,
                $e
            );
        }
    }

    private function resolvePackageVariableValue(string $package, $value, array $visited)
    {
        // handle array
        if (is_array($value)) {
            $resolvedValue = [];

            foreach ($value as $item) {
                $resolvedItemValue = (array) $this->resolvePackageVariableValue($package, $item, $visited);

                if ($resolvedItemValue) {
                    array_push($resolvedValue, ...$resolvedItemValue);
                }
            }

            return $resolvedValue;
        }

        $value = (string) $value;

        // resolve direct variable reference
        if (preg_match(self::DIRECT_VARIABLE_REFERENCE_REGEXP, $value, $match)) {
            return $this->compilePackageVariable($package, $match[1], $visited)
                ?? $this->globalVariables[$match[1]]
                ?? '';
        }

        // resolve variable placeholders
        return preg_replace_callback(
            self::VARIABLE_PLACEHOLDER_REGEXP,
            function (array $match) use ($package, $visited) {
                $value = $this->compilePackageVariable($package, $match[1], $visited)
                    ?? $this->globalVariables[$match[1]]
                    ?? '';

                if (is_array($value)) {
                    throw new InvalidScriptVariableException(sprintf('Cannot embed array variable [%s]', $match[1]));
                }

                return (string) $value;
            },
            $value
        );
    }
}
