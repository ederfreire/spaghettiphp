<?php

require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'bootstrap.php';
import('core.Mapper');

class MapperTest extends PHPUnit_Framework_TestCase {
    public function testMatch() {
        $check = String::insert('/:controller/:action', array(
            'controller' => '([a-z-_]+)',
            'action' => '([a-z-_]+)'
        ));
        $result = Mapper::match($check, '/controller/action');

        $this->assertTrue($result);
    }
    public function testParse() {
        $expected = array(
            'controller' => 'controller',
            'action' => 'action'
        );
        $result = Mapper::parse('/controller/action');
        
        $this->assertEquals($expected, $result);
    }
    public function testParseDefaults() {
        $expected = array(
            'controller' => 'home',
            'action' => 'index'
        );
        $result = Mapper::parse('/');
        
        $this->assertEquals($expected, $result);
    }
}