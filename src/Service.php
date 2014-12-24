<?php
/**
 * Opine\Layout\Service
 *
 * Copyright (c)2013, 2014 Ryan Mahoney, https://github.com/Opine-Org <ryan@virtuecenter.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
namespace Opine\Layout;

use Opine\Interfaces\Cache as CacheInterface;
use Opine\Interfaces\Route as RouteInterface;
use Opine\Interfaces\Layout as LayoutInterface;

class Service implements LayoutInterface
{
    private $root;
    private $engine;
    private $cache;
    private $route;

    public function __construct($root, $engine, CacheInterface $cache, RouteInterface $route)
    {
        $this->root = $root;
        $this->engine = $engine;
        $this->cache = $cache;
        $this->route = $route;
    }

    public function make($config, $container = '', Array $context = [], $debug = false)
    {
        if (empty($container)) {
            $container = $config;
        }

        return $this->instance($config, $container, $context, $debug)->
            render();
    }

    public function config($paths, Array $context = [], $debug = false)
    {
        return $this->instance($paths, $paths, $context, $debug);
    }

    public function container($paths, Array $context = [], $debug = false)
    {
        return $this->instance('', $paths, $context, $debug);
    }

    private function instance($config = '', $container = '', Array $context, $debug)
    {
        return new Container($config, $container, $context, $debug, $this->root, $this->engine, $this->cache, $this->route);
    }
}
