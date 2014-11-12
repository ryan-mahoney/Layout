<?php
namespace Opine\Layout;

class Model {
    private $root;
    private $layout;
    private $cache;

    public function __construct ($root, $layout, $cache) {
        $this->root = $root;
        $this->layout = $layout;
        $this->cache = $cache;
    }

    public function build () {
        $apps = $this->folderRead($this->root . '/../app');
    }

    private function folderRead($folder) {
        $dir = new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($dir);
        $fileList = [];
        foreach ($files as $file) {
            $fileList[] = $file->getPathname();
        }
        return $fileList;
    }
}