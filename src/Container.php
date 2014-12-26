<?php
/**
 * Opine\Layout\Container
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

use Exception;
use ArrayObject;
use Symfony\Component\Yaml\Yaml;
use Opine\Interfaces\Cache as CacheInterface;
use Opine\Interfaces\Route as RouteInterface;
use Opine\Interfaces\LayoutContainer as LayoutContainerInterface;

class Container implements LayoutContainerInterface
{
    private $root;
    private $engine;
    private $cache;
    private $route;
    private $layout;
    private $configFile;
    private $regions = [];
    private $regionsHash = [];
    private $debug = false;
    private $containerFile;
    private $containerFileName;
    private $context = [];
    private $urlCache = [];

    public function __construct($config, $container, Array $context, $debug, $root, $engine, CacheInterface $cache, RouteInterface $route)
    {
        $this->root = $root;
        $this->engine = $engine;
        $this->cache = $cache;
        $this->route = $route;
        if ($debug === true) {
            $this->debug = true;
        }
        if (!empty($config)) {
            $this->config($config);
        }
        if (!empty($container)) {
            $this->container($container);
        }
        if (!empty($context)) {
            $this->context = $context;
        }
    }

    private function regionTypeFromUrl($url, Array $args)
    {
        $type = 'json';
        if (substr_count($url, '/api/form/')) {
            $type = 'Form';
        } elseif (substr_count($url, '/api/collection/')) {
            $type = 'Collection';
        }
        if (substr_count($url, '/api/collection/') && isset($args) && (isset($args['slug']) || isset($args['id']))) {
            $type = 'Document';
        } elseif (substr_count($url, '/bySlug/') == 1 || substr_count($url, '/byId/') == 1) {
            $type = 'Document';
        }

        return $type;
    }

    public function region($id, Array $region)
    {
        if (!isset($region['type'])) {
            $region['type'] = 'array';
        }
        if (!isset($region['args']) || !is_array($region['args'])) {
            $region['args'] = [];
        }
        if (isset($region['url']) && $region['type'] === 'array') {
            $region['type'] = $this->regionTypeFromUrl($region['url'], $region['args']);
        }
        if (isset($region['data'])) {
            $this->context[$id] = $region['data'];
            unset($region['data']);
        }
        $offset = count($this->regions);
        $this->regions[$offset] = new ArrayObject($region);
        $this->regions[$offset]['id'] = $id;
        $this->regionsHash[$id] = $this->regions[$offset];

        return $this;
    }

    private function config($paths)
    {
        $path = false;
        if (!is_array($paths)) {
            $paths = [$paths];
        }
        foreach ($paths as $path) {
            if ($this->configPathDetermine($path) === true) {
                break;
            }
        }
        if ($path === false) {
            throw new LayoutContainerException('Can not find config file: '.implode(', ', $paths), 1);
        }
        $this->configLoad($path);
    }

    private function configPathDetermine(&$path)
    {
        if (substr($path, 0, 1) == '/') {
            $path = $path.'.yml';
        } else {
            if (substr($path, 0, 16) == 'config/layouts/') {
                $path = $this->root.'/../'.$path.'.yml';
            } else {
                $path = $this->root.'/../config/layouts/'.$path.'.yml';
            }
        }
        $path = str_replace('.yml.yml', '.yml', $path);
        if (!file_exists($path)) {
            return false;
        }

        return true;
    }

    public function container($paths, Array $context = [])
    {
        if (!empty($context)) {
            $this->context = array_merge($this->context, $context);
        }
        if (!is_array($paths)) {
            $paths = [$paths];
        }
        $path = false;
        foreach ($paths as $path) {
            if ($this->containerPathDetermine($path) === true) {
                break;
            }
        }
        if ($path === false) {
            throw new LayoutContainerException('Can not find layout file: '.implode(', ', $paths), 2);
        }
        $this->containerFileName = $path;
        $this->containerFile = $this->compiledAsset($path);
        if (empty($this->containerFile)) {
            if (!file_exists($path)) {
                throw new LayoutContainerException('Layout container does not exist: '.$path, 3);
            }
            $this->containerFile = file_get_contents($path);
        }

        return $this;
    }

    private function containerPathDetermine(&$path)
    {
        if (substr($path, 0, 1) == '/') {
            $path = $path.'.html';
        } else {
            $path = $this->root.'/layouts/'.$path.'.html';
        }
        $path = str_replace('.html.html', '.html', $path);
        if (!file_exists($path)) {
            return false;
        }

        return true;
    }

    private function configLoad($configFile)
    {
        if (!file_exists($configFile)) {
            throw new LayoutContainerException('Layout config does not exist: '.$configFile, 4);
        }
        try {
            $layout = Yaml::parse(file_get_contents($configFile));
        } catch (Exception $e) {
            throw new LayoutContainerException('Can not parse YAML file: '.$configFile, 5);
        }
        if ($layout == false) {
            throw new LayoutContainerException('Can not parse YAML file: '.$configFile, 5);
        }
        if (isset($layout['imports']) && is_array($layout['imports']) && !empty($layout['imports'])) {
            foreach ($layout['imports'] as $import) {
                $first = substr($import, 0, 1);
                if ($first != '/') {
                    $import = $this->root.'/../config/layouts/'.$import;
                }
                $this->configLoad($import);
            }
        }
        if (!isset($layout['regions']) || !is_array($layout['regions']) || empty($layout['regions'])) {
            return;
        }
        foreach ($layout['regions'] as $id => $region) {
            $this->region($id, $region);
        }
    }

    public function url($id, $url)
    {
        $this->regionsHash[$id]['url'] = $url;

        return $this;
    }

    public function args($id, Array $args)
    {
        $this->regionsHash[$id]['args'] = $args;

        return $this;
    }

    public function partial($id, $partial)
    {
        $this->regionsHash[$id]['partial'] = $partial;

        return $this;
    }

    public function data($id, $data, $type = 'array')
    {
        $this->context[$id] = $data;
        $this->regionsHash[$id]['type'] = $type;

        return $this;
    }

    private function documentUrl(&$region)
    {
        if (isset($region['args']['slug'])) {
            $region['url'] = str_replace(':slug', $region['args']['slug'], $region['url']);
        }
        if (isset($region['args']['id'])) {
            $region['url'] = str_replace('/bySlug/:slug', '/byId/'.$region['args']['id'], $region['url']);
        }
    }

    private function regionUrlSet(&$region)
    {
        if (!isset($region['url'])) {
            $region['url'] = '';
        }
        if (isset($region['args']) && is_array($region['args']) && count($region['args']) > 0) {
            $delimiter = '?';
            if (substr_count($region['url'], '?') > 0) {
                $delimiter = '&';
            }
            $region['url'] .= $delimiter.urldecode(http_build_query($region['args']));
        }
        if (isset($region['type']) && $region['type'] == 'Document') {
            $this->documentUrl($region);
        }

        return $this;
    }

    private function renderContainer()
    {
        foreach ($this->regions as $region) {
            if (!isset($region['partial']) || empty($region['partial'])) {
                $template = '';
            } elseif (substr($region['partial'], -4) == '.hbs') {
                $partialPath = $this->root.'/partials/'.$region['partial'];
                $template = $this->compiledAsset($partialPath);
                if (empty($template)) {
                    if (!file_exists($partialPath)) {
                        throw new LayoutContainerException('Layout partial does not exist: '.$partialPath, 6);
                    }
                    $template = file_get_contents($partialPath);
                }
            } else {
                $template = $region['partial'];
            }
            if (!isset($this->context[$region['id']])) {
                if (isset($region['cache'])) {
                    $this->context[$region['id']] = $this->cache->getSetGet($this->root.'-region-'.$region['url'], function () use ($region) {
                        return $this->dataUrlRead($region['url']);
                    }, $region['cache']);
                } else {
                    $this->context[$region['id']] = $this->dataUrlRead($region['url']);
                }
            }
            $type = 'json';
            if (isset($region['type'])) {
                $type = $region['type'];
            }
            if (in_array($type, ['json', 'Collection', 'Document', 'Form'])) {
                if (!in_array(substr($this->context[$region['id']], 0, 1), ['{', ']'])) {
                    $this->context[$region['id']] = substr($this->context[$region['id']], (strpos($this->context[$region['id']], '(') + 1), -1);
                }
                $this->context[$region['id']] = json_decode($this->context[$region['id']], true);
            }
            if ($template === false) {
                $this->context[$region['id']] = $this->context[$region['id']];
            } else {
                if (is_callable($template)) {
                    $this->context[$region['id']] = $template($this->context[$region['id']]);
                } else {
                    $template = $this->engine->prepare($this->engine->compile($template));
                    $this->context[$region['id']] = $template((Array) $this->context[$region['id']]);
                }
            }
        }
        if ($this->debug) {
            echo 'Context:', "\n";
            var_dump($this->context);
            echo 'Regions:', "\n";
            var_dump($this->regions);
        }
        if (is_callable($this->containerFile)) {
            $function = $this->containerFile;

            return $function($this->context);
        }
        $function = $this->engine->prepare($this->engine->compile($this->containerFile));

        return $function($this->context);
    }

    private function dataUrlRead($dataUrl)
    {
        if (isset($this->urlCache[$dataUrl])) {
            return $this->urlCache[$dataUrl];
        }
        if (strtolower(substr($dataUrl, 0, 4)) != 'http') {
            $data = $this->route->run('GET', $dataUrl);
            if ($data === false) {
                throw new LayoutContainerException('Layout local route URL failed: '.$dataUrl, 7);
            } elseif (is_string($data)) {
                $data = trim($data);
            } else {
                throw new LayoutContainerException('Layout data return non-string: '.$dataUrl.': '.gettype($data), 8);
            }
            $this->urlCache[$dataUrl] = $data;

            return $data;
        }
        $data = file_get_contents($dataUrl);
        if ($data === false) {
            throw new LayoutContainerException('External route URL failed: '.$dataUrl, 9);
        } else {
            $data = trim($data);
        }

        return $data;
    }

    public function write()
    {
        $this->render('echo');
    }

    public function render($mode = 'return')
    {
        $fullCache = true;
        $key = '';
        $ttl = -1;
        foreach ($this->regions as &$region) {
            $this->regionUrlSet($region);
            if (!isset($region['cache']) || empty($region['cache'])) {
                $fullCache = false;
            } else {
                if ($ttl > $region['cache'] || $ttl == -1) {
                    $ttl = $region['cache'];
                }
            }
            $key .= $region['url'];
        }
        if (count($this->regions) == 0 && count($this->context) > 0) {
            $fullCache = false;
        }
        if ($fullCache === false || $ttl === -1) {
            $this->output = $this->renderContainer();
        } else {
            $key = $this->root.'-layout-'.md5($this->configFile.'-'.$this->containerFileName.'-'.$key);
            $this->output = $this->cache->getSetGet($key, function () {
                return $this->renderContainer();
            }, $ttl);
        }
        if ($mode == 'return') {
            return $this->output;
        }
        echo $this->output;
    }

    private function compiledAsset($path)
    {
        $path = str_replace('/public/', '/var/cache/public/', $path);
        if (file_exists($path)) {
            $function = require $path;

            return $function;
        }

        return;
    }
}

class LayoutContainerException extends Exception
{
}
