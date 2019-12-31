<?php

/**
 * @see       https://github.com/mezzio/mezzio-twigrenderer for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio-twigrenderer/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio-twigrenderer/blob/master/LICENSE.md New BSD License
 */

namespace MezzioTest\Twig\TestAsset\Extension;

class FooTwigExtension extends \Twig_Extension
{
    public function getName()
    {
        return 'foo-twig-extension';
    }
}
