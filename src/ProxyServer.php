<?php

namespace Bumax\Socket;

use Exception;

use App\Socket\Server;
use Amp\Socket\ConnectContext;
use Amp\Socket\Server as AmpServer;
use Amp\Socket\Socket;
use Amp\Socket\SocketException;
use Amp\ByteStream;
use function Amp\asyncCall;
use function Amp\Socket\connect;

class ProxyServer extends Server implements ServerInterface
{

    static array $loopHandlers = [];

    protected array $clients = [];
    protected array $handlers = [];

    protected array $onStartHandlers = [];
    protected array $clientConnectHandlers = [];
    protected array $clientDisconnectHandlers = [];
    protected AmpServer $server;

    protected array $listenHandlers = [];
    private string $host;
    private int $port;
    private string $proxyHost;
    private int $proxyPort;

    protected ClientInterface $destination;
    protected ClientInterface $source;


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

    public function onStart(callable $callable):self
    {
        $this->onStartHandlers []= $callable;
        return $this;
    }

    function onClientConnect(callable $callable):self
    {
        $this->clientConnectHandlers []= $callable;
        return $this;
    }

    public function onClientDisconnect(callable $callable):self
    {
        $this->clientDisconnectHandlers []= $callable;
        return $this;
    }


    function listen(string $host, int $port = 0):self
    {

        $this-> host = $host;
        $this-> port = $port;

        return $this;
    }

    function proxy(string $host, int $port): self
    {
        $this-> proxyHost = $host;
        $this-> proxyPort = $port;
        return $this;
    }


    function run(): iterable
    {

        try {

            // открываем произвольный порт для telnet
            $this->server = AmpServer::listen($this->host . ":" . $this->port);
            //$proxy = new ProxyConnection($server);

            try {

                $destinationSocket = yield connect($this->proxyHost . ':' . $this->proxyPort, (new ConnectContext));
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
                throw new \Exception('Can not connect to device', 4);
            }

        } catch (SocketException $e) {
            throw new \Exception('Can listen port', 5);
        }


        $this->server = AmpServer::listen($this->host . ':' . $this->port);

        foreach ($this->onStartHandlers as $handler) $handler($this);


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

                        // если протоколы не указаны
                        if (!isset($protocol)) {
                            // работаем со строками
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
                            //$i = 0; do {
                            //    $callable = $request->handle($callable, $client);
                            //} while(is_callable($callable) && $i++ < 5);
                        } catch (Exception $e) {
                            //
                        }
                        break;
                    }
                }

            } catch (ByteStream\ClosedException $e) {
                // это очень часто случается, так как люди передающие нам данные
                // сами отключаются не успев получить ответ
            }
        }

    }


}