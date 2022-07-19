<?php

namespace Bumax\Socket;

interface ServerInterface {

    /**
     * Устанавливаем данные сокета, которые будем слушать
     * @param string $host
     * @param int $port
     * @return $this
     */
    function listen(string $host, int $port = 0):self;

    /**
     * Добавляем обработчик входящих подключений
     * @param callable|object $handler
     * @param ProtocolInterface|null $protocol
     * @param callable|null $clientHandler
     * @param ProtocolInterface|null $responseProtocol
     * @return $this
     */
    function handler(
        callable|object $handler,
        ProtocolInterface $protocol = null,
        callable $clientHandler = null,
        ProtocolInterface $responseProtocol = null,
    ):self;

    /**
     * Стартуем сервер
     * @return $this
     */
    function start():self;

    /**
     * Возвращаем порт на котором поднят сервер
     * @return int
     */
    function getPort():int;

    /**
     * Добавляем обработчик который выполнится при запуске сервера
     * @param callable $callable
     * @return $this
     */
    function onStart(callable $callable):self;

    /**
     * Добавляем обработчик подключений
     * @param callable $callable
     * @return $this
     */
    function onConnect(callable $callable):self;

    /**
     * Добавляем обработчик разрыва соединений
     * @param callable $callable
     * @return $this
     */
    function onDisconnect(callable $callable):self;

}