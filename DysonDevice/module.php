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

        $associations = [];
        $associations[] = ['Wert' => false, 'Name' => $this->Translate('Back'), 'Farbe' => -1];
        $associations[] = ['Wert' => true, 'Name' => $this->Translate('Front'), 'Farbe' => -1];
        $this->CreateVarProfile('Dyson.AirflowDirection', VARIABLETYPE_BOOLEAN, '', 0, 0, 0, 0, '', $associations);

        $this->CreateVarProfile('Dyson.Wifi', VARIABLETYPE_INTEGER, ' dBm', 0, 0, 0, 0, 'Intensity');
        $this->CreateVarProfile('Dyson.PM', VARIABLETYPE_INTEGER, ' µg/m³', 0, 0, 0, 0, 'Snow');
        $this->CreateVarProfile('Dyson.VOC', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, 'Gauge');
        $this->CreateVarProfile('Dyson.NOx', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, 'Gauge');

        $associations = [];
        $associations[] = ['Wert' => 0, 'Name' => $this->Translate('Off'), 'Farbe' => -1];
        $associations[] = ['Wert' => 1, 'Name' => '%d', 'Farbe' => -1];
        $this->CreateVarProfile('Dyson.SleepTimer', VARIABLETYPE_INTEGER, '', 0, 539, 1, 0, '', $associations);

        $associations = [];
        $associations[] = ['Wert' => 0, 'Name' => $this->Translate('Off'), 'Farbe' => -1];
        $associations[] = ['Wert' => 1, 'Name' => '%d', 'Farbe' => -1];
        $associations[] = ['Wert' => 2, 'Name' => '%d', 'Farbe' => -1];
        $associations[] = ['Wert' => 3, 'Name' => '%d', 'Farbe' => -1];
        $associations[] = ['Wert' => 4, 'Name' => '%d', 'Farbe' => -1];
        $associations[] = ['Wert' => 5, 'Name' => '%d', 'Farbe' => -1];
        $associations[] = ['Wert' => 6, 'Name' => '%d', 'Farbe' => -1];
        $associations[] = ['Wert' => 7, 'Name' => '%d', 'Farbe' => -1];
        $associations[] = ['Wert' => 8, 'Name' => '%d', 'Farbe' => -1];
        $associations[] = ['Wert' => 9, 'Name' => '%d', 'Farbe' => -1];
        $this->CreateVarProfile('Dyson.AirflowRate', VARIABLETYPE_INTEGER, '', 0, 9, 0, 0, '', $associations);

        $associations = [];
        $associations[] = ['Wert' =>  45, 'Name' => '45', 'Farbe' => -1];
        $associations[] = ['Wert' =>  90, 'Name' => '90', 'Farbe' => -1];
        $associations[] = ['Wert' => 180, 'Name' => '180', 'Farbe' => -1];
        $associations[] = ['Wert' => 350, 'Name' => '350', 'Farbe' => -1];
        $this->CreateVarProfile('Dyson.RotationAngle', VARIABLETYPE_INTEGER, '°', 0, 9, 0, 0, '', $associations);

        $this->CreateVarProfile('Dyson.RotationStart', VARIABLETYPE_INTEGER, '°', 0, 359, 1, 0, '');

        $this->CreateVarProfile('Dyson.Percent', VARIABLETYPE_INTEGER, ' %', 0, 0, 0, 0, '');

        $this->CreateVarProfile('Dyson.Temperature', VARIABLETYPE_FLOAT, ' °C', 0, 0, 0, 0, 'Temperature');
        $this->CreateVarProfile('Dyson.Humidity', VARIABLETYPE_FLOAT, ' %', 0, 0, 0, 0, 'Drops');
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

        $product_type = $this->ReadPropertyString('product_type');
        $field = $this->product2field($product_type);

        $this->SendDebug(__FUNCTION__, 'field=' . print_r($field, true), 0);

        $vpos = 1;

        $this->MaintainVariable('Power', $this->Translate('Power'), VARIABLETYPE_BOOLEAN, '~Switch', $vpos++, $field['power']);
        if ($field['power']) {
            $this->MaintainAction('Power', true);
        }

        $this->MaintainVariable('AutomaticMode', $this->Translate('Automatic mode'), VARIABLETYPE_BOOLEAN, '~Switch', $vpos++, $field['automatic_mode']);
        if ($field['automatic_mode']) {
            $this->MaintainAction('AutomaticMode', true);
        }

        $this->MaintainVariable('NightMode', $this->Translate('Night mode'), VARIABLETYPE_BOOLEAN, '~Switch', $vpos++, $field['night_mode']);
        if ($field['night_mode']) {
            $this->MaintainAction('NightMode', true);
        }

        $this->MaintainVariable('SleepTimer', $this->Translate('Sleep timer'), VARIABLETYPE_INTEGER, 'Dyson.SleepTimer', $vpos++, $field['sleep_timer']);
        if ($field['sleep_timer']) {
            $this->MaintainAction('SleepTimer', true);
        }

        $this->MaintainVariable('AirflowRate', $this->Translate('Airflow rate'), VARIABLETYPE_INTEGER, 'Dyson.AirflowRate', $vpos++, $field['airflow_rate']);
        if ($field['airflow_rate']) {
            $this->MaintainAction('AirflowRate', true);
        }
        $this->MaintainVariable('RotationMode', $this->Translate('Rotation mode'), VARIABLETYPE_BOOLEAN, '~Switch', $vpos++, $field['rotation_mode']);
        $this->MaintainVariable('RotationAngle', $this->Translate('Rotation angle'), VARIABLETYPE_INTEGER, 'Dyson.RotationAngle', $vpos++, $field['rotation_mode']);
        $this->MaintainVariable('RotationStart', $this->Translate('Rotation start'), VARIABLETYPE_INTEGER, 'Dyson.RotationStart', $vpos++, $field['rotation_mode']);
        if ($field['rotation_mode']) {
            $this->MaintainAction('RotationMode', true);
            $this->MaintainAction('RotationAngle', true);
            $this->MaintainAction('RotationStart', true);
        }
        $this->MaintainVariable('AirflowDirection', $this->Translate('Airflow direction'), VARIABLETYPE_BOOLEAN, 'Dyson.AirflowDirection', $vpos++, $field['airflow_direction']);
        if ($field['airflow_direction']) {
            $this->MaintainAction('AirflowDirection', true);
        }

        $this->MaintainVariable('Temperature', $this->Translate('Temperature'), VARIABLETYPE_FLOAT, 'Dyson.Temperature', $vpos++, $field['temperature']);
        $this->MaintainVariable('Humidity', $this->Translate('Humidity'), VARIABLETYPE_FLOAT, 'Dyson.Humidity', $vpos++, $field['humidity']);
        $this->MaintainVariable('PM25', $this->Translate('Particulate matter (PM 2.5)'), VARIABLETYPE_INTEGER, 'Dyson.PM', $vpos++, $field['pm25']);
        $this->MaintainVariable('PM10', $this->Translate('Particulate matter (PM 10)'), VARIABLETYPE_INTEGER, 'Dyson.PM', $vpos++, $field['pm10']);
        $this->MaintainVariable('VOC', $this->Translate('Volatile organic compounds (VOC)'), VARIABLETYPE_INTEGER, 'Dyson.VOC', $vpos++, $field['voc']);
        $this->MaintainVariable('NOx', $this->Translate('Nitrogen oxides (NOx)'), VARIABLETYPE_INTEGER, 'Dyson.NOx', $vpos++, $field['nox']);

        $this->MaintainVariable('StandbyMonitoring', $this->Translate('Standby monitoring'), VARIABLETYPE_BOOLEAN, '~Switch', $vpos++, $field['standby_monitoring']);
        if ($field['standby_monitoring']) {
            $this->MaintainAction('StandbyMonitoring', true);
        }

        $this->MaintainVariable('CarbonFilterLifetime', $this->Translate('Carbon filter lifetime'), VARIABLETYPE_INTEGER, 'Dyson.Percent', $vpos++, $field['carbon_filter']);
        $this->MaintainVariable('HepaFilterLifetime', $this->Translate('HEPA filter lifetime'), VARIABLETYPE_INTEGER, 'Dyson.Percent', $vpos++, $field['hepa_filter']);

        $this->MaintainVariable('WifiStrength', $this->Translate('Wifi signal strenght'), VARIABLETYPE_INTEGER, 'Dyson.Wifi', $vpos++, true);

        $this->MaintainVariable('LastUpdate', $this->Translate('Last update'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);
        $this->MaintainVariable('LastChange', $this->Translate('Last change'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

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

        $this->SendDebug(__FUNCTION__, $s, 0);
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

        $product_type = $this->ReadPropertyString('product_type');
        $product_name = $this->product2name($product_type);

        $formElements[] = [
            'type'    => 'Label',
            'caption' => 'Dyson'
        ];

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
        //  US, FR, NL, GB, AU. Other codes should be supported.
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
            'type'    => 'RowLayout',
            'items'   => [
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'product_type',
                    'caption' => 'Product type'
                ],
                [
                    'type'    => 'Label',
                    'caption' => $product_name
                ]
            ]
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
            'type'    => 'RowLayout',
            'items'   => [
                [
                    'type'    => 'Button',
                    'caption' => 'Relogin',
                    'onClick' => 'Dyson_ManualRelogin($id);'
                ],
                [
                    'type'    => 'Button',
                    'caption' => 'Reload Config',
                    'onClick' => 'Dyson_ManualReloadConfig($id);'
                ],
                [
                    'type'    => 'Button',
                    'caption' => 'Update Status',
                    'onClick' => 'Dyson_ManualUpdateStatus($id);'
                ]
            ]
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

    public function ManualRelogin()
    {
        $auth = $this->doLogin(true);
        echo $this->Translate(($auth == false ? 'Login failed' : 'Login successful'));
    }

    public function ManualReloadConfig()
    {
        $r = $this->loadConfig();
        echo $this->Translate(($r == false ? 'Load config failed' : 'Load config succeeded'));
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
                    Ausnahme zu Punkt 13 der Reviewrichtlinien, wurde per Mail vom 07.07.2020 von Niels genehmigt
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
            return true;
        }

        return false;
    }

    private function DecodeState($payload, $changeState)
    {
        $now = time();
        $is_changed = false;

        $product_type = $this->ReadPropertyString('product_type');
        $field = $this->product2field($product_type);

        $used_fields = [];
        $ignored_fields = ['mode-reason', 'state-reason'];

        $this->SendDebug(__FUNCTION__, 'used variables', 0);

        $msg = $payload['msg'];
        $msg = $used_fields[] = 'msg';

        $ts = strtotime($payload['time']);
        $used_fields[] = 'time';
        $this->SendDebug(__FUNCTION__, '... msg=' . $msg . ', time=' . date('d.m.y H:i:s', $ts), 0);

        if ($changeState == false) {
            if ($field['rssi']) {
                // rssi - wifi signal strength
                $rssi = (int) $this->GetArrayElem($payload, 'rssi', 0);
                $used_fields[] = 'rssi';
                $this->SendDebug(__FUNCTION__, '... rssi=' . $rssi, 0);
                $this->SaveValue('WifiStrength', $rssi, $is_changed);
            }
        }

        // channel - wifi channel
        $ignored_fields[] = 'channel';

        if ($field['power']) {
            // fpwr - Fan Power (ON|OFF)
            $fpwr = $this->GetArrayElem($payload, 'product-state.fpwr', '');
            $used_fields[] = 'product-state.fpwr';
            if ($changeState) {
                $do = $fpwr[0] != $fpwr[1];
                $fpwr = $fpwr[1];
            } else {
                $do = true;
            }
            if ($do) {
                $b = $this->str2bool($fpwr);
                $this->SendDebug(__FUNCTION__, '... fan power (fpwr)=' . $fpwr . ' => ' . $this->bool2str($b), 0);
                $this->SaveValue('Power', $b, $is_changed);
                $this->adjustAction($b);
            }
        }

        // fmod - fan mode
        $ignored_fields[] = 'product-state.fmod';

        // fnst - fan status (OFF|FAN)
        $ignored_fields[] = 'product-state.fnst';

        if ($field['airflow_rate']) {
            // fnsp - fan speed (OFF|1..9)
            $fnsp = $this->GetArrayElem($payload, 'product-state.fnsp', '');
            $used_fields[] = 'product-state.fnsp';
            if ($changeState) {
                $do = $fnsp[0] != $fnsp[1];
                $fnsp = $fnsp[1];
            } else {
                $do = true;
            }
            if ($do) {
                $i = $fnsp == 'OFF' ? 0 : (int) $fnsp;
                $this->SendDebug(__FUNCTION__, '... fan speed (fnsp)=' . $fnsp . ' => ' . $i, 0);
                $this->SaveValue('AirflowRate', $i, $is_changed);
            }
        }

        if ($field['rotation_mode']) {
            // oson - oscillation on (ON|OFF)
            $ignored_fields[] = 'product-state.oson';

            // oscs - oscillation state (ON|OFF)
            $oscs = $this->GetArrayElem($payload, 'product-state.oscs', '');
            $used_fields[] = 'product-state.oscs';

            if ($changeState) {
                $do = $oscs[0] != $oscs[1];
                $oscs = $oscs[1];
            } else {
                $do = true;
            }
            if ($do) {
                $b = $this->str2bool($oscs);
                $this->SendDebug(__FUNCTION__, '... oscillation state (oscs)=' . $oscs . ' => ' . $this->bool2str($b), 0);
                $this->SaveValue('RotationMode', $b, $is_changed);
            }

            // osal - oscillation angle low (5..309)
            $osal = (int) $this->GetArrayElem($payload, 'product-state.osal', 0);
            $used_fields[] = 'product-state.osal';

            // osau - oscillation angle up (50..354)
            $osau = (int) $this->GetArrayElem($payload, 'product-state.osau', 0);
            $used_fields[] = 'product-state.osau';

            if ($changeState) {
                $do = $osal[0] != $osal[1] || $osau[0] != $osau[1];
                $osal = $osal[1];
                $osau = $osau[1];
            } else {
                $do = true;
            }
            if ($do) {
                $angle = $osau - $osal;
                $start = $osal;
                $end = 0;
                $this->adjust_rotation($angle, $start, $end);

                $this->SendDebug(__FUNCTION__, '... oscillation angle low (osal)=' . $osal . ', up (osau)=' . $osau, 0);
                $this->SendDebug(__FUNCTION__, '    => angle=' . $angle . ', start=' . $start . ', end=' . $end, 0);
                $this->SaveValue('RotationAngle', $angle, $is_changed);
                $this->SaveValue('RotationStart', $start, $is_changed);
            }

            // ancp - angle ... (CUST)
            $ignored_fields[] = 'product-state.ancp';
        }

        if ($field['airflow_direction']) {
            // fdir - front direction  (ON|OFF)
            $fdir = $this->GetArrayElem($payload, 'product-state.fdir', '');
            $used_fields[] = 'product-state.fdir';
            if ($changeState) {
                $do = $fdir[0] != $fdir[1];
                $fdir = $fdir[1];
            } else {
                $do = true;
            }
            if ($do) {
                $b = $this->str2bool($fdir);
                $this->SendDebug(__FUNCTION__, '... front direction (fdir)=' . $fdir . ' => ' . $this->bool2str($b), 0);
                $this->SaveValue('AirflowDirection', $b, $is_changed);
            }
        }

        if ($field['automatic_mode']) {
            // auto - automatic mode (ON|OFF)
            $auto = $this->GetArrayElem($payload, 'product-state.auto', '');
            $used_fields[] = 'product-state.auto';
            if ($changeState) {
                $do = $auto[0] != $auto[1];
                $auto = $auto[1];
            } else {
                $do = true;
            }
            if ($do) {
                $b = $this->str2bool($auto);
                $this->SendDebug(__FUNCTION__, '... automatic mode (auto)=' . $auto . ' => ' . $this->bool2str($b), 0);
                $this->SaveValue('AutomaticMode', $b, $is_changed);
            }
        }

        if ($field['night_mode']) {
            // nmod - night mode (ON|OFF)
            $nmod = $this->GetArrayElem($payload, 'product-state.nmod', '');
            $used_fields[] = 'product-state.nmod';
            if ($changeState) {
                $do = $nmod[0] != $nmod[1];
                $nmod = $nmod[1];
            } else {
                $do = true;
            }
            if ($do) {
                $b = $this->str2bool($nmod);
                $this->SendDebug(__FUNCTION__, '... night mode (nmod)=' . $nmod . ' => ' . $this->bool2str($b), 0);
                $this->SaveValue('NightMode', $b, $is_changed);
            }
        }

        if ($field['sleep_timer']) {
            // sltm - sleep-timer (OFF|1..539)
            $sltm = $this->GetArrayElem($payload, 'product-state.sltm', 0);
            $used_fields[] = 'product-state.sltm';
            if ($changeState) {
                $do = $sltm[0] != $sltm[1];
                $sltm = $sltm[1];
            } else {
                $do = true;
            }
            if ($do) {
                $sleep_timer = $sltm == 'OFF' ? 0 : (int) $sltm;
                $this->SendDebug(__FUNCTION__, '... sleep timer (sltm)=' . $sltm . ' => ' . $sleep_timer, 0);
                $this->SaveValue('SleepTimer', $sleep_timer, $is_changed);
            }
        }

        if ($field['carbon_filter']) {
            // cflt - carbon filter type (CARF)
            $ignored_fields[] = 'product-state.cflt';

            // cflr - carbon filter range (0..100%)
            $cflr = (int) $this->GetArrayElem($payload, 'product-state.cflr', 0);
            $used_fields[] = 'product-state.cflr';
            if ($changeState) {
                $do = $cflr[0] != $cflr[1];
                $cflr = $cflr[1];
            } else {
                $do = true;
            }
            if ($do) {
                $sleep_timer = $sltm == 'OFF' ? 0 : (int) $sltm;
                $this->SendDebug(__FUNCTION__, '... carbon filter lifetime (cflr)=' . $cflr, 0);
                $this->SaveValue('CarbonFilterLifetime', $cflr, $is_changed);
            }
        }

        if ($field['hepa_filter']) {
            // hflt - hepa filter type (GHEP)
            $ignored_fields[] = 'product-state.hflt';

            // hflr - hepa filter range (0..100%)
            $hflr = (int) $this->GetArrayElem($payload, 'product-state.hflr', 0);
            $used_fields[] = 'product-state.hflr';
            if ($changeState) {
                $do = $hflr[0] != $hflr[1];
                $hflr = $hflr[1];
            } else {
                $do = true;
            }
            if ($do) {
                $sleep_timer = $sltm == 'OFF' ? 0 : (int) $sltm;
                $this->SendDebug(__FUNCTION__, '... hepa filter lifetime (hflr)=' . $hflr, 0);
                $this->SaveValue('HepaFilterLifetime', $hflr, $is_changed);
            }
        }

        if ($field['standby_monitoring']) {
            // rhtm - standby monitoring (ON|OFF)
            $rhtm = $this->GetArrayElem($payload, 'product-state.rhtm', '');
            $used_fields[] = 'product-state.rhtm';
            if ($changeState) {
                $do = $rhtm[0] != $rhtm[1];
                $rhtm = $rhtm[1];
            } else {
                $do = true;
            }
            if ($do) {
                $b = $this->str2bool($rhtm);
                $this->SendDebug(__FUNCTION__, '... standby monitoring (rhtm)=' . $rhtm . ' => ' . $this->bool2str($b), 0);
                $this->SaveValue('StandbyMonitoring', $b, $is_changed);
            }
        }

        $this->SetValue('LastUpdate', $now);
        if ($is_changed) {
            $this->SetValue('LastChange', $ts);
        }

        for ($i = 0; $i < 2; $i++) {
            $b = false;
            $s = ($i == 0 ? 'ignored' : 'unused') . ' variables';
            foreach ($payload as $var => $val) {
                if (is_array($val)) {
                    continue;
                }
                if ($i == 0) {
                    $skip = !in_array($var, $ignored_fields);
                } else {
                    $skip = in_array($var, $used_fields) || in_array($var, $ignored_fields);
                }
                if ($skip) {
                    continue;
                }
                if ($b == false) {
                    $b = true;
                    $this->SendDebug(__FUNCTION__, $s, 0);
                }
                $this->SendDebug(__FUNCTION__, '... ' . $var . '="' . $val . '"', 0);
            }
            foreach ($payload['product-state'] as $var => $val) {
                $var = 'product-state.' . $var;
                if ($i == 0) {
                    $skip = !in_array($var, $ignored_fields);
                } else {
                    $skip = in_array($var, $used_fields) || in_array($var, $ignored_fields);
                }
                if ($skip) {
                    continue;
                }
                if ($changeState) {
                    if ($val[0] == $val[1]) {
                        continue;
                    }
                    if ($b == false) {
                        $b = true;
                        $this->SendDebug(__FUNCTION__, $s, 0);
                    }
                    $this->SendDebug(__FUNCTION__, '... ' . $var . '="' . $val[1] . '"', 0);
                } else {
                    if ($b == false) {
                        $b = true;
                        $this->SendDebug(__FUNCTION__, $s, 0);
                    }
                    $this->SendDebug(__FUNCTION__, '... ' . $var . '="' . $val . '"', 0);
                }
            }
            foreach ($payload['scheduler'] as $var => $val) {
                $var = 'scheduler.' . $var;
                if ($i == 0) {
                    $skip = !in_array($var, $ignored_fields);
                } else {
                    $skip = in_array($var, $used_fields) || in_array($var, $ignored_fields);
                }
                if ($skip) {
                    continue;
                }
                if ($changeState) {
                    if ($val[0] == $val[1]) {
                        continue;
                    }
                    if ($b == false) {
                        $b = true;
                        $this->SendDebug(__FUNCTION__, $s, 0);
                    }
                    $this->SendDebug(__FUNCTION__, '... ' . $var . '="' . $val[1] . '"', 0);
                } else {
                    if ($b == false) {
                        $b = true;
                        $this->SendDebug(__FUNCTION__, $s, 0);
                    }
                    $this->SendDebug(__FUNCTION__, '... ' . $var . '="' . $val . '"', 0);
                }
            }
        }
    }

    private function DecodeSensorData($payload)
    {
        $product_type = $this->ReadPropertyString('product_type');
        $field = $this->product2field($product_type);
        $this->SendDebug(__FUNCTION__, 'field=' . print_r($field, true), 0);

        $now = time();
        $is_changed = false;

        $used_fields = [];
        $ignored_fields = [];

        $this->SendDebug(__FUNCTION__, 'used variables', 0);

        $msg = $payload['msg'];
        $msg = $used_fields[] = 'msg';

        $ts = strtotime($payload['time']);
        $used_fields[] = 'time';
        $this->SendDebug(__FUNCTION__, '... msg=' . $msg . ', time=' . date('d.m.y H:i:s', $ts), 0);

        if ($field['temperature']) {
            // tact - temperature actual
            $tact = (int) $this->GetArrayElem($payload, 'data.tact', 0);
            $used_fields[] = 'data.tact';
            $temp = $tact / 10 - 273;
            $this->SendDebug(__FUNCTION__, '... temperature (tact)=' . $tact . ' => ' . $temp, 0);
            $this->SaveValue('Temperature', $temp, $is_changed);
        }

        if ($field['humidity']) {
            // hact - humidity actual
            $hum = (int) $this->GetArrayElem($payload, 'data.hact', 0);
            $used_fields[] = 'data.hact';
            $this->SendDebug(__FUNCTION__, '... humidity (hact)=' . $hum, 0);
            $this->SaveValue('Humidity', $hum, $is_changed);
        }

        if ($field['pm25']) {
            // pm25 - PM 10 ?
            $ignored_fields[] = 'data.pm25';

            // p25r - PM 2.5 real
            $pm25 = (int) $this->GetArrayElem($payload, 'data.p25r', 0);
            $used_fields[] = 'data.p25r';
            $this->SendDebug(__FUNCTION__, '... PM2.5 (p25r)=' . $pm25, 0);
            $this->SaveValue('PM25', $pm25, $is_changed);
        }

        if ($field['pm10']) {
            // pm10 - PM 10 ?
            $ignored_fields[] = 'data.pm10';

            // p10r - PM 10 real
            $pm10 = (int) $this->GetArrayElem($payload, 'data.p10r', 0);
            $used_fields[] = 'data.p10r';
            $this->SendDebug(__FUNCTION__, '... PM10 (p10r)=' . $pm10, 0);
            $this->SaveValue('PM10', $pm10, $is_changed);
        }

        if ($field['voc']) {
            // va10 - volatile organic compounds
            $voc = (int) $this->GetArrayElem($payload, 'data.va10', 0);
            $used_fields[] = 'data.va10';
            $this->SendDebug(__FUNCTION__, '... VOC (va10)=' . $voc, 0);
            $this->SaveValue('VOC', $voc, $is_changed);
        }

        if ($field['nox']) {
            // noxl - nitrogen oxides
            $nox = (int) $this->GetArrayElem($payload, 'data.noxl', 0);
            $used_fields[] = 'data.noxl';
            $this->SendDebug(__FUNCTION__, '... NOx (noxl)=' . $nox, 0);
            $this->SaveValue('NOx', $nox, $is_changed);
        }

        // sltm - sleep-timer (OFF|1..539)
        $ignored_fields[] = 'data.sltm'; // siehe DecodeState()

        $this->SetValue('LastUpdate', $now);
        if ($is_changed) {
            $this->SetValue('LastChange', $ts);
        }

        for ($i = 0; $i < 2; $i++) {
            $b = false;
            $s = ($i == 0 ? 'ignored' : 'unused') . ' variables';
            foreach ($payload as $var => $val) {
                if (is_array($val)) {
                    continue;
                }
                if ($i == 0) {
                    $skip = !in_array($var, $ignored_fields);
                } else {
                    $skip = in_array($var, $used_fields) || in_array($var, $ignored_fields);
                }
                if ($skip) {
                    continue;
                }
                if ($b == false) {
                    $b = true;
                    $this->SendDebug(__FUNCTION__, $s, 0);
                }
                $this->SendDebug(__FUNCTION__, '... ' . $var . '="' . $val . '"', 0);
            }
            foreach ($payload['data'] as $var => $val) {
                $var = 'data.' . $var;
                if ($i == 0) {
                    $skip = !in_array($var, $ignored_fields);
                } else {
                    $skip = in_array($var, $used_fields) || in_array($var, $ignored_fields);
                }
                if ($skip) {
                    continue;
                }
                if ($b == false) {
                    $b = true;
                    $this->SendDebug(__FUNCTION__, $s, 0);
                }
                $this->SendDebug(__FUNCTION__, '... ' . $var . '="' . $val . '"', 0);
            }
        }
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
        if (isset($buf['Payload'])) {
            $payload = json_decode($buf['Payload'], true);
            $this->SendDebug(__FUNCTION__, 'payload=' . print_r($payload, true), 0);

            $msg = $payload['msg'];
            $ts = strtotime($payload['time']);
            switch ($msg) {
                case 'CURRENT-STATE':
                    $this->DecodeState($payload, false);
                    break;
                case 'STATE-CHANGE':
                    $this->DecodeState($payload, true);
                    break;
                case 'ENVIRONMENTAL-CURRENT-SENSOR-DATA':
                    $this->DecodeSensorData($payload);
                    break;

                default:
                    $this->SendDebug(__FUNCTION__, 'unknown msg=' . $msg . ', time=' . date('d.m.y H:i:s', $ts), 0);
                    break;
            }
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
        $this->SendDebug(__FUNCTION__, 'cmd=' . print_r($json, true), 0);
        parent::SendDataToParent(json_encode($json));
    }

    public function ManualUpdateStatus()
    {
        $this->SubscribeStatus();
        $this->RequestStateCommand();
    }

    public function UpdateStatus()
    {
        $min = $this->ReadPropertyInteger('UpdateStatusInterval');

        $this->SendDebug(__FUNCTION__, '', 0);
        $this->SubscribeStatus();
        $this->RequestStateCommand();
        $this->SetTimerInterval('UpdateStatus', $min * 60 * 1000);
    }

    private function RequestStateCommand()
    {
        $payload = [
            'msg'         => 'REQUEST-CURRENT-STATE',
            'time'        => strftime('%Y-%m-%dT%H:%M:%SZ', time()),
            'mode-reason' => 'LAPP',
        ];

        $this->SendCommand(__FUNCTION__, json_encode($payload));
    }

    private function SendCommand($func, $payload)
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

        $this->SendDebug(__FUNCTION__, 'func=' . $func . ', cmd=' . print_r($json, true), 0);
        parent::SendDataToParent(json_encode($json));
    }

    private function checkAction($func, $verbose)
    {
        $enabled = false;

        $product_type = $this->ReadPropertyString('product_type');
        $field = $this->product2field($product_type);

        switch ($func) {
            case 'SwitchPower':
                if ($field['power']) {
                    $enabled = true;
                }
                $this->SendDebug(__FUNCTION__, 'field[\'power\']=' . $this->bool2str($field['power']) . ', enabled=' . $this->bool2str($enabled), 0);
                break;
            case 'SwitchAutomaticMode':
                if ($field['automatic_mode']) {
                    $enabled = true;
                }
                break;
            case 'SwitchNightMode':
                if ($field['night_mode']) {
                    $enabled = true;
                }
                break;
            case 'SetSleepTimer':
                if ($field['sleep_timer']) {
                    $enabled = true;
                }
                break;
            case 'SetAirflowRate':
                if ($field['airflow_rate']) {
                    $enabled = true;
                }
                break;
            case 'SwitchAirflowDirection':
                if ($field['airflow_direction']) {
                    $enabled = true;
                }
                break;
            case 'SwitchRotationMode':
                if ($field['rotation_mode']) {
                    $enabled = true;
                }
                break;
            case 'SetRotationAngle':
                if ($field['rotation_mode']) {
                    $enabled = true;
                }
                break;
            case 'SetRotationStart':
                if ($field['rotation_mode']) {
                    $enabled = true;
                }
                break;
            case 'SwitchStandbyMonitoring':
                if ($field['standby_monitoring']) {
                    $enabled = true;
                }
                break;
        }

        $this->SendDebug(__FUNCTION__, 'action "' . $func . '" is ' . ($enabled ? 'enabled' : 'disabled'), 0);
        if ($verbose && !$enabled) {
            $this->LogMessage(__FUNCTION__ . ': action "' . $func . '" is not enabled for ' . IPS_GetName($this->InstanceID), KL_WARNING);
        }
        return $enabled;
    }

    private function CallAction($func, $action)
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'func=' . $func . ', action=' . print_r($action, true), 0);
    }

    private function SetStateCommand($func, $data)
    {
        $payload = [
            'msg'         => 'STATE-SET',
            'time'        => strftime('%Y-%m-%dT%H:%M:%SZ', time()),
            'mode-reason' => 'LAPP',
            'data'        => $data,
        ];

        $this->SendCommand($func, json_encode($payload));

        return true;
    }

    private function SwitchPower(bool $mode)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $data = [
            'fpwr'=> ($mode ? 'ON' : 'OFF')
        ];

        return $this->SetStateCommand(__FUNCTION__, $data);
    }

    private function SwitchAutomaticMode(bool $mode)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $data = [
            'auto'=> ($mode ? 'ON' : 'OFF')
        ];

        return $this->SetStateCommand(__FUNCTION__, $data);
    }

    private function SwitchNightMode(bool $mode)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $data = [
            'nmod'=> ($mode ? 'ON' : 'OFF')
        ];

        return $this->SetStateCommand(__FUNCTION__, $data);
    }

    private function SetSleepTimer(int $min)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $data = [
            'sltm'=> sprintf('%04d', $min)
        ];

        return $this->SetStateCommand(__FUNCTION__, $data);
    }

    private function SetAirflowRate(int $val)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $data = [
            'fnsp'=> sprintf('%04d', $val)
        ];

        return $this->SetStateCommand(__FUNCTION__, $data);
    }

    private function SwitchAirflowDirection(bool $mode)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $data = [
            'fdir'=> ($mode ? 'ON' : 'OFF')
        ];

        return $this->SetStateCommand(__FUNCTION__, $data);
    }

    private function SwitchRotationMode(bool $mode)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $data = [
            'fmod'=> ($mode ? 'ON' : 'OFF')
        ];

        return $this->SetStateCommand(__FUNCTION__, $data);
    }

    private function SetRotationAngle(int $angle)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $start = (int) $this->GetValue('RotationStart');
        $end = $start + $angle;
        $this->adjust_rotation($angle, $start, $end);

        $data = [
            'ancp'=> 'CUST',
            'osal'=> sprintf('%04d', $start),
            'osau'=> sprintf('%04d', $end),
        ];

        $r = $this->SetStateCommand(__FUNCTION__, $data);
        if ($r) {
            $this->SetValue('RotationAngle', $angle);
            $this->SetValue('RotationStart', $start);
        }
        return $r;
    }

    private function SetRotationStart(int $start)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $angle = (int) $this->GetValue('RotationAngle');
        $end = $start + $angle;
        $this->adjust_rotation($angle, $start, $end);

        $data = [
            'ancp'=> 'CUST',
            'osal'=> sprintf('%04d', $start),
            'osau'=> sprintf('%04d', $end),
        ];

        $r = $this->SetStateCommand(__FUNCTION__, $data);
        if ($r) {
            $this->SetValue('RotationAngle', $angle);
            $this->SetValue('RotationStart', $start);
        }
        return $r;
    }

    private function SwitchStandbyMonitoring(bool $mode)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $data = [
            'rhtm'=> ($mode ? 'ON' : 'OFF')
        ];

        return $this->SetStateCommand(__FUNCTION__, $data);
    }

    public function RequestAction($Ident, $Value)
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $r = false;
        switch ($Ident) {
            case 'Power':
                $r = $this->SwitchPower((bool) $Value);
                $this->SendDebug(__FUNCTION__, $Ident . '=' . $Value . ' => ret=' . $r, 0);
                break;
            case 'AutomaticMode':
                $r = $this->SwitchAutomaticMode((bool) $Value);
                $this->SendDebug(__FUNCTION__, $Ident . '=' . $Value . ' => ret=' . $r, 0);
                break;
            case 'NightMode':
                $r = $this->SwitchNightMode((bool) $Value);
                $this->SendDebug(__FUNCTION__, $Ident . '=' . $Value . ' => ret=' . $r, 0);
                break;
            case 'SleepTimer':
                $r = $this->SetSleepTimer((int) $Value);
                $this->SendDebug(__FUNCTION__, $Ident . '=' . $Value . ' => ret=' . $r, 0);
                break;
            case 'AirflowRate':
                $r = $this->SetAirflowRate((int) $Value);
                $this->SendDebug(__FUNCTION__, $Ident . '=' . $Value . ' => ret=' . $r, 0);
                break;
            case 'AirflowDirection':
                $r = $this->SwitchAirflowDirection((bool) $Value);
                $this->SendDebug(__FUNCTION__, $Ident . '=' . $Value . ' => ret=' . $r, 0);
                break;
            case 'RotationMode':
                $r = $this->SwitchRotationMode((bool) $Value);
                $this->SendDebug(__FUNCTION__, $Ident . '=' . $Value . ' => ret=' . $r, 0);
                break;
            case 'RotationAngle':
                $r = $this->SetRotationAngle((int) $Value);
                $this->SendDebug(__FUNCTION__, $Ident . '=' . $Value . ' => ret=' . $r, 0);
                break;
            case 'RotationStart':
                $r = $this->SetRotationStart((int) $Value);
                $this->SendDebug(__FUNCTION__, $Ident . '=' . $Value . ' => ret=' . $r, 0);
                break;
            case 'StandbyMonitoring':
                $r = $this->SwitchStandbyMonitoring((bool) $Value);
                $this->SendDebug(__FUNCTION__, $Ident . '=' . $Value . ' => ret=' . $r, 0);
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $Ident, 0);
                break;
        }
    }

    private function str2bool($s)
    {
        return $s == 'ON';
    }

    private function product2field($product_type)
    {
        // STATUS
        $field['rssi'] = false;
        $field['power'] = false;
        $field['airflow_rate'] = false;
        $field['rotation_mode'] = false;
        $field['airflow_direction'] = false;
        $field['automatic_mode'] = false;
        $field['night_mode'] = false;
        $field['sleep_timer'] = false;

        $field['standby_monitoring'] = false;
        $field['carbon_filter'] = false;
        $field['hepa_filter'] = false;

        // ENVIROMENTAL SENSOR DATA
        $field['temperature'] = false;
        $field['humidity'] = false;
        $field['pm25'] = false;
        $field['pm10'] = false;
        $field['voc'] = false;
        $field['nox'] = false;

        switch ($product_type) {
            case 438:
                $field['rssi'] = true;
                $field['power'] = true;
                $field['airflow_rate'] = true;
                $field['rotation_mode'] = true;
                $field['airflow_direction'] = true;
                $field['automatic_mode'] = true;
                $field['night_mode'] = true;
                $field['sleep_timer'] = true;

                $field['standby_monitoring'] = true;
                $field['carbon_filter'] = true;
                $field['hepa_filter'] = true;

                $field['temperature'] = true;
                $field['humidity'] = true;
                $field['pm25'] = true;
                $field['pm10'] = true;
                $field['voc'] = true;
                $field['nox'] = true;
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'unknown product ' . $product_type, 0);
                break;
        }
        return $field;
    }

    private function product2name($product_type)
    {
        $product2name = [
            438 => 'Dyson Pure Cool purifier fan tower',
        ];

        if (isset($product2name[$product_type])) {
            $name = $this->Translate($product2name[$product_type]);
        } else {
            $name = $this->Translate('unknown Dyson product') . ' ' . $product_type;
        }
        return $name;
    }

    private function adjust_rotation(&$angle, &$start, &$end)
    {
        $this->SendDebug(__FUNCTION__, 'ORG: angle=' . $angle . ', start=' . $start . ', end=' . $end, 0);
        if ($angle < 68) {        // 45 + (90 - 45) / 2 = 67.5
            $angle = 45;
        } elseif ($angle < 135) {  // 90 + (180 - 90) / 2 = 135
            $angle = 90;
        } elseif ($angle < 265) {  // 180 + (350 - 180) / 2 = 265
            $angle = 180;
        } else {
            $angle = 350;
        }
        $this->SendDebug(__FUNCTION__, 'MID: angle=' . $angle, 0);

        if ($start < 5) {
            $start = 5;
        } elseif ($start > 309) {
            $start = 309;
        }
        $end = $start + $angle;
        if ($end > 354) {
            $start -= $end - 354;
            $end = $start + $angle;
        }
        $this->SendDebug(__FUNCTION__, 'NEW: angle=' . $angle . ', start=' . $start . ', end=' . $end, 0);
    }

    private function adjustAction($mode)
    {
        $product_type = $this->ReadPropertyString('product_type');
        $field = $this->product2field($product_type);

        if ($field['automatic_mode']) {
            $this->MaintainAction('AutomaticMode', $mode);
        }
        if ($field['night_mode']) {
            $this->MaintainAction('NightMode', $mode);
        }
        if ($field['sleep_timer']) {
            $this->MaintainAction('SleepTimer', $mode);
        }
        if ($field['airflow_rate']) {
            $this->MaintainAction('AirflowRate', $mode);
        }
        if ($field['rotation_mode']) {
            $this->MaintainAction('RotationMode', $mode);
            $this->MaintainAction('RotationAngle', $mode);
            $this->MaintainAction('RotationStart', $mode);
        }
        if ($field['airflow_direction']) {
            $this->MaintainAction('AirflowDirection', $mode);
        }
    }
}
