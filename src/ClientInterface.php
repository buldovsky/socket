<?php

namespace Bumax\Socket;

interface ClientInterface {

    /**
     * Возвращаем локальный хост
     * @return string
     */
    function getHost():string;

    /**
     * Возрашаем порт, который слушаем
     * @return int
     */
    function getPort():int;

    /**
     * Добавляем
     * @param callable|null $callable
     * @return mixed
     */
    function onConnect(callable $callable = null);

    /**
     * Возращаем экземпляр сокета, к которому подключились
     * @return ServerInterface|null
     */
    function getServer():?ServerInterface;

    /**
     * Читаем из сокета
     * @param callable|object $callback
     * @return $this
     */
    function read(callable|object $callback):self;

    /**
     * Пишем в сокет
     * @param string $str
     * @return mixed
     */
    function write(string $str);

    /**
     * Пишем сокет и завершаем соединение
     * @param string $str
     * @return mixed
     */
    function end(string $str);

    /**
     * Закрываем сокет
     * @return mixed
     */
    function close();

}