<?php
/**
 * Opine\Layout
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
namespace Opine;
use Exception;
use ArrayObject;
use Symfony\Component\Yaml\Yaml;

class Layout {
    private $layout;
    private $regions = [];
    private $regionsHash = [];
    private $engine;
    private $root;
    private $cache;
    private $dataCache = [];
    private $appCalled = false;
    private $route = false;
    private $debug = false;
    private $appFile;
    private $layoutFile = false;
    private $layoutFileName;

    public function __construct($root, $engine, $cache, $route, $appFile=false) {
        $this->root = $root;
        $this->engine = $engine;
        $this->cache = $cache;
        $this->route = $route;
        if ($appFile != false) {
            $this->appConfig($appFile);
            $this->appCalled = true;
            $this->appFile = $appFile;
        }
    }

    public function showBindings () {
        return print_r($this->regions, true);
    }

    public function make ($app, $layout=false) {
        if ($layout === false) {
            $layout = $app;
        }
        return $this->
            app($app)->
            render($layout);
    }

    private function appPathDetermine (&$path) {
        if (substr($path, 0, 1) == '/') {
            $path = $path . '.yml';
        } else {
            if (substr($path, 0, 4) == 'app/') {
                $path = $this->root . '/../' . $path . '.yml';
            } else {
                $path = $this->root . '/../app/' . $path . '.yml';
            }
        }
        $path = str_replace('.yml.yml', '.yml', $path);
        if (!file_exists($path)) {
            return false;
        }
        return true;
    }

    public function app ($paths) {
        if (!is_array($paths)) {
            $paths = [$paths];
        }
        $path = false;
        foreach ($paths as $path) {
            if ($this->appPathDetermine($path) === true) {
                break;
            }
        }
        if ($path === false) {
            throw new Exception('Can not find config file: ' . implode(', ', $paths));
        }
        return new Layout($this->root, $this->engine, $this->cache, $this->route, $path);
    }

    public function debug () {
        $this->debug = true;
        return $this;
    }

    private function layoutPathDetermine (&$path) {
        if (substr($path, 0, 1) == '/') {
            $path = $path . '.html';
        } else {
            $path = $this->root . '/layouts/' . $path . '.html';
        }
        $path = str_replace('.html.html', '.html', $path);
        if (!file_exists($path)) {
            return false;
        }
        return true;
    }

    public function layout ($paths) {
        if (!is_array($paths)) {
            $paths = [$paths];
        }
        $path = false;
        foreach ($paths as $path) {
            if ($this->layoutPathDetermine($path) === true) {
                break;
            }
        }
        if ($path === false) {
            throw new Exception('Can not find layout file: ' . implode(', ', $paths));
        }
        $this->layoutFileName = $path;
        $this->layoutFile = $this->compiledAsset($path);
        if ($this->layoutFile === false) {
            $this->layoutFile = file_get_contents($path);
        }
        return $this;
    }

    private function appConfig ($configFile) {
        if (function_exists('yaml_parse_file')) {
            $layout = yaml_parse_file($configFile);
        } else {
            $layout = Yaml::parse(file_get_contents($configFile));
        }
        if ($layout == false) {
            throw new Exception('Can not parse YAML file: ' . $configFile);
        }
        if (isset($layout['imports']) && is_array($layout['imports']) && !empty($layout['imports'])) {
            foreach ($layout['imports'] as $import) {
                $first = substr($import, 0, 1);
                if ($first != '/') {
                    $import = $this->root . '/../app/' . $import;
                }
                $this->appConfig($import);
            }
        }
        if (!isset($layout['regions']) || !is_array($layout['regions']) || empty($layout['regions'])) {
            return;
        }
        foreach ($layout['regions'] as $id => $region) {
            $this->regionAdd($id, $region);
        }
    }

    public function regionAdd ($id, $region, $data=false) {
        $offset = count($this->regions);
        $this->regions[$offset] = new ArrayObject($region);
        $this->regions[$offset]['id'] = $id;
        $this->regionsHash[$id] = $this->regions[$offset];
        if ($data !== false) {
            $this->dataCache[$id] = $data;
        }
    }

    public function url ($id, $url) {
        $this->regionsHash[$id]['url'] = $url;
        return $this;
    }

    public function args ($id, $args) {
        $this->regionsHash[$id]['args'] = $args;
        return $this;
    }

    public function partial ($id, $partial) {
        $this->regionsHash[$id]['partial'] = $partial;
        return $this;
    }

    public function data ($id, $data, $type='array') {
        $this->dataCache[$id] = $data;
        $this->regionsHash[$id]['type'] = $type;
        return $this;
    }

    private function documentUrl (&$region) {
        if (isset($region['args']['slug'])) {
            $region['url'] = str_replace(':slug', $region['args']['slug'], $region['url']);
        }
        if (isset($region['args']['id'])) {
            $region['url'] = str_replace('/bySlug/:slug', '/byId/' . $region['args']['id'], $region['url']);
        }
    }

    private function regionUrlSet (&$region) {
        if (!isset($region['url'])) {
            $region['url'] = '';
        }
        if (isset($region['args']) && is_array($region['args']) && count($region['args']) > 0) {
            $delimiter = '?';
            if (substr_count($region['url'], '?') > 0) {
                $delimiter = '&';
            }
            $region['url'] .= $delimiter . urldecode(http_build_query($region['args']));
        }
        if (isset($region['type']) && $region['type'] == 'Document') {
            $this->documentUrl($region);
        }
    }

    private function renderRegions () {
        $context = [];
        foreach ($this->regions as $region) {
            if (!isset($region['partial']) || empty($region['partial'])) {
                $template = false;
            } elseif (substr($region['partial'], -4) == '.hbs') {
                $partialPath = $this->root . '/partials/' . $region['partial'];
                $template = $this->compiledAsset($partialPath);
                if ($template === false) {
                    $template = file_get_contents($partialPath);
                }
            } else {
                $template = $region['partial'];
            }
            if (isset($region['type'])) {
                if ($region['type'] == 'Post') {
                    $this->dataCache[$region['id']] = (isset($_POST) ? $_POST : []);
                } elseif ($region['type'] == 'Get') {
                    $this->dataCache[$region['id']] = (isset($_GET) ? $_GET : []);
                }
            }
            if (!isset($this->dataCache[$region['id']])) {
                if (isset($region['cache'])) {
                    $this->dataCache[$region['id']] = $this->cache->getSetGet($this->root . '-region-' . $region['url'], function () use ($region) {
                        return $this->dataUrlRead($region['url']);
                    }, $region['cache']);
                } else {
                    $this->dataCache[$region['id']] = $this->dataUrlRead($region['url']);
                }
            }
            $type = 'json';
            if (isset($region['type'])) {
                $type = $region['type'];
            }
            if (in_array($type, ['json', 'Collection', 'Document', 'Form'])) {
                if (!in_array(substr($this->dataCache[$region['id']], 0, 1), ['{', ']'])) {
                    $this->dataCache[$region['id']] = substr($this->dataCache[$region['id']], (strpos($this->dataCache[$region['id']], '(') + 1), -1);
                }
                $this->dataCache[$region['id']] = json_decode($this->dataCache[$region['id']], true);
            }
            if ($template === false) {
                $context[$region['id']] = $this->dataCache[$region['id']];
            } else {
                if (is_callable($template)) {
                    $context[$region['id']] = $template($this->dataCache[$region['id']]);
                } else {
                    $template = $this->engine->prepare($this->engine->compile($template));
                    $context[$region['id']] = $template($this->dataCache[$region['id']]);
                }
            }
        }
        if ($this->debug) {
            echo 'Context:', "\n";
            var_dump($context);
            echo 'Regions:', "\n";
            var_dump($this->regions);
        }
        if (is_callable($this->layoutFile)) {
            $function = $this->layoutFile;
            return $function($context);
        }
        $function = $this->engine->prepare($this->engine->compile($this->layoutFile));
        return $function($context);
    }

    private function dataUrlRead ($dataUrl) {
        if (strtolower(substr($dataUrl, 0, 4)) != 'http') {
            $data = $this->route->run('GET', $dataUrl);
            if ($data === false) {
                throw new Exception('Layout data url failed: ' . $dataUrl);
            }
            return $data;
        }
        return trim(file_get_contents($dataUrl));
    }

    public function write($layout=false) {
        $this->render($layout, 'echo');
    }

    public function render ($layout=false, $mode='return') {
        if ($this->appCalled === false) {
            throw new Exception('Layout can not render: must call app first');
        }
        if ($layout !== false) {
            $this->layout($layout);
        }
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
        if ($fullCache === false || $ttl === -1) {
            $this->output = $this->renderRegions();
        } else {
            $key = $this->root . '-layout-' . md5($this->appFile . '-' . $this->layoutFileName . '-' . $key);
            $this->output = $this->cache->getSetGet($key, function () {
                return $this->renderRegions();
            }, $ttl);
        }
        if ($mode == 'return') {
            return $this->output;
        }
        echo $this->output;
    }

    public function compiledAsset ($path) {
        $path = str_replace('/public/', '/cache/', $path);
        if (file_exists($path)) {
            $function = require $path;
            return $function;
        }
        return false;
    }
}