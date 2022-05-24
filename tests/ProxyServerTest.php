<?php
namespace Tests\Socket;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Socket;
use Amp\Socket\ConnectContext;
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

    function testTest()
    {
        $this-> assertFalse(false);
    }

}