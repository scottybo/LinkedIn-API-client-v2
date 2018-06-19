<?php

namespace Scottybo\LinkedIn2\Storage;

use Illuminate\Support\Facades\Session;

/**
 * Class SessionStorageTest.
 *
 * @author Andreas Creten
 */
class IlluminateSessionStorageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Scottybo\LinkedIn2\Storage\SessionStorage storage
     */
    protected $storage;

    protected $prefix = 'linkedIn_';

    public function setUp()
    {
        $this->storage = new IlluminateSessionStorage();
    }

    public function testSet()
    {
        Session::shouldReceive('put')->once()->with($this->prefix.'code', 'foobar');

        $this->storage->set('code', 'foobar');
    }

    /**
     * @expectedException \Scottybo\LinkedIn2\Exception\InvalidArgumentException
     */
    public function testSetFail()
    {
        $this->storage->set('foobar', 'baz');
    }

    public function testGet()
    {
        $expected = 'foobar';
        Session::shouldReceive('get')->once()->with($this->prefix.'code')->andReturn($expected);
        $result = $this->storage->get('code');
        $this->assertEquals($expected, $result);

        Session::shouldReceive('get')->once()->with($this->prefix.'state')->andReturn(null);
        $result = $this->storage->get('state');
        $this->assertNull($result);
    }

    public function testClear()
    {
        Session::shouldReceive('forget')->once()->with($this->prefix.'code')->andReturn(true);
        $this->storage->clear('code');
    }

    /**
     * @expectedException \Scottybo\LinkedIn2\Exception\InvalidArgumentException
     */
    public function testClearFail()
    {
        $this->storage->clear('foobar');
    }
}
