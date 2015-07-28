<?php
/**
 * Customerbalance history model
 *
 * @method Icube_CustomerBalance_Model_Resource_Balance_History _getResource()
 * @method Icube_CustomerBalance_Model_Resource_Balance_History getResource()
 * @method int getBalanceId()
 * @method Icube_CustomerBalance_Model_Balance_History setBalanceId(int $value)
 * @method string getUpdatedAt()
 * @method Icube_CustomerBalance_Model_Balance_History setUpdatedAt(string $value)
 * @method int getAction()
 * @method Icube_CustomerBalance_Model_Balance_History setAction(int $value)
 * @method float getBalanceAmount()
 * @method Icube_CustomerBalance_Model_Balance_History setBalanceAmount(float $value)
 * @method float getBalanceDelta()
 * @method Icube_CustomerBalance_Model_Balance_History setBalanceDelta(float $value)
 * @method string getAdditionalInfo()
 * @method Icube_CustomerBalance_Model_Balance_History setAdditionalInfo(string $value)
 * @method int getIsCustomerNotified()
 * @method Icube_CustomerBalance_Model_Balance_History setIsCustomerNotified(int $value)
 *
 * @category    Icube
 * @package     Icube_CustomerBalance
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Icube_CustomerBalance_Model_Balance_History extends Mage_Core_Model_Abstract
{
    const ACTION_UPDATED  = 1;
    const ACTION_CREATED  = 2;
    const ACTION_USED     = 3;
    const ACTION_REFUNDED = 4;
    const ACTION_REVERTED = 5;

    /**
     * Initialize resource
     *
     */
    protected function _construct()
    {
        $this->_init('icube_customerbalance/balance_history');
    }

    /**
     * Available action names getter
     *
     * @return array
     */
    public function getActionNamesArray()
    {
        return array(
            self::ACTION_CREATED  => Mage::helper('icube_customerbalance')->__('Created'),
            self::ACTION_UPDATED  => Mage::helper('icube_customerbalance')->__('Updated'),
            self::ACTION_USED     => Mage::helper('icube_customerbalance')->__('Used'),
            self::ACTION_REFUNDED => Mage::helper('icube_customerbalance')->__('Refunded'),
            self::ACTION_REVERTED => Mage::helper('icube_customerbalance')->__('Reverted'),
        );
    }

    /**
     * Validate balance history before saving
     *
     * @return Icube_CustomerBalance_Model_Balance_History
     */
    protected function _beforeSave()
    {
        $balance = $this->getBalanceModel();
        if ((!$balance) || !$balance->getId()) {
            Mage::throwException(
                Mage::helper('icube_customerbalance')->__('Balance history cannot be saved without existing balance.')
            );
        }

        $this->addData(array(
            'balance_id'     => $balance->getId(),
            'updated_at'     => time(),
            'balance_amount' => $balance->getAmount(),
            'balance_delta'  => $balance->getAmountDelta(),
        ));

        switch ((int)$balance->getHistoryAction())
        {
            case self::ACTION_CREATED:
                // break intentionally omitted
            case self::ACTION_UPDATED:
                if (!$balance->getUpdatedActionAdditionalInfo()) {
                    if ($user = Mage::getSingleton('admin/session')->getUser()) {
                        if ($user->getUsername()) {
                            if (!trim($balance->getComment())){
                                $this->setAdditionalInfo(
                                    Mage::helper('icube_customerbalance')->__('By admin: %s.', $user->getUsername())
                                );
                            }else{
                                $this->setAdditionalInfo(
                                    Mage::helper('icube_customerbalance')->__('By admin: %1$s. (%2$s)', $user->getUsername(), $balance->getComment())
                                );
                            }
                        }
                    }
                } else {
                    $this->setAdditionalInfo($balance->getUpdatedActionAdditionalInfo());
                }
                break;
            case self::ACTION_USED:
                $this->_checkBalanceModelOrder($balance);
                $this->setAdditionalInfo(
                    Mage::helper('icube_customerbalance')->__('Order #%s', $balance->getOrder()->getIncrementId())
                );
                break;
            case self::ACTION_REFUNDED:
                $this->_checkBalanceModelOrder($balance);
                if ((!$balance->getCreditMemo()) || !$balance->getCreditMemo()->getIncrementId()) {
                    Mage::throwException(
                        Mage::helper('icube_customerbalance')->__('There is no creditmemo set to balance model.')
                    );
                }
                $this->setAdditionalInfo(
                    Mage::helper('icube_customerbalance')->__('Order #%s, creditmemo #%s', $balance->getOrder()->getIncrementId(), $balance->getCreditMemo()->getIncrementId())
                );
                break;
            case self::ACTION_REVERTED:
                $this->_checkBalanceModelOrder($balance);
                $this->setAdditionalInfo(
                    Mage::helper('icube_customerbalance')->__('Order #%s', $balance->getOrder()->getIncrementId())
                );
                break;
            default:
                Mage::throwException(
                    Mage::helper('icube_customerbalance')->__('Unknown balance history action code')
                );
                // break intentionally omitted
        }
        $this->setAction((int)$balance->getHistoryAction());

        return parent::_beforeSave();
    }

    /**
     * Send balance update if required
     *
     * @return Icube_CustomerBalance_Model_Balance_History
     */
    protected function _afterSave()
    {
        parent::_afterSave();

        // attempt to send email
        $this->setIsCustomerNotified(false);
        if ($this->getBalanceModel()->getNotifyByEmail()) {
            $storeId = $this->getBalanceModel()->getStoreId();
            $email = Mage::getModel('core/email_template')->setDesignConfig(array('store' => $storeId));
            $customer = $this->getBalanceModel()->getCustomer();
            $email->sendTransactional(
                Mage::getStoreConfig('customer/icube_customerbalance/email_template', $storeId),
                Mage::getStoreConfig('customer/icube_customerbalance/email_identity', $storeId),
                $customer->getEmail(), $customer->getName(),
                array(
                    'balance' => Mage::app()->getWebsite($this->getBalanceModel()->getWebsiteId())
                        ->getBaseCurrency()->format($this->getBalanceModel()->getAmount(), array(), false),
                    'name'    => $customer->getName(),
            ));
            if ($email->getSentSuccess()) {
                $this->getResource()->markAsSent($this->getId());
                $this->setIsCustomerNotified(true);
            }
        }

        return $this;
    }

    /**
     * Validate order model for balance update
     *
     * @param Mage_Sales_Model_Order $model
     */
    protected function _checkBalanceModelOrder($model)
    {
        if ((!$model->getOrder()) || !$model->getOrder()->getIncrementId()) {
            Mage::throwException(
                Mage::helper('icube_customerbalance')->__('There is no order set to balance model.')
            );
        }
    }

    /**
     * Retrieve history data items as array
     *
     * @param  string $customerId
     * @param string|null $websiteId
     * @return array
     */
    public function getHistoryData($customerId, $websiteId = null)
    {
        $result = array();
        /** @var $collection Icube_CustomerBalance_Model_Resource_Balance_History_Collection */
        $collection = $this->getCollection()->loadHistoryData($customerId, $websiteId);
        foreach($collection as $historyItem) {
            $result[] = $historyItem->getData();
        }
        return $result;
    }
}
