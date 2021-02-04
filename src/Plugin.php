<?php declare(strict_types=1);

namespace Kuria\ComposerPkgScripts;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use Kuria\ComposerPkgScripts\Capability\ScriptCommandProvider;
use Kuria\ComposerPkgScripts\Script\ScriptManager;

class Plugin implements PluginInterface, Capable, EventSubscriberInterface
{
    /** @var Composer */
    private $composer;

    /** @var ScriptManager */
    private $scriptManager;

    function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->scriptManager = new ScriptManager($composer);
    }

    function getCapabilities()
    {
        return [
            CommandProvider::class => ScriptCommandProvider::class,
        ];
    }

    static function getSubscribedEvents()
    {
        return [
            PluginEvents::INIT => 'registerScripts',
            ScriptEvents::POST_INSTALL_CMD => 'registerScripts',
            ScriptEvents::POST_UPDATE_CMD => 'registerScripts',
        ];
    }

    function registerScripts(): void
    {
        $this->scriptManager->registerScripts();
    }

    function getScriptManager(): ScriptManager
    {
        return $this->scriptManager;
    }

    function deactivate(Composer $composer, IOInterface $io)
    {
    }

    function uninstall(Composer $composer, IOInterface $io)
    {
    }
}
