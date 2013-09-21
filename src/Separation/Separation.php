<?php
namespace Separation;

class Separation {
	private $htmlFile;
	private $html;
	private $configFile;
	private $entities = [];
	private $entitiesHash = [];
	private $handlebars;
	private static $config = [];

	public static function config ($config) {
		self::$config = $config;
	}

	public function __construct($path) {
		$this->htmlFile = self::$config['layouts'] . $path . '.html';
		if (!file_exists($this->htmlFile)) {
			throw new \Exception('Can not load html file: ' . $this->htmlFile);
		}
		$this->html = file_get_contents($this->htmlFile);
		$this->configFile = self::$config['sep'] . $path . '.js';
		if (!file_exists($this->configFile)) {
			return;
		}
		$this->entities = file_get_contents($this->configFile);
		$this->entities = explode('$().separation(', $this->entities, 2)[1];
		$this->entities = substr($this->entities, 0, (strrpos($this->entities, ']') + 1));
		$this->entities = json_decode($this->entities, true);
		foreach ($this->entities as $offset => $entity) {
			$this->entities[$offset] = new \ArrayObject($entity);
			$this->entitiesHash[$entity['id']] = $this->entities[$offset];
		}
		$this->handlebars = new \Handlebars_Engine();
		$this->handlebars->addHelper('paginate', 
			function ($template, $context, $args, $source) {
				$pagination = $context->get('pagination');
				$options = [];
				foreach (explode(' ', $args) as $arg) {
					if (substr_count($arg, '=') == 0) {
						continue;
					}
					$parts = explode('=', $arg);
					$options[$parts[0]] = trim($parts[1], '"');				
				}
				//print_r($pagination);
				//print_r($options);


				$type = 'middle';
				if (isset($options['type'])) {
					$type = $options['type'];
				}
		      	$ret = '';
		    	$pageCount = $pagination['pageCount'];
		    	$page = intval($pagination['page']);
		      	$limit;
		      	if (isset($options['limit'])) {
		      		$limit = $options['limit'];
		      	}

		      //page pageCount
		      $newContext = [];
		      switch ($type) {
		        case 'middle':
		        	if (is_numeric($limit)) {
			            $i = 0;
			            $leftCount = ceil($limit / 2) - 1;
			            $rightCount = $limit - $leftCount - 1;
			            if ($page + $rightCount > $pageCount) {
			            	$leftCount = $limit - ($pageCount - $page) - 1;
			            }
			            if ($page - $leftCount < 1) {
			            	$leftCount = $page - 1;
			            	$start = $page - $leftCount;
					        while ($i < $limit && $i < $pageCount) {
						        $newContext = ['n' => $start];
						        if ($start === $page) {
						        	$newContext['active'] = true;
						        	$ret .= $template->render($newContext);
						        }
						        $start++;
						        $i++;
					        }
					    }
		            } else {
		            	for ($i = 1; $i <= $pageCount; $i++) {
		              		$newContext['n'] = $i;
		              		if ($i === $page) {
		              			$newContext['active'] = true;
		              		}
		              		$ret .= $template->render($newContext);
		            	}
		          	}
		          	break;
		        
		        case 'previous':
		         	if ($page === 1) {
		            	$newContext = ['disabled' => true, 'n' => 1 ];
		          	} else {
		            	$newContext = ['n' =>  $page - 1];
		          	}
		          	$ret .= $template->render($newContext);
		          	break;
		        
		        case 'next':
		        	$newContext = [];
		        	if ($page === $pageCount) {
		            	$newContext = ['disabled' => true, 'n' => $pageCount];
		          	} else {
		            	$newContext = ['n' =>  $page + 1];
		          	}
		          	$ret .= $template->render($newContext);
		          	break;
		      	}

		    	return $ret;
			}
		);
	}

	public static function layout ($path) {
		return new Separation($path);
	}

	public function set ($data) {
		foreach ($data as $partial) {
			$entity = $this->entitiesHash[$partial['Sep']];
			if (isset($partial['a'])) {
				$entity['args'] = array_merge($entity['args'], $partial['a']);
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
        foreach (['domain', 'path', 'collection', 'method', 'limit', 'skip', 'sort'] as $offset => $key) {
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
		foreach ($this->entities as $entity) {
			$template = file_get_contents(self::$config['partials'] . $entity['hbs']);
			$dataUrl = $entity['url'] . '?' . http_build_query($entity['args']);
			if ($entity['type'] == 'Collection') {
				$dataUrl = self::collectionUrl($entity);
			} elseif ($entity['type'] == 'Document') {
				$dataUrl = self::documentUrl($entity);
			}
			$data = trim(file_get_contents($dataUrl));
			if (!in_array(substr($data, 0, 1), ['{', ']'])) {
				$data = substr($data, (strpos($data, '(') + 1), -1);
			}
			$data = str_replace("\\'", "'", $data);
			$data = json_decode($data, true);
			$this->html = str_replace('{{{' . $entity['target'] . '}}}', $this->handlebars->render($template, $data), $this->html);
			//serverize scripts, css and images
			$this->html = str_replace(
				[
					'<link href="../css/', 
					'<script src="../sep/', 
					'<script src="../js/', 
					'<img src="../images/', 
					'require.js" data-main="../sep/',
					'<link href="../bootstrap/',
					'<script src="../bootstrap/'
				], [
					'<link href="/css/', 
					'<script src="/sep/', 
					'<script src="/js/', 
					'<img src="/images/"', 
					'require.js" data-main="/sep/',
					'<link href="/bootstrap/',
					'<script src="/bootstrap/'
				], $this->html);
		}
		return $this;
	}

	public function write() {
		echo $this->html;
	}
}