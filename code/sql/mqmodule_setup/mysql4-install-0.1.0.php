<?php

$sales_setup = new Mage_Sales_Model_Mysql4_Setup('sales_setup');
$sales_setup->addAttribute('order', 'mqmodule_status', array('type'=>'varchar', 'default_value'=> null));
