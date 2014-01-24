<?php
/**
 * @category Warby
 * @package Warby_MqModule
 * @author Warby Parker (Jarad Delorenzo, Jeff Uthaichai, Jesse Zlata, Karina Ruzinov) <oss@warbyparker.com>
 * @copyright Massachusetts Institute of Technology License (MITL)
 * @license  http://opensource.org/licenses/MIT
 * Class Warby_Mqmodule_Model_Rabbitlistener
 */

//callback methods to process messages
function process_message($msg) {
    sleep(10);

    //ack the message
    $msg->delivery_info['channel']->
        basic_ack($msg->delivery_info['delivery_tag']);
}

function shutdown($ch, $conn) {
    $ch->close();
    $conn->close();
}

/**
  * This will listen to a queue that was defined in the admin panel then
  * pass the messages to a processing class.
  *
  * This class limits the amount of messages to process via the batch size
  * field. Once the batch size if met the channel and connection to rabbit
  * will close and the class will have to be called again to process the next batch.
  *
  */
class Warby_Mqmodule_Model_Rabbitlistener {
    protected $conn, $ch, $batchsize;

    /* 
     * This function is currently not being used due to the fact that
     * it continously creates queues
     */
    public function listen() {
        //Configs from RabbitMQ Module
        extract(Mage::helper('mqmodule')->getConfigs());

        if($active == 1) {

            $consumer_tag = 'consumer';

            //create connection to rabbit and open a channel
            $conn = new PhpAmqpLib_Connection_AMQPConnection($host, $port, $user, $password, $vhost);
            $ch = $conn->channel();

            //define channel and attach queue and exchange
            $ch->queue_declare($queue, false, true, false, false);
            $ch->exchange_declare($exchange, 'topic', false, true, false);
            $ch->queue_bind($queue, $exchange);

            register_shutdown_function('shutdown', $ch, $conn);

            //create consumer on the queue that passes messages to callback function
            $ch->basic_consume($queue, $consumer_tag, false, false, false, false, 'process_message');

            while(count($ch->callbacks)) {
                $ch->wait(null,false,10);
            }

            $ch->basic_cancel($consumer_tag);

        }
    }

    /**
     * This method iterates through orders which have an mqmodule_status
     * of '0'.  It will then attempt to republish these orders which should
     * have been published previously, but didn't for one reason or another.
     * This method is called every 5 minutes in a scheduled cron job.
     */
    public function processPending() {
        $orders = Mage::getModel('sales/order')->getCollection()
            ->addAttributeToFilter('mqmodule_status', array('eq'=>0));

        $key = Mage::getStoreConfig('mqmodule/mqmodule/salesroutingkey');
        if($orders instanceof Varien_Data_Collection) {
            foreach($orders as $i => $order) {
                Mage::helper('mqmodule')->publishMessage($order, $key);
            }
        }
    }
}
