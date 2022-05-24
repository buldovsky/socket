<?php

namespace Bumax\Socket;

use Amp\Socket\SocketException;
use function Amp\asyncCall;
use Amp\Socket\Socket;
use Amp\Loop;
use Amp\Socket\Server as AmpServer;
use Amp\ByteStream;

use Exception;

class Server implements ServerInterface
{

    private string $host;
    private int $port;

    protected AmpServer $server;
    protected mixed $onStartHandler;
    protected array $handlers = [];

    protected array $attributes = [];
    protected array $clients = [];

    protected array $clientConnectHandlers = [];
    protected array $clientDisconnectHandlers = [];


    function handler(
        callable|object $handler,
        ProtocolInterface $protocol = null,
        callable $clientHandler = null,
        ProtocolInterface $responseProtocol = null,
    ):self {

        /**
         * Реализуем маршрутизацию на атрибутах протоколов
         */
        if(is_object($handler) && !is_callable($handler)){

            if(!isset($protocol)) throw new \InvalidArgumentException('Protocol is required');

            $reflection = new \ReflectionObject($handler);
            foreach ($reflection->getMethods() as $method) {

                $methodName = $method->getName();

                foreach($method->getAttributes() as $attribute){
                    if(!is_subclass_of($attribute->getName(), ProtocolInterface::class)) continue;

                    if(!key_exists($attribute->getName(), $this-> attributes))
                        $this-> attributes[$attribute->getName()] = [];

                    $this-> attributes [$attribute->getName()] []= [$handler, $methodName, $attribute-> getArguments()];

                }
            }
        }

        $this->handlers [] = [$handler, $clientHandler, $protocol, $responseProtocol];

        return $this;
    }

    public function onStart(callable $callable):self
    {
        $this->onStartHandler = $callable;
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


    function listen(string $host, int $port = 0):self
    {

        $this-> host = $host;
        $this-> port = $port;

        return $this;
    }

    function start():self
    {
        asyncCall([$this, 'run']);

        return $this;
    }

    /**
     * @todo нужно проверять, что сервер уже запущен
     * @return int
     */
    function getPort(): int
    {
        return $this->server->getAddress()->getPort();
    }

    function run():iterable
    {

        try {

            $this->server = AmpServer::listen("{$this-> host}:{$this-> port}");

        } catch(SocketException $e){

            throw $e;

        }

        if(isset($this->onStartHandler)) asyncCall($this->onStartHandler, $this);

        /**
         * обрабатываем все подключения
         * @var Socket $socket
         */
        while ($socket = yield $this->server->accept()) {

            $handlers = [];

            try {

                $client = new Client($socket, $this);
                $this->clients ["{$client->getHost()}:{$client->getPort()}"] = $client;
                foreach ($this->clientConnectHandlers as $handler) asyncCall($handler, $client);

                /**
                 * оставляем только подходящие для этого клиетна обработчики и протоколы
                 * @var callable|object $handler
                 * @var callable $clientHandler
                 * @var ProtocolInterface $protocol
                 * @var ProtocolInterface $responseProtocol
                 */
                foreach ($this->handlers as [$handler, $clientHandler, $protocol, $responseProtocol]){
                    if (isset($clientHandler) && !$clientHandler($client)) continue;
                    $handlers []= [$handler, $protocol, $responseProtocol];
                }

                if(empty($handlers)) {
                    $socket->close();
                    throw new Exception('Нет ни одного обработчика');
                }

                while (null !== $chunk = yield $socket->read()) {

                    foreach($handlers as [$handler, $protocol, $responseProtocol]){

                        // работаем со строками и closure
                        if(!isset($protocol)){
                            $result = $handler($chunk, $client);
                            if(is_string($result)){
                                yield $client-> write($result);
                            } elseif($result instanceof \Generator){
                                yield from $result;
                            }
                            break;
                        }

                        try {
                            if (!$request = $protocol->isValidRequest($chunk)) continue;
                        } catch (Exception $e) {
                            continue;
                        }

                        // если обработчик просто функция
                        if(is_callable($handler)){
                            $result = $request-> handle($handler, $client, $responseProtocol);
                            if(is_string($result)){
                                yield $client-> write($result);
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

                            yield from $request-> handle([$handler, $method], $client, $responseProtocol);
                            break 2;
                        }
                    }
                }

            } catch (ByteStream\ClosedException $e) {
                // это очень часто случается, так как люди передающие нам данные
                // сами отключаются не успев получить ответ
            }
        }

    }

}