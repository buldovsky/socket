<?php
namespace Bumax\Socket;

use Amp\Loop;
use Amp\Websocket\Message;
use Psr\Log\LoggerInterface;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Promise;
use Amp\Success;
use Amp\Websocket\Client;
use Amp\Socket\SocketException;
use Amp\Socket\Server as AmpServer;
use Amp\Http\Server\HttpServer;
use Amp\Websocket\Server\{ClientHandler, Gateway, Websocket as AmpWebSocket};
use Amp\ByteStream;
use function Amp\asyncCall;
use function Amp\call;
use Exception;

class WebSocketServer implements ServerInterface, ClientHandler
{

    protected LoggerInterface $logger;
    protected array $onStartHandlers = [];
    protected array $clientConnectHandlers = [];
    protected array $clientDisconnectHandlers = [];

    protected array $attributes = [];
    protected array $clients = [];
    protected array $handlers = [];


    private int $clientsLimit = 5;
    private array $allowedHosts = [];
    protected array $sockets = [];


    public function __construct(LoggerInterface $logger) //, string $config_path
    {
        $this-> logger = $logger;
    }


    function listen(string $host, int $port = 0):self
    {
        try {

            $this->sockets [] = AmpServer::listen($host . ':' . $port);

        } catch (SocketException $e) {

            throw new \Exception("{$e-> getMessage()}");

        }
        return $this;
    }


    function start(bool $loop = false):self
    {

        asyncCall([$this, 'run']);

        return $this;
    }


    function run(){

        $server = new HttpServer($this->sockets, new AmpWebSocket($this), $this->logger);
        $server->start();

        foreach ($this->onStartHandlers as $handler) $handler($this);

    }

    function getPort(): int
    {
        /** @var $socket AmpServer $socket */
        foreach ($this-> sockets as $socket){
            return $socket->  getAddress()-> getPort();
        }
        return 0;
    }

    public function onStart(callable $callable):self
    {
        $this->onStartHandlers []= $callable;
        return $this;
    }

    public function onConnect(callable $callable):self
    {
        $this->clientConnectHandlers []= $callable;
        return $this;
    }

    public function onDisconnect(callable $callable):self
    {
        $this->clientDisconnectHandlers []= $callable;
        return $this;
    }

    function handler(
        callable|object $handler,
        ProtocolInterface $protocol = null,
        callable $clientHandler = null,
        ProtocolInterface $responseProtocol = null,
    ):self {
        $this->handlers [] = [$handler, $protocol, $clientHandler, $responseProtocol];
        return $this;
    }

    /**
     * Обработка подключения внешних программ через веб-сокет
     * Обычно подключаются различные веб-морды, телеграм боты
     * @param Gateway $gateway
     * @param Request $request
     * @param Response $response
     * @return Promise
     */
    public function handleHandshake(Gateway $gateway, Request $request, Response $response): Promise
    {


        // проверяем ограничение по подключениям
        if (count($gateway->getClients()) >= $this->clientsLimit) {
            return $gateway->getErrorHandler()->handleError(403);
        }

        /*
        // проверяем откуда пришли
        if (!\in_array($request->getHeader('origin'), $this->allowedHosts, true)) {
            return $gateway->getErrorHandler()->handleError(403);
        }
        */
        return new Success($response);
    }

    /**
     * Обрабатываем клиентские запросы
     * @param Gateway $gateway
     * @param Client $client
     * @param Request $request
     * @param Response $response
     * @return Promise
     */
    public function handleClient(Gateway $gateway, Client $client, Request $request, Response $response): Promise
    {

        $newSocket = new WebSocketClient($this, $client);
        $this->clients ["{$newSocket->getHost()}:{$newSocket->getPort()}"] = $client;
        foreach ($this->clientConnectHandlers as $handler) asyncCall($handler, $newSocket);


        $handlers = [];
        /**
         * оставляем только подходящие для этого клиетна обработчики и протоколы
         * @var callable|object $handler
         * @var callable $clientHandler
         * @var ProtocolInterface $protocol
         * @var ProtocolInterface $responseProtocol
         */
        foreach ($this->handlers as [$handler, $protocol, $clientHandler, $responseProtocol]){
            if (isset($clientHandler) && !$clientHandler($newSocket)) continue;
            $handlers []= [$handler, $protocol, $clientHandler, $responseProtocol];
        }

        if(empty($handlers)) {
            $newSocket->close();
            throw new Exception('Нет ни одного обработчика');
        }

        return call(function () use ($gateway, $client, $newSocket, $handlers): \Generator {



            /** @var Message $message */
            while ($message = yield $client->receive()) {

                try {

                    $text = yield $message->buffer();
                    foreach($handlers as [$handler, $protocol, $clientHandler, $responseProtocol]){

                        // работаем со строками и closure
                        if(!isset($protocol)){
                            $result = $handler($text, $newSocket);
                            if(is_string($result)){
                                yield $newSocket-> write($result);
                            } elseif($result instanceof \Generator){
                                yield from $result;
                            }
                            break;
                        }

                        try {
                            if (!$request = $protocol->isValidRequest($text)) continue;
                        } catch (Exception $e) {
                            continue;
                        }

                        // если обработчик просто функция
                        if(is_callable($handler)){
                            $result = $request-> handle($handler, $newSocket, $responseProtocol);
                            if(is_string($result)){
                                yield $newSocket-> write($result);
                            } elseif($result instanceof \Generator){
                                yield from $result;
                            }
                            break;
                        }

                        // будем искать обработчик по атрибутам
                        if(!key_exists($protocol::class, $this-> attributes)) continue;

                        $args = $protocol-> attributeArguments($request);

                        // если обработчик нужно искать по атрибутам
                        foreach($this-> attributes[$protocol::class] as [$attrHandler, $method, $arguments]){

                            if($arguments !== $args) continue;
                            if($handler !== $attrHandler) continue;

                            yield from $request-> handle([$handler, $method], $newSocket, $responseProtocol);
                            break 2;
                        }
                    }



                } catch (ByteStream\ClosedException $e) {
                    // если клиенты отключаются заносим в лог
                }

            }

        });
    }

}