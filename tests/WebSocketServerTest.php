<?php
namespace Tests\Socket;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Websocket\Client;


use Bumax\Request;
use Bumax\Socket\ClientInterface;
use Bumax\Socket\WebSocketServer as Server;
use Bumax\Socket\ServerInterface;
use Bumax\Loop;
use Monolog\ErrorHandler;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Tests\TestProtocol;

use Amp\Socket;
use Amp\Socket\ConnectContext;
//use function Amp\Socket\connect;

/**
 * Тесты запускаются в Amp\Loop::run()
 */
class WebSocketServerTest extends AsyncTestCase
{

    public function testStart()
    {
        $this->expectOutputString("Test server is started...");


        (new Server(new Logger('testwebsocket')))
            -> listen('127.0.0.1')
            -> onStart(function(){
                echo "Test server is started...";
                Loop::stop();
            })
            -> start()
        ;
    }

    public function testStartPort()
    {
        $this->setTimeout(2000);
        $this->expectOutputRegex('/[0-9]{4,5}$/');

        (new Server(new Logger('testwebsocket')))
            -> listen('127.0.0.1')
            -> onStart(function(ServerInterface $server){
                echo $server-> getPort();
                Loop::stop();
            })
            -> start()
        ;
    }

    public function testStartBadPort()
    {
        $this-> setTimeout(2000);
        // сработает Permission denied
        $this-> expectException(\Exception::class);

        (new Server(new Logger('testwebsocket')))
            -> listen('127.0.0.1', 22)
            -> start()
        ;
    }

    public function testHandlerClientCallback()
    {
        $this->setTimeout(2000);

        // create a log channel
        $log = new Logger('name');
        //$log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

        $host = '127.0.0.1';
        $server = (new Server($log))
            -> listen($host)
            -> handler(
                handler: function(){},
                clientHandler: function($client)use($host){
                    $this-> assertEquals($host, $client-> getHost());
                    Loop::stop();
                }
            )
            -> start()
        ;

        /** @var Client\Connection $connection */
        Client\connect("ws://127.0.0.1:{$server-> getPort()}/");

    }

    /**
     * Logger перехватывает исключения, и поэтому тест не срабатывает
     */
    public function testNoClientHandler()
    {

        $this->setTimeout(2000);
        // ни одного хэндлера нет, значит ждем исключение
        $this-> expectException(\Exception::class);

        // create a log channel
        $log = new Logger('name');
        $errorHandler = new StreamHandler('php://stdout', Logger::DEBUG);
        $log->pushHandler($errorHandler);
        $log->setExceptionHandler(function($e){
            throw $e;
        });

        $log->error('2222222222222');

        $host = '127.0.0.1';
        $server = (new Server($log))
            -> listen($host)
            -> handler(
                handler: function(){  },
                clientHandler: function(){ return false; }
            )
            -> start()
        ;

        /** @var Client\Connection $connection */
        yield Client\connect("ws://127.0.0.1:{$server-> getPort()}/");

    }


    public function testHandlerString()
    {
        $this->setTimeout(2000);

        $log = new Logger('name');
        //$log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

        $host = '127.0.0.1';
        $server = (new Server($log))
            -> listen($host)
            -> handler(function(string $str, $client){
                $this-> assertEquals('Hello', $str);
                $this-> assertInstanceOf(ClientInterface::class, $client);
                Loop::stop();
            })
            -> start()
        ;

        /** @var Client\Connection $connection */
        $connection = yield Client\connect("ws://127.0.0.1:{$server-> getPort()}/");
        yield $connection-> send('Hello');

    }


    public function disabled_testHandleResponseString()
    {

        $this->setTimeout(2000);

        $log = new Logger('name');
        //$log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

        $host = '127.0.0.1';
        (new Server($log))
            -> listen($host)
            -> onStart(function($server) use($host){

                /** @var Client\Connection $connection */
                $connection = yield Client\connect("ws://127.0.0.1:{$server-> getPort()}/");
                yield $connection-> send('Hello');

                /*
                while (null !== $chunk = yield $connection->receive()){
                    $this-> assertEquals('Hi!', $chunk);
                    Loop::stop();
                }
                */
            })
            -> handler(function(string $str){
                return match($str){
                    'Hello' => 'Hi!',
                    'Thanks' => 'You are welcome!',
                    default => "I didn't catch you!"
                };
            })
            -> start()
        ;

    }

    /*

    public function testHandleResponse()
    {

        $this->setTimeout(2000);

        $host = '127.0.0.1';
        (new Server(new Logger('testwebsocket')))
            -> listen($host)
            -> onStart(function($server) use($host){

                $port = $server-> getPort();
                $socket = yield connect("$host:$port", (new ConnectContext));
                $socket-> write('Hello');

                while (null !== $chunk = yield $socket->read()){
                    $this-> assertEquals('Hi!', $chunk);
                    Loop::stop();
                }

            })
            -> handler(function(string $str, ClientInterface $socket){
                yield $socket-> write(match($str){
                    'Hello' => 'Hi!',
                    'Thanks' => 'You are welcome!',
                    default => "I didn't catch you!"
                });
            })
            -> start()
        ;

    }

    public function testHandleTestProtocol()
    {

        $this->setTimeout(2000);

        $testData = [
            'action' => 'to_upper_case',
            'data' => ['key' => 'value']
        ];

        $host = '127.0.0.1';
        $server = (new Server(new Logger('testwebsocket')))
            -> listen($host)
            -> handler(
                handler: function(object $data, $request){
                    $this-> assertEquals('value', $data-> key);
                    $this-> assertInstanceOf(Request::class, $request);
                    Loop::stop();
                },
                protocol: new TestProtocol
            )
            -> start()
        ;

        $port = $server-> getPort();
        $socket = yield connect("$host:$port", (new ConnectContext));
        yield $socket-> write(json_encode($testData));

    }



    /**
     * @depends testController
     * @return void
     * /
    public function testHandleTestController($controller)
    {

        $this->setTimeout(2000);

        $testData = [
            'action' => 'to_upper_case',
            'data' => ['key' => 'Value']
        ];

        $host = '127.0.0.1';
        (new Server(new Logger('testwebsocket')))
            -> listen($host)
            -> onStart(function($server) use($host, $testData){

                $port = $server-> getPort();
                $socket = yield connect("$host:$port", (new ConnectContext));
                yield $socket-> write(json_encode($testData));

                while (null !== $chunk = yield $socket->read()){
                    $this-> assertEquals(mb_strtoupper($testData['data']['key']), $chunk);
                    Loop::stop();
                }

            })
            -> handler(handler: $controller, protocol: new TestProtocol)
            -> start()
        ;
    }


    function testSocketOnConnect()
    {
        $this->expectOutputString("12");

        $host = '127.0.0.1';
        (new Server(new Logger('testwebsocket')))
            -> listen($host)
            -> onStart(function($server) use($host){

                $socket = yield connect("$host:{$server-> getPort()}", (new ConnectContext));
                yield $socket-> write('Hi!');

            })
            -> onConnect(function($socket){
                $this-> assertInstanceOf(ClientInterface::class, $socket);
                echo "1";
            })
            -> onConnect(function(){
                echo "2";
                Loop::stop();
            })
            -> start()
        ;


    }

     /**/

}