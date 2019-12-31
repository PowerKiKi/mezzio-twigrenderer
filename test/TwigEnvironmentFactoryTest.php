<?php

/**
 * @see       https://github.com/mezzio/mezzio-twigrenderer for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio-twigrenderer/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio-twigrenderer/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace MezzioTest\Twig;

use DateTimeZone;
use Mezzio\Helper\ServerUrlHelper;
use Mezzio\Helper\UrlHelper;
use Mezzio\Twig\Exception\InvalidConfigException;
use Mezzio\Twig\Exception\InvalidExtensionException;
use Mezzio\Twig\Exception\InvalidRuntimeLoaderException;
use Mezzio\Twig\TwigEnvironmentFactory;
use Mezzio\Twig\TwigExtension;
use Mezzio\Twig\TwigExtensionFactory;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ProphecyInterface;
use Psr\Container\ContainerInterface;
use Twig_Environment as TwigEnvironment;
use Twig_Extension_Core as TwigExtensionCore;
use Twig_Extension_Escaper as TwigExtensionEscaper;
use Twig_Extension_Optimizer as TwigExtensionOptimizer;
use Twig_RuntimeLoaderInterface as TwigRuntimeLoaderInterface;

use function is_string;

class TwigEnvironmentFactoryTest extends TestCase
{
    /**
     * @var ContainerInterface|ProphecyInterface
     */
    private $container;

    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
    }

    public function testCallingFactoryWithNoConfigReturnsTwigEnvironmentInstance()
    {
        $this->container->has('config')->willReturn(false);
        $this->container->has(TwigExtension::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Twig\TwigExtension::class)->willReturn(false);
        $this->container->has(ServerUrlHelper::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Helper\ServerUrlHelper::class)->willReturn(false);
        $this->container->has(UrlHelper::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Helper\UrlHelper::class)->willReturn(false);
        $factory     = new TwigEnvironmentFactory();
        $environment = $factory($this->container->reveal());

        $this->assertInstanceOf(TwigEnvironment::class, $environment);

        return $environment;
    }

    public function testUsesDebugConfiguration()
    {
        $config = ['debug' => true];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $this->container->has(TwigExtension::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Twig\TwigExtension::class)->willReturn(false);
        $this->container->has(ServerUrlHelper::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Helper\ServerUrlHelper::class)->willReturn(false);
        $this->container->has(UrlHelper::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Helper\UrlHelper::class)->willReturn(false);
        $factory     = new TwigEnvironmentFactory();
        $environment = $factory($this->container->reveal());

        $this->assertTrue($environment->isDebug());
        $this->assertFalse($environment->getCache());
        $this->assertTrue($environment->isStrictVariables());
        $this->assertTrue($environment->isAutoReload());
    }

    /**
     * @depends testCallingFactoryWithNoConfigReturnsTwigEnvironmentInstance
     *
     * @param TwigEnvironment $environment
     */
    public function testDebugDisabledSetsUpEnvironmentForProduction(TwigEnvironment $environment)
    {
        $this->assertFalse($environment->isDebug());
        $this->assertFalse($environment->isStrictVariables());
        $this->assertFalse($environment->isAutoReload());
    }

    public function testCanSpecifyCacheDirectoryViaConfiguration()
    {
        $config = ['templates' => ['cache_dir' => __DIR__]];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $this->container->has(TwigExtension::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Twig\TwigExtension::class)->willReturn(false);
        $this->container->has(ServerUrlHelper::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Helper\ServerUrlHelper::class)->willReturn(false);
        $this->container->has(UrlHelper::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Helper\UrlHelper::class)->willReturn(false);
        $factory     = new TwigEnvironmentFactory();
        $environment = $factory($this->container->reveal());

        $this->assertEquals($config['templates']['cache_dir'], $environment->getCache());
    }

    public function testAddsTwigExtensionIfRouterIsInContainer()
    {
        $twigExtensionFactory = new TwigExtensionFactory();
        $serverUrlHelper = $this->prophesize(ServerUrlHelper::class)->reveal();
        $urlHelper       = $this->prophesize(UrlHelper::class)->reveal();
        $this->container->has('config')->willReturn(false);
        $this->container->has(ServerUrlHelper::class)->willReturn(true);
        $this->container->get(ServerUrlHelper::class)->willReturn($serverUrlHelper);
        $this->container->has(UrlHelper::class)->willReturn(true);
        $this->container->get(UrlHelper::class)->willReturn($urlHelper);
        $this->container->has(TwigExtension::class)->willReturn(true);
        $this->container->get(TwigExtension::class)->willReturn($twigExtensionFactory($this->container->reveal()));
        $factory     = new TwigEnvironmentFactory();
        $environment = $factory($this->container->reveal());

        $this->assertTrue($environment->hasExtension(TwigExtension::class));
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
     *
     * @param mixed $extension
     */
    public function testRaisesExceptionForInvalidExtensions($extension)
    {
        $config = [
            'templates' => [],
            'twig'      => [
                'extensions' => [$extension],
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

        if (is_string($extension)) {
            $this->container->has($extension)->willReturn(false);
        }

        $factory = new TwigEnvironmentFactory();

        $this->expectException(InvalidExtensionException::class);
        $factory($this->container->reveal());
    }

    public function invalidConfiguration()
    {
        //                        [Config value, Type]
        return [
            'true'             => [true, 'boolean'],
            'false'            => [false, 'boolean'],
            'zero'             => [0, 'integer'],
            'int'              => [1, 'integer'],
            'zero-float'       => [0.0, 'double'],
            'float'            => [1.1, 'double'],
            'string'           => ['not-configuration', 'string'],
            'non-array-object' => [(object) ['not' => 'configuration'], 'stdClass'],
        ];
    }

    /**
     * @dataProvider invalidConfiguration
     *
     * @param mixed $config
     * @param string $contains
     */
    public function testRaisesExceptionForInvalidConfigService($config, $contains)
    {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $factory = new TwigEnvironmentFactory();

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage($contains);
        $factory($this->container->reveal());
    }

    public function testUsesTimezoneConfiguration()
    {
        $tz = DateTimeZone::listIdentifiers()[0];
        $config = [
            'twig' => [
                'timezone' => $tz,
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
        $factory = new TwigEnvironmentFactory();
        $environment = $factory($this->container->reveal());
        $fetchedTz = $environment->getExtension(TwigExtensionCore::class)->getTimezone();
        $this->assertEquals(new DateTimeZone($tz), $fetchedTz);
    }

    public function testRaisesExceptionForInvalidTimezone()
    {
        $tz = 'Luna/Copernicus_Crater';
        $config = [
            'twig' => [
                'timezone' => $tz,
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
        $factory = new TwigEnvironmentFactory();

        $this->expectException(InvalidConfigException::class);
        $factory($this->container->reveal());
    }

    public function testRaisesExceptionForNonStringTimezone()
    {
        $config = [
            'twig' => [
                'timezone' => new DateTimeZone('UTC'),
            ],
        ];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $factory = new TwigEnvironmentFactory();

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('"timezone" configuration value must be a string');

        $factory($this->container->reveal());
    }

    public function invalidRuntimeLoaders()
    {
        return [
            'null'                  => [null],
            'true'                  => [true],
            'false'                 => [false],
            'zero'                  => [0],
            'int'                   => [1],
            'zero-float'            => [0.0],
            'float'                 => [1.1],
            'non-service-string'    => ['not-an-runtime-loader'],
            'array'                 => [['not-an-runtime-loader']],
            'non-extensions-object' => [(object) ['extension' => 'not-an-runtime-loader']],
        ];
    }

    /**
     * @dataProvider invalidRuntimeLoaders
     *
     * @param mixed $runtimeLoader
     */
    public function testRaisesExceptionForInvalidRuntimeLoaders($runtimeLoader)
    {
        $config = [
            'templates' => [],
            'twig' => [
                'runtime_loaders' => [$runtimeLoader],
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

        if (is_string($runtimeLoader)) {
            $this->container->has($runtimeLoader)->willReturn(false);
        }

        $factory = new TwigEnvironmentFactory();

        $this->expectException(InvalidRuntimeLoaderException::class);
        $factory($this->container->reveal());
    }

    public function testInjectsCustomRuntimeLoadersIntoTwigEnvironment()
    {
        $fooRuntime = $this->prophesize(TwigRuntimeLoaderInterface::class);
        $fooRuntime->load('Test\Runtime\FooRuntime')->willReturn('foo-runtime');
        $fooRuntime->load('Test\Runtime\BarRuntime')->willReturn(null);

        $barRuntime = $this->prophesize(TwigRuntimeLoaderInterface::class);
        $barRuntime->load('Test\Runtime\BarRuntime')->willReturn('bar-runtime');
        $barRuntime->load('Test\Runtime\FooRuntime')->willReturn(null);

        $config = [
            'templates' => [],
            'twig' => [
                'runtime_loaders' => [
                    $fooRuntime->reveal(),
                    'Test\Runtime\BarRuntimeLoader',
                ],
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
        $this->container->has('Test\Runtime\BarRuntimeLoader')->willReturn(true);
        $this->container->get('Test\Runtime\BarRuntimeLoader')->willReturn($barRuntime->reveal());

        $factory = new TwigEnvironmentFactory();
        $environment = $factory($this->container->reveal());

        $this->assertInstanceOf(TwigEnvironment::class, $environment);
        $this->assertEquals('bar-runtime', $environment->getRuntime('Test\Runtime\BarRuntime'));
        $this->assertEquals('foo-runtime', $environment->getRuntime('Test\Runtime\FooRuntime'));
    }

    public function testUsesOptimizationsConfiguration()
    {
        $config = [
            'twig' => [
                'optimizations' => 0,
            ]
        ];
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $this->container->has(TwigExtension::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Twig\TwigExtension::class)->willReturn(false);
        $factory     = new TwigEnvironmentFactory();
        $environment = $factory($this->container->reveal());

        $extension = $environment->getExtension(TwigExtensionOptimizer::class);
        $property = new \ReflectionProperty($extension, 'optimizers');
        $property->setAccessible(true);

        $this->assertSame(0, $property->getValue($extension));
    }

    public function testUsesAutoescapeConfiguration()
    {
        $config = [
            'twig' => [
                'autoescape' => false,
            ]
        ];

        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn($config);
        $this->container->has(TwigExtension::class)->willReturn(false);
        $this->container->has(\Zend\Expressive\Twig\TwigExtension::class)->willReturn(false);
        $factory     = new TwigEnvironmentFactory();
        $environment = $factory($this->container->reveal());
        $extension = $environment->getExtension(TwigExtensionEscaper::class);
        $this->assertFalse($extension->getDefaultStrategy('template::name'));
    }
}
