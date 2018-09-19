<?php declare(strict_types=1);

namespace Kuria\ComposerPkgScripts\Script;

use Kuria\DevMeta\Test;

class ScriptTest extends Test
{
    function testShouldCreateScript()
    {
        $script = new Script('acme/example', 'short-name', 'name', ['alias'], 'echo foo', 'help');

        $this->assertSame('acme/example', $script->package);
        $this->assertSame('short-name', $script->shortName);
        $this->assertSame('name', $script->name);
        $this->assertSame(['alias'], $script->aliases);
        $this->assertSame('echo foo', $script->definition);
        $this->assertSame('help', $script->help);
    }
}
