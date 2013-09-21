<?php
/**
 * @category Warby
 * @package Warby_MqModule
 * @author Warby Parker (Jarad Delorenzo, Jeff Uthaichai, Jesse Zlata, Karina Ruzinov) <oss@warbyparker.com>
 * @copyright Massachusetts Institute of Technology License (MITL)
 * @license  http://opensource.org/licenses/MIT
 * Class Warby_Mqmodule_Model_Order_Confirmation_Observer
 */
class Warby_Mqmodule_Model_Order_Confirmation_Observer {
    /**
     * Convert order to json and push to Rabbit
     *
     */
    public function __construct() {
    }

    /**
      * This method is invoked when an order placement event is fired off
      */
    public function enqueue($order) {
        $configs = Mage::helper('mqmodule')->getConfigs();

        if($configs['active'] == 1) {
            $key = Mage::getStoreConfig('mqmodule/mqmodule/salesroutingkey');
            Mage::helper('mqmodule')->publishMessage($order, $key);
        }
    }
}
