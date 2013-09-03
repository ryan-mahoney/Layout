<?php
require __DIR__ . '/Handlebars/Autoloader.php';
Handlebars_Autoloader::register();

class Separation {
	private $htmlFile;
	private $html;
	private $configFile;
	private $entities = [];
	private $handlebars;
	private static $config = [];

	public static function config ($config) {
		self::$config = $config;
	}

	public function __construct($path) {
		$path = self::$config['layouts'] . $path;
		if (!file_exists($path)) {
			throw new Exception('Can not load html file: ' . $path);
		}
		$this->htmlFile = $path;
		$this->html = file_get_contents($path);
		$this->configFile = str_replace('.html', '-sep.js', $path);
		if (!file_exists($this->configFile)) {
			return;
		}
		$this->entities = file_get_contents($this->configFile);
		$this->entities = explode('$().separation(', $this->entities, 2)[1];
		$this->entities = substr($this->entities, 0, (strrpos($this->entities, ']') + 1));
		$this->entities = json_decode($this->entities, true);
		$this->handlebars = new Handlebars_Engine();
	}

	public static function layout ($path) {
		return new Separation($path);
	}

	public function template () {
		foreach ($this->entities as $entity) {
			$hbs = $entity['hbs'];
			if (substr_count($hbs, '/') > 0) {
				$hbs = explode('/', $hbs);
				$hbs = array_pop($hbs);
			}
			$template = file_get_contents(self::$config['templates'] . $hbs);
			$dataUrl = $entity['url'] . '?' . http_build_query($entity['args']);
			$data = trim(file_get_contents($dataUrl));
			if (!in_array(substr($data, 0, 1), ['{', ']'])) {
				$data = substr($data, (strpos($data, '(') + 1), -1);
			}
			$data = str_replace("\\'", "'", $data);
			$data = json_decode($data, true);
			$this->html = str_replace('<script type="text/x-separation" selector="' . $entity['selector'] . '"></script>', $this->handlebars->render($template, $data), $this->html);
		}
		return $this;
	}

	public function write() {
		echo $this->html;
	}
}