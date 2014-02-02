<?php
/**
 * Opine\Separation
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

class Separation {
    private $htmlFile;
    private $html;
    private $configFile;
    private $bindings = [];
    private $bindingsHash = [];
    private $engine;
    private $root;
    private $cache;
    private $dataCache = [];
    private $app = false;
    private $dataAPI = false;
    private $yamlSlow;
    private $slim = false;

    public function __construct($root, $engine, $cache, $config, $yamlSlow, $slim=false) {
        $this->root = $root;
        $this->engine = $engine;
        $this->cache = $cache;
        $this->yamlSlow = $yamlSlow;
        if (isset($config->db['dataAPI'])) {
            $this->dataAPI = $config->db['dataAPI'];
            if ($this->dataAPI == '%HTTP_HOST%' && isset($_SERVER['HTTP_HOST'])) {
                $this->baseURL = 'http://' . $_SERVER['HTTP_HOST'];
                $this->dataAPI = $this->baseURL;
            }
        }
        $this->slim = $slim;
    }

    public function showBindings () {
        return print_r($this->bindings, true);
    }

    public function app ($app) {
        $this->app = $this->root . '/../' . $app;
        return $this;
    }

    public function layout ($path) {
        $this->htmlFile = $this->root . '/layouts/' . $path . '.html';
        if (!file_exists($this->htmlFile)) {
            throw new \Exception('Can not load html file: ' . $this->htmlFile);
        }
        $this->html = file_get_contents($this->htmlFile);
        $this->configFile = (($this->app !== false) ? $this->app : ($this->root . '/../app/' . $path)) . '.yml';
        if (!file_exists($this->configFile)) {
            return $this;
        }
        $this->appConfig($this->configFile);
        return $this;
    }

    private function appConfig ($configFile) {
        if (function_exists('yaml_parse_file')) {
            $separation = yaml_parse_file($configFile);
        } else {
            $separation = $this->yamlSlow->parse($configFile);
        }
        if ($separation == false) {
            throw new \Exception('Can not parse YAML file: ' . $configFile);
        }
        if (isset($separation['imports']) && is_array($separation['imports']) && !empty($separation['imports'])) {
            foreach ($separation['imports'] as $import) {
                $first = substr($import, 0, 1);
                if ($first != '/') {
                    $import = $this->root . '/../app/' . $import;
                }
                $this->appConfig($import);
            }
        }
        $offset = count($this->bindings);
        if (!isset($separation['binding']) || !is_array($separation['binding']) || empty($separation['binding'])) {
            return;
        }
        foreach ($separation['binding'] as $id => $binding) {
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

    private static function collectionUrl (&$binding, $url) {
        $protocol = explode('://', $url)[0];
        if (empty($protocol)) {
            $protocol = 'http';
        }
        $qs = '';
        if (substr_count($url, '?') > 0) {
            $tmp = explode('?', $url);
            $url = $tmp[0];
               $count = count($tmp);
               if ($count > 1) {
                for ($i=1; $i < $count; $i++) {
                    $qs .= '?' . $tmp[$i];
                }
            }
        }
        $pieces = explode('/', preg_replace('/.*?:\/\//', '', $url));
        $url = '';
        foreach (['domain', 'path', 'collection', 'method', 'limit', 'page', 'sort'] as $offset => $key) {
            if (isset($binding['args']) && isset($binding['args'][$key])) {
                $url .= $binding['args'][$key];
            } else if (isset($pieces[$offset])) {
                $url .= $pieces[$offset];
            } else {
                break;
            }
            $url .= '/';
        }
        $url = $protocol . '://' . $url;
        return substr($url, 0, -1) . $qs;
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
        $context = [];
        foreach ($this->bindings as $binding) {
            $local = false;
            if (!isset($binding['partial']) || empty($binding['partial'])) {
                $template = false;
            } elseif (substr($binding['partial'], -4) == '.hbs') {
                $template = file_get_contents($this->root . '/partials/' . $binding['partial']);
            } else {
                $template = $binding['partial'];
            }
            $dataUrl = '';
            if (isset($binding['url'])) {
                $dataUrl = $binding['url'];
            }
            if ($this->dataAPI !== false && !empty($dataUrl)) {
                if (substr_count($dataUrl, '%dataAPI%') > 0) {
                    if ($this->dataAPI == $this->baseURL) {
                        $local = true;
                    }
                    $dataUrl = str_replace('%dataAPI%', $this->dataAPI, $dataUrl);
                }
            }
            if (isset($binding['args']) && is_array($binding['args']) && count($binding['args']) > 0) {
                $delimiter = '?';
                if (substr_count($dataUrl, '?') > 0) {
                    $delimiter = '&';
                }
                $dataUrl .= $delimiter . http_build_query($binding['args']);
            }
            if (isset($binding['type'])) {
                if ($binding['type'] == 'Collection') {
                    $dataUrl = self::collectionUrl($binding, $dataUrl);
                } elseif ($binding['type'] == 'Document') {
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
                    if (isset($binding['cache'])) {
                        $data = $this->cache->getSetGet('sep-data-' . $dataUrl, function () use ($dataUrl) {
                            return trim(file_get_contents($dataUrl));
                        }, $binding['cache']);
                    } else {
//                        if ($local == true && $this->slim !== false) {
//                            ob_start();
//                            $this->slimDirect($dataUrl);
//                            $data = ob_get_clean();
//                        } else {
                            $data = trim(file_get_contents($dataUrl));
//                        }
                    }
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
                $context[$binding['id']] = $this->engine->render($template, $data);
            }
        }
        $this->html = $this->engine->render($this->html, $context);
        return $this;
    }

    public function slimDirect ($url) {}

    public function write(&$reference=false) {
        if ($reference === false) {
            echo $this->html;
        } else {
            $reference = $this->html;
        }
    }
}