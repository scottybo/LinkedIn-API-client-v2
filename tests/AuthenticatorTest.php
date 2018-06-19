<?php

namespace Scottybo\LinkedIn2;

use GuzzleHttp\Psr7\Response;
use Scottybo\LinkedIn2\Exception\LinkedInException;
use Mockery as m;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class AuthenticatorTest extends \PHPUnit_Framework_TestCase
{
    const APP_ID = '123456789';
    const APP_SECRET = '987654321';

    private function getRequestManagerMock()
    {
        return m::mock('Scottybo\LinkedIn2\Http\RequestManager');
    }

    public function testGetLoginUrl()
    {
        $expected = 'loginUrl';
        $state = 'random';
        $params = [
            'response_type' => 'code',
            'client_id' => self::APP_ID,
            'redirect_uri' => null,
            'state' => $state,
        ];

        $storage = $this->getMock('Scottybo\LinkedIn2\Storage\DataStorageInterface');
        $storage->method('get')->with('state')->willReturn($state);

        $auth = $this->getMock('Scottybo\LinkedIn2\Authenticator', ['establishCSRFTokenState', 'getStorage'], [$this->getRequestManagerMock(), self::APP_ID, self::APP_SECRET]);
        $auth->expects($this->exactly(2))->method('establishCSRFTokenState')->willReturn(null);
        $auth->method('getStorage')->will($this->returnValue($storage));

        $generator = m::mock('Scottybo\LinkedIn2\Http\LinkedInUrlGeneratorInterface')
            ->shouldReceive('getUrl')->once()->with('www', 'oauth/v2/authorization', $params)->andReturn($expected)
            ->getMock();

        $this->assertEquals($expected, $auth->getLoginUrl($generator));

        /*
         * Test with a url in the param
         */
        $otherUrl = 'otherUrl';
        $scope = ['foo', 'bar', 'baz'];
        $params = [
            'response_type' => 'code',
            'client_id' => self::APP_ID,
            'redirect_uri' => $otherUrl,
            'state' => $state,
            'scope' => 'foo bar baz',
        ];

        $generator = m::mock('Scottybo\LinkedIn2\Http\LinkedInUrlGeneratorInterface')
            ->shouldReceive('getUrl')->once()->with('www', 'oauth/v2/authorization', $params)->andReturn($expected)
            ->getMock();

        $this->assertEquals($expected, $auth->getLoginUrl($generator, ['redirect_uri' => $otherUrl, 'scope' => $scope]));
    }

    public function testFetchNewAccessToken()
    {
        $generator = m::mock('Scottybo\LinkedIn2\Http\UrlGenerator');
        $code = 'newCode';
        $storage = m::mock('Scottybo\LinkedIn2\Storage\DataStorageInterface')
            ->shouldReceive('set')->once()->with('code', $code)
            ->shouldReceive('set')->once()->with('access_token', 'at')
            ->getMock();

        $auth = $this->getMock('Scottybo\LinkedIn2\Authenticator', ['getCode', 'getStorage', 'getAccessTokenFromCode'], [], '', false);
        $auth->expects($this->any())->method('getStorage')->will($this->returnValue($storage));
        $auth->expects($this->once())->method('getAccessTokenFromCode')->with($generator, $code)->will($this->returnValue('at'));
        $auth->expects($this->once())->method('getCode')->will($this->returnValue($code));

        $this->assertEquals('at', $auth->fetchNewAccessToken($generator));
    }

    /**
     * @expectedException \Scottybo\LinkedIn2\Exception\LinkedInException
     */
    public function testFetchNewAccessTokenFail()
    {
        $generator = m::mock('Scottybo\LinkedIn2\Http\UrlGenerator');
        $code = 'newCode';
        $storage = m::mock('Scottybo\LinkedIn2\Storage\DataStorageInterface')
            ->shouldReceive('clearAll')->once()
            ->getMock();

        $auth = $this->getMock('Scottybo\LinkedIn2\Authenticator', ['getCode', 'getStorage', 'getAccessTokenFromCode'], [], '', false);
        $auth->expects($this->any())->method('getStorage')->will($this->returnValue($storage));
        $auth->expects($this->once())->method('getAccessTokenFromCode')->with($generator, $code)->willThrowException(new LinkedInException());
        $auth->expects($this->once())->method('getCode')->will($this->returnValue($code));

        $auth->fetchNewAccessToken($generator);
    }

    public function testFetchNewAccessTokenNoCode()
    {
        $generator = m::mock('Scottybo\LinkedIn2\Http\UrlGenerator');
        $storage = m::mock('Scottybo\LinkedIn2\Storage\DataStorageInterface')
            ->shouldReceive('get')->with('code')->andReturn('foobar')
            ->shouldReceive('get')->once()->with('access_token')->andReturn('baz')
            ->getMock();

        $auth = $this->getMock('Scottybo\LinkedIn2\Authenticator', ['getCode', 'getStorage'], [], '', false);
        $auth->expects($this->any())->method('getStorage')->will($this->returnValue($storage));
        $auth->expects($this->once())->method('getCode');

        $this->assertEquals('baz', $auth->fetchNewAccessToken($generator));
    }

    /**
     * @expectedException \Scottybo\LinkedIn2\Exception\LinkedInException
     */
    public function testGetAccessTokenFromCodeEmptyString()
    {
        $generator = m::mock('Scottybo\LinkedIn2\Http\UrlGenerator');

        $method = new \ReflectionMethod('Scottybo\LinkedIn2\Authenticator', 'getAccessTokenFromCode');
        $method->setAccessible(true);
        $auth = $this->getMock('Scottybo\LinkedIn2\Authenticator', [], [], '', false);

        $method->invoke($auth, $generator, '');
    }

    /**
     * @expectedException \Scottybo\LinkedIn2\Exception\LinkedInException
     */
    public function testGetAccessTokenFromCodeNull()
    {
        $generator = m::mock('Scottybo\LinkedIn2\Http\UrlGenerator');

        $method = new \ReflectionMethod('Scottybo\LinkedIn2\Authenticator', 'getAccessTokenFromCode');
        $method->setAccessible(true);
        $auth = $this->getMock('Scottybo\LinkedIn2\Authenticator', [], [], '', false);

        $method->invoke($auth, $generator, null);
    }

    /**
     * @expectedException \Scottybo\LinkedIn2\Exception\LinkedInException
     */
    public function testGetAccessTokenFromCodeFalse()
    {
        $generator = m::mock('Scottybo\LinkedIn2\Http\UrlGenerator');

        $method = new \ReflectionMethod('Scottybo\LinkedIn2\Authenticator', 'getAccessTokenFromCode');
        $method->setAccessible(true);
        $auth = $this->getMock('Scottybo\LinkedIn2\Authenticator', [], [], '', false);

        $method->invoke($auth, $generator, false);
    }

    public function testGetAccessTokenFromCode()
    {
        $method = new \ReflectionMethod('Scottybo\LinkedIn2\Authenticator', 'getAccessTokenFromCode');
        $method->setAccessible(true);

        $code = 'code';
        $generator = m::mock('Scottybo\LinkedIn2\Http\UrlGenerator')
            ->shouldReceive('getUrl')->with(
                'www',
                'oauth/v2/accessToken'
            )->andReturn('url')
            ->getMock();

        $response = ['access_token' => 'foobar', 'expires_in' => 10];
        $auth = $this->prepareGetAccessTokenFromCode($code, $response);
        $token = $method->invoke($auth, $generator, $code);
        $this->assertEquals('foobar', $token, 'Standard get access token form code');
    }

    /**
     * @expectedException \Scottybo\LinkedIn2\Exception\LinkedInException
     */
    public function testGetAccessTokenFromCodeNoTokenInResponse()
    {
        $method = new \ReflectionMethod('Scottybo\LinkedIn2\Authenticator', 'getAccessTokenFromCode');
        $method->setAccessible(true);

        $code = 'code';
        $generator = m::mock('Scottybo\LinkedIn2\Http\UrlGenerator')
            ->shouldReceive('getUrl')->with(
                'www',
                'oauth/v2/accessToken'
            )->andReturn('url')
            ->getMock();

        $response = ['foo' => 'bar'];
        $auth = $this->prepareGetAccessTokenFromCode($code, $response);
        $this->assertNull($method->invoke($auth, $generator, $code), 'Found array but no access token');
    }

    /**
     * @expectedException \Scottybo\LinkedIn2\Exception\LinkedInException
     */
    public function testGetAccessTokenFromCodeEmptyResponse()
    {
        $method = new \ReflectionMethod('Scottybo\LinkedIn2\Authenticator', 'getAccessTokenFromCode');
        $method->setAccessible(true);

        $code = 'code';
        $generator = m::mock('Scottybo\LinkedIn2\Http\UrlGenerator')
            ->shouldReceive('getUrl')->with(
                'www',
                'oauth/v2/accessToken'
            )->andReturn('url')
            ->getMock();

        $response = '';
        $auth = $this->prepareGetAccessTokenFromCode($code, $response);
        $this->assertNull($method->invoke($auth, $generator, $code), 'Empty result');
    }

    /**
     * Default stuff for GetAccessTokenFromCode.
     *
     * @param $response
     *
     * @return array
     */
    protected function prepareGetAccessTokenFromCode($code, $responseData)
    {
        $response = new Response(200, [], json_encode($responseData));
        $currentUrl = 'foobar';

        $storage = m::mock('Scottybo\LinkedIn2\Storage\DataStorageInterface')
            ->shouldReceive('get')->with('redirect_uri')->andReturn($currentUrl)
            ->getMock();

        $requestManager = m::mock('Scottybo\LinkedIn2\Http\RequestManager')
            ->shouldReceive('sendRequest')->once()->with('POST', 'url', [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ], http_build_query([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $currentUrl,
                'client_id' => self::APP_ID,
                'client_secret' => self::APP_SECRET,
            ]))->andReturn($response)
            ->getMock();

        $auth = $this->getMock('Scottybo\LinkedIn2\Authenticator', ['getStorage'], [$requestManager, self::APP_ID, self::APP_SECRET]);
        $auth->expects($this->any())->method('getStorage')->will($this->returnValue($storage));

        return $auth;
    }

    public function testEstablishCSRFTokenState()
    {
        $method = new \ReflectionMethod('Scottybo\LinkedIn2\Authenticator', 'establishCSRFTokenState');
        $method->setAccessible(true);

        $storage = m::mock('Scottybo\LinkedIn2\Storage\DataStorageInterface')
            ->shouldReceive('get')->with('state')->andReturn(null, 'state')
            ->shouldReceive('set')->once()->with('state', \Mockery::on(function (&$param) {
                return !empty($param);
            }))
            ->getMock();

        $auth = $this->getMock('Scottybo\LinkedIn2\Authenticator', ['getStorage'], [], '', false);
        $auth->expects($this->any())->method('getStorage')->will($this->returnValue($storage));

        // Make sure we only set the state once
        $method->invoke($auth);
        $method->invoke($auth);
    }

    public function testGetCodeEmpty()
    {
        unset($_REQUEST['code']);
        unset($_GET['code']);

        $method = new \ReflectionMethod('Scottybo\LinkedIn2\Authenticator', 'getCode');
        $method->setAccessible(true);
        $auth = $this->getMock('Scottybo\LinkedIn2\Authenticator', [], [], '', false);

        $this->assertNull($method->invoke($auth));
    }

    public function testGetCode()
    {
        $method = new \ReflectionMethod('Scottybo\LinkedIn2\Authenticator', 'getCode');
        $method->setAccessible(true);
        $state = 'bazbar';

        $storage = m::mock('Scottybo\LinkedIn2\Storage\DataStorageInterface')
            ->shouldReceive('clear')->once()->with('state')
            ->shouldReceive('get')->once()->with('code')->andReturn(null)
            ->shouldReceive('get')->once()->with('state')->andReturn($state)
            ->getMock();

        $auth = $this->getMock('Scottybo\LinkedIn2\Authenticator', ['getStorage'], [], '', false);
        $auth->expects($this->once())->method('getStorage')->will($this->returnValue($storage));

        $_REQUEST['code'] = 'foobar';
        $_REQUEST['state'] = $state;

        $this->assertEquals('foobar', $method->invoke($auth));
    }

    /**
     * @expectedException \Scottybo\LinkedIn2\Exception\LinkedInException
     */
    public function testGetCodeInvalidCode()
    {
        $method = new \ReflectionMethod('Scottybo\LinkedIn2\Authenticator', 'getCode');
        $method->setAccessible(true);

        $storage = m::mock('Scottybo\LinkedIn2\Storage\DataStorageInterface')
            ->shouldReceive('get')->once()->with('code')->andReturn(null)
            ->shouldReceive('get')->once()->with('state')->andReturn('bazbar')
            ->getMock();

        $auth = $this->getMock('Scottybo\LinkedIn2\Authenticator', ['getStorage'], [], '', false);
        $auth->expects($this->once())->method('getStorage')->will($this->returnValue($storage));

        $_REQUEST['code'] = 'foobar';
        $_REQUEST['state'] = 'invalid';

        $this->assertEquals('foobar', $method->invoke($auth));
    }

    public function testGetCodeUsedCode()
    {
        $method = new \ReflectionMethod('Scottybo\LinkedIn2\Authenticator', 'getCode');
        $method->setAccessible(true);

        $storage = m::mock('Scottybo\LinkedIn2\Storage\DataStorageInterface')
            ->shouldReceive('get')->once()->with('code')->andReturn('foobar')
            ->getMock();

        $auth = $this->getMock('Scottybo\LinkedIn2\Authenticator', ['getStorage'], [], '', false);
        $auth->expects($this->once())->method('getStorage')->will($this->returnValue($storage));

        $_REQUEST['code'] = 'foobar';

        $this->assertEquals(null, $method->invoke($auth));
    }

    public function testStorageAccessors()
    {
        $method = new \ReflectionMethod('Scottybo\LinkedIn2\Authenticator', 'getStorage');
        $method->setAccessible(true);
        $requestManager = $this->getRequestManagerMock();
        $auth = new Authenticator($requestManager, self::APP_ID, self::APP_SECRET);

        // test default
        $this->assertInstanceOf('Scottybo\LinkedIn2\Storage\SessionStorage', $method->invoke($auth));

        $object = m::mock('Scottybo\LinkedIn2\Storage\DataStorageInterface');
        $auth->setStorage($object);
        $this->assertEquals($object, $method->invoke($auth));
    }
}
