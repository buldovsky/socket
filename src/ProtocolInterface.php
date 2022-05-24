<?php

namespace Bumax\Protocol;

use Bumax\Request;

interface ProtocolInterface {

    function isValidRequest(string $str): Request;

    function requestMessage(Request $request): string;

    /**
     * Для конкретного запроса возвращаем массив аргументов,
     * для последующей сверки их с теми, что переданы в атрибутах
     * @param Request $request
     * @return array
     */
    function attributeArguments(Request $request): array;

    // это можно вынести в другой интейфейс
    function errorMessage(string $message, array|object $data = null): string;

    function successMessage(string $message, array|object $data = null): string;

}