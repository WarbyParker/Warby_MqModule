<?php

class PhpAmqpLib_Channel_AbstractChannel
{
    public static $PROTOCOL_CONSTANTS_CLASS;

    protected $debug;
    /**
     *
     * @var AbstractConnection
     */
    protected $connection;

    protected $protocolVersion;

    protected $protocolWriter;

    protected $waitHelper;

    protected $methodMap;

    protected $channel_id;

    /**
     * @param \PhpAmqpLib\Connection\AbstractConnection $connection
     * @param                                       $channel_id
     */
    public function __construct(PhpAmqpLib_Connection_AbstractConnection $connection, $channel_id)
    {
        $this->connection = $connection;
        $this->channel_id = $channel_id;
        $connection->channels[$channel_id] = $this;
        $this->frame_queue = array();  // Lower level queue for frames
        $this->method_queue = array(); // Higher level queue for methods
        $this->auto_decode = false;
        $this->debug = defined('AMQP_DEBUG') ? AMQP_DEBUG : false;

        $this->protocolVersion = defined('AMQP_PROTOCOL') ? AMQP_PROTOCOL : '0.9.1';
        switch ($this->protocolVersion) {
        case '0.9.1':
            self::$PROTOCOL_CONSTANTS_CLASS = 'PhpAmqpLib_Wire_Constants091';
            $c = self::$PROTOCOL_CONSTANTS_CLASS;
            $this->amqp_protocol_header = $c::$AMQP_PROTOCOL_HEADER;
            $this->protocolWriter = new PhpAmqpLib_Helper_Protocol_Protocol091();
            $this->waitHelper = new PhpAmqpLib_Helper_Protocol_Wait091();
            $this->methodMap = new PhpAmqpLib_Helper_Protocol_MethodMap091();
            break;
        case '0.8':
            self::$PROTOCOL_CONSTANTS_CLASS = 'PhpAmqpLib_Wire_Constants080';
            $c = self::$PROTOCOL_CONSTANTS_CLASS;
            $this->amqp_protocol_header = $c::$AMQP_PROTOCOL_HEADER;
            $this->protocolWriter = new PhpAmqpLib_Helper_Protocol_Protocol080();
            $this->waitHelper = new PhpAmqpLib_Helper_Protocol_Wait080();
            $this->methodMap = new PhpAmqpLib_Helper_Protocol_MethodMap080();
            break;
        default:
            throw new PhpAmqpLib_Exception_AMQPRuntimeException('Protocol: ' . $this->protocolVersion . ' not implemented.');
        }
    }

    public function getChannelId()
    {
        return $this->channel_id;
    }

    public function dispatch($method_sig, $args, $content)
    {
        if (!$this->methodMap->valid_method($method_sig)) {
            throw new PhpAmqpLib_Exception_AMQPRuntimeException("Unknown AMQP method $method_sig");
        }

        $amqp_method = $this->methodMap->get_method($method_sig);

        if (!method_exists($this, $amqp_method)) {
            throw new PhpAmqpLib_Exception_AMQPRuntimeException("Method: $amqp_method not implemented by class: " . get_class($this));
        }

        if ($content == null) {
            return call_user_func(array($this, $amqp_method), $args);
        }

        return call_user_func(array($this, $amqp_method), $args, $content);
    }

    public function next_frame($timeout = 0)
    {
        if ($this->debug) {
          PhpAmqpLib_Helper_MiscHelper::debug_msg("waiting for a new frame");
        }

        if (!empty($this->frame_queue)) {
            return array_shift($this->frame_queue);
        }

        return $this->connection->wait_channel($this->channel_id, $timeout);
    }

    protected function send_method_frame($method_sig, $args="")
    {
        $this->connection->send_channel_method_frame($this->channel_id, $method_sig, $args);
    }

