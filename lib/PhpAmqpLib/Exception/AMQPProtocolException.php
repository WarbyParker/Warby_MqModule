<?php

class PhpAmqpLib_Exception_AMQPProtocolException extends \Exception implements PhpAmqpLib_Exception_AMQPExceptionInterface
{
    public function __construct($reply_code, $reply_text, $method_sig)
    {
        parent::__construct($reply_text,$reply_code);

        $this->amqp_reply_code = $reply_code; // redundant, but kept for BC
        $this->amqp_reply_text = $reply_text; // redundant, but kept for BC
        $this->amqp_method_sig = $method_sig;

        $ms = PhpAmqpLib_Helper_MiscHelper::methodSig($method_sig);

        $PROTOCOL_CONSTANTS_CLASS = PhpAmqpLib_Channel_AbstractChannel::$PROTOCOL_CONSTANTS_CLASS;
        $mn = isset($PROTOCOL_CONSTANTS_CLASS::$GLOBAL_METHOD_NAMES[$ms])
                ? $PROTOCOL_CONSTANTS_CLASS::$GLOBAL_METHOD_NAMES[$ms]
                : $mn = "";

        $this->args = array(
            $reply_code,
            $reply_text,
            $method_sig,
            $mn
        );
    }
}
