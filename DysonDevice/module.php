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

        $this->RegisterPropertyString('intervall', '');

        $this->RegisterPropertyInteger('ReloadConfigInterval', '60');
        $this->RegisterPropertyInteger('UpdateStatusInterval', '1');

        $this->RegisterTimer('ReloadConfig', 0, 'Dyson_ReloadConfig(' . $this->InstanceID . ');');
        $this->RegisterTimer('UpdateStatus', 0, 'Dyson_UpdateStatus(' . $this->InstanceID . ');');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);

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

        $cID = $this->GetConnectionID();
        if ($cID != false) {
            $this->RegisterMessage($cID, IM_CHANGESTATUS);
            $this->SetTimerInterval('ReloadConfig', 1000);
            $this->SetTimerInterval('UpdateStatus', 2 * 1000);
            $this->SubscribeStatus();
        }
    }

    private function GetConnectionID()
    {
        $inst = IPS_GetInstance($this->InstanceID);
        $cID = $inst['ConnectionID'];

        return $cID;
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
        $items[] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'user',
            'caption' => 'User'
        ];
        $items[] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'password',
            'caption' => 'Password'
        ];
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
        $items[] = [
            'type'    => 'Label',
            'caption' => ''
        ];
        $items[] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'product_type',
            'caption' => 'Product type'
        ];
        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => $items,
            'caption' => 'Basic configuration (don\'t change)',
        ];

        $items = [];
        $items[] = [
            'type'    => 'Label',
            'caption' => ''
        ];
        $items[] = [
            'type'    => 'Label',
            'caption' => 'Load configuration every X minutes'
        ];
        $items[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'ReloadConfigInterval',
            'caption' => 'Minutes'
        ];
        $items[] = [
            'type'    => 'Label',
            'caption' => 'Update status every X minutes'
        ];
        $items[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'UpdateStatusInterval',
            'caption' => 'Minutes'
        ];
        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => $items,
            'caption' => 'Call settings'
        ];

        return $formElements;
    }

    protected function GetFormActions()
    {
        $formActions = [];

        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Relogin',
            'onClick' => 'Dyson_Relogin($id);'
        ];
        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Reload Config',
            'onClick' => 'Dyson_ManualReloadConfig($id);'
        ];

        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Subscribe',
            'onClick' => 'Dyson_RenewSubscribe($id);'
        ];
        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Update Status',
            'onClick' => 'Dyson_ManualUpdateStatus($id);'
        ];

        return $formActions;
    }

    public function GetConfigurationForParent()
    {
        $serial = $this->ReadPropertyString('serial');
        $oldPw = $this->ReadAttributeString('localPassword');

        // siehe DysonConfig::getConfiguratorValues
        $formElements = [
            'User'          => $serial,
            'Password'      => $oldPw,
            'ModuleType'    => 2,
            'script'        => 0,
            'TLS'           => false,
            'AutoSubscribe' => false,
            'MQTTVersion'   => 2,
            'ClientID'      => 'symcon',
            'PingInterval'  => 30
        ];
        return json_encode($formElements);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug(__FUNCTION__, 'SenderID=' . $SenderID . ', MessageID=' . $Message . ', Data=' . print_r($Data, true), 0);

        switch ($Message) {
            case IM_CHANGESTATUS:
                if ($SenderID == $this->GetConnectionID() && $Data[0] == IS_ACTIVE) {
                    $this->SendDebug(__FUNCTION__, 'MQTTClient changed to active', 0);
                    $this->SubscribeStatus();
                }
                break;
            case IPS_KERNELSTARTED:
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'Unknown Message' . $Message, 0);
                break;
        }
    }

    public function Relogin()
    {
        $auth = $this->doLogin(true);
        echo $this->Translate(($auth == false ? 'Login failed' : 'Login successful'));
    }

    public function ManualReloadConfig()
    {
        $localPassword = $this->loadConfig();
        echo $this->Translate(($localPassword == false ? 'Load config failed' : 'Load config succeeded'));
    }

    public function ReloadConfig()
    {
        $min = $this->ReadPropertyInteger('ReloadConfigInterval');

        $this->loadConfig();
        $this->SetTimerInterval('ReloadConfig', $min * 60 * 1000);
    }

    private function loadConfig()
    {
        $oldPw = $this->ReadAttributeString('localPassword');

        $serial = $this->ReadPropertyString('serial');
        $device = $this->getDevice($serial);
        if ($device != false) {
            $this->SendDebug(__FUNCTION__, 'device=' . print_r($device, true), 0);
            $newPw = $this->decryptPassword($device['LocalCredentials']);
            if ($newPw != false && $newPw != $oldPw) {
                $this->WriteAttributeString('localPassword', $newPw);

                /*
                    Ausnahme zu Punkt 13 der Reviewrichtlinien, wurde als Ausnahme in Mail vom 07.07.2020 von Niels genehmigt
                    Grund: das Passwort, das im MQTTClient als Property gesetzt werden muss, kann sich ändern. Der aktuelle
                    Wert wird zyklisch per HTTP von der Dyson-Cloud geholt und bei Änderungen in der MQTTClient-Instanz gesetzt.
                 */
                $cID = $this->GetConnectionID();
                $this->SendDebug(__FUNCTION__, 'set property "Password" of instance ' . $cID . ' to "' . $newPw . '"', 0);
                if ($cID != false) {
                    if (IPS_SetProperty($cID, 'Password', $newPw)) {
                        IPS_ApplyChanges($cID);
                    }
                }
            }
        }
    }

    private function DecodeState($payload)
    {
        $ts = strtotime($payload['time']);
        $this->SendDebug(__FUNCTION__, 'time=' . date('d.m.y H:i:s', $ts), 0);

        $rssi = (int) $this->GetArrayElem($payload, 'rssi', 0);
        $this->SendDebug(__FUNCTION__, 'rssi=' . $rssi, 0);

        $fpwr = $this->GetArrayElem($payload, 'product-state.fpwr', '');
        $this->SendDebug(__FUNCTION__, 'fpwr=' . $fpwr, 0);

        $auto = $this->GetArrayElem($payload, 'product-state.auto', '');
        $this->SendDebug(__FUNCTION__, 'auto=' . $auto, 0);

        /*
        [rssi] => -45
        [fqhp] => 105608
        [fghp] => 75392
        [product-state] => Array
            (
                [fpwr] => OFF
                [auto] => OFF
                [oscs] => OFF
                [oson] => ON
                [nmod] => OFF
                [rhtm] => ON
                [fnst] => OFF
                [ercd] => NONE
                [wacd] => NONE
                [nmdv] => 0004
                [fnsp] => 0004
                [bril] => 0002
                [corf] => ON
                [cflr] => 0099
                [hflr] => 0099
                [cflt] => CARF
                [hflt] => GHEP
                [sltm] => OFF
                [osal] => 0147
                [osau] => 0192
                [ancp] => CUST
                [fdir] => ON
            )

        [scheduler] => Array
            (
                [srsc] => 0000000000000000
                [dstv] => 0000
                [tzid] => 0001
            )

         */
    }

    private function DecodeSensorData($payload)
    {
        $ts = strtotime($payload['time']);
        $this->SendDebug(__FUNCTION__, 'time=' . date('d.m.y H:i:s', $ts), 0);

        $pm25 = (int) $this->GetArrayElem($payload, 'data.pm25', 0);
        $this->SendDebug(__FUNCTION__, 'pm25=' . $pm25, 0);

        $pm10 = (int) $this->GetArrayElem($payload, 'data.pm10', 0);
        $this->SendDebug(__FUNCTION__, 'pm10=' . $pm10, 0);

        $va10 = (int) $this->GetArrayElem($payload, 'data.va10', 0);
        $this->SendDebug(__FUNCTION__, 'va10=' . $va10, 0);

        $noxl = (int) $this->GetArrayElem($payload, 'data.noxl', 0);
        $this->SendDebug(__FUNCTION__, 'noxl=' . $noxl, 0);
        /*
        [data] => Array
            (
                [tact] => 2977
                [hact] => 0049
                [pm25] => 0001
                [pm10] => 0000
                [va10] => 0003
                [noxl] => 0004
                [p25r] => 0002
                [p10r] => 0002
                [sltm] => OFF
            )
         */
    }

    public function ReceiveData($data)
    {
        $this->SendDebug(__FUNCTION__, 'data=' . $data, 0);

        if ($this->CheckStatus() == STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $jdata = json_decode($data, true);

        $buf = json_decode($jdata['Buffer'], true);
        $payload = json_decode($buf['Payload'], true);
        $this->SendDebug(__FUNCTION__, 'payload=' . print_r($payload, true), 0);

        $msg = $payload['msg'];
        $ts = strtotime($payload['time']);
        switch ($msg) {
            case 'CURRENT-STATE':
                $this->DecodeState($payload);
                break;
            case 'ENVIRONMENTAL-CURRENT-SENSOR-DATA':
                $this->DecodeSensorData($payload);
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'unknown msg=' . $msg . ', time=' . date('d.m.y H:i:s', $ts), 0);
                break;
        }

        $this->SetStatus(IS_ACTIVE);
    }

    public function RenewSubscribe()
    {
        $this->SubscribeStatus();
    }

    private function SubscribeStatus()
    {
        $serial = $this->ReadPropertyString('serial');
        $product_type = $this->ReadPropertyString('product_type');

        $topic = $product_type . '/' . $serial . '/status/current';

        $cmd = [
            'Function'  => 'Subscribe',
            'Topic'     => $topic,
        ];
        $json = [
            'DataID'    => '{97475B04-67C3-A74D-C970-E9409B0EFA1D}',
            'Buffer'    => json_encode($cmd)
        ];
        $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($json, true) . ')', 0);
        parent::SendDataToParent(json_encode($json));
    }

    public function ManualUpdateStatus()
    {
        $this->SubscribeStatus();
        $this->RequestStatus();
    }

    public function UpdateStatus()
    {
        $min = $this->ReadPropertyInteger('UpdateStatusInterval');

        $this->SendDebug(__FUNCTION__, '', 0);
        $this->SubscribeStatus();
        $this->RequestStatus();
        $this->SetTimerInterval('UpdateStatus', $min * 60 * 1000);
    }

    public function RequestStatus()
    {
        $payload = [
            'msg'  => 'REQUEST-CURRENT-STATE',
            'time' => strftime('%Y-%m-%dT%H:%M:%SZ', time()),
        ];

        $this->SendCommand(json_encode($payload));
    }

    private function SendCommand($payload)
    {
        $serial = $this->ReadPropertyString('serial');
        $product_type = $this->ReadPropertyString('product_type');

        $topic = $product_type . '/' . $serial . '/command';

        $cmd = [
            'Function'  => 'Publish',
            'Topic'     => $topic,
            'Payload'   => utf8_encode($payload),
            'Retain'    => false
        ];
        $json = [
            'DataID'    => '{97475B04-67C3-A74D-C970-E9409B0EFA1D}',
            'Buffer'    => json_encode($cmd),
        ];
        $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($json, true) . ')', 0);
        parent::SendDataToParent(json_encode($json));
    }
}
