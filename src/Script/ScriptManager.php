<?php declare(strict_types=1);

namespace Kuria\ComposerPkgScripts\Script;

use Composer\Composer;
use Composer\Package\CompletePackage;

class ScriptManager
{
    /** @var Composer */
    private $composer;
    /** @var ScriptLoader */
    private $loader;
    /** @var ScriptCompiler */
    private $compiler;
    /** @var Script[] */
    private $registeredScripts = [];
    /** @var Script[] */
    private $loadedScripts;

    function __construct(Composer $composer, ?ScriptLoader $loader = null, ?ScriptCompiler $compiler = null)
    {
        $this->composer = $composer;
        $this->loader = $loader ?? new ScriptLoader();
        $this->compiler = $compiler ?? new ScriptCompiler();
        $this->compiler->setGlobalVariables($composer->getConfig()->all()['config']);
    }

    function registerScripts(): void
    {
        $rootPackage = $this->composer->getPackage();

        if (!$rootPackage instanceof CompletePackage) {
            return;
        }

        $rootScripts = $rootPackage->getScripts();
        $packages = $this->composer->getRepositoryManager()->getLocalRepository()->getPackages();

        // clear previously registered scripts
        foreach ($this->registeredScripts as $scriptName => $_) {
            unset($rootScripts[$scriptName]);
        }

        // reset state
        $this->registeredScripts = [];
        $this->loadedScripts = [];

        // setup compiler
        $this->compiler->setPackageVariables($this->loader->loadScriptVariables($rootPackage, $packages));

        // add package scripts
        $this->loadedScripts = $this->loader->loadScripts($packages);

        foreach ($this->loadedScripts as $script) {
            $listeners = $this->compiler->compile($script);

            if (!isset($rootScripts[$script->name])) {
                $rootScripts[$script->name] = $listeners;
                $this->registeredScripts[$script->name] = $script;
            }

            foreach ($script->aliases as $alias) {
                if (!isset($rootScripts[$alias])) {
                    $rootScripts[$alias] = ['@' . $script->name];
                    $this->registeredScripts[$alias] = $script;
                }
            }
        }

        $rootPackage->setScripts($rootScripts);
    }

    /**
     * @return Script[]
     */
    function getRegisteredScripts(): array
    {
        return $this->registeredScripts;
    }

    /**
     * @return Script[]
     */
    function getLoadedScripts(): array
    {
        return $this->loadedScripts;
    }

    /**
     * Get currently compiled scripts, including root scripts
     */
    function getCompiledScripts(): array
    {
        return $this->composer->getPackage()->getScripts();
    }

    /**
     * Get package variables used during last script compilation
     */
    function getPackageVariables(): array
    {
        return $this->compiler->getPackageVariables();
    }
}
