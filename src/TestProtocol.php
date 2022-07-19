<?php

namespace Bumax\Socket;

#[\Attribute]
class TestProtocol implements ProtocolInterface {


    function isValidRequest(string $str): Request
    {
        // принимаем подключения только в формате JSON
        $data = json_decode($str);
        // проверяем данные на валидность
        if ($data === null && json_last_error() !== JSON_ERROR_NONE)
            throw new \Exception('JSON is not valid');

        if(!isset($data-> action))
            throw new \Exception('EnhancedJSON is not valid');

        if(!is_object($data-> data))
            throw new \Exception('EnhancedJSON is not valid');

        $requestData = $data-> data;

        return (new Request($this, $requestData))-> setMetaData('action', $data-> action);
    }

    function requestMessage(Request $request): string
    {
        $data = $request-> data();
        if($action = $request-> getMetaData('action')) $data['action'] = $action;
        return json_encode($data);
    }

    function attributeArguments(Request $request):array
    {
        return [$request-> getMetaData('action')];
    }


    function errorMessage(string $message, object|array $data = null): string
    {
        return json_encode($data);
    }

    function successMessage(string $message, object|array $data = null): string
    {
        return json_encode($data);
    }

}