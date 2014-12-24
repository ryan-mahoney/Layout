<?php
namespace Opine;

use PHPUnit_Framework_TestCase;
use Opine\Container\Service as Container;
use Opine\Config\Service as Config;
use Exception;

class LayoutTest extends PHPUnit_Framework_TestCase {
    private $layout;
    private $route;

    public function setup () {
        $root = __DIR__ . '/../public';
        $config = new Config($root);
        $config->cacheSet();
        $container = Container::instance($root, $config, $root . '/../config/containers/test-container.yml');
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

    public function testContainerContextEcho () {
        $context = ['second' => 'ABC'];
        ob_start();
        $this->layout->container('test', $context)->write();
        $buffer = ob_get_clean();
        $this->assertTrue('<html><body>ABC</body></html>' === str_replace([' ', "\n"], '', $buffer));
    }

    public function testContainerRegions () {
        $region = [
            'partial' => 'test.hbs',
            'data' => ['data' => [['value' => 'Q'], ['value' => 'R'], ['value' => 'S']]]];

        $this->assertTrue(
            '<html><body><ul><li>Q</li><li>R</li><li>S</li></ul></body></html>' === str_replace([' ', "\n"], '',
            $this->layout->container('test')->
                region('first', $region)->
                render()
        ));
    }

    public function testContainerRegionsContext () {
        $region = [
            'partial' => 'test.hbs',
            'data' => ['data' => [['value' => 'Q'], ['value' => 'R'], ['value' => 'S']]]];

        $region2 = [
            'partial' => 'test.hbs'
        ];

        $context = [
            'third' => [
                'data' => [['value' => 'X'], ['value' => 'Y'], ['value' => 'Z']]
            ]
        ];

        $this->assertTrue(
            '<html><body><ul><li>Q</li><li>R</li><li>S</li></ul><ul><li>X</li><li>Y</li><li>Z</li></ul></body></html>' === str_replace([' ', "\n"], '',
            $this->layout->container('test', $context)->
                region('first', $region)->
                region('third', $region2)->
                render()
        ));
    }

    public function testBadConfig () {
        $caught = false;
        try {
            $this->layout->config('x');
        } catch (Exception $e) {
            if ($e->getCode() === 4) {
                $caught = true;
            }
        }
        $this->assertTrue($caught);
    }

    public function testBadContainer () {
        $caught = false;
        try {
            $this->layout->container('x');
        } catch (Exception $e) {
            if ($e->getCode() === 3) {
                $caught = true;
            }
        }
        $this->assertTrue($caught);
    }

    public function testBadPartial () {
        $caught = false;
        $region = [
            'partial' => 'x.hbs',
            'data' => ['data' => [['value' => 'Q'], ['value' => 'R'], ['value' => 'S']]]];
        try {
            $this->layout->container('test')->region('first', $region)->render();
        } catch (Exception $e) {
            if ($e->getCode() === 6) {
                $caught = true;
            }
        }
        $this->assertTrue($caught);
    }

    public function testBadLocalRoute () {
        $caught = false;
        $region = [
            'partial' => 'test.hbs',
            'url' => '/x'];
        try {
            $this->layout->container('test')->region('first', $region)->render();
        } catch (Exception $e) {
            if ($e->getCode() === 7) {
                $caught = true;
            }
        }
        $this->assertTrue($caught);
    }

    public function testBadExternalRoute () {
        $caught = false;
        $region = [
            'partial' => 'test.hbs',
            'url' => 'http://x'];
        try {
            $this->layout->container('test')->region('first', $region)->render();
        } catch (Exception $e) {
            if ($e->getCode() === 2) {
                $caught = true;
            }
        }
        $this->assertTrue($caught);
    }

    public function testBadYamlSyntaxRoute () {
        $caught = false;
        try {
            $this->layout->config('badsyntax');
        } catch (Exception $e) {
            if ($e->getCode() === 5) {
                $caught = true;
            }
        }
        $this->assertTrue($caught);
    }
}