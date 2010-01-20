<?php

require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'bootstrap.php';
import('core.common.Inflector');
import('core.model.Model');

class ModelTest extends PHPUnit_Framework_TestCase {
    public function setUp() {
        $this->User = new User(false, false, true);
    }
    public function tearDown() {
        $this->User = null;
    }
    public function testMainInstanceShouldNotBeNewRecord() {
        $this->assertFalse($this->User->isNewRecord());
    }
    public function testShouldSetAndGetFieldForSingleRecord() {
        $user = $this->User->create();
        $user->name = $expected = 'spaghettiphp';
        
        $this->assertEquals($expected, $user->name);
    }
    public function testShouldPassFieldsThroughSettersWhenRequired() {
        $user = $this->User->create();
        $user->password = 'spaghettiphp';
        $expected = md5('spaghettiphp');
        
        $this->assertEquals($expected, $user->password);
    }
    public function testShouldThrowExceptionWhenFieldDoesNotExist() {
        $this->setExpectedException('UndefinedPropertyException');
        $user = $this->User->create();
        $expected = $user->password;
    }
    public function testShouldPassFieldsThroughGettersWhenRequired() {
        $user = $this->User->create();
        $user->name = $expected = 'spaghettiphp';

        $this->assertEquals($expected, $user->username);
    }
    public function testShouldUseAliasesForGettingFields() {
        $user = $this->User->create();
        $user->password = 'spaghettiphp';
        $expected = md5('spaghettiphp');
        
        $this->assertEquals($expected, $user->passwd);
    }
    public function testShouldUseAliasesForSettingFields() {
        $user = $this->User->create();
        $user->myName = $expected = 'spaghettiphp';
        $this->assertEquals($expected, $user->name);
    }
    public function testShouldSetMultipleAttributesWithMassSetting() {
        $user = $this->User->create();
        $user->attributes(array(
            'name' => 'spaghetti',
            'password' => 'spaghetti'
        ));
        
        $this->assertEquals('spaghetti', $user->name);
        $this->assertEquals(md5('spaghetti'), $user->password);
    }
    public function testShouldNotSetProtectedAttributesWithMassSetting() {
        $user = $this->User->create();
        $user->admin = false;
        $user->attributes(array(
            'name' => 'spaghettiphp',
            'password' => 'spaghettiphp',
            'admin' => true
        ));
        
        $this->assertEquals('spaghettiphp', $user->name);
        $this->assertEquals(md5('spaghettiphp'), $user->password);
        $this->assertFalse($user->admin);
    }
    public function testShouldOnlySetUnprotectedAttributesWithMassSetting() {
        $user = $this->User->create();
        $user->whitelist = array('name', 'password');
        $user->admin = false;
        $user->attributes(array(
            'name' => 'spaghettiphp',
            'password' => 'spaghettiphp',
            'admin' => true
        ));
        
        $this->assertEquals('spaghettiphp', $user->name);
        $this->assertEquals(md5('spaghettiphp'), $user->password);
        $this->assertFalse($user->admin);
    }
    public function testShouldCreateANewEmptyRecord() {
        $user = $this->User->create();
        
        $this->assertTrue($user->isNewRecord());
    }
    public function testShouldCreateANewRecordWithAttributes() {
        $user = $this->User->create(array(
            'name' => 'spaghettiphp',
            'password' => 'spaghettiphp'
        ));
        
        $this->assertEquals('spaghettiphp', $user->name);
        $this->assertEquals(md5('spaghettiphp'), $user->password);
        $this->assertTrue($user->isNewRecord());
    }
    public function testShouldCreateANewRecordWithClosure() {
        if(version_compare(PHP_VERSION, '5.3') < 0):
            return $this->assertTrue(true);
        endif;
        $user = $this->User->create(function(&$self) {
            $self->name = 'spaghettiphp';
            $self->password = 'spaghettiphp';
        });
        
        $this->assertEquals('spaghettiphp', $user->name);
        $this->assertEquals(md5('spaghettiphp'), $user->password);
        $this->assertTrue($user->isNewRecord());
    }
    public function testShouldCreateANewRecordWithNew() {
        $user = new User(array(
            'name' => 'spaghettiphp',
            'password' => 'spaghettiphp'
        ));
        
        $this->assertEquals('spaghettiphp', $user->name);
        $this->assertEquals(md5('spaghettiphp'), $user->password);
        $this->assertTrue($user->isNewRecord());
    }
    public function testShouldCreateAnExistingRecordAndBypassProtection() {
        $user = new User(array(
            'admin' => true
        ), false);
        
        $this->assertTrue($user->admin);
        $this->assertTrue($user->admin);
    }
    public function testShouldReturnAConnectionAccordingToDatabaseConfig() {
        $result = $this->User->connection('test');
        $database = Config::read('database');
        $type = Inflector::camelize($database['test']['driver']) . 'Datasource';
        
        $this->assertType($type, $result);
    }
}

class User extends Model {
    public $aliasAttribute = array(
        'passwd' => 'password',
        'myName' => 'name'
    );
    public $getters = array('username');
    public $setters = array('password');
    public $blacklist = array('admin');
    
    public function getUsername() {
        return $this->name;
    }
    public function setPassword($password) {
        $this->set('password', md5($password));
    }
}