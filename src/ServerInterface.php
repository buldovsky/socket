<?php
namespace Bumax\Socket;

use Bumax\Protocol\ProtocolInterface;

interface ServerInterface {

    // function getSockets();
    // function getClients();

    // function stop();
    // function isStopped():bool;


    function listen(string $host, int $port = 0):self;

    function handler(
        callable|object $handler,
        ProtocolInterface $protocol = null,
        callable $clientHandler = null,
        ProtocolInterface $responseProtocol = null,
    ):self;

    function start():self;

    function getPort():int;

    function onStart(callable $callable):self;

    function onConnect(callable $callable):self;

    function onDisconnect(callable $callable):self;

}