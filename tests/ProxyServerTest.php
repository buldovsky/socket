<?php
namespace Tests\Socket;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Socket;
use Amp\Socket\ConnectContext;
use Bumax\Socket\ProxyServer;
use function Amp\Socket\connect;

use Bumax\Socket\Request;
use Bumax\Socket\ClientInterface;
use Bumax\Socket\Server;
use Bumax\Socket\ServerInterface;
use Bumax\Socket\Loop;
use Tests\Socket\TestProtocol;

/**
 * Тесты запускаются в Amp\Loop::run()
 */
class ProxyServerTest extends AsyncTestCase
{

    public function testStart()
    {
        $this->setTimeout(2000);
        $this->expectOutputString("Test proxy server is started...");

        (new ProxyServer())
            -> listen('127.0.0.1', 12345)
            -> proxy('127.0.0.1', 31234)
            -> onStart(function(){
                echo "Test proxy server is started...";
                Loop::stop();
            })
            -> start()
        ;
    }

}