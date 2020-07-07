<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen
require_once __DIR__ . '/../libs/library.php';  // globale Funktionen

class DysonDevice extends IPSModule
{
    use DysonCommon;
    use DysonLibrary;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('user', '');
        $this->RegisterPropertyString('password', '');
        $this->RegisterPropertyString('country', '');

        $this->RegisterPropertyString('serial', '');
        $this->RegisterPropertyString('product_type', '');

        /*
        $this->RegisterTimer('UpdateData', 0, 'Dyson_UpdateData(' . $this->InstanceID . ');');
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
        $this->RegisterMessage(0, IPS_KERNELSHUTDOWN);
         */

        $this->RegisterAttributeString('localPassword', '');
        $this->RegisterAttributeString('Auth', '');

        $this->RequireParent('{EE0D345A-CF31-428A-A613-33CE98E752DD}');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $s = $this->CheckPrerequisites();
        if ($s != '') {
            $this->SetStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');
        if ($user == '' || $password == '') {
            $this->SetStatus(self::$IS_UNAUTHORIZED);
            return;
        }

        $serial = $this->ReadPropertyString('serial');
        $product_type = $this->ReadPropertyString('product_type');
        if ($serial == '' || $product_type == '') {
            $this->SetStatus(self::$IS_NOPRODUCT);
            return;
        }

        $this->SetStatus(IS_ACTIVE);
    }

    private function CheckPrerequisites()
    {
        $s = '';
        $r = [];

        if (IPS_ModuleExists('{EE0D345A-CF31-428A-A613-33CE98E752DD}') == false) {
            $r[] = $this->Translate('Module MQTTClient');
        }

        if ($r != []) {
            $s = $this->Translate('The following system prerequisites are missing') . ': ' . implode(', ', $r);
        }

        return $s;
    }

    public function GetConfigurationForm()
    {
        $formElements = $this->GetFormElements();
        $formActions = $this->GetFormActions();
        $formStatus = $this->GetFormStatus();

        $form = json_encode(['elements' => $formElements, 'actions' => $formActions, 'status' => $formStatus]);
        if ($form == '') {
            $this->SendDebug(__FUNCTION__, 'json_error=' . json_last_error_msg(), 0);
            $this->SendDebug(__FUNCTION__, '=> formElements=' . print_r($formElements, true), 0);
            $this->SendDebug(__FUNCTION__, '=> formActions=' . print_r($formActions, true), 0);
            $this->SendDebug(__FUNCTION__, '=> formStatus=' . print_r($formStatus, true), 0);
        }
        return $form;
    }

    public function GetFormElements()
    {
        $formElements = [];

        $formElements[] = ['type' => 'Label', 'caption' => 'Dyson Device'];

        $items = [];
        $items[] = ['type' => 'ValidationTextBox', 'name' => 'user', 'caption' => 'User'];
        $items[] = ['type' => 'ValidationTextBox', 'name' => 'password', 'caption' => 'Password'];
        $opts_country = [
            [
                'caption' => $this->Translate('England'),
                'value'   => 'en'
            ],
            [
                'caption' => $this->Translate('Germany'),
                'value'   => 'de'
            ],
        ];
        $items[] = [
            'type'    => 'Select',
            'name'    => 'country',
            'caption' => 'Country',
            'options' => $opts_country
        ];
        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => $items,
            'caption' => 'Basic configuration (don\'t change)',
        ];

        $formElements[] = ['name' => 'product_type', 'type' => 'ValidationTextBox', 'caption' => 'Product type'];

        return $formElements;
    }

    protected function GetFormActions()
    {
        $formActions = [];

        return $formActions;
    }
}
