<?php declare(strict_types=1);

namespace Kuria\ComposerPkgScripts\Command;

use Composer\Command\BaseCommand;
use Kuria\ComposerPkgScripts\Script\Script;
use Kuria\ComposerPkgScripts\Script\ScriptManager;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListPackageScriptsCommand extends BaseCommand
{
    /** @var ScriptManager */
    private $scriptManager;

    function __construct(ScriptManager $scriptManager)
    {
        parent::__construct();

        $this->scriptManager = $scriptManager;
    }

    protected function configure()
    {
        $this->setName('package-scripts:list');
        $this->setAliases(['psl']);
        $this->setDescription('List available package scripts');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // list available package scripts
        $output->writeln('<comment>Available package scripts:</comment>');
        $this->getScriptList($output)->render();

        // list inactive scripts, if any
        $inactiveScripts = $this->listInactiveScripts();

        if ($inactiveScripts->valid()) {
            $output->writeln('');
            $output->writeln('<comment>Inactive package scripts:</comment>');
            $this->getInactiveScriptList($output, $inactiveScripts)->render();
            $output->writeln('');
            $output->writeln('Package script or alias is inactive if it conflicts with another package script, alias or a root script.');
        }
    }

    private function getScriptList(OutputInterface $output): Table
    {
        $list = new Table($output);
        $list->setStyle('compact');

        $registeredScripts = $this->scriptManager->getRegisteredScripts();

        // determine unique scripts (exclude aliases)
        $uniqueScripts = [];

        foreach ($registeredScripts as $script) {
            $uniqueScripts[$script->name] = $script;
        }

        ksort($uniqueScripts, SORT_NATURAL);

        foreach ($uniqueScripts as $script) {
            $activeAliases = $this->getActiveAliases($script, $registeredScripts);

            $aliasList = !empty($activeAliases) && !$output->isVerbose()
                ? sprintf(' <comment>(%s)</comment>', implode(', ', $activeAliases))
                : '';

            $list->addRow([sprintf(' <info>%s</info>%s', $script->name, $aliasList), $script->help]);

            if ($output->isVerbose()) {
                $extraInfo = implode("\n", [
                    '  - package: ' . $script->package,
                    '  - definition: ' . json_encode($script->definition, JSON_UNESCAPED_SLASHES),
                    '  - aliases: ' . implode(', ', $activeAliases),
                ]);

                $list->addRow([new TableCell(sprintf('<comment>%s</comment>', $extraInfo), ['colspan' => 3])]);
            }
        }

        return $list;
    }

    private function getInactiveScriptList(OutputInterface $output, \Traversable $inactiveScripts): Table
    {
        $list = new Table($output);
        $list->setStyle('compact');

        foreach ($inactiveScripts as $inactiveScript) {
            if ($inactiveScript['type'] === 'script') {
                // script
                $list->addRow([
                    $inactiveScript['script']->package,
                    sprintf(' script <info>"%s"</info>', $inactiveScript['script']->name),
                ]);
            } else {
                // alias
                $list->addRow([
                    $inactiveScript['script']->package,
                    sprintf(' alias <comment>"%s"</comment> of <info>"%s"</info>', $inactiveScript['alias'], $inactiveScript['script']->name),
                ]);
            }
        }

        return $list;
    }

    private function listInactiveScripts(): \Iterator
    {
        $registeredScripts = $this->scriptManager->getRegisteredScripts();

        foreach ($this->scriptManager->getLoadedScripts() as $script) {
            if (!isset($registeredScripts[$script->name]) || $registeredScripts[$script->name] !== $script) {
                yield [
                    'type' => 'script',
                    'script' => $script,
                ];
            }

            foreach ($script->aliases as $alias) {
                if (!isset($registeredScripts[$alias]) || $registeredScripts[$alias] !== $script) {
                    yield [
                        'type' => 'alias',
                        'alias' => $alias,
                        'script' => $script,
                    ];
                }
            }
        }
    }

    private function getActiveAliases(Script $script, array $registeredScripts): array
    {
        $activeAliases = [];

        foreach ($script->aliases as $alias) {
            if (isset($registeredScripts[$alias]) && $registeredScripts[$alias] === $script) {
                $activeAliases[] = $alias;
            }
        }

        return $activeAliases;
    }
}
