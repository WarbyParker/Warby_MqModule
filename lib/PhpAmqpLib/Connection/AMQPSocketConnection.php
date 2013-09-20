<?php

class PhpAmqpLib_Connection_AMQPSocketConnection extends PhpAmqpLib_Connection_AbstractConnection
{
    public function __construct($host, $port,
                                $user, $password,
                                $vhost="/",$insist=false,
                                $login_method="AMQPLAIN",
                                $login_response=null,
                                $locale="en_US",
                                $timeout = 3)
    {
        $io = new PhpAmqpLib_Wire_IO_SocketIO($host, $port, $timeout);

        parent::__construct($user, $password, $vhost, $insist, $login_method, $login_response, $locale, $io);
    }

}
