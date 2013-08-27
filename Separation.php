<?php
require __DIR__ . '/Handlebars/Autoloader.php';
Handlebars_Autoloader::register();

class Separation {
	private $htmlFile;
	private $html;
	private $configFile;
	private $config;
	private $handlebars;

	public function __construct($path) {
		if (!file_exists($path)) {
			throw new Exception('Can not load html file: ' . $path);
		}
		$this->htmlFile = $path;
		$this->html = file_get_contents($this->htmlFile);
		$this->configFile = str_replace('.html', '-sep.js', $path);
		if (!file_exists($this->configFile)) {
			throw new Exception('Can not load config file: ' . $this->configFile);
		}
		$this->config = file_get_contents($this->configFile);
		$this->config = explode('$().separation(', $this->config, 2)[1];
		$this->config = substr($this->config, 0, (strrpos($this->config, ']') + 1));
		$this->config = json_decode($this->config, true);
		$this->handlebars = new Handlebars_Engine;
	}

	public function dump () {
		echo 
			$this->htmlFile, "\n", 
			$this->configFile, "\n\n";
		print_r ($this->config);	
	}

	public function render () {
		foreach ($this->config as $partial) {
			$template = file_get_contents($partial['template']);
			$dataUrl = str_replace('?jsoncallback=?', '', $partial['jsonUrl']) . '?' . http_build_query($partial['jsonArgs']);
			$data = trim(file_get_contents($dataUrl));
			if (!in_array(substr($data, 0, 1), ['{', ']'])) {
				$data = substr($data, (strpos($data, '(') + 1), -1);
			}
			echo $data;
			return;
			$data = json_decode($data, true);
			print_r($data);
			return;
			echo $this->handlebars->render($template, $data);
		}
	}
}

$separation = new Separation('example.html');
//$separation->dump();
$separation->render();