<?php
namespace Opine\Layout;

class Model {
	private $root;
	private $layout;
	private $bundleRoot;
	private $cache;

	public function __construct ($root, $layout, $bundleRoot, $cache) {
		$this->root = $root;
		$this->layout = $layout;
		$this->bundleRoot = $bundleRoot;
		$this->cache = $cache;
	}

	public function build () {
		$apps = [];
		$apps[] = $this->root . '/../app';
		$bundles = $this->bundleRoot->bundles();
		foreach ($bundles as $bundles) {
			$apps[] = $this->root . '/../bundles/' . $bundle['name'] . '/app';
		}
	}

	private function directoryScan ($path) {
		//find all .yml files under directory
		//convery YAML to JSON
		//store in cache
	}
}