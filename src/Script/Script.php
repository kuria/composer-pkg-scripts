<?php declare(strict_types=1);

namespace Kuria\ComposerPkgScripts\Script;

class Script
{
    /** @var string */
    public $package;

    /** @var string */
    public $shortName;

    /** @var string */
    public $name;

    /** @var string[] */
    public $aliases;

    /** @var mixed */
    public $definition;

    /** @var string */
    public $help;

    function __construct(
        string $package,
        string $shortName,
        string $name,
        array $aliases,
        $definition,
        string $help
    ) {
        $this->package = $package;
        $this->shortName = $shortName;
        $this->name = $name;
        $this->aliases = $aliases;
        $this->definition = $definition;
        $this->help = $help;
    }
}
