<?php

namespace Bumax\Socket;

use Amp\Socket\Server as AmpServer;
use Amp\Socket\ConnectContext;
use Amp\Socket\Socket;
use Amp\Socket\SocketException;
use Amp\ByteStream;
use function Amp\asyncCall;
use function Amp\Socket\connect;

/**
 * Проксирующий сокет
 */
class ProxyServer extends Server
{

    static array $loopHandlers = [];

    protected array $clients = [];
    protected array $handlers = [];

    protected AmpServer $server;

    protected array $listenHandlers = [];
    private string $proxyHost;
    private int $proxyPort;

    protected ClientInterface $destination;
    protected ClientInterface $source;

    /**
     * @param callable|object $handler
     * @param ProtocolInterface|null $protocol
     * @param callable|null $clientHandler
     * @param ProtocolInterface|null $responseProtocol
     * @return $this
     */
    function handler(
        callable|object     $handler,
        ProtocolInterface   $protocol = null,
        callable            $clientHandler = null,
        ProtocolInterface   $responseProtocol = null,
    ):self
    {
        $this->handlers [] = [$handler, $clientHandler, $protocol, $responseProtocol];
        return $this;
    }


    /**
     * @param string $host
     * @param int $port
     * @return $this
     */
    function proxy(string $host, int $port): self
    {
        $this-> proxyHost = $host;
        $this-> proxyPort = $port;
        return $this;
    }

    /**
     * @return iterable
     * @throws Exception
     * @throws SocketException
     * @throws \Amp\CancelledException
     */
    function run(): iterable
    {

        try {

            // открываем произвольный порт для telnet
            $this->server = AmpServer::listen("{$this->host}:{$this->port}");

            if(isset($this->onStartHandler)) asyncCall($this->onStartHandler, $this);

            try {

                $destinationSocket = yield connect("{$this->proxyHost}:{$this->proxyPort}", (new ConnectContext));
                // сохраняем подключение к устройству, чтобы потом закрыть
                //$proxy-> setDeviceConnection($deviceConnection);
                $this->destination = new Client($this, $destinationSocket);

                asyncCall(function () {
                    // обрабатываем все подключения по телнету
                    while ($client = yield $this->server->accept()) {


                        // у каждого сервера может быть только один клиент
                        // так как сервер одноразовый
                        //$this-> server-> close();

                        // сохраняем подключение к клиенту, чтобы потом закрыть
                        $this->source = new Client($this, $client);

                        asyncCall(function () {
                            try {
                                // весь telnet трафик от устроства будем возращать пользователям
                                while (null !== $chunk = yield $this->destination->read())
                                    yield $this->source->write($chunk);
                            } catch (ByteStream\ClosedException $e) {
                                $this->destination->close();
                                $this->source->close();
                            }
                        });

                        try {
                            // весь полученнный по телнету трафик
                            while (null !== $chunk = yield $this->source->read())
                                // пересылаем на устрйоство
                                yield $this->destination->write($chunk);
                        } catch (ByteStream\ClosedException $e) {
                            $this->destination->close();
                            $this->source->close();
                        }

                    }
                });

            } catch (Socket\ConnectException $e) {
                throw new Exception('Can not connect to device', 4);
            }

        } catch (SocketException $e) {
            throw new Exception('Can not listen port', 5);
        }

        /**
         * обрабатываем все подключения
         * @var Socket $socket
         */
        while ($socket = yield $this->server->accept()) {

            try {

                $client = new Client($this, $socket);

                $this->clients [$client->getHost() . ':' . $client->getPort()] = $client;
                foreach ($this->clientConnectHandlers as $handler) $handler($client);

                while (null !== $chunk = yield $socket->read()) {

                    /**
                     * Перебираем все обработчики по очереди, ищем подходящий протокол
                     * @var callable $callable
                     * @var ProtocolInterface $protocol
                     * @var ProtocolInterface $proto $responseProtocol
                     */
                    foreach ($this->handlers as list($callable, $protocol, $responseProtocol)) {

                        // если протокол не определен
                        if (!isset($protocol)) {
                            // работаем со текствой строкой
                            $callable($chunk, $client);
                            break;
                        }

                        try {
                            if (!$request = $protocol->isValidRequest($chunk)) continue;
                        } catch (Exception $e) {
                            continue;
                        }

                        try {
                            asyncCall([$request, 'handle'], $callable, $client);
                            /** @todo сделать поддержку вложенных обработчиков */
                        } catch (Exception $e) {
                            throw $e;
                        }
                        break;
                    }
                }

            } catch (ByteStream\ClosedException $e) {

                // если клиенты отключаются заносим в лог

            }
        }

    }


}