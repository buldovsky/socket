<?php
namespace Tests\Socket;

use Amp\PHPUnit\AsyncTestCase;

use Bumax\Request;
use Bumax\Socket\ClientInterface;
use Bumax\Socket\Server;
use Bumax\Socket\ServerInterface;
use Bumax\Loop;
use Tests\TestProtocol;


use Amp\Socket;
use Amp\Socket\ConnectContext;
use function Amp\Socket\connect;

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