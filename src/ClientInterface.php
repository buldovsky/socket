<?php
namespace Bumax\Socket;

interface ClientInterface {

    function getHost():string;

    function getPort():int;

    function onConnect(callable $callable = null);

    function getServer():?ServerInterface;

    function read(callable|object $callback):self;

    function write(string $str);

    function end(string $str);

    function close();

}