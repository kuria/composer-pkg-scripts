<?php declare(strict_types=1);

namespace Kuria\ComposerPkgScripts\Script;

use Composer\Package\PackageInterface;

class ScriptLoader
{
    const EXTRA_SCRIPTS_KEY = 'package-scripts';
    const EXTRA_SCRIPTS_META_KEY = 'package-scripts-meta';
    const EXTRA_SCRIPTS_VARIABLES_KEYS = 'package-scripts-vars';

    /**
     * @param PackageInterface[] $packages
     * @return Script[]
     */
    function loadScripts(array $packages): array
    {
        $scripts = [];

        foreach ($packages as $package) {
            $extra = $package->getExtra();

            if (empty($extra[static::EXTRA_SCRIPTS_KEY])) {
                continue;
            }

            $packageName = $package->getName();

            $scriptNames = $this->resolveScriptNames($packageName, $extra[static::EXTRA_SCRIPTS_KEY]);

            foreach ($extra[static::EXTRA_SCRIPTS_KEY] as $shortName => $definition) {
                $name = $scriptNames[$shortName];

                // determine aliases
                $aliases = (array) ($extra[static::EXTRA_SCRIPTS_META_KEY][$shortName]['aliases'] ?? []);

                // determine help
                $help = $extra[static::EXTRA_SCRIPTS_META_KEY][$shortName]['help']
                    ?? sprintf('Run the "%s" script from %s', $shortName, $packageName);

                // resolve definition
                if (is_array($definition)) {
                    $definition = array_map(
                        function ($listener) use ($scriptNames) { return $this->resolveScriptListener($scriptNames, (string) $listener); },
                        $definition
                    );
                } else {
                    $definition = $this->resolveScriptListener($scriptNames, (string) $definition);
                }

                // add script
                $scripts[$name] = new Script($packageName, $shortName, $name, $aliases, $definition, $help);
            }
        }

        return $scripts;
    }

    /**
     * @param PackageInterface[] $packages
     */
    function loadScriptVariables(PackageInterface $rootPackage, array $packages): array
    {
        $variables = [];

        // load variables from packages
        foreach ($packages as $package) {
            $variables[$package->getName()] = $package->getExtra()[static::EXTRA_SCRIPTS_VARIABLES_KEYS] ?? [];
        }

        // load variables from root package
        foreach ($rootPackage->getExtra()[static::EXTRA_SCRIPTS_VARIABLES_KEYS] ?? [] as $packageName => $rootVariables) {
            if (isset($variables[$packageName])) {
                $variables[$packageName] = $rootVariables + $variables[$packageName];
            }
        }

        return $variables;
    }

    private function resolveScriptNames(string $packageName, array $packageScripts): array
    {
        $names = [];

        $prefix = strtr($packageName, '/', ':') . ':';

        foreach ($packageScripts as $shortName => $_) {
            $names[$shortName] = $prefix . $shortName;
        }

        return $names;
    }

    private function resolveScriptListener(array $scriptNames, string $listener): string
    {
        if (
            $listener !== ''
            && $listener[0] === '@'
            && isset($scriptNames[$referencedScriptName = substr($listener, 1)])
        ) {
            $listener = '@' . $scriptNames[$referencedScriptName];
        }

        return $listener;
    }
}
