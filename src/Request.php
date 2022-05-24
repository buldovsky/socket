<?php

namespace Bumax\Socket;

use Exception;

class Request
{

    protected ?ClientInterface $client;
    protected ?ProtocolInterface $responseProtocol;

    protected array $metaData = [];

    function __construct(
        protected ProtocolInterface $protocol,
        protected object|array $data
    ){

    }

    function getMetaData(string $key):mixed
    {
        return $this->metaData[$key] ?? null;
    }

    function setMetaData(string $key, mixed $value):self
    {
        $this->metaData[$key] = $value;
        return $this;
    }

    function data():object|array{
        return $this-> data;
    }

    function handle(callable $callable, ClientInterface $client = null, ProtocolInterface $responseProtocol = null)
    {
        $this-> client = $client;
        $this-> responseProtocol = $responseProtocol;

        try {
            return call_user_func($callable, $this-> data, $this, $client);
        } catch(Exception $e){
            throw $e;
        } finally {
            $this-> client = null;
            $this-> responseProtocol = null;
        }

    }

    function responseError(...$args)
    {
        if(!$this-> checkResponse()) return;
        return $this-> client-> send($this->responseProtocol->errorMessage(...$args));
    }

    function responseSuccess(...$args)
    {
        if(!$this-> checkResponse()) return;
        return $this-> client-> send($this->responseProtocol->successMessage(...$args));
    }

    protected function checkResponse():bool
    {
        if(!isset($this-> client)) return false;

        // по умолчанию отвечаем темже протоколом, что и получили
        if(!isset($this-> responseProtocol))
            $this-> responseProtocol = $this-> protocol;

        return true;
    }

}