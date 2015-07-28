<?php
class Icube_GiftCardAccount_Block_Adminhtml_Giftcardaccount_Edit_Tab_Send extends Mage_Adminhtml_Block_Widget_Form
{
    public function initForm()
    {
        $form = new Varien_Data_Form();
        $form->setHtmlIdPrefix('_send');

        $model = Mage::registry('current_giftcardaccount');

        $fieldset = $form->addFieldset('base_fieldset',
            array('legend'=>Mage::helper('icube_giftcardaccount')->__('Send Gift Card'))
        );

/*
        $emailTemplates = array();
        foreach (Mage::getModel('adminhtml/system_config_source_email_template')->toOptionArray() as $option) {
            $emailTemplates[$option['value']] = $option['label'];
        }

        $fieldset->addField('email_template', 'select', array(
            'label'     => Mage::helper('icube_giftcardaccount')->__('Email Template'),
            'title'     => Mage::helper('icube_giftcardaccount')->__('Email Template'),
            'name'      => 'email_template',
            'options'   => $emailTemplates,
        ));
*/

        $fieldset->addField('recipient_email', 'text', array(
            'label'     => Mage::helper('icube_giftcardaccount')->__('Recipient Email'),
            'title'     => Mage::helper('icube_giftcardaccount')->__('Recipient Email'),
            'class'     => 'validate-email',
            'name'      => 'recipient_email',
        ));

        $fieldset->addField('recipient_name', 'text', array(
            'label'     => Mage::helper('icube_giftcardaccount')->__('Recipient Name'),
            'title'     => Mage::helper('icube_giftcardaccount')->__('Recipient Name'),
            'name'      => 'recipient_name',
        ));

        $field = $fieldset->addField('store_id', 'select', array(
            'name'     => 'recipient_store',
            'label'    => Mage::helper('icube_customerbalance')->__('Send Email from the Following Store View'),
            'title'    => Mage::helper('icube_customerbalance')->__('Send Email from the Following Store View'),
            'after_element_html' => $this->_getStoreIdScript()
        ));
        $renderer = $this->getLayout()->createBlock('adminhtml/store_switcher_form_renderer_fieldset_element');
        $field->setRenderer($renderer);

        $fieldset->addField('action', 'hidden', array(
            'name'      => 'send_action',
        ));

        $form->setValues($model->getData());
        $this->setForm($form);
        return $this;
    }

    protected function _getStoreIdScript()
    {
        $websiteStores = array();
        foreach (Mage::app()->getWebsites() as $websiteId => $website) {
            $websiteStores[$websiteId] = array();
            foreach ($website->getGroups() as $groupId => $group) {
                $websiteStores[$websiteId][$groupId] = array(
                    'name' => $group->getName()
                );
                foreach ($group->getStores() as $storeId => $store) {
                    $websiteStores[$websiteId][$groupId]['stores'][] = array(
                        'id'   => $storeId,
                        'name' => $store->getName(),
                    );
                }
            }
        }

        $websiteStores = Mage::helper('core')->jsonEncode($websiteStores);

        $result  = '<script type="text/javascript">//<![CDATA[' . "\n";
        $result .= "var websiteStores = $websiteStores;";
        $result .= "Event.observe('_infowebsite_id', 'change', setCurrentStores);";
        $result .= "setCurrentStores();";
        $result .= 'function setCurrentStores(){
            var wSel = $("_infowebsite_id");
            var sSel = $("_sendstore_id");

            sSel.innerHTML = \'\';
            var website = wSel.options[wSel.selectedIndex].value;
            if (websiteStores[website]) {
                groups = websiteStores[website];
                for (groupKey in groups) {
                    group = groups[groupKey];
                    optionGroup = document.createElement("OPTGROUP");
                    optionGroup.label = group["name"];
                    sSel.appendChild(optionGroup);

                    stores = group["stores"];
                    for (i=0; i < stores.length; i++) {
                        var option = document.createElement("option");
                        option.appendChild(document.createTextNode(stores[i]["name"]));
                        option.setAttribute("value", stores[i]["id"]);
                        optionGroup.appendChild(option);
                    }
                }
            }
            else {
              var option = document.createElement("option");
              option.appendChild(document.createTextNode(\''.Mage::helper('icube_giftcardaccount')->__('-- First Please Select a Website --').'\'));
              sSel.appendChild(option);
            }
        }
        //]]></script>';

        return $result;
    }
}
