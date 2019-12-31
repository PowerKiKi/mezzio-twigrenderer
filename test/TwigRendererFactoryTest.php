<?php

/**
 * @see       https://github.com/mezzio/mezzio-twigrenderer for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio-twigrenderer/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio-twigrenderer/blob/master/LICENSE.md New BSD License
 */

namespace MezzioTest\Twig;

use Interop\Container\ContainerInterface;
use Mezzio\Router\RouterInterface;
use Mezzio\Template\TemplatePath;
use Mezzio\Twig\Exception\InvalidExtensionException;
use Mezzio\Twig\TwigExtension;
use Mezzio\Twig\TwigRenderer;
use Mezzio\Twig\TwigRendererFactory;
use MezzioTest\Twig\TestAsset\Extension\BarTwigExtension;
use MezzioTest\Twig\TestAsset\Extension\FooTwigExtension;
use PHPUnit_Framework_TestCase as TestCase;
use ReflectionProperty;

class TwigRendererFactoryTest extends TestCase
{
    /**
     * @var ContainerInterface
    */
    private $container;

    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
    }

    public function fetchTwigEnvironment(TwigRenderer $twig)
    {
        $r = new ReflectionProperty($twig, 'template');
        $r->setAccessible(true);
        return $r->getValue($twig);
    }

    public function getConfigurationPaths()
    {
        return [
            'foo' => __DIR__ . '/TestAsset/bar',
            1 => __DIR__ . '/TestAsset/one',
            'bar' => [
                __DIR__ . '/TestAsset/baz',
                __DIR__ . '/TestAsset/bat',
            ],
            0 => [
                __DIR__ . '/TestAsset/two',
                __DIR__ . '/TestAsset/three',
            ],
        ];
    }

    public function assertPathsHasNamespace($namespace, array $paths, $message = null)
    {
        $message = $message ?: sprintf('Paths do not contain namespace %s', $namespace ?: 'null');

        $found = false;
        foreach ($paths as $path) {
            $this->assertInstanceOf(TemplatePath::class, $path, 'Non-TemplatePath found in paths list');
            if ($path->getNamespace() === $namespace) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, $message);
    }

    public function assertPathNamespaceCount($expected, $namespace, array $paths, $message = null)
    {
        $message = $message ?: sprintf('Did not find %d paths with namespace %s', $expected, $namespace ?: 'null');

        $count = 0;
        foreach ($paths as $path) {
            $this->assertInstanceOf(TemplatePath::class, $path, 'Non-TemplatePath found in paths list');
            if ($path->getNamespace() === $namespace) {
                $count += 1;
            }
        }
        $this->assertSame($expected, $count, $message);
    }

    public function assertPathNamespaceContains($expected, $namespace, array $paths, $message = null)
    {
        $message = $message ?: sprintf('Did not find path %s in namespace %s', $expected, $namespace ?: null);

        $found = [];
        foreach ($paths as $path) {
            $this->assertInstanceOf(TemplatePath::class, $path, 'Non-TemplatePath found in paths list');
            if ($path->getNamespace() === $namespace) {
                $found[] = $path->getPath();
            }
        }
        $this->assertContains($expected, $found, $message);
    }

    public function testCallingFactoryWithNoConfigReturnsTwigInstance()
    {
        $this->container->has('config')->willReturn(false);
        $this->container->has(RouterInterface::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Router\RouterInterface::class)->willReturn(false);
        $factory = new TwigRendererFactory();
        $twig = $factory($this->container->reveal());
        $this->assertInstanceOf(TwigRenderer::class, $twig);
        return $twig;
    }

    /**
     * @depends testCallingFactoryWithNoConfigReturnsTwigInstance
     */
    public function testUnconfiguredTwigInstanceContainsNoPaths(TwigRenderer $twig)
    {
        $paths = $twig->getPaths();
        $this->assertInternalType('array', $paths);
        $this->assertEmpty($paths);
    }

    public function testUsesDebugConfigurationToPrepareEnvironment()
    {
        $config = ['debug' => true];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $this->container->has(RouterInterface::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Router\RouterInterface::class)->willReturn(false);
        $factory = new TwigRendererFactory();
        $twig = $factory($this->container->reveal());

        $environment = $this->fetchTwigEnvironment($twig);

        $this->assertTrue($environment->isDebug());
        $this->assertFalse($environment->getCache());
        $this->assertTrue($environment->isStrictVariables());
        $this->assertTrue($environment->isAutoReload());
    }

    /**
     * @depends testCallingFactoryWithNoConfigReturnsTwigInstance
     */
    public function testDebugDisabledSetsUpEnvironmentForProduction(TwigRenderer $twig)
    {
        $environment = $this->fetchTwigEnvironment($twig);

        $this->assertFalse($environment->isDebug());
        $this->assertFalse($environment->isStrictVariables());
        $this->assertFalse($environment->isAutoReload());
    }

    public function testCanSpecifyCacheDirectoryViaConfiguration()
    {
        $config = ['templates' => ['cache_dir' => __DIR__]];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $this->container->has(RouterInterface::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Router\RouterInterface::class)->willReturn(false);
        $factory = new TwigRendererFactory();
        $twig = $factory($this->container->reveal());

        $environment = $this->fetchTwigEnvironment($twig);
        $this->assertEquals($config['templates']['cache_dir'], $environment->getCache());
    }

    public function testAddsTwigExtensionIfRouterIsInContainer()
    {
        $router = $this->prophesize(RouterInterface::class)->reveal();
        $this->container->has('config')->willReturn(false);
        $this->container->has(RouterInterface::class)->willReturn(true);
        $this->container->get(RouterInterface::class)->willReturn($router);
        $factory = new TwigRendererFactory();
        $twig = $factory($this->container->reveal());

        $environment = $this->fetchTwigEnvironment($twig);
        $this->assertTrue($environment->hasExtension('mezzio'));
    }

    public function testUsesAssetsConfigurationWhenAddingTwigExtension()
    {
        $config = [
            'templates' => [
                'assets_url'     => 'http://assets.example.com/',
                'assets_version' => 'XYZ',
            ],
        ];
        $router = $this->prophesize(RouterInterface::class)->reveal();
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $this->container->has(RouterInterface::class)->willReturn(true);
        $this->container->get(RouterInterface::class)->willReturn($router);
        $factory = new TwigRendererFactory();
        $twig = $factory($this->container->reveal());

        $environment = $this->fetchTwigEnvironment($twig);
        $extension = $environment->getExtension('mezzio');
        $this->assertInstanceOf(TwigExtension::class, $extension);
        $this->assertAttributeEquals($config['templates']['assets_url'], 'assetsUrl', $extension);
        $this->assertAttributeEquals($config['templates']['assets_version'], 'assetsVersion', $extension);
        $this->assertAttributeSame($router, 'router', $extension);
    }

    public function testConfiguresTemplateSuffix()
    {
        $config = [
            'templates' => [
                'extension' => 'tpl',
            ],
        ];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $this->container->has(RouterInterface::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Router\RouterInterface::class)->willReturn(false);
        $factory = new TwigRendererFactory();
        $twig = $factory($this->container->reveal());

        $this->assertAttributeSame($config['templates']['extension'], 'suffix', $twig);
    }

    public function testConfiguresPaths()
    {
        $config = [
            'templates' => [
                'paths' => $this->getConfigurationPaths(),
            ],
        ];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $this->container->has(RouterInterface::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Router\RouterInterface::class)->willReturn(false);
        $factory = new TwigRendererFactory();
        $twig = $factory($this->container->reveal());

        $paths = $twig->getPaths();
        $this->assertPathsHasNamespace('foo', $paths);
        $this->assertPathsHasNamespace('bar', $paths);
        $this->assertPathsHasNamespace(null, $paths);

        $this->assertPathNamespaceCount(1, 'foo', $paths);
        $this->assertPathNamespaceCount(2, 'bar', $paths);
        $this->assertPathNamespaceCount(3, null, $paths);

        $this->assertPathNamespaceContains(__DIR__ . '/TestAsset/bar', 'foo', $paths);
        $this->assertPathNamespaceContains(__DIR__ . '/TestAsset/baz', 'bar', $paths);
        $this->assertPathNamespaceContains(__DIR__ . '/TestAsset/bat', 'bar', $paths);
        $this->assertPathNamespaceContains(__DIR__ . '/TestAsset/one', null, $paths);
        $this->assertPathNamespaceContains(__DIR__ . '/TestAsset/two', null, $paths);
        $this->assertPathNamespaceContains(__DIR__ . '/TestAsset/three', null, $paths);
    }

    public function testInjectsCustomExtensionsIntoTwigEnvironment()
    {
        $config = [
            'templates' => [
            ],
            'twig' => [
                'extensions' => [
                    new FooTwigExtension(),
                    BarTwigExtension::class,
                ],
            ],
        ];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $this->container->has(RouterInterface::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Router\RouterInterface::class)->willReturn(false);
        $this->container->has(BarTwigExtension::class)->willReturn(true);
        $this->container->get(BarTwigExtension::class)->willReturn(new BarTwigExtension());
        $factory = new TwigRendererFactory();
        $view = $factory($this->container->reveal());
        $this->assertInstanceOf(TwigRenderer::class, $view);
        $environment = $this->fetchTwigEnvironment($view);
        $this->assertTrue($environment->hasExtension('foo-twig-extension'));
        $this->assertInstanceOf(FooTwigExtension::class, $environment->getExtension('foo-twig-extension'));
        $this->assertTrue($environment->hasExtension('bar-twig-extension'));
        $this->assertInstanceOf(BarTwigExtension::class, $environment->getExtension('bar-twig-extension'));
    }

    public function invalidExtensions()
    {
        return [
            'null'                  => [null],
            'true'                  => [true],
            'false'                 => [false],
            'zero'                  => [0],
            'int'                   => [1],
            'zero-float'            => [0.0],
            'float'                 => [1.1],
            'non-service-string'    => ['not-an-extension'],
            'array'                 => [['not-an-extension']],
            'non-extensions-object' => [(object) ['extension' => 'not-an-extension']],
        ];
    }

    /**
     * @dataProvider invalidExtensions
     */
    public function testRaisesExceptionForInvalidExtensions($extension)
    {
        $config = [
            'templates' => [
            ],
            'twig' => [
                'extensions' => [ $extension ],
            ],
        ];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $this->container->has(RouterInterface::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Router\RouterInterface::class)->willReturn(false);

        if (is_string($extension)) {
            $this->container->has($extension)->willReturn(false);
        }

        $factory = new TwigRendererFactory();

        $this->setExpectedException(InvalidExtensionException::class);
        $factory($this->container->reveal());
    }
}
