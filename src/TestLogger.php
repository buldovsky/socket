<?php

namespace Bumax\Socket;

use Psr\Log\LoggerInterface;

class TestLogger implements LoggerInterface
{

    function alert(\Stringable|string $message, array $context = [])
    {
    }

    function critical(\Stringable|string $message, array $context = [])
    {
    }

    function debug(\Stringable|string $message, array $context = [])
    {
    }

    function emergency(\Stringable|string $message, array $context = [])
    {
    }

    function error(\Stringable|string $message, array $context = [])
    {
    }

    function info(\Stringable|string $message, array $context = [])
    {
    }

    function log($level, \Stringable|string $message, array $context = [])
    {
    }

    function notice(\Stringable|string $message, array $context = [])
    {
    }

    function warning(\Stringable|string $message, array $context = [])
    {
    }

}