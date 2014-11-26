<?php
namespace Opine;

use PHPUnit_Framework_TestCase;
use Opine\Container\Service as Container;
use Opine\Config\Service as Config;

class LayoutTest extends PHPUnit_Framework_TestCase {
    private $layout;
    private $route;

    public function setup () {
        $root = __DIR__ . '/../public';
        $config = new Config($root);
        $config->cacheSet();
        $container = Container::instance($root, $config, $root . '/../config/container.yml');
        $this->layout = $container->get('layout');
        $this->route = $container->get('route');
    }

    private function initializeRoutes () {
        $this->route->get('/test', 'controller@test');
    }

    public function testObjectType () {
        $this->initializeRoutes();
        $this->assertTrue('Opine\Layout\Service' === get_class($this->layout));
    }
/*
    public function testMakeOneArg () {
        $this->assertTrue('<html><body><ul><li>A</li><li>B</li><li>C</li></ul></body></html>' === str_replace([' ', "\n"], '', $this->layout->make('test')));
    }

    public function testMakeTwoArgs () {
        $this->assertTrue('<html><body>ABC<ul><li>A</li><li>B</li><li>C</li></ul></body></html>' === str_replace([' ', "\n"], '', $this->layout->make('test', 'test2')));
    }

    public function testMakeThreeArgs () {
        $context = ['second' => '<ul><li>Q</li><li>R</li><li>S</li></ul>'];
        $this->assertTrue('<html><body>ABC<ul><li>A</li><li>B</li><li>C</li></ul><ul><li>Q</li><li>R</li><li>S</li></ul></body></html>' === str_replace([' ', "\n"], '', $this->layout->make('test', 'test2', $context)));
    }

    public function testConfig () {
        $this->assertTrue('<html><body><ul><li>A</li><li>B</li><li>C</li></ul></body></html>' === str_replace([' ', "\n"], '', $this->layout->config('test')->render()));
    }

    public function testConfigContainer () {
        $this->assertTrue('<html><body>ABC<ul><li>A</li><li>B</li><li>C</li></ul></body></html>' === str_replace([' ', "\n"], '', $this->layout->config('test')->container('test2')->render()));
    }

    public function testContainer () {
        $this->assertTrue('<html><body></body></html>' === str_replace([' ', "\n"], '', $this->layout->container('test')->render()));
    }

    public function testContainerContext () {
        $context = ['second' => 'ABC'];
        $this->assertTrue('<html><body>ABC</body></html>' === str_replace([' ', "\n"], '', $this->layout->container('test', $context)->render()));
    }
*/
    public function testContainerRegions () {
        $region = [
            'partial' => 'test.hbs',
            'data' => ['data' => ['value' => 'Q'], ['value' => 'R'], ['value' => 'S']]];

        var_dump($this->layout->container('test', [], true)->
                region('first', $region)->
                render());

        exit;
        $this->assertTrue(
            '<html><body></body></html>' === str_replace([' ', "\n"], '',
            $this->layout->container('test', $context)->
                region('first', $region)->
                render()
        ));
    }

    public function testContainerRegionsContext () {

    }
}