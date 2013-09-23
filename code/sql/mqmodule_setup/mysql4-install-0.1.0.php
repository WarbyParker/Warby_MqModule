<?php
/**
 * @category Warby
 * @package Warby_MqModule
 * @author Warby Parker (Jarad Delorenzo, Jeff Uthaichai, Jesse Zlata, Karina Ruzinov) <oss@warbyparker.com>
 * @copyright Massachusetts Institute of Technology License (MITL)
 * @license  http://opensource.org/licenses/MIT
 */

$sales_setup = new Mage_Sales_Model_Mysql4_Setup('sales_setup');
$sales_setup->addAttribute('order', 'mqmodule_status', array('type'=>'varchar', 'default_value'=> null));
