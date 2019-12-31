<?php

/**
 * @see       https://github.com/mezzio/mezzio-twigrenderer for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio-twigrenderer/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio-twigrenderer/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace MezzioTest\Twig;

use Mezzio\Helper\ServerUrlHelper;
use Mezzio\Helper\UrlHelper;
use Mezzio\Template\TemplatePath;
use Mezzio\Twig\Exception\InvalidConfigException;
use Mezzio\Twig\TwigEnvironmentFactory;
use Mezzio\Twig\TwigExtension;
use Mezzio\Twig\TwigRenderer;
use Mezzio\Twig\TwigRendererFactory;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ProphecyInterface;
use Psr\Container\ContainerInterface;
use ReflectionProperty;
use Twig_Environment as TwigEnvironment;

use function restore_error_handler;
use function set_error_handler;
use function sprintf;

use const E_USER_DEPRECATED;

class TwigRendererFactoryTest extends TestCase
{
    /**
     * @var ContainerInterface|ProphecyInterface
     */
    private $container;

    /**
     * @var callable
     */
    private $errorHandler;

    public function setUp()
    {
        $this->restoreErrorHandler();
        $this->container = $this->prophesize(ContainerInterface::class);
    }

    public function tearDown()
    {
        $this->restoreErrorHandler();
    }

    public function restoreErrorHandler()
    {
        if ($this->errorHandler) {
            restore_error_handler();
            $this->errorHandler = null;
        }
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
            1     => __DIR__ . '/TestAsset/one',
            'bar' => [
                __DIR__ . '/TestAsset/baz',
                __DIR__ . '/TestAsset/bat',
            ],
            0     => [
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
        $this->container->has(TwigExtension::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Twig\TwigExtension::class)->willReturn(false);
        $this->container->has(ServerUrlHelper::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Helper\ServerUrlHelper::class)->willReturn(false);
        $this->container->has(UrlHelper::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Helper\UrlHelper::class)->willReturn(false);
        $environment = new TwigEnvironmentFactory();
        $this->container->has(TwigEnvironment::class)->willReturn(true);
        $this->container->get(TwigEnvironment::class)->willReturn(
            $environment($this->container->reveal())
        );

        $factory = new TwigRendererFactory();
        $twig    = $factory($this->container->reveal());
        $this->assertInstanceOf(TwigRenderer::class, $twig);

        return $twig;
    }

    /**
     * @depends testCallingFactoryWithNoConfigReturnsTwigInstance
     *
     * @param TwigRenderer $twig
     */
    public function testUnconfiguredTwigInstanceContainsNoPaths(TwigRenderer $twig)
    {
        $paths = $twig->getPaths();
        $this->assertInternalType('array', $paths);
        $this->assertEmpty($paths);
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
        $this->container->has(TwigExtension::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Twig\TwigExtension::class)->willReturn(false);
        $this->container->has(ServerUrlHelper::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Helper\ServerUrlHelper::class)->willReturn(false);
        $this->container->has(UrlHelper::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Helper\UrlHelper::class)->willReturn(false);
        $environment = new TwigEnvironmentFactory();
        $this->container->has(TwigEnvironment::class)->willReturn(true);
        $this->container->get(TwigEnvironment::class)->willReturn(
            $environment($this->container->reveal())
        );
        $factory = new TwigRendererFactory();
        $twig    = $factory($this->container->reveal());

        $this->assertAttributeSame($config['templates']['extension'], 'suffix', $twig);
    }

    public function testUsesGlobalsConfigurationWhenAddingTwigExtension()
    {
        $config = [
            'templates' => [
                'paths' => $this->getConfigurationPaths(),
            ],
        ];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $this->container->has(TwigExtension::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Twig\TwigExtension::class)->willReturn(false);
        $this->container->has(ServerUrlHelper::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Helper\ServerUrlHelper::class)->willReturn(false);
        $this->container->has(UrlHelper::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Helper\UrlHelper::class)->willReturn(false);
        $environment = new TwigEnvironmentFactory();
        $this->container->has(TwigEnvironment::class)->willReturn(true);
        $this->container->get(TwigEnvironment::class)->willReturn(
            $environment($this->container->reveal())
        );
        $factory = new TwigRendererFactory();
        $twig    = $factory($this->container->reveal());

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

    public function testCallingFactoryWithoutTwigEnvironmentServiceEmitsDeprecationNotice()
    {
        $this->container->has('config')->willReturn(false);
        $this->container->has(TwigExtension::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Twig\TwigExtension::class)->willReturn(false);
        $this->container->has(ServerUrlHelper::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Helper\ServerUrlHelper::class)->willReturn(false);
        $this->container->has(UrlHelper::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Helper\UrlHelper::class)->willReturn(false);
        $this->container->has(TwigEnvironment::class)->willReturn(false);

        $factory = new TwigRendererFactory();

        $this->errorHandler = set_error_handler(function ($errno, $errstr) {
            $this->assertContains(TwigEnvironment::class, $errstr);
            return true;
        }, E_USER_DEPRECATED);

        $twig = $factory($this->container->reveal());
        $this->assertInstanceOf(TwigRenderer::class, $twig);
    }

    public function testMergeConfigRaisesExceptionForInvalidConfig()
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Config service MUST be an array or ArrayObject; received string');

        TwigRendererFactory::mergeConfig('foo');
    }

    public function testMergesConfigCorrectly()
    {
        $config = [
            'templates' => [
                'extension' => 'file extension used by templates; defaults to html.twig',
                'paths' => [],
            ],
            'twig' => [
                'cache_dir' => 'path to cached templates',
                'assets_url' => 'base URL for assets',
                'assets_version' => 'base version for assets',
                'extensions' => [],
                'runtime_loaders' => [],
                'globals' => ['ga_tracking' => 'UA-XXXXX-X'],
                'timezone' => 'default timezone identifier, e.g.: America/New_York',
            ],
        ];

        $mergedConfig = TwigRendererFactory::mergeConfig($config);

        $this->assertArrayHasKey('extension', $mergedConfig);
        $this->assertArrayHasKey('paths', $mergedConfig);
        $this->assertArrayHasKey('cache_dir', $mergedConfig);
        $this->assertArrayHasKey('assets_version', $mergedConfig);
        $this->assertArrayHasKey('runtime_loaders', $mergedConfig);
        $this->assertArrayHasKey('globals', $mergedConfig);
        $this->assertArrayHasKey('timezone', $mergedConfig);
    }
}
