<?php

abstract class PhpAmqpLib_Wire_IO_AbstractIO
{
    abstract public function read($n);

    abstract public function write($data);

    abstract public function close();

    abstract public function select($sec, $usec);
}