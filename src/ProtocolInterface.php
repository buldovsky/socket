<?php

namespace Bumax\Socket;

interface ProtocolInterface {

    /**
     * Метод проверки корректности пришедшего запроса
     * @param string $str
     * @return Request экземпляр запроса
     */
    function isValidRequest(string $str): Request;

    /**
     * Создаем текстовое сообщение на основе запроса
     * @param Request $request
     * @return string
     */
    function requestMessage(Request $request): string;

    /**
     * Для конкретного запроса возвращаем массив аргументов,
     * для последующей сверки их с теми, что переданы в атрибутах
     * @param Request $request
     * @return array
     */
    function attributeArguments(Request $request): array;


    /**
     * Создаем текстовое представление сообщения об ошибке
     * @param string $message
     * @param array|object|null $data
     * @return string
     * @todo перенести в отдельный интерфейс
     */
    function errorMessage(string $message, array|object $data = null): string;

    /**
     * Создаем текстовое представление сообщения
     * @param string $message
     * @param array|object|null $data
     * @return string
     * @todo перенести в отдельный интерфейс
     */
    function successMessage(string $message, array|object $data = null): string;

}