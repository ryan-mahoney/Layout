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

class Layout {
    private $layout;
    private $config;
    private $bindings = [];
    private $bindingsHash = [];
    private $engine;
    private $root;
    private $cache;
    private $dataCache = [];
    private $appCalled = false;
    private $yamlSlow;
    private $route = false;
    private $debug = true;

    public function __construct($root, $engine, $cache, $config, $yamlSlow, $route, $appPath=false) {
        $this->root = $root;
        $this->engine = $engine;
        $this->cache = $cache;
        $this->yamlSlow = $yamlSlow;
        $this->route = $route;
        $this->config = $config;
        $this->route = $route;
        if ($appPath != false) {
            $this->appConfig($appPath);
            $this->appCalled = true;
        }
    }

    public function showBindings () {
        return print_r($this->bindings, true);
    }

    public function app ($path) {
        if (substr($path, 0, 1) == '/') {
            $appPath = $path . '.yml';
        } else {
            $appPath = $this->root . '/../app/' . $path . '.yml';
        }
        $appPath = str_replace('.yml.yml', '.yml', $appPath);
        return new Layout($this->root, $this->engine, $this->cache, $this->config, $this->yamlSlow, $this->route, $appPath);
    }

    public function debug () {
        $this->debug = true;
        return $this;
    }

    public function layout ($path) {
        if (substr($path, 0, 1) == '/') {
            $layoutPath = $path . '.html';
        } else {
            $layoutPath = $this->root . '/layouts/' . $path . '.html';
        }
        $layoutPath = str_replace('.html.html', '.html', $layoutPath);
        if (!file_exists($layoutPath)) {
            print_r($this->bindings);
            throw new Exception('Can not load html file: ' . $layoutPath);
        }
        $this->layoutFile = $this->compiledAsset($layoutPath);
        if ($this->layoutFile === false) {
            $this->layoutFile = file_get_contents($layoutPath);
        }
        return $this;
    }

    private function appConfig ($configFile) {
        if (!file_exists($configFile)) {
            throw new Exception('Can not load app config: ' . $configFile);
        }
        if (function_exists('yaml_parse_file')) {
            $layout = yaml_parse_file($configFile);
        } else {
            $layout = $this->yamlSlow->parse($configFile);
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
        if (!isset($layout['binding']) || !is_array($layout['binding']) || empty($layout['binding'])) {
            return;
        }
        foreach ($layout['binding'] as $id => $binding) {
            $this->bindingAdd($id, $binding);
        }
    }

    public function bindingAdd ($id, $binding, $data=false) {
        $offset = count($this->bindings);
        $this->bindings[$offset] = new \ArrayObject($binding);
        $this->bindings[$offset]['id'] = $id;
        $this->bindingsHash[$id] = $this->bindings[$offset];
        if ($data !== false) {
            $this->dataCache[$id] = $data;
        }
    }

    public function url ($id, $url) {
        $this->bindingsHash[$id]['url'] = $url;
        return $this;
    }

    public function args ($id, $args) {
        $this->bindingsHash[$id]['args'] = $args;
        return $this;
    }

    public function partial ($id, $partial) {
        $this->bindingsHash[$id]['partial'] = $partial;
        return $this;
    }

    public function data ($id, $data, $type='array') {
        $url = $this->bindingsHash[$id]['url'];
        $this->dataCache[$url] = $data;
        $this->bindingsHash[$id]['type'] = $type;
        return $this;
    }

    private static function documentUrl (&$binding, $url) {
        if (isset($binding['args']['slug'])) {
            return str_replace(':slug', $binding['args']['slug'], $url);
        }
        if (isset($binding['args']['id'])) {
            return str_replace('/bySlug/:slug', '/byId/' . $binding['args']['id'], $url);
        }
    }

    public function template () {
        if ($this->appCalled === false) {
            throw new Exception('must call app first');
        }
        $context = [];
        foreach ($this->bindings as $binding) {
            $local = false;
            if (!isset($binding['partial']) || empty($binding['partial'])) {
                $template = false;
            } elseif (substr($binding['partial'], -4) == '.hbs') {
                $partialPath = $this->root . '/partials/' . $binding['partial'];
                $template = $this->compiledAsset($partialPath);
                if ($template === false) {
                    $template = file_get_contents($partialPath);
                }
            } else {
                $template = $binding['partial'];
            }
            $dataUrl = '';
            if (isset($binding['url'])) {
                $dataUrl = $binding['url'];
            }
            if (strtolower(substr($dataUrl, 0, 4)) != 'http') {
                $local = true;
            }
            if (isset($binding['args']) && is_array($binding['args']) && count($binding['args']) > 0) {
                $delimiter = '?';
                if (substr_count($dataUrl, '?') > 0) {
                    $delimiter = '&';
                }
                $dataUrl .= $delimiter . urldecode(http_build_query($binding['args']));
            }
            if (isset($binding['type'])) {
                if ($binding['type'] == 'Document') {
                    $dataUrl = self::documentUrl($binding, $dataUrl);
                } elseif ($binding['type'] == 'Post') {
                    $this->dataCache[$binding['id']] = (isset($_POST) ? $_POST : []);
                } elseif ($binding['type'] == 'Get') {
                    $this->dataCache[$binding['id']] = (isset($_GET) ? $_GET : []);
                }
            }
            if (substr($dataUrl, 0, 1) == '@') {
                $data = $this->dataCache[substr($dataUrl, 1)];
            } else {
                if (!isset($this->dataCache[$binding['id']])) {
                    //if (isset($binding['cache'])) {
                    //    $data = $this->cache->getSetGet('sep-data-' . $dataUrl, function () use ($dataUrl) {
                    //        return trim(file_get_contents($dataUrl));
                    //    }, $binding['cache']);
                    //} else {
                        if ($local == true) {
                            //$dataUrl = urldecode($dataUrl);
                            //echo $dataUrl, '<br>';
                            //continue;
                            $data = $this->route->run('GET', $dataUrl);
                            if ($data === false) {
                                throw new Exception('sub-route failed: ' . $dataUrl);
                            }
                        } else {
                            $data = trim(file_get_contents($dataUrl));
                        }
                    //}
                    $this->dataCache[$binding['id']] = $data;
                } else {
                    $data = $this->dataCache[$binding['id']];
                }
            }
            $type = 'json';
            if (isset($binding['type'])) {
                $type = $binding['type'];
            }
            if (in_array($type, ['json', 'Collection', 'Document', 'Form'])) {
                if (!in_array(substr($data, 0, 1), ['{', ']'])) {
                    $data = substr($data, (strpos($data, '(') + 1), -1);
                }
                $data = json_decode($data, true);
            }
            if ($template === false) {
                $context[$binding['id']] = $data;
            } else {
                if (is_callable($template)) {
                    $context[$binding['id']] = $template($data);
                } else {
                    $context[$binding['id']] = $this->engine->render($template, $data);
                }
            }
        }
        if (is_callable($this->layoutFile)) {
            $function = $this->layoutFile;
            $this->layoutFile = $function($context);
        } else {
            $this->layoutFile = $this->engine->render($this->layoutFile, $context);
        }
        return $this;
    }

    public function write(&$reference=false) {
        if ($reference === false) {
            echo $this->layoutFile;
        } else {
            $reference = $this->layoutFile;
        }
    }

    public function compiledAsset ($path) {
        $path = rtrim(rtrim($path, 'html'), 'hbs') . 'php';
        if (file_exists($path)) {
            $function = require $path;
            return $function;
        }
        return false;
    }
}