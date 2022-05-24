<?php

namespace Tests\Socket;

use Bumax\Socket\Client;
use Bumax\Socket\ClientInterface;
use Bumax\Socket\Server;
use Bumax\Socket\ServerInterface;
use Bumax\Loop;
use Bumax\Request;
use Tests\TestProtocol;


/**
 * Тесты запускаются в Amp\Loop::run()
 */
class ClientTest extends \Amp\PHPUnit\AsyncTestCase
{

    /**
     * @doesNotPerformAssertions
     * @return ServerInterface
     */
    protected function getServer()
    {
        $host = '127.0.0.1';
        return (new Server)
            -> listen($host)
            -> handler(function($str){
                return match($str){
                    'Hi' => 'Hello',
                    'Thanks' => 'You are welcome',
                    'Bye' => 'See you later',
                    default => 'I did\'t catch you'
                };
            })
        ;
    }


    function testSocketOnConnectYieldSyntax()
    {

        $this-> setTimeout(2000);
        $server = $this-> getServer()-> start();

        $socket = (new Client("127.0.0.1:{$server->getPort()}"))
            -> read(function($str){
                $this-> assertEquals('Hello', $str);
                Loop::stop();
            })
        ;

        yield $socket-> onConnect();
        $socket-> write('Hi');

    }


    function testSocketOnConnectMethod()
    {

        $this-> setTimeout(2000);
        $server = $this-> getServer()-> start();

        (new Client("127.0.0.1:{$server->getPort()}"))
            -> onConnect(function($socket){
                $this->assertInstanceOf(ClientInterface::class, $socket);
                $socket-> write('Hi');
            })
            -> read(function($str){
                $this-> assertEquals('Hello', $str);
                Loop::stop();
            })
        ;

    }


    function testGetServer()
    {
        $this-> setTimeout(2000);
        $server1 = $this-> getServer()-> start();
        $server2 = (new Client("127.0.0.1:{$server1->getPort()}", $server1))
            -> getServer();

        $this-> assertEquals($server1, $server2);
        Loop::stop();
    }


    function testGetHostPort()
    {
        $this-> setTimeout(2000);
        $server = $this-> getServer()-> start();
        (new Client("127.0.0.1:{$server->getPort()}"))
            -> onConnect(function(ClientInterface $client){
                $this-> assertIsString($client-> getHost());
                $this-> assertIsInt($client-> getPort());
                Loop::stop();
            })
        ;
    }

    function testSocketEnd()
    {
        // сработает ByteStream\ClosedException
        $this-> expectException(\Amp\ByteStream\ClosedException::class);

        $this-> setTimeout(2000);
        $server = $this-> getServer()-> start();
        (new Client("127.0.0.1:{$server->getPort()}"))
            ->onConnect(function (ClientInterface $socket){
                $socket-> end('Bye');
                yield $socket-> write('Hi');
                Loop::stop();
            })
        ;
    }

    function testSocketClose()
    {
        // сработает ByteStream\ClosedException
        $this-> expectException(\Amp\ByteStream\ClosedException::class);

        $this-> setTimeout(2000);
        $server = $this-> getServer()-> start();
        (new Client("127.0.0.1:{$server->getPort()}"))
            ->onConnect(function (ClientInterface $socket){
                $socket-> close();
                yield $socket-> write('Hi');
                Loop::stop();
            })
        ;
    }
}