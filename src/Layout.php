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
use Symfony\Component\Yaml\Yaml;

class Layout {
    private $layout;
    private $config;
    private $regions = [];
    private $regionsHash = [];
    private $engine;
    private $root;
    private $cache;
    private $dataCache = [];
    private $appCalled = false;
    private $route = false;
    private $debug = true;

    public function __construct($root, $engine, $cache, $config, $route, $appPath=false) {
        $this->root = $root;
        $this->engine = $engine;
        $this->cache = $cache;
        $this->route = $route;
        $this->config = $config;
        $this->route = $route;
        if ($appPath != false) {
            $this->appConfig($appPath);
            $this->appCalled = true;
        }
    }

    public function showBindings () {
        return print_r($this->regions, true);
    }

    public function app ($path) {
        if (substr($path, 0, 1) == '/') {
            $appPath = $path . '.yml';
        } else {
            if (substr($path, 0, 4) == 'app/') {
                $appPath = $this->root . '/../' . $path . '.yml';
            } else {
                $appPath = $this->root . '/../app/' . $path . '.yml';
            }
        }
        $appPath = str_replace('.yml.yml', '.yml', $appPath);
        return new Layout($this->root, $this->engine, $this->cache, $this->config, $this->route, $appPath);
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
            print_r($this->regions);
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
        $this->regions[$offset] = new \ArrayObject($region);
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
        $url = $this->regionsHash[$id]['url'];
        $this->dataCache[$url] = $data;
        $this->regionsHash[$id]['type'] = $type;
        return $this;
    }

    private static function documentUrl (&$region, $url) {
        if (isset($region['args']['slug'])) {
            return str_replace(':slug', $region['args']['slug'], $url);
        }
        if (isset($region['args']['id'])) {
            return str_replace('/bySlug/:slug', '/byId/' . $region['args']['id'], $url);
        }
    }

    public function template ($layout=false) {
        if ($this->appCalled === false) {
            throw new Exception('must call app first');
        }
        if ($layout !== false) {
            $this->layout($layout);
        }
        $context = [];
        foreach ($this->regions as $region) {
            $local = false;
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
            $dataUrl = '';
            if (isset($region['url'])) {
                $dataUrl = $region['url'];
            }
            if (strtolower(substr($dataUrl, 0, 4)) != 'http') {
                $local = true;
            }
            if (isset($region['args']) && is_array($region['args']) && count($region['args']) > 0) {
                $delimiter = '?';
                if (substr_count($dataUrl, '?') > 0) {
                    $delimiter = '&';
                }
                $dataUrl .= $delimiter . urldecode(http_build_query($region['args']));
            }
            if (isset($region['type'])) {
                if ($region['type'] == 'Document') {
                    $dataUrl = self::documentUrl($region, $dataUrl);
                } elseif ($region['type'] == 'Post') {
                    $this->dataCache[$region['id']] = (isset($_POST) ? $_POST : []);
                } elseif ($region['type'] == 'Get') {
                    $this->dataCache[$region['id']] = (isset($_GET) ? $_GET : []);
                }
            }
            if (substr($dataUrl, 0, 1) == '@') {
                $data = $this->dataCache[substr($dataUrl, 1)];
            } else {
                if (!isset($this->dataCache[$region['id']])) {
                    //if (isset($region['cache'])) {
                    //    $data = $this->cache->getSetGet('sep-data-' . $dataUrl, function () use ($dataUrl) {
                    //        return trim(file_get_contents($dataUrl));
                    //    }, $region['cache']);
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
                    $this->dataCache[$region['id']] = $data;
                } else {
                    $data = $this->dataCache[$region['id']];
                }
            }
            $type = 'json';
            if (isset($region['type'])) {
                $type = $region['type'];
            }
            if (in_array($type, ['json', 'Collection', 'Document', 'Form'])) {
                if (!in_array(substr($data, 0, 1), ['{', ']'])) {
                    $data = substr($data, (strpos($data, '(') + 1), -1);
                }
                $data = json_decode($data, true);
            }
            if ($template === false) {
                $context[$region['id']] = $data;
            } else {
                if (is_callable($template)) {
                    $context[$region['id']] = $template($data);
                } else {
                    $template = $this->engine->prepare($this->engine->compile($template));
                    $context[$region['id']] = '<!-- not cached: ' . $region['id'] . ' -->';
                    $context[$region['id']] .= $template($data);
                }
            }
        }
        if (is_callable($this->layoutFile)) {
            $function = $this->layoutFile;
            $this->layoutFile = $function($context);
        } else {
            $function = $this->engine->prepare($this->engine->compile($this->layoutFile));
            $this->layoutFile = $function($context);
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
        $path = str_replace('/public/', '/cache/', $path);
        if (file_exists($path)) {
            $function = require $path;
            return $function;
        }
        return false;
    }
}