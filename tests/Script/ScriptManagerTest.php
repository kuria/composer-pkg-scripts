<?php declare(strict_types=1);

namespace Kuria\ComposerPkgScripts\Script;

use Composer\Composer;
use Composer\Config;
use Composer\Package\CompletePackage;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Repository\RepositoryManager;
use Composer\Repository\WritableRepositoryInterface;
use PHPUnit\Framework\TestCase;

class ScriptManagerTest extends TestCase
{
    function testShouldRegisterScripts()
    {
        $rootPackageMock = $this->createMock(CompletePackage::class);
        $loaderMock = $this->createMock(ScriptLoader::class);
        $compilerMock = $this->createTestProxy(ScriptCompiler::class);

        $scripts = [
            'acme:example:foo' => new Script('acme/example', 'foo', 'acme:example:foo', ['foo', 'acme-foo'], 'acme foo', 'help'),
            'acme:example:bar' => new Script('acme/example', 'bar', 'acme:example:bar', [], ['acme bar', 'acme qux'], 'help'),
            'acme:example:baz' => new Script('acme/example', 'baz', 'acme:example:baz', [], 'acme baz', 'help'),
        ];

        $rootPackageScripts = [
            'foo' => ['root foo'],
            'bar' => ['root bar'],
            'acme:example:baz' => ['overridden'],
        ];

        $compiledScripts = $rootPackageScripts + [
            'acme:example:foo' => ['acme foo'],
            'acme-foo' => ['@acme:example:foo'],
            'acme:example:bar' => ['acme bar', 'acme qux'],
        ];

        $packageVariables = [
            'acme/example' => ['var' => 'value'],
            'acme/other' => [],
        ];

        $rootPackageMock
            ->method('getScripts')
            ->willReturnReference($rootPackageScripts);

        $compilerMock->expects($this->once())
            ->method('setGlobalVariables')
            ->with(['global-config' => 'value']);

        $compilerMock->expects($this->once())
            ->method('setPackageVariables')
            ->with($this->identicalTo($packageVariables));

        $loaderMock->expects($this->once())
            ->method('loadScripts')
            ->willReturn($scripts);

        $loaderMock->expects($this->once())
            ->method('loadScriptVariables')
            ->with($this->identicalTo($rootPackageMock), $this->isType('array'))
            ->willReturn($packageVariables);

        $rootPackageMock->expects($this->once())
            ->method('setScripts')
            ->with($this->identicalTo($compiledScripts))
            ->willReturnCallback(function (array $scripts) use (&$rootPackageScripts) {
                $rootPackageScripts = $scripts;
            });

        $manager = $this->createScriptManager($rootPackageMock, $loaderMock, $compilerMock);

        $manager->registerScripts();

        $this->assertSame(
            [
                'acme:example:foo' => $scripts['acme:example:foo'],
                'acme-foo' => $scripts['acme:example:foo'],
                'acme:example:bar' => $scripts['acme:example:bar'],
            ],
            $manager->getRegisteredScripts()
        );
        $this->assertSame($scripts, $manager->getLoadedScripts());
        $this->assertSame($compiledScripts, $manager->getCompiledScripts());
        $this->assertSame($packageVariables, $manager->getPackageVariables());
    }

    function testShouldClearPreviouslyRegisteredScripts()
    {
        $rootPackageMock = $this->createMock(CompletePackage::class);
        $loaderMock = $this->createMock(ScriptLoader::class);

        $firstScriptSet = [
            'acme:example:foo' => new Script('acme/example', 'foo', 'acme:example:foo', ['foo', 'acme-foo'], 'acme foo', 'help'),
            'acme:example:bar' => new Script('acme/example', 'bar', 'acme:example:bar', [], 'acme bar', 'help'),
        ];

        $secondScriptSet = [
            'acme:example:baz' => new Script('acme/example', 'baz', 'acme:example:baz', [], 'acme baz', 'help'),
        ];

        $rootScripts = [
            'foo' => ['root foo'],
            'bar' => ['root bar'],
        ];

        $scriptsAfterFirstCall = $rootScripts + [
            'acme:example:foo' => ['acme foo'],
            'acme-foo' => ['@acme:example:foo'],
            'acme:example:bar' => ['acme bar'],
        ];

        $scriptsAfterSecondCall = $rootScripts + [
            'acme:example:baz' => ['acme baz'],
        ];

        $rootPackageMock->expects($this->exactly(2))
            ->method('getScripts')
            ->willReturnOnConsecutiveCalls(
                $rootScripts,
                $scriptsAfterFirstCall
            );

        $loaderMock->expects($this->exactly(2))
            ->method('loadScripts')
            ->willReturnOnConsecutiveCalls(
                $firstScriptSet,
                $secondScriptSet
            );

        $rootPackageMock->expects($this->exactly(2))
            ->method('setScripts')
            ->withConsecutive(
                [$this->identicalTo($scriptsAfterFirstCall)],
                [$this->identicalTo($scriptsAfterSecondCall)]
            );

        $manager = $this->createScriptManager($rootPackageMock, $loaderMock);

        // first call
        $manager->registerScripts();

        $this->assertSame($firstScriptSet, $manager->getLoadedScripts());
        $this->assertSame(
            [
                'acme:example:foo' => $firstScriptSet['acme:example:foo'],
                'acme-foo' => $firstScriptSet['acme:example:foo'],
                'acme:example:bar' => $firstScriptSet['acme:example:bar'],
            ],
            $manager->getRegisteredScripts()
        );

        // second call - should clear scripts from first call
        $manager->registerScripts();

        $this->assertSame($secondScriptSet, $manager->getLoadedScripts());
        $this->assertSame(
            [
                'acme:example:baz' => $secondScriptSet['acme:example:baz'],
            ],
            $manager->getRegisteredScripts()
        );
    }

    function testShouldDoNothingIfRootPackageIsIncompatibleInstance()
    {
        $rootPackage = $this->createMock(RootPackageInterface::class);

        $rootPackage->expects($this->never())
            ->method('getScripts');

        $manager = $this->createScriptManager($rootPackage, new ScriptLoader());

        $manager->registerScripts();
    }

    private function createScriptManager($rootPackage, $scriptLoader = null, $scriptCompiler = null): ScriptManager
    {
        /** @var Composer $composerMock */
        $composerMock = $this->createConfiguredMock(Composer::class, [
            'getPackage' => $rootPackage,
            'getConfig' => $this->createConfiguredMock(Config::class, [
                'all' => ['config' => ['global-config' => 'value']],
            ]),
            'getRepositoryManager' => $this->createConfiguredMock(RepositoryManager::class, [
                'getLocalRepository' => $this->createConfiguredMock(WritableRepositoryInterface::class, [
                    'getPackages' => [],
                ])
            ])
        ]);

        return new ScriptManager($composerMock, $scriptLoader, $scriptCompiler);
    }
}