    public function wait_content()
    {
        $frm = $this->next_frame();
        $frame_type = $frm[0];
        $payload = $frm[1];

        if ($frame_type != 2) {
            throw new PhpAmqpLib_Exception_AMQPRuntimeException("Expecting Content header");
        }

        $payload_reader = new PhpAmqpLib_Wire_AMQPReader(substr($payload,0,12));
        $class_id = $payload_reader->read_short();
        $weight = $payload_reader->read_short();

        $body_size = $payload_reader->read_longlong();
        $msg = new PhpAmqpLib_Message_AMQPMessage();
        $msg->load_properties(substr($payload,12));

        $body_parts = array();
        $body_received = 0;
        while (bccomp($body_size,$body_received) == 1) {
            $frm = $this->next_frame();
            $frame_type = $frm[0];
            $payload = $frm[1];

            if ($frame_type != 3) {
                $PROTOCOL_CONSTANTS_CLASS = self::$PROTOCOL_CONSTANTS_CLASS;
                throw new PhpAmqpLib_Exception_AMQPRuntimeException("Expecting Content body, received frame type $frame_type ("
                        .$PROTOCOL_CONSTANTS_CLASS::$FRAME_TYPES[$frame_type].")");
            }

            $body_parts[] = $payload;
            $body_received = bcadd($body_received, strlen($payload));
        }

        $msg->body = implode("",$body_parts);

        if ($this->auto_decode && isset($msg->content_encoding)) {
            try {
                $msg->body = $msg->body->decode($msg->content_encoding);
            } catch (\Exception $e) {
              if ($this->debug) {
                PhpAmqpLib_Helper_MiscHelper::debug_msg("Ignoring body decoding exception: " . $e->getMessage());
              }
            }
        }

        return $msg;
    }

    /**
     * Wait for some expected AMQP methods and dispatch to them.
     * Unexpected methods are queued up for later calls to this PHP
     * method.
     */
    public function wait($allowed_methods=null, $non_blocking = false, $timeout = 0)
    {
        $PROTOCOL_CONSTANTS_CLASS = self::$PROTOCOL_CONSTANTS_CLASS;

        if ($allowed_methods && $this->debug) {
            PhpAmqpLib_Helper_MiscHelper::debug_msg("waiting for " . implode(", ", $allowed_methods));
        } elseif ($this->debug) {
            PhpAmqpLib_Helper_MiscHelper::debug_msg("waiting for any method");
        }

        //Process deferred methods
        foreach ($this->method_queue as $qk=>$queued_method) {
          if ($this->debug) {
            PhpAmqpLib_Helper_MiscHelper::debug_msg("checking queue method " . $qk);
          }

            $method_sig = $queued_method[0];
            if ($allowed_methods==null || in_array($method_sig, $allowed_methods)) {
                unset($this->method_queue[$qk]);

                if ($this->debug) {
                  PhpAmqpLib_Helper_MiscHelper::debug_msg("Executing queued method: $method_sig: " .
                            $PROTOCOL_CONSTANTS_CLASS::$GLOBAL_METHOD_NAMES[PhpAmqpLib_Helper_MiscHelper::methodSig($method_sig)]);
                }

                return $this->dispatch($queued_method[0],
                                       $queued_method[1],
                                       $queued_method[2]);
            }
        }

        // No deferred methods?  wait for new ones
        while (true) {
            $frm = $this->next_frame($timeout);
            $frame_type = $frm[0];
            $payload = $frm[1];

            if ($frame_type != 1) {
                throw new PhpAmqpLib_Exception_AMQPRuntimeException("Expecting AMQP method, received frame type: $frame_type ("
                        .$PROTOCOL_CONSTANTS_CLASS::$FRAME_TYPES[$frame_type].")");
            }

            if (strlen($payload) < 4) {
                throw new PhpAmqpLib_Exception_AMQPOutOfBoundsException("Method frame too short");
            }

            $method_sig_array = unpack("n2", substr($payload,0,4));
            $method_sig = "" . $method_sig_array[1] . "," . $method_sig_array[2];
            $args = new PhpAmqpLib_Wire_AMQPReader(substr($payload,4));

            if ($this->debug) {
              PhpAmqpLib_Helper_MiscHelper::debug_msg("> $method_sig: " . $PROTOCOL_CONSTANTS_CLASS::$GLOBAL_METHOD_NAMES[PhpAmqpLib_Helper_MiscHelper::methodSig($method_sig)]);
            }

            if (in_array($method_sig, $PROTOCOL_CONSTANTS_CLASS::$CONTENT_METHODS)) {
                $content = $this->wait_content();
            } else {
                $content = null;
            }

            if ($allowed_methods == null ||
                in_array($method_sig,$allowed_methods) ||
                in_array($method_sig, $PROTOCOL_CONSTANTS_CLASS::$CLOSE_METHODS)
                ) {
                return $this->dispatch($method_sig, $args, $content);
            }

            // Wasn't what we were looking for? save it for later
            if ($this->debug) {
              PhpAmqpLib_Helper_MiscHelper::debug_msg("Queueing for later: $method_sig: " . $PROTOCOL_CONSTANTS_CLASS::$GLOBAL_METHOD_NAMES[PhpAmqpLib_Helper_MiscHelper::methodSig($method_sig)]);
            }
            $this->method_queue[] = array($method_sig, $args, $content);

            if ($non_blocking) {
                break;
            };
        }
    }
}
