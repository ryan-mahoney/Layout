<?php
namespace Separation;
use Handlebars\Handlebars;
use Cache\Cache;

class Separation {
	private $htmlFile;
	private $html;
	private $configFile;
	private $entities = [];
	private $entitiesHash = [];
	private $handlebars;
	private static $config = [];
	private static $dataCache = [];

	public static function config ($config) {
		self::$config = $config;
	}

	public function __construct($path) {
		$this->htmlFile = self::$config['layouts'] . $path . '.html';
		if (!file_exists($this->htmlFile)) {
			throw new \Exception('Can not load html file: ' . $this->htmlFile);
		}
		$this->html = file_get_contents($this->htmlFile);
		$this->configFile = self::$config['app'] . $path . '.json';
		if (!file_exists($this->configFile)) {
			return;
		}
		$this->entities = json_decode(file_get_contents($this->configFile), true);
		foreach ($this->entities as $offset => $entity) {
			$this->entities[$offset] = new \ArrayObject($entity);
			$this->entitiesHash[$entity['id']] = $this->entities[$offset];
		}
		$this->handlebars = Handlebars::factory();
	}

	public static function layout ($path) {
		return new Separation($path);
	}

	public function set ($data) {
		foreach ($data as $partial) {
			$entity = $this->entitiesHash[$partial['id']];
			if (isset($partial['args'])) {
				if (isset($entity['args'])) {
					$entity['args'] = array_merge($entity['args'], $partial['args']);
				} else {
					$entity['args'] = $partial['args'];
				}
			}			
		}
		return $this;
	}

	private static function collectionUrl (&$entity) {
		$url = $entity['url'];
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
        	if (isset($entity['args'][$key])) {
            	$url .= $entity['args'][$key];
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

	private static function documentUrl (&$entity) {
		$url = $entity['url'];
		if (isset($entity['args']['slug'])) {
			return str_replace(':slug', $entity['args']['slug'], $url);
		}
		if (isset($entity['args']['id'])) {
			return str_replace('/bySlug/:slug', '/byId/' . $entity['args']['id'], $url);
		}
	}

	public function template () {
		$context = [];
		foreach ($this->entities as $entity) {
			if (!isset($entity['hbs']) || empty($entity['hbs'])) {
				$template = false;
			} elseif (substr($entity['hbs'], -4) == '.hbs') {
				$template = file_get_contents(self::$config['partials'] . $entity['hbs']);
			} else {
				$template = $entity['hbs'];
			}
			$dataUrl = $entity['url'];
			if (isset($entity['args']) && is_array($entity['args']) && count($entity['args']) > 0) {
				$delimiter = '?';
				if (substr_count($dataUrl, '?') > 0) {
					$delimiter = '&';
				}
				$dataUrl .= $delimiter . http_build_query($entity['args']);
			}
			if (isset($entity['type'])) {
				if ($entity['type'] == 'Collection') {
					$dataUrl = self::collectionUrl($entity);
				} elseif ($entity['type'] == 'Document') {
					$dataUrl = self::documentUrl($entity);
				}
			}
			if (substr($entity['url'], 0, 1) == '@') {
				$data = self::$dataCache[substr($entity['url'], 1)];
			} else {
				if (!isset(self::$dataCache[$entity['id']])) {
					if (isset($entity['cache'])) {
						$data = Cache::getSetGet('sep-data-' . $dataUrl, function () use ($dataUrl) {
							return trim(file_get_contents($dataUrl));
						}, $entity['cache']);
					} else {
						$data = trim(file_get_contents($dataUrl));
					}
					self::$dataCache[$entity['id']] = $data;
				}
			}
			$type = 'json';
			if (isset($entity['type'])) {
				$type = $entity['type'];
			}
			if (in_array($type, ['json', 'Collection', 'Document'])) {
				if (!in_array(substr($data, 0, 1), ['{', ']'])) {
					$data = substr($data, (strpos($data, '(') + 1), -1);
				}
				$data = json_decode($data, true);
			}
			if ($template === false) {
				$context[$entity['id']] = $data;
			} else {
				$context[$entity['id']] = $this->handlebars->render($template, $data);
			}
		}
		$this->html = $this->handlebars->render($this->html, $context);
		return $this;
	}

	public function write() {
		echo $this->html;
	}
}