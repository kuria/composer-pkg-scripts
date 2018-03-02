<?php declare(strict_types=1);

namespace Kuria\ComposerPkgScripts\Capability;

use Composer\Command\ScriptAliasCommand;
use Composer\Plugin\Capability\CommandProvider;
use Kuria\ComposerPkgScripts\Command\DumpPackageScriptsCommand;
use Kuria\ComposerPkgScripts\Command\ListPackageScriptsCommand;
use Kuria\ComposerPkgScripts\Script\ScriptManager;

class ScriptCommandProvider implements CommandProvider
{
    /** @var ScriptManager */
    private $scriptManager;

    function __construct(array $args)
    {
        $this->scriptManager = $args['plugin']->getScriptManager();
    }

    function getCommands()
    {
        $commands = [
            new ListPackageScriptsCommand($this->scriptManager),
            new DumpPackageScriptsCommand($this->scriptManager),
        ];

        foreach ($this->scriptManager->getRegisteredScripts() as $name => $script) {
            $scriptCommand = new ScriptAliasCommand($name, $script->help);
            $scriptCommand->setHelp(sprintf(
                <<<'HELP'
The <info>%s</info> command runs the <comment>"%s"</comment> script defined by <comment>%s</comment>.

HELP
                ,
                $name,
                $script->shortName,
                $script->package
            ));

            $commands[] = $scriptCommand;
        }

        return $commands;
    }
}
