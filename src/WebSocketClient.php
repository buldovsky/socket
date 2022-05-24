<?php
namespace Bumax\Socket;

use Amp\WebSocket\Client as WSClient;
use Amp\Websocket\ClosedException;
use Amp\ByteStream\StreamException;
use function Amp\asyncCall;

class WebSocketClient implements ClientInterface
{

    private WSClient $socket;
    private ServerInterface $server;

    function __construct(ServerInterface $server, WSClient $client)
    {
        $this-> server = $server;
        $this-> socket = $client;
    }

    function getServer():ServerInterface
    {
        return $this->server;
    }

    function getHost():string
    {
        return $this-> socket-> getLocalAddress()-> getHost();
    }

    function getPort():int
    {
        return $this-> socket-> getLocalAddress()-> getPort();
    }

    /**
     * @param string $str
     * @return void
     */
    function send(string $str)
    {
        asyncCall(function () use($str) {
            try {

                $result = yield $this->socket->send($str);

            } catch (ClosedException|StreamException $e) {


            }
        });
    }

    /**
     * @param string|null $str
     * @return void
     */
    function end(string $str = null)
    {
        asyncCall(function () use($str) {
            try {

                yield $this->socket-> send($str);
                $this-> socket-> close();

            } catch (ClosedException|StreamException $e) {


            }
        });
    }

    function onConnect(callable $callable = null)
    {
        // TODO: Implement onConnect() method.
    }

    function read(callable|object $callback): ClientInterface
    {
        // TODO: Implement read() method.
        return $this;
    }

    function write(string $str)
    {
        try {
            return $this->socket->send($str);
        } catch (ClosedException|StreamException $e) {
            throw new \Exception("Can not write to socket. {$e-> getMessage()}");
        }
    }

    function close()
    {
        // TODO: Implement close() method.
    }
}