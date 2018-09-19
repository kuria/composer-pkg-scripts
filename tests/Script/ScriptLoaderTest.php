<?php declare(strict_types=1);

namespace Kuria\ComposerPkgScripts\Script;

use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Kuria\DevMeta\Test;

class ScriptLoaderTest extends Test
{
    function testShouldLoadScriptsAndVariables()
    {
        /** @var RootPackageInterface $rootPackageMock */
        $rootPackageMock = $this->createConfiguredMock(PackageInterface::class, [
            'getExtra' => [
                ScriptLoader::EXTRA_SCRIPTS_VARIABLES_KEYS => [
                    'acme/complex' => [
                        'foo' => 'overridden',
                    ],
                ],
            ],
        ]);

        /** @var PackageInterface[] $packageMocks */
        $packageMocks = [
            $this->createConfiguredMock(PackageInterface::class, [
                'getName' => 'acme/empty',
                'getExtra' => [],
            ]),
            $basicPackageMock = $this->createConfiguredMock(PackageInterface::class, [
                'getName' => 'acme/basic',
                'getExtra' => [
                    ScriptLoader::EXTRA_SCRIPTS_KEY => [
                        'foo' => 'echo foo',
                        'bar' => ['echo bar', 'echo baz'],
                    ],
                ],
            ]),
            $complexPackageMock = $this->createConfiguredMock(PackageInterface::class, [
                'getName' => 'acme/complex',
                'getExtra' => [
                    ScriptLoader::EXTRA_SCRIPTS_KEY => [
                        'lorem' => ['@ipsum', '@dolor'],
                        'ipsum' => '@some-script',
                        'dolor' => '@acme:basic:foo',
                    ],
                    ScriptLoader::EXTRA_SCRIPTS_META_KEY => [
                        'lorem' => ['aliases' => 'lorem'],
                        'ipsum' => ['aliases' => ['ipsum', 'ips'], 'help' => 'ipsum help'],
                    ],
                    ScriptLoader::EXTRA_SCRIPTS_VARIABLES_KEYS => [
                        'foo' => 'foo',
                        'bar' => 'bar',
                    ],
                ],
            ]),
        ];

        $expectedScripts = [
            'acme:basic:foo' => [
                'package' => 'acme/basic',
                'shortName' => 'foo',
                'name' => 'acme:basic:foo',
                'aliases' => [],
                'definition' => 'echo foo',
                'help' => 'Run the "foo" script from acme/basic',
            ],
            'acme:basic:bar' => [
                'package' => 'acme/basic',
                'shortName' => 'bar',
                'name' => 'acme:basic:bar',
                'aliases' => [],
                'definition' => ['echo bar', 'echo baz'],
                'help' => 'Run the "bar" script from acme/basic',
            ],
            'acme:complex:lorem' => [
                'package' => 'acme/complex',
                'shortName' => 'lorem',
                'name' => 'acme:complex:lorem',
                'aliases' => ['lorem'],
                'definition' => ['@acme:complex:ipsum', '@acme:complex:dolor'],
                'help' => 'Run the "lorem" script from acme/complex',
            ],
            'acme:complex:ipsum' => [
                'package' => 'acme/complex',
                'shortName' => 'ipsum',
                'name' => 'acme:complex:ipsum',
                'aliases' => ['ipsum', 'ips'],
                'definition' => '@some-script',
                'help' => 'ipsum help',
            ],
            'acme:complex:dolor' => [
                'package' => 'acme/complex',
                'shortName' => 'dolor',
                'name' => 'acme:complex:dolor',
                'aliases' => [],
                'definition' => '@acme:basic:foo',
                'help' => 'Run the "dolor" script from acme/complex',
            ],
        ];

        $expectedVariables = [
            'acme/empty' => [],
            'acme/basic' => [],
            'acme/complex' => [
                'foo' => 'overridden',
                'bar' => 'bar',
            ],
        ];

        $loader = new ScriptLoader();

        $scripts = $loader->loadScripts($packageMocks);
        $variables = $loader->loadScriptVariables($rootPackageMock, $packageMocks);

        $this->assertSame($expectedScripts, array_map(function ($script) { return (array) $script; }, $scripts));
        $this->assertSame($expectedVariables, $variables);
    }
}
