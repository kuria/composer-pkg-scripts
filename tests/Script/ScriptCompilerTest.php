<?php declare(strict_types=1);

namespace Kuria\ComposerPkgScripts\Script;

use Kuria\ComposerPkgScripts\Exception\ScriptCompilerException;
use PHPUnit\Framework\TestCase;

class ScriptCompilerTest extends TestCase
{
    /** @var ScriptCompiler */
    private $compiler;

    protected function setUp()
    {
        $this->compiler = new ScriptCompiler();

        // output of escapeshellarg() is platform-dependant
        $this->compiler->setShellArgEscaper(function ($arg) {
            return '"' . addcslashes($arg, '"') . '"';
        });
    }

    function testShouldConfigure()
    {
        $globalVariables = ['foo' => 'bar'];
        $packageVariables = ['acme/example' => ['baz' => 'qux']];

        $this->compiler->setGlobalVariables($globalVariables);
        $this->compiler->setPackageVariables($packageVariables);

        $this->assertSame($globalVariables, $this->compiler->getGlobalVariables());
        $this->assertSame($packageVariables, $this->compiler->getPackageVariables());
    }

    /**
     * @dataProvider provideScripts
     */
    function testShouldCompile(
        Script $script,
        array $globalVariables,
        array $packageVariables,
        array $expectedResult
    ) {
        $this->compiler->setGlobalVariables($globalVariables);
        $this->compiler->setPackageVariables([$script->package => $packageVariables]);

        $this->assertSame($expectedResult, $this->compiler->compile($script));
    }

    function provideScripts(): array
    {
        return [
            // script, globalVariables, packageVariables, expectedResult
            'Empty string' => [
                $this->createScript(''),
                [],
                [],
                [''],
            ],
            'Simple string' => [
                $this->createScript('foo'),
                [],
                [],
                ['foo'],
            ],
            'String with nonexistent var' => [
                $this->createScript('echo {$nonexistent}'),
                [],
                [],
                ['echo ""'],
            ],
            'String with global var' => [
                $this->createScript('echo {$var}'),
                ['var' => 'value'],
                [],
                ['echo "value"'],
            ],
            'String with package var' => [
                $this->createScript('echo {$var}'),
                ['var' => 'global value'],
                ['var' => 'package value'],
                ['echo "package value"'],
            ],
            'String with multiple vars' => [
                $this->createScript('echo {$global-var} {$package-var}'),
                ['global-var' => 'global value'],
                ['package-var' => 'package value'],
                ['echo "global value" "package value"'],
            ],
            'String with array var' => [
                $this->createScript('echo {$array-var}'),
                ['array-var' => ['foo', 'bar', 'baz']],
                [],
                ['echo "foo" "bar" "baz"'],
            ],
            'String with complex package var' => [
                $this->createScript('echo {$var}'),
                ['global-var' => '/global'],
                ['var' => '{$global-var}/var/{$other-var}', 'other-var' => 'other'],
                ['echo "/global/var/other"'],
            ],
            'String with reused package var' => [
                $this->createScript('echo {$var}'),
                [],
                ['var' => '{$other-var} {$other-var}', 'other-var' => 'other'],
                ['echo "other other"'],
            ],
            'String with direct package array variable reference' => [
                $this->createScript('echo {$var}'),
                [],
                ['var' => '{$other-var}', 'other-var' => ['foo', 'bar']],
                ['echo "foo" "bar"'],
            ],
            'String with direct global array variable reference' => [
                $this->createScript('echo {$var}'),
                ['global-var' => ['foo', 'bar']],
                ['var' => '{$global-var}'],
                ['echo "foo" "bar"'],
            ],
            'String with nested array references' => [
                $this->createScript('echo {$var}'),
                [],
                [
                    'var' => ['foo', '{$bar}', 'baz'],
                    'bar' => ['bar 1', 'bar 2', '{$more-bars}', '{$no-bars}'],
                    'more-bars' => ['bar 3', 'bar 4'],
                    'no-bars' => [],
                ],
                ['echo "foo" "bar 1" "bar 2" "bar 3" "bar 4" "baz"'],
            ],
            'String with unsupported placeholders in global var' => [
                $this->createScript('echo {$global-var} {$var}'),
                ['global-var' => '{$placeholder}'],
                ['var' => 'global={$global-var}'],
                ['echo "{$placeholder}" "global={$placeholder}"'],
            ],
            'String with complex array package var' => [
                $this->createScript('echo {$var}'),
                ['global-var' => '/global'],
                ['var' => ['{$foo}', '{$bar}', 'baz'], 'foo' => '/foo', 'bar' => '{$global-var}/bar'],
                ['echo "/foo" "/global/bar" "baz"'],
            ],
            'Empty array' => [
                $this->createScript([]),
                [],
                [],
                [],
            ],
            'Array with vars' => [
                $this->createScript([
                    'echo hello',
                    'echo {$global-var}',
                    'echo {$package-var}',
                    'echo {$array-var}',
                    'echo {$complex-var}',
                ]),
                ['global-var' => 'global value', 'array-var' => ['foo', 'bar', 'baz']],
                ['package-var' => 'package value', 'complex-var' => 'global={$global-var}'],
                [
                    'echo hello',
                    'echo "global value"',
                    'echo "package value"',
                    'echo "foo" "bar" "baz"',
                    'echo "global=global value"',
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideScriptsWithInvalidVariables
     */
    function testShouldThrowExceptionWhenVariableCannotBeCompiled(
        Script $script,
        array $globalVariables,
        array $packageVariables,
        string $expectedMessage
    ) {
        $this->compiler->setGlobalVariables($globalVariables);
        $this->compiler->setPackageVariables([$script->package => $packageVariables]);

        $this->expectException(ScriptCompilerException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->compiler->compile($script);
    }

    function provideScriptsWithInvalidVariables(): array
    {
        return [
            // script, globalVariables, packageVariables, expectedMessage
            'Embedded array package var' => [
                $this->createScript('echo {$var}'),
                [],
                ['var' => 'foo {$pkg-array-var} bar', 'pkg-array-var' => ['foo', 'bar']],
                'Failed to compile package script variable [acme/example][var] - Cannot embed array variable [pkg-array-var]',
            ],
            'Embedded global array var' => [
                $this->createScript('echo {$var}'),
                ['global-array-var' => ['foo', 'bar']],
                ['var' => 'foo {$global-array-var} bar'],
                'Failed to compile package script variable [acme/example][var] - Cannot embed array variable [global-array-var]',
            ],
            'Direct circular reference' => [
                $this->createScript('echo {$var}'),
                [],
                ['var' => '{$var}'],
                'Circular reference to package script variable [var] detected at [acme/example][var]',
            ],
            'Deep circular reference' => [
                $this->createScript('echo {$var}'),
                [],
                ['var' => '{$foo}', 'foo' => '{$bar}', 'bar' => '{$baz}', 'baz' => '{$foo}'],
                'Circular reference to package script variable [foo] detected at [acme/example][var][foo][bar][baz]',
            ],
        ];
    }

    private function createScript($definition): Script
    {
        return new Script('acme/example', 'short-name', 'name', [], $definition, 'help');
    }
}
