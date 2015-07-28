<?php
$installer = $this;
/* @var $installer Icube_GiftCardAccount_Model_Mysql4_Setup */
$installer->startSetup();

$installer->addAttribute('quote', 'gift_cards', array('type'=>'text'));

$installer->getConnection()->changeColumn($this->getTable('sales_flat_quote'),
    'gift_cards', 'gift_cards',
    'text CHARACTER SET utf8'
);


$installer->endSetup();
