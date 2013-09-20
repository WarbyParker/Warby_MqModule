<?php

class Warby_Mqmodule_Helper_Data extends Mage_Core_Helper_Abstract {

	/**
	 * Maps an order and its addresses, payment, and items
	 * to a JSON string.
	 *
	 * @return string
	 */
    public function orderToJSON($order) {
        $items = array();
        foreach ($order->getItemsCollection() as $item) {
            $items[] = $item->toArray();
        }

        if (!$order->getShippingAddress()) {
             $shipping = $order->getBillingAddress();
        }
        else  {
             $shipping = $order->getShippingAddress();
        }

        $addresses = array(
            "billing-address" => $order->getBillingAddress()->toArray(),
            "shipping-address" => $shipping
            );

        $payment = $order->getPayment()->toArray();

        $msg = array(
            "order" => $order->toArray(),
            "items" => $items,
            "addresses" => $addresses,
            "payment" => $payment
            );

        return json_encode($msg);
    }

    /**
     * Publish an order confirmation message to the queue.
     * 
     * @param $observer	Either a varien object or an event object, depending on where it's coming from
     * @param $key		Routing key to where the message will be published	
     * @param $type		Content type of the message
     */
    public function publishMessage($observer, $key, $type="application/json") {

        //Converting observer to a proper order object
        if($observer instanceof Varien_Event_Observer) {
            $order = $observer->getEvent()->getOrder();
        } else if($observer instanceof Varien_Object) {
            $order = $observer;
        }

        //Set mqmodule_status to 0 - indicating confirmation has not been sent yet
        $order->setMqmoduleStatus(0);

        $msg_body = $this->orderToJSON($order);
        extract($this->getConfigs());

        try {
            //Establishing connection and channel with appropriate configs
            $conn = new PhpAmqpLib_Connection_AMQPConnection($host, $port, $user, $password, $vhost);
            $ch = $conn->channel();

            //Set up and send the message
            $msg = new PhpAmqpLib_Message_AMQPMessage($msg_body, array('content_type' => $type, 'delivery_mode' => 2));
            $ch->basic_publish($msg, $exchange, $key);
            $order->setMqmoduleStatus(1);

            //Closing the channel and connection
            $ch->close();
            $conn->close();
        } catch(Exception $e) {
            Mage::logException($e);
            Mage::log("Order confirmation failed to publish - mqmodule_status still set to 0. ");
        }
    }

    /**
     * Get configs from Magento Admin for the MQ module as an array
     *
     * @return array
     */
    public function getConfigs() {
        $configs = array(
            'exchange' => Mage::getStoreConfig('mqmodule/mqmodule/exchange'),
            'host' => Mage::getStoreConfig('mqmodule/mqmodule/host'),
            'user' => Mage::getStoreConfig('mqmodule/mqmodule/user'),
            'password' => Mage::getStoreConfig('mqmodule/mqmodule/password'),
            'port' => Mage::getStoreConfig('mqmodule/mqmodule/port'),
            'vhost' => Mage::getStoreConfig('mqmodule/mqmodule/virtualhost'),
            'active' => Mage::getStoreConfig('mqmodule/mqmodule/active'),
            'queue' => Mage::getStoreConfig('mqmodule/mqmodule/queue'),
            'batchsize' => Mage::getStoreConfig('mqmodule/mqmodule/batchsize')
            );

        return $configs;
    }
}
