<?php

namespace Bumax\Socket;

use Amp\CancelledException;
use Amp\Promise;
use Amp\Socket\ConnectContext;
use Amp\Socket\ConnectException;
use Amp\Socket\Socket as AmpSocket;
use Amp\ByteStream\ClosedException;
use Amp\ByteStream\StreamException;

use function Amp\asyncCall;
use function Amp\Socket\connect;

/**
 * С помощью этого класса можно подключиться к удаленному сокету
 */
class Client implements ClientInterface
{
    
    private AmpSocket $socket;
    private Promise $connectPromise;
    private array $readHandlers = [];
    private array $onConnectHandlers = [];

    function __construct(AmpSocket|string $socket, protected ?ServerInterface $server = null)
    {
        if(is_string($socket)){
            try {
                $this-> connectPromise = connect($socket, (new ConnectContext));
                $this-> connectPromise-> onResolve(function ($error, $value){

                    $this-> socket = $value;

                    foreach ($this-> onConnectHandlers as $callback)
                        asyncCall($callback, $this);

                });
            } catch (CancelledException $e) {
                throw new Exception("Connection cancelled. {$e-> getMessage()}");
            } catch (ConnectException $e) {
                throw new Exception("Connection error. {$e-> getMessage()}");
            }
        } else {

            $this-> socket = $socket;

        }

    }

    /**
     * Добавляем обработчик подключения пользователя
     * @param callable|null $callable
     * @return $this|Promise
     */
    function onConnect(callable $callable = null)
    {
        if(!isset($callable)) return $this-> connectPromise;

        $this-> onConnectHandlers []= $callable;
        return $this;
    }

    /**
     * Возвращаем сокет к которому мы подключились
     * @return ServerInterface
     */
    function getServer():ServerInterface
    {
        return $this->server;
    }

    /**
     * Возвращаем локальный хост
     * @return string
     * @throws Exception
     */
    function getHost():string
    {
        $this-> checkSocket();
        return $this-> socket-> getLocalAddress()-> getHost();
    }

    /**
     * Возвращаем локальный порт
     * @return int
     * @throws Exception
     */
    function getPort():int
    {
        $this-> checkSocket();
        return $this-> socket-> getLocalAddress()-> getPort();
    }

    /**
     * Читаем из сокета
     * @param callable|object $callback
     * @return $this
     */
    function read(callable|object $callback):self
    {
        $this-> readHandlers []= $callback;

        // если мы уже здесь были уходим
        if(count($this-> readHandlers) !== 1)
            return $this;

        // запускаем процесс чтения сокета только 1 раз
        asyncCall(function()use($callback){

            if(!isset($this-> socket))
                yield $this-> connectPromise;

            while (null !== $chunk = yield $this-> socket-> read()){
                foreach ($this-> readHandlers as $callback){
                    if(is_callable($callback)){
                        asyncCall($callback, $chunk, $this);
                    }
                }
            }
        });
        return $this;
    }

    /**
     * Пишем в сокет
     * @param string $str
     * @return Promise
     * @throws Exception
     */
    function write(string $str)
    {
        $this-> checkSocket();
        try {
            return $this->socket->write($str);
        } catch (ClosedException|StreamException $e) {
            throw new Exception("Can not write to socket. {$e-> getMessage()}");
        }
    }

    /**
     * Пишем всокет и завершаем соединение
     * @param string $str
     * @throws Exception
     */
    function end(string $str)
    {
        $this-> write($str);
        $this-> close();
    }

    /**
     * Завершаем соединение
     * @return void
     * @throws Exception
     */
    function close()
    {
        $this-> checkSocket();
        $this-> socket-> close();
    }

    /**
     * Метод проверяет, что подключение установлено
     * @throws Exception
     */
    private function checkSocket()
    {
        if(!isset($this-> socket))
            throw new Exception('Дождитесь установления подключения');
    }
}