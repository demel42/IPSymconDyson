<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class DysonDevice extends IPSModule
{
    use Dyson\StubsCommonLib;
    use DysonLocalLib;

    private $ModuleDir;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->ModuleDir = __DIR__;
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('user', '');
        $this->RegisterPropertyString('password', '');
        $this->RegisterPropertyString('country', '');

        $this->RegisterPropertyString('serial', '');
        $this->RegisterPropertyString('product_type', '');

        $this->RegisterPropertyInteger('UpdateStatusInterval', '1');

        $this->RegisterTimer('UpdateStatus', 0, $this->GetModulePrefix() . '_UpdateStatus(' . $this->InstanceID . ');');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);

        $this->RegisterAttributeString('localPassword', '');
        $this->RegisterAttributeString('Auth', '');

        $this->RegisterAttributeString('Faults', '');

        $this->RegisterAttributeString('UpdateInfo', '');

        $this->RequireParent('{F7A0DD2E-7684-95C0-64C2-D2A9DC47577B}');

        $this->InstallVarProfiles(false);
    }

    private function CheckModuleConfiguration()
    {
        $r = [];

        $user = $this->ReadPropertyString('user');
        if ($user == '') {
            $this->SendDebug(__FUNCTION__, '"user" is needed', 0);
            $r[] = $this->Translate('Username must be specified');
        }

        $password = $this->ReadPropertyString('password');
        if ($password == '') {
            $this->SendDebug(__FUNCTION__, '"password" is needed', 0);
            $r[] = $this->Translate('Password must be specified');
        }

        return $r;
    }

    private function CheckModuleUpdate(array $oldInfo, array $newInfo)
    {
        $r = [];

        if ($this->version2num($oldInfo) < $this->version2num('2.5')) {
            $r[] = $this->Translate('Spelling error in variableprofile \'Dyson.RotationMode2\'');
        }

        return $r;
    }

    private function CompleteModuleUpdate(array $oldInfo, array $newInfo)
    {
        if ($this->version2num($oldInfo) < $this->version2num('2.5')) {
            if (IPS_VariableProfileExists('Dyson.RotationMode2')) {
                IPS_DeleteVariableProfile('Dyson.RotationMode2');
            }
            $this->InstallVarProfiles(false);
        }

        return '';
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->MaintainReferences();

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->MaintainStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->MaintainStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->MaintainStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $product_type = $this->ReadPropertyString('product_type');
        $options = $this->product2options($product_type);

        $this->SendDebug(__FUNCTION__, 'option=' . print_r($options, true), 0);

        $vpos = 1;

        $this->MaintainVariable('Power', $this->Translate('Power'), VARIABLETYPE_BOOLEAN, '~Switch', $vpos++, $options['power']);
        if ($options['power']) {
            $this->MaintainAction('Power', true);
        }

        $this->MaintainVariable('AutomaticMode', $this->Translate('Air purification automatic mode'), VARIABLETYPE_BOOLEAN, '~Switch', $vpos++, $options['automatic_mode']);
        if ($options['automatic_mode']) {
            $this->MaintainAction('AutomaticMode', true);
        }

        $this->MaintainVariable('NightMode', $this->Translate('Night mode'), VARIABLETYPE_BOOLEAN, '~Switch', $vpos++, $options['night_mode']);
        if ($options['night_mode']) {
            $this->MaintainAction('NightMode', true);
        }

        $this->MaintainVariable('SleepTimer', $this->Translate('Sleep timer'), VARIABLETYPE_INTEGER, 'Dyson.SleepTimer', $vpos++, $options['sleep_timer']);
        if ($options['sleep_timer']) {
            $this->MaintainAction('SleepTimer', true);
        }

        $this->MaintainVariable('AirflowRate', $this->Translate('Airflow rate'), VARIABLETYPE_INTEGER, 'Dyson.AirflowRate', $vpos++, $options['airflow_rate']);
        if ($options['airflow_rate']) {
            $this->MaintainAction('AirflowRate', true);
        }
        $this->MaintainVariable('RotationMode', $this->Translate('Rotation mode'), VARIABLETYPE_BOOLEAN, '~Switch', $vpos++, $options['rotation_mode']);
        if ($options['rotation_mode']) {
            $this->MaintainAction('RotationMode', true);
        }
        $this->MaintainVariable('RotationAngle', $this->Translate('Rotation angle'), VARIABLETYPE_INTEGER, 'Dyson.RotationAngle', $vpos++, $options['rotation_angle']);
        $this->MaintainVariable('RotationStart', $this->Translate('Rotation start'), VARIABLETYPE_INTEGER, 'Dyson.RotationStart', $vpos++, $options['rotation_angle']);
        if ($options['rotation_angle']) {
            $this->MaintainAction('RotationAngle', true);
            $this->MaintainAction('RotationStart', true);
        }
        $this->MaintainVariable('RotationMode2', $this->Translate('Rotation mode'), VARIABLETYPE_INTEGER, 'Dyson.RotationMode2', $vpos++, $options['rotation_mode2']);
        if ($options['rotation_mode2']) {
            $this->MaintainAction('RotationMode2', true);
        }
        $this->MaintainVariable('AirflowDirection', $this->Translate('Airflow direction'), VARIABLETYPE_BOOLEAN, 'Dyson.AirflowDirection', $vpos++, $options['airflow_direction']);
        if ($options['airflow_direction']) {
            $this->MaintainAction('AirflowDirection', true);
        }
        $this->MaintainVariable('AirflowDistribution', $this->Translate('Airflow distribution'), VARIABLETYPE_BOOLEAN, 'Dyson.AirflowDistribution', $vpos++, $options['airflow_distribution']);
        if ($options['airflow_distribution']) {
            $this->MaintainAction('AirflowDistribution', true);
        }

        $this->MaintainVariable('HeaterMode', $this->Translate('Heater mode'), VARIABLETYPE_BOOLEAN, '~Switch', $vpos++, $options['heating']);
        $this->MaintainVariable('HeatingTemperature', $this->Translate('Heating temperature'), VARIABLETYPE_FLOAT, 'Dyson.HeatingTemperature', $vpos++, $options['heating']);
        if ($options['heating']) {
            $this->MaintainAction('HeaterMode', true);
            $this->MaintainAction('HeatingTemperature', true);
        }

        $this->MaintainVariable('Humidification', $this->Translate('Humidification'), VARIABLETYPE_BOOLEAN, '~Switch', $vpos++, $options['humidify']);
        $this->MaintainVariable('HumidifyAutomaticMode', $this->Translate('Humidify automatic mode'), VARIABLETYPE_BOOLEAN, '~Switch', $vpos++, $options['humidify']);
        $this->MaintainVariable('HumidifyTarget', $this->Translate('Humidify target value'), VARIABLETYPE_FLOAT, 'Dyson.Humidify', $vpos++, $options['humidify']);
        if ($options['humidify']) {
            $this->MaintainAction('Humidification', true);
            $this->MaintainAction('HumidifyAutomaticMode', true);
            $this->MaintainAction('HumidifyTarget', true);
        }
        $this->MaintainVariable('HumidifyAutomaticTarget', $this->Translate('Humidify automatic target value'), VARIABLETYPE_FLOAT, 'Dyson.Humidify', $vpos++, $options['humidify']);
        $this->MaintainVariable('DurationUntilCleaningCycle', $this->Translate('Duration until next deep cleaning cycle'), VARIABLETYPE_INTEGER, 'Dyson.Hours', $vpos++, $options['humidify']);

        $this->MaintainVariable('Temperature', $this->Translate('Temperature'), VARIABLETYPE_FLOAT, 'Dyson.Temperature', $vpos++, $options['temperature']);
        $this->MaintainVariable('Humidity', $this->Translate('Humidity'), VARIABLETYPE_FLOAT, 'Dyson.Humidity', $vpos++, $options['humidity']);
        $this->MaintainVariable('PM25', $this->Translate('Particulate matter (PM 2.5)'), VARIABLETYPE_INTEGER, 'Dyson.PM25', $vpos++, $options['pm25']);
        $this->MaintainVariable('PM10', $this->Translate('Particulate matter (PM 10)'), VARIABLETYPE_INTEGER, 'Dyson.PM10', $vpos++, $options['pm10']);
        $this->MaintainVariable('VOC', $this->Translate('Volatile organic compounds (VOC)'), VARIABLETYPE_INTEGER, 'Dyson.VOC', $vpos++, $options['voc']);
        $this->MaintainVariable('NOx', $this->Translate('Nitrogen oxides (NOx)'), VARIABLETYPE_INTEGER, 'Dyson.NOx', $vpos++, $options['nox']);
        $this->MaintainVariable('DustIndex', $this->Translate('Dust index'), VARIABLETYPE_INTEGER, 'Dyson.DustIndex', $vpos++, $options['dust_index']);
        $this->MaintainVariable('VOCIndex', $this->Translate('Volatile organic compounds (VOC) index'), VARIABLETYPE_INTEGER, 'Dyson.VOCIndex', $vpos++, $options['voc_index']);
        $this->MaintainVariable('HCHO', $this->Translate('Formaldehyd'), VARIABLETYPE_FLOAT, 'Dyson.HCHO', $vpos++, $options['hcho']);

        $this->MaintainVariable('StandbyMonitoring', $this->Translate('Standby monitoring'), VARIABLETYPE_BOOLEAN, '~Switch', $vpos++, $options['standby_monitoring']);
        if ($options['standby_monitoring']) {
            $this->MaintainAction('StandbyMonitoring', true);
        }

        $this->MaintainVariable('CarbonFilterLifetime', $this->Translate('Carbon filter lifetime'), VARIABLETYPE_INTEGER, 'Dyson.Percent', $vpos++, $options['carbon_filter']);
        $this->MaintainVariable('HepaFilterLifetime', $this->Translate('HEPA filter lifetime'), VARIABLETYPE_INTEGER, 'Dyson.Percent', $vpos++, $options['hepa_filter']);

        $this->MaintainVariable('FilterLifetime', $this->Translate('Filter lifetime'), VARIABLETYPE_INTEGER, 'Dyson.Percent', $vpos++, $options['filter_lifetime']);

        $this->MaintainVariable('AirQualityTarget', $this->Translate('Air qualitiy target'), VARIABLETYPE_INTEGER, 'Dyson.AQT', $vpos++, $options['air_quality_target']);
        if ($options['air_quality_target']) {
            $this->MaintainAction('AirQualityTarget', true);
        }

        $this->MaintainVariable('WifiStrength', $this->Translate('Wifi signal strenght'), VARIABLETYPE_INTEGER, 'Dyson.Wifi', $vpos++, true);

        $this->MaintainVariable('LastUpdate', $this->Translate('Last update'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);
        $this->MaintainVariable('LastChange', $this->Translate('Last change'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $this->MaintainVariable('warnings', $this->Translate('Warnings'), VARIABLETYPE_STRING, '', $vpos++, true);
        $this->MaintainVariable('errors', $this->Translate('Errors'), VARIABLETYPE_STRING, '', $vpos++, true);

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->MaintainStatus(IS_INACTIVE);
            return;
        }

        $serial = $this->ReadPropertyString('serial');
        $this->SetSummary($product_type . ' (#' . $serial . ')');

        $this->MaintainStatus(IS_ACTIVE);

        $cID = $this->GetConnectionID();
        if ($cID != false) {
            $this->RegisterMessage($cID, IM_CHANGESTATUS);
            $this->MaintainTimer('UpdateStatus', 2 * 1000);
        }
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('Dyson device');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
        }

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Disable instance'
        ];

        $product_type = $this->ReadPropertyString('product_type');
        $product_name = $this->product2name($product_type);
        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => [
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'user',
                    'caption' => 'User'
                ],
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'password',
                    'caption' => 'Password'
                ],
                [
                    'type'    => 'Select',
                    'name'    => 'country',
                    'caption' => 'Country',
                    'options' => [
                        [
                            'caption' => $this->Translate('England'),
                            'value'   => 'EN'
                        ],
                        [
                            'caption' => $this->Translate('Germany'),
                            'value'   => 'DE'
                        ],
                        [
                            'caption' => $this->Translate('Austria'),
                            'value'   => 'AU'
                        ],
                        [
                            'caption' => $this->Translate('Switzerland'),
                            'value'   => 'CH'
                        ],
                        [
                            'caption' => $this->Translate('Netherlands'),
                            'value'   => 'NL'
                        ],
                        [
                            'caption' => $this->Translate('France'),
                            'value'   => 'FR'
                        ],
                    ],
                ],
                [
                    'type'    => 'Label',
                    'caption' => ''
                ],
                [
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
                ],
            ],
            'caption' => 'Basic configuration (don\'t change)',
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => [
                [
                    'type'    => 'NumberSpinner',
                    'name'    => 'UpdateStatusInterval',
                    'minimum' => 0,
                    'suffix'  => 'Minutes',
                    'caption' => 'Update interval',
                ],
            ],
            'caption' => 'Call settings',
        ];

        return $formElements;
    }

    private function GetFormActions()
    {
        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            $formActions[] = $this->GetCompleteUpdateFormAction();

            $formActions[] = $this->GetInformationFormAction();
            $formActions[] = $this->GetReferencesFormAction();

            return $formActions;
        }

        if ($this->IsInternalMQTTClient() == false) {
            $formActions[] = [
                'type'    => 'Button',
                'caption' => 'Convert MQTT-Client',
                'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "ConvertSplitter", "");',
            ];
        } else {
            $formActions[] = [
                'type'    => 'Button',
                'caption' => 'Update Status',
                'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "ManualUpdateStatus", "");',
            ];

            $formActions[] = [
                'type'      => 'ExpansionPanel',
                'caption'   => 'Reload configuration and log in again if necessary',
                'expanded'  => false,
                'items'     => [
                    [
                        'type'    => 'Label',
                        'caption' => 'Reloading configuration is only required if necessary',
                    ],
                    [
                        'type'    => 'Label',
                        'caption' => 'This action also adjusts configuration of the MQTT-Client',
                    ],
                    [
                        'type'    => 'Button',
                        'caption' => 'Reload Config',
                        'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "ManualReloadConfig", "");',
                    ],
                    [
                        'type'    => 'Label',
                        'caption' => 'Re-login is only required if necessary',
                    ],
                    [
                        'type'    => 'Label',
                        'caption' => 'observe dokumentation',
                    ],
                    [
                        'type'      => 'RowLayout',
                        'items'     => [
                            [
                                'type'    => 'Label',
                                'caption' => 'Step 1',
                            ],
                            [
                                'type'    => 'Button',
                                'caption' => 'Request code',
                                'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "ManualRelogin1", "");',
                            ],
                        ]
                    ],
                    [
                        'type'      => 'RowLayout',
                        'items'     => [
                            [
                                'type'    => 'Label',
                                'caption' => 'Step 2',
                            ],
                            [
                                'type'    => 'ValidationTextBox',
                                'name'    => 'otpCode',
                                'caption' => 'Code (from mail)'
                            ],
                            [
                                'type'    => 'Button',
                                'caption' => 'Verify login',
                                'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "ManualRelogin2", json_encode(["otpCode" => $otpCode]));',
                            ]
                        ]
                    ]
                ]
            ];

            $formActions[] = [
                'type'      => 'ExpansionPanel',
                'caption'   => 'Test area',
                'expanded'  => false,
                'items'     => [
                    [
                        'type'    => 'TestCenter',
                    ]
                ]
            ];

            $formActions[] = [
                'type'      => 'ExpansionPanel',
                'caption'   => 'Expert area',
                'expanded'  => false,
                'items'     => [
                    [
                        'type'    => 'Label',
                        'caption' => 'Test own \'SET-STATE\' command; the command has to be a JSON-coded string (see documentation)',
                    ],
                    [
                        'type'    => 'Label',
                        'caption' => 'Examine the debug-window for information about \'ExecuteSetState\'',
                    ],
                    [
                        'type'    => 'ValidationTextBox',
                        'name'    => 'cmd',
                        'caption' => 'Command'
                    ],
                    [
                        'type'    => 'Button',
                        'caption' => 'Execute',
                        'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "ExecuteSetState", json_encode(["cmd" => $cmd]));',
                    ],
                    [
                        'type'    => 'Label',
                    ],
                    $this->GetInstallVarProfilesFormItem(),
                ],
            ];
        }

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    private function ManualRelogin1()
    {
        $this->SendDebug(__FUNCTION__, '', 0);
        $msg = '';
        $ret = $this->doLogin_2fa_1(true, $msg);
        $this->SendDebug(__FUNCTION__, 'ret=' . $ret . ', msg=' . $msg, 0);
        if ($msg != false) {
            $this->PopupMessage($this->Translate($msg));
        }
    }

    private function ManualRelogin2(string $params)
    {
        $jparams = json_decode($params, true);
        $otpCode = isset($jparams['otpCode']) ? $jparams['otpCode'] : '';

        $this->SendDebug(__FUNCTION__, 'otpCode=' . $otpCode, 0);
        $msg = '';
        $ret = $this->doLogin_2fa_2($otpCode, $msg);
        $this->SendDebug(__FUNCTION__, 'ret=' . $ret . ', msg=' . $msg, 0);
        if ($msg != false) {
            $this->PopupMessage($this->Translate($msg));
        }
    }

    public function GetConfigurationForParent()
    {
        $r = IPS_GetConfiguration($this->GetConnectionID());
        $this->SendDebug(__FUNCTION__, print_r($r, true), 0);
        return $r;
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug(__FUNCTION__, 'SenderID=' . $SenderID . ', MessageID=' . $Message . ', Data=' . print_r($Data, true), 0);

        switch ($Message) {
            case IM_CHANGESTATUS:
                if ($SenderID == $this->GetConnectionID() && $Data[0] == IS_ACTIVE) {
                    $this->SendDebug(__FUNCTION__, 'MQTT-Client changed to active', 0);
                }
                break;
            case IPS_KERNELSTARTED:
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'Unknown Message' . $Message, 0);
                break;
        }
    }

    private function ManualReloadConfig()
    {
        $cID = $this->GetConnectionID();
        if ($cID == false) {
            $this->SendDebug(__FUNCTION__, 'has no parent instance', 0);
            $msg = $this->Translate('has no parent instance');
            $this->PopupMessage($msg);
            return;
        }

        $doApply = false;

        $serial = $this->ReadPropertyString('serial');
        $device = $this->getDevice($serial);
        if ($device == false) {
            $msg = $this->Translate('Load config failed');
            $this->PopupMessage($msg);
            return;
        }
        $this->SendDebug(__FUNCTION__, 'device=' . print_r($device, true), 0);
        $oldPw = $this->ReadAttributeString('localPassword');
        $newPw = $this->decryptPassword($device['LocalCredentials']);
        if ($newPw != false && $newPw != $oldPw) {
            $this->WriteAttributeString('localPassword', $newPw);
            $this->SendDebug(__FUNCTION__, 'set property "Password" of instance ' . $cID . ' to "' . $newPw . '"', 0);
            if (IPS_SetProperty($cID, 'Password', $newPw)) {
                $this->SendDebug(__FUNCTION__, 'Password=' . $newPw, 0);
                $doApply = true;
            }
        }

        if (IPS_GetProperty($cID, 'UserName') != $serial && IPS_SetProperty($cID, 'UserName', $serial)) {
            $this->SendDebug(__FUNCTION__, 'UserName=' . $serial, 0);
            $doApply = true;
        }

        if (IPS_GetProperty($cID, 'ClientID') != 'symcon' && IPS_SetProperty($cID, 'ClientID', 'symcon')) {
            $this->SendDebug(__FUNCTION__, 'ClientID=' . 'symcon', 0);
            $doApply = true;
        }

        $product_type = $this->ReadPropertyString('product_type');
        $serial = $this->ReadPropertyString('serial');
        $topics = [];
        foreach (['current', 'faults', 'connection', 'software', 'summary'] as $sub) {
            $topics[] = [
                'Topic' => $product_type . '/' . $serial . '/status/' . $sub,
                'QoS'   => 0
            ];
        }
        $subscriptions = json_encode($topics);
        if (IPS_GetProperty($cID, 'Subscriptions') != $subscriptions && IPS_SetProperty($cID, 'Subscriptions', $subscriptions)) {
            $this->SendDebug(__FUNCTION__, 'Subscriptions=' . $subscriptions, 0);
            $doApply = true;
        }

        /*
            Ausnahme zu Punkt 13 der Reviewrichtlinien, wurde per Mail vom 07.07.2020 von Niels genehmigt
            Grund: das Passwort, das im MQTTClient als Property gesetzt werden muss, kann sich ändern. Der aktuelle
            Wert wird zyklisch per HTTP von der Dyson-Cloud geholt und bei Änderungen in der MQTTClient-Instanz gesetzt.
         */
        if ($doApply) {
            IPS_ApplyChanges($cID);
        }

        $msg = $this->Translate('Load config succeeded');
        $this->PopupMessage($msg);
    }

    private function DecodeState($payload, $changeState)
    {
        $now = time();
        $is_changed = false;

        $product_type = $this->ReadPropertyString('product_type');
        $options = $this->product2options($product_type);

        $used_fields = [];
        $missing_fields = [];
        $ignored_fields = ['mode-reason', 'state-reason'];

        $this->SendDebug(__FUNCTION__, 'used variables', 0);

        $msg = $payload['msg'];
        $used_fields[] = 'msg';

        $ts = strtotime($payload['time']);
        $used_fields[] = 'time';
        $this->SendDebug(__FUNCTION__, '... msg=' . $msg . ', time=' . date('d.m.y H:i:s', $ts), 0);

        if ($changeState == false) {
            if ($options['rssi']) {
                // rssi - wifi signal strength
                $rssi = (int) $this->GetArrayElem($payload, 'rssi', 0);
                $used_fields[] = 'rssi';
                $this->SendDebug(__FUNCTION__, '... rssi=' . $rssi, 0);
                $this->SaveValue('WifiStrength', $rssi, $is_changed);
            }
        }

        // channel - wifi channel
        $ignored_fields[] = 'channel';

        if ($options['power']) {
            if ($options['power_use_fmod']) {
                // fmod - fan mode (OFF|FAN|AUTO)
                $fmod = $this->GetArrayElem($payload, 'product-state.fmod', '');
                if ($fmod != '') {
                    $used_fields[] = 'product-state.fmod';
                    if ($changeState) {
                        $do = $fmod[0] != $fmod[1];
                        $fmod = $fmod[1];
                    } else {
                        $do = true;
                    }
                    if ($do) {
                        $b = $this->str2bool($fmod);
                        $this->SendDebug(__FUNCTION__, '... fan power (fmod)=' . $fmod . ' => ' . $this->bool2str($b), 0);
                        $this->SaveValue('Power', $b, $is_changed);
                        $this->AdjustActions($b);
                    }
                } else {
                    $missing_fields[] = 'product-state.fmod';
                }
            } else {
                // fpwr - Fan Power (OFF|ON)
                $fpwr = $this->GetArrayElem($payload, 'product-state.fpwr', '');
                if ($fpwr != '') {
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
                        $this->AdjustActions($b);
                    }
                } else {
                    $missing_fields[] = 'product-state.fpwr';
                }

                // fmod - fan mode (OFF|FAN|AUTO)
                $ignored_fields[] = 'product-state.fmod';
            }

            // fnst - fan status (OFF|FAN)
            $ignored_fields[] = 'product-state.fnst';
        }

        if ($options['airflow_rate']) {
            // fnsp - fan speed (AUTO|OFF|1..9)
            $fnsp = $this->GetArrayElem($payload, 'product-state.fnsp', '');
            if ($fnsp != '') {
                $used_fields[] = 'product-state.fnsp';
                if ($changeState) {
                    $do = $fnsp[0] != $fnsp[1];
                    $fnsp = $fnsp[1];
                } else {
                    $do = true;
                }
                if ($do) {
                    switch ($fnsp) {
                        case 'OFF':
                            $i = 0;
                            break;
                        case 'AUTO':
                            $i = -1;
                            break;
                        default:
                            $i = (int) $fnsp;
                            break;
                    }
                    $this->SendDebug(__FUNCTION__, '... fan speed (fnsp)=' . $fnsp . ' => ' . $i, 0);
                    $this->SaveValue('AirflowRate', $i, $is_changed);
                }
            } else {
                $missing_fields[] = 'product-state.fnsp';
            }
        }

        if ($options['rotation_mode']) {
            if ($options['rotation_mode_use_oson']) {
                // oson - oscillation on (OFF|ON)
                $oson = $this->GetArrayElem($payload, 'product-state.oson', '');
                if ($oson != '') {
                    $used_fields[] = 'product-state.oson';

                    if ($changeState) {
                        $do = $oson[0] != $oson[1];
                        $oson = $oson[1];
                    } else {
                        $do = true;
                    }
                    if ($do) {
                        $b = $this->str2bool($oson);
                        $this->SendDebug(__FUNCTION__, '... oscillation mode (oson)=' . $oson . ' => ' . $this->bool2str($b), 0);
                        $this->SaveValue('RotationMode', $b, $is_changed);
                    }
                } else {
                    $missing_fields[] = 'product-state.oson';
                }
            } else {
                // oson - oscillation on (OFF|ON)
                $ignored_fields[] = 'product-state.oson';

                // oscs - oscillation state (OFF|ON)
                $oscs = $this->GetArrayElem($payload, 'product-state.oscs', '');
                if ($oscs != '') {
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
                } else {
                    $missing_fields[] = 'product-state.oscs';
                }
            }
        }

        if ($options['rotation_mode2']) {
            // oson - oscillation on (OFF|ON)
            $oson = $this->GetArrayElem($payload, 'product-state.oson', '');
            if ($oson != '') {
                $used_fields[] = 'product-state.oson';
            } else {
                $missing_fields[] = 'product-state.oson';
            }

            // ancp - fan focus (OFF|45|90|BRZE)
            $ancp = $this->GetArrayElem($payload, 'product-state.ancp', '');
            if ($ancp != '') {
                $used_fields[] = 'product-state.ancp';
            } else {
                $missing_fields[] = 'product-state.ancp';
            }

            if ($oson != '' && $ancp != '') {
                if ($changeState) {
                    $do = ($oson[0] != $oson[1]) || ($ancp[0] != $ancp[1]);
                    $oson = $oson[1];
                    $ancp = $ancp[1];
                } else {
                    $do = true;
                }
                if ($do) {
                    if ($oson == 'OFF') {
                        $mode = 0;
                    } else {
                        switch ($ancp) {
                            case 'BRZE':
                                $mode = 360;
                                break;
                            default:
                                $mode = (int) $ancp;
                                break;
                        }
                    }
                    $this->SendDebug(__FUNCTION__, '... oscillation mode (oson)=' . $oson . ', (ancp)=' . $ancp . ' => mode=' . $mode, 0);
                    $this->SaveValue('RotationMode2', $mode, $is_changed);
                }
            }

            // osal - oscillation angle low (5..309)
            $ignored_fields[] = 'product-state.osal';

            // osau - oscillation angle up (50..354)
            $ignored_fields[] = 'product-state.osau';
        }

        if ($options['rotation_angle']) {
            // osal - oscillation angle low (5..309)
            $osal = $this->GetArrayElem($payload, 'product-state.osal', '');
            if ($osal != '') {
                $used_fields[] = 'product-state.osal';
            } else {
                $missing_fields[] = 'product-state.osal';
            }

            // osau - oscillation angle up (50..354)
            $osau = $this->GetArrayElem($payload, 'product-state.osau', '');
            if ($osau != '') {
                $used_fields[] = 'product-state.osau';
            } else {
                $missing_fields[] = 'product-state.osau';
            }

            if ($osal != '' && $osau != '') {
                if ($changeState) {
                    $do = ($osal[0] != $osal[1]) || ($osau[0] != $osau[1]);
                    $osal = $osal[1];
                    $osau = $osau[1];
                } else {
                    $do = true;
                }
                if ($do) {
                    $angle = (int) $osau - (int) $osal;
                    $start = (int) $osal;
                    $end = 0;
                    $this->adjust_rotation($angle, $start, $end);

                    $this->SendDebug(__FUNCTION__, '... oscillation angle low (osal)=' . $osal . ', up (osau)=' . $osau . ' => angle=' . $angle . ', start=' . $start . ', end=' . $end, 0);
                    $this->SaveValue('RotationAngle', $angle, $is_changed);
                    $this->SaveValue('RotationStart', $start, $is_changed);
                }
            }

            // ancp - angle ... (CUST)
            $ignored_fields[] = 'product-state.ancp';
        }

        if ($options['airflow_direction']) {
            // fdir - front direction  (OFF|ON)
            $fdir = $this->GetArrayElem($payload, 'product-state.fdir', '');
            if ($fdir != '') {
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
            } else {
                $missing_fields[] = 'product-state.fdir';
            }
        }

        if ($options['airflow_distribution']) {
            // ffoc - fan focus (OFF|ON)
            $ffoc = $this->GetArrayElem($payload, 'product-state.ffoc', '');
            if ($ffoc != '') {
                $used_fields[] = 'product-state.ffoc';
                if ($changeState) {
                    $do = $ffoc[0] != $ffoc[1];
                    $ffoc = $ffoc[1];
                } else {
                    $do = true;
                }
                if ($do) {
                    $b = $this->str2bool($ffoc);
                    $this->SendDebug(__FUNCTION__, '... fan focus (ffoc)=' . $ffoc . ' => ' . $this->bool2str($b), 0);
                    $this->SaveValue('AirflowDistribution', $b, $is_changed);
                }
            } else {
                $missing_fields[] = 'product-state.ffoc';
            }
        }

        if ($options['automatic_mode']) {
            if ($options['automatic_mode_use_fmod']) {
                // fmod - fan mode (OFF|FAN|AUTO)
                $fmod = $this->GetArrayElem($payload, 'product-state.fmod', '');
                if ($fmod != '') {
                    $used_fields[] = 'product-state.fmod';
                    if ($changeState) {
                        $do = $fmod[0] != $fmod[1];
                        $fmod = $fmod[1];
                    } else {
                        $do = true;
                    }
                    if ($do) {
                        $b = $fmod == 'AUTO';
                        $this->SendDebug(__FUNCTION__, '... automatic mod from fan mode (fmod)=' . $fmod . ' => ' . $this->bool2str($b), 0);
                        $this->SaveValue('AutomaticMode', $b, $is_changed);
                    }
                } else {
                    $missing_fields[] = 'product-state.fmod';
                }
            } else {
                // auto - automatic mode (OFF|ON)
                $auto = $this->GetArrayElem($payload, 'product-state.auto', '');
                if ($auto != '') {
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
                } else {
                    $missing_fields[] = 'product-state.auto';
                }
            }
        }

        if ($options['humidify']) {
            // hume - humidification (HUMD|OFF)
            $hume = $this->GetArrayElem($payload, 'product-state.hume', '');
            if ($hume != '') {
                $used_fields[] = 'product-state.hume';
                if ($changeState) {
                    $do = $hume[0] != $hume[1];
                    $hume = $hume[1];
                } else {
                    $do = true;
                }
                if ($do) {
                    $b = $this->str2bool($hume);
                    $this->SendDebug(__FUNCTION__, '... humidification (hume)=' . $hume . ' => ' . $this->bool2str($b), 0);
                    $this->SaveValue('Humidification', $b, $is_changed);
                }
            } else {
                $missing_fields[] = 'product-state.hume';
            }

            // humt - humidify target
            $humt = $this->GetArrayElem($payload, 'product-state.humt', '');
            if ($humt != '') {
                $used_fields[] = 'product-state.humt';
                if ($changeState) {
                    $do = $humt[0] != $humt[1];
                    $humt = $humt[1];
                } else {
                    $do = true;
                }
                if ($do) {
                    $hum = (int) $humt;
                    $this->SendDebug(__FUNCTION__, '... humidify target (humt)=' . $hum, 0);
                    $this->SaveValue('HumidifyTarget', $hum, $is_changed);
                }
            } else {
                $missing_fields[] = 'product-state.humt';
            }

            // haut - humidify automatic (ON|OFF)
            $haut = $this->GetArrayElem($payload, 'product-state.haut', '');
            if ($haut != '') {
                $used_fields[] = 'product-state.haut';
                if ($changeState) {
                    $do = $haut[0] != $haut[1];
                    $haut = $haut[1];
                } else {
                    $do = true;
                }
                if ($do) {
                    $b = $this->str2bool($haut);
                    $this->SendDebug(__FUNCTION__, '... automatic mode (haut)=' . $haut . ' => ' . $this->bool2str($b), 0);
                    $this->SaveValue('HumidifyAutomaticMode', $b, $is_changed);
                }
            } else {
                $missing_fields[] = 'product-state.haut';
            }

            // rect - humidify internal set point
            $rect = $this->GetArrayElem($payload, 'product-state.rect', '');
            if ($rect != '') {
                $used_fields[] = 'product-state.rect';
                $hum = (int) $rect;
                $this->SendDebug(__FUNCTION__, '... humidify internal set point (rect)=' . $hum, 0);
                $this->SaveValue('HumidifyAutomaticTarget', $hum, $is_changed);
            } else {
                $missing_fields[] = 'product-state.rect';
            }

            // cltr - cleaning cylcle timerange
            $cltr = $this->GetArrayElem($payload, 'product-state.cltr', '');
            if ($cltr != '') {
                $used_fields[] = 'product-state.cltr';
                $dur = (int) $cltr;
                $this->SendDebug(__FUNCTION__, '... cleaning cylcle timerange (cltr)=' . $dur, 0);
                $this->SaveValue('DurationUntilCleaningCycle', $dur, $is_changed);
            } else {
                $missing_fields[] = 'product-state.cltr';
            }
        }

        if ($options['night_mode']) {
            // nmod - night mode (OFF|ON)
            $nmod = $this->GetArrayElem($payload, 'product-state.nmod', '');
            if ($nmod != '') {
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
            } else {
                $missing_fields[] = 'product-state.nmod';
            }
        }

        if ($options['sleep_timer']) {
            if ($options['sleep_timer_from_sensor'] == false) {
                // sltm - sleep-timer (OFF|1..539)
                $sltm = $this->GetArrayElem($payload, 'product-state.sltm', '');
                if ($sltm != '') {
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
                } else {
                    $missing_fields[] = 'product-state.sltm';
                }
            } else {
                $ignored_fields[] = 'product-state.sltm';
            }
        }

        if ($options['carbon_filter']) {
            // cflt - carbon filter type (CARF)
            $ignored_fields[] = 'product-state.cflt';

            // cflr - carbon filter range (0..100%)
            $cflr = $this->GetArrayElem($payload, 'product-state.cflr', '');
            if ($cflr != '') {
                $used_fields[] = 'product-state.cflr';
                if ($changeState) {
                    $do = $cflr[0] != $cflr[1];
                    $cflr = $cflr[1];
                } else {
                    $do = true;
                }
                if ($do) {
                    $this->SendDebug(__FUNCTION__, '... carbon filter lifetime (cflr)=' . $cflr, 0);
                    $this->SaveValue('CarbonFilterLifetime', $cflr, $is_changed);
                }
            } else {
                $missing_fields[] = 'product-state.cflr';
            }
        }

        if ($options['hepa_filter']) {
            // hflt - hepa filter type (GHEP|GCOM)
            $ignored_fields[] = 'product-state.hflt';

            // hflr - hepa filter range (0..100%)
            $hflr = $this->GetArrayElem($payload, 'product-state.hflr', '');
            if ($hflr != '') {
                $used_fields[] = 'product-state.hflr';
                if ($changeState) {
                    $do = $hflr[0] != $hflr[1];
                    $hflr = $hflr[1];
                } else {
                    $do = true;
                }
                if ($do) {
                    $this->SendDebug(__FUNCTION__, '... hepa filter lifetime (hflr)=' . $hflr, 0);
                    $this->SaveValue('HepaFilterLifetime', $hflr, $is_changed);
                }
            } else {
                $missing_fields[] = 'product-state.hflr';
            }
        }

        if ($options['standby_monitoring']) {
            // rhtm - standby monitoring (OFF|ON)
            $rhtm = $this->GetArrayElem($payload, 'product-state.rhtm', '');
            if ($rhtm != '') {
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
            } else {
                $missing_fields[] = 'product-state.rhtm';
            }
        }

        if ($options['heating']) {
            // hmod - heater mode  => OFF
            $hmod = $this->GetArrayElem($payload, 'product-state.hmod', '');
            if ($hmod != '') {
                $used_fields[] = 'product-state.hmod';
                if ($changeState) {
                    $do = $hmod[0] != $hmod[1];
                    $hmod = $hmod[1];
                } else {
                    $do = true;
                }
                if ($do) {
                    $b = $this->str2bool($hmod);
                    $this->SendDebug(__FUNCTION__, '... heater mode (hmod)=' . $hmod . ' => ' . $this->bool2str($b), 0);
                    $this->SaveValue('HeaterMode', $b, $is_changed);
                }
            } else {
                $missing_fields[] = 'product-state.hmod';
            }

            // hmax - heating temperature (1/10° K)
            $hmax = $this->GetArrayElem($payload, 'product-state.hmax', '');
            if ($hmax != '') {
                $used_fields[] = 'product-state.hmax';
                if ($changeState) {
                    $do = $hmax[0] != $hmax[1];
                    $hmax = $hmax[1];
                } else {
                    $do = true;
                }
                if ($do) {
                    $temp = (float) $this->decode_temperature($hmax);
                    $this->SendDebug(__FUNCTION__, '... temperature (hmax)=' . $hmax . ' => ' . $temp, 0);
                    $this->SaveValue('HeatingTemperature', $temp, $is_changed);
                }
            } else {
                $missing_fields[] = 'product-state.hmax';
            }

            // hsta - heater status(OFF|ON)
            $ignore_fields[] = 'product-state.hsta';
        }

        if ($options['filter_lifetime']) {
            // filf - filter life (in Stunden)
            $filf = $this->GetArrayElem($payload, 'product-state.filf', '');
            if ($filf != '') {
                $used_fields[] = 'product-state.filf';
                if ($changeState) {
                    $do = $filf[0] != $filf[1];
                    $filf = $filf[1];
                } else {
                    $do = true;
                }
                if ($do) {
                    $perc = (int) round($filf / $options['filter_lifetime_max'] * 100);
                    $this->SendDebug(__FUNCTION__, '... filter lifetime (filf)=' . $filf . ' => ' . $perc . '%', 0);
                    $this->SaveValue('FilterLifetime', $perc, $is_changed);
                }
            } else {
                $missing_fields[] = 'product-state.filf';
            }
        }

        if ($options['air_quality_target']) {
            // qtar - quality target (Zielwert der Luftqualität: 1="LOW", 3="AVERAGE", 4="HIGH")
            $qtar = $this->GetArrayElem($payload, 'product-state.qtar', '');
            if ($qtar != '') {
                $used_fields[] = 'product-state.qtar';
                if ($changeState) {
                    $do = $qtar[0] != $qtar[1];
                    $qtar = $qtar[1];
                } else {
                    $do = true;
                }
                if ($do) {
                    $this->SendDebug(__FUNCTION__, '... air quality target (qtar)=' . $qtar, 0);
                    $this->SaveValue('AirQualityTarget', (int) $qtar, $is_changed);
                }
            } else {
                $missing_fields[] = 'product-state.qtar';
            }
        }

        // dial - unklar
        // tilt - device tilt

        $this->SetValue('LastUpdate', $now);
        if ($is_changed) {
            $this->SetValue('LastChange', $ts);
        }

        if ($missing_fields != []) {
            $this->SendDebug(__FUNCTION__, 'missing fields', 0);
            foreach ($missing_fields as $var) {
                $this->SendDebug(__FUNCTION__, '... ' . $var, 0);
            }
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
        $options = $this->product2options($product_type);
        $this->SendDebug(__FUNCTION__, 'option=' . print_r($options, true), 0);

        $now = time();
        $is_changed = false;

        $used_fields = [];
        $missing_fields = [];
        $ignored_fields = [];

        $this->SendDebug(__FUNCTION__, 'used variables', 0);

        $msg = $payload['msg'];
        $used_fields[] = 'msg';

        $ts = strtotime($payload['time']);
        $used_fields[] = 'time';
        $this->SendDebug(__FUNCTION__, '... msg=' . $msg . ', time=' . date('d.m.y H:i:s', $ts), 0);

        if ($options['temperature']) {
            // tact - temperature actual (1/10° K)
            $tact = $this->GetArrayElem($payload, 'data.tact', '');
            if ($tact != '') {
                $used_fields[] = 'data.tact';
                $temp = (float) $this->decode_temperature($tact);
                $this->SendDebug(__FUNCTION__, '... temperature (tact)=' . $tact . ' => ' . $temp, 0);
                $this->SaveValue('Temperature', $temp, $is_changed);
            } else {
                $missing_fields[] = 'data.tact';
            }
        }

        if ($options['humidity']) {
            // hact - humidity actual
            $hact = $this->GetArrayElem($payload, 'data.hact', '');
            if ($hact != '') {
                $used_fields[] = 'data.hact';
                $hum = (int) $hact;
                $this->SendDebug(__FUNCTION__, '... humidity (hact)=' . $hum, 0);
                $this->SaveValue('Humidity', $hum, $is_changed);
            } else {
                $missing_fields[] = 'data.hact';
            }
        }

        if ($options['pm25']) {
            // pm25 - PM25 ?
            $ignored_fields[] = 'data.pm25';

            // p25r - PM 2.5 real
            $p25r = $this->GetArrayElem($payload, 'data.p25r', '');
            if ($p25r != '') {
                $used_fields[] = 'data.p25r';
                $pm25 = (int) $p25r;
                $this->SendDebug(__FUNCTION__, '... PM2.5 (p25r)=' . $pm25, 0);
                $this->SaveValue('PM25', $pm25, $is_changed);
            } else {
                $missing_fields[] = 'data.p25r';
            }
        }

        if ($options['pm10']) {
            // pm10 - PM10 ?
            $ignored_fields[] = 'data.pm10';

            // p10r - PM 10 real
            $p10r = $this->GetArrayElem($payload, 'data.p10r', '');
            if ($p10r != '') {
                $used_fields[] = 'data.p10r';
                $pm10 = (int) $p10r;
                $this->SendDebug(__FUNCTION__, '... PM10 (p10r)=' . $pm10, 0);
                $this->SaveValue('PM10', $pm10, $is_changed);
            } else {
                $missing_fields[] = 'data.p10r';
            }
        }

        if ($options['dust_index']) {
            // pact - particular matter aktual
            $pact = $this->GetArrayElem($payload, 'data.pact', '');
            if ($pact != '') {
                $used_fields[] = 'data.pact';
                $dust = (int) $pact;
                $this->SendDebug(__FUNCTION__, '... Dust (pact)=' . $dust, 0);
                $this->SaveValue('DustIndex', $dust, $is_changed);
            } else {
                $missing_fields[] = 'data.pact';
            }
        }

        if ($options['voc']) {
            // va10 - volatile organic compounds
            $va10 = $this->GetArrayElem($payload, 'data.va10', '');
            if ($va10 != '') {
                $used_fields[] = 'data.va10';
                $voc = floor((int) $va10 * 0.125);
                $this->SendDebug(__FUNCTION__, '... VOC (va10)=' . $voc, 0);
                $this->SaveValue('VOC', $voc, $is_changed);
            } else {
                $missing_fields[] = 'data.va10';
            }
        }

        if ($options['voc_index']) {
            // vact - volatile organic compounds
            $vact = $this->GetArrayElem($payload, 'data.vact', '');
            if ($vact != '') {
                $used_fields[] = 'data.vact';
                $voc = (int) $vact;
                $this->SendDebug(__FUNCTION__, '... VOC (vact)=' . $voc, 0);
                $this->SaveValue('VOCIndex', $voc, $is_changed);
            } else {
                $missing_fields[] = 'data.vact';
            }
        }

        if ($options['nox']) {
            // noxl - nitrogen oxides
            $noxl = $this->GetArrayElem($payload, 'data.noxl', 0);
            if ($noxl != '') {
                $used_fields[] = 'data.noxl';
                $nox = (int) $noxl;
                $this->SendDebug(__FUNCTION__, '... NOx (noxl)=' . $nox, 0);
                $this->SaveValue('NOx', $nox, $is_changed);
            } else {
                $missing_fields[] = 'data.noxl';
            }
        }

        if ($options['hcho']) {
            // hcho - formaldehyd index
            $ignored_fields[] = 'data.hcho';

            // hchr - formaldehyd real
            $hchr = $this->GetArrayElem($payload, 'data.hchr', '');
            if ($hchr != '') {
                $used_fields[] = 'data.hchr';
                $hcho = (int) $hchr / 1000;
                $this->SendDebug(__FUNCTION__, '... PM10 (hchr)=' . $hcho, 0);
                $this->SaveValue('HCHO', $hcho, $is_changed);
            } else {
                $missing_fields[] = 'data.hchr';
            }
        }

        if ($options['sleep_timer']) {
            if ($options['sleep_timer_from_sensor']) {
                // sltm - sleep-timer (OFF|1..539)
                $sltm = $this->GetArrayElem($payload, 'data.sltm', '');
                if ($sltm != '') {
                    $used_fields[] = 'data.sltm';
                    $sleep_timer = $sltm == 'OFF' ? 0 : (int) $sltm;
                    $this->SendDebug(__FUNCTION__, '... sleep timer (sltm)=' . $sltm . ' => ' . $sleep_timer, 0);
                    $this->SaveValue('SleepTimer', $sleep_timer, $is_changed);
                } else {
                    $missing_fields[] = 'data.sltm';
                }
            } else {
                $ignored_fields[] = 'data.sltm';
            }
        }

        $this->SetValue('LastUpdate', $now);
        if ($is_changed) {
            $this->SetValue('LastChange', $ts);
        }

        if ($missing_fields != []) {
            $this->SendDebug(__FUNCTION__, 'missing fields', 0);
            foreach ($missing_fields as $var) {
                $this->SendDebug(__FUNCTION__, '... ' . $var, 0);
            }
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

    private function DecodeFaults($payload, $changeState)
    {
        $now = time();
        $is_changed = false;

        $product_type = $this->ReadPropertyString('product_type');
        $options = $this->product2options($product_type);

        $this->SendDebug(__FUNCTION__, 'used variables', 0);

        $msg = $payload['msg'];
        $ts = strtotime($payload['time']);
        $this->SendDebug(__FUNCTION__, '... msg=' . $msg . ', time=' . date('d.m.y H:i:s', $ts), 0);

        $faults = json_decode($this->ReadAttributeString('Faults'), true);
        if ($faults == false) {
            $faults = [];
        }
        foreach (['errors', 'warnings'] as $lvl) {
            $txt = '';
            foreach (['product', 'module'] as $typ) {
                $fld = $typ . '-' . $lvl;
                foreach ($payload[$fld] as $var => $val) {
                    if ($changeState) {
                        $val = $val[1];
                    }
                    $faults[$fld][$var] = $val;
                    if ($val == 'OK') {
                        continue;
                    }
                    $txt .= $this->fault2text($var) . PHP_EOL;
                }
            }
            $this->SendDebug(__FUNCTION__, '... ' . $lvl . '=' . $txt, 0);
            $this->SaveValue($lvl, $txt, $is_changed);
        }
        $this->WriteAttributeString('Faults', json_encode($faults));
        $this->SendDebug(__FUNCTION__, 'faults=' . print_r($faults, true), 0);

        $this->SetValue('LastUpdate', $now);
        if ($is_changed) {
            $this->SetValue('LastChange', $ts);
        }
    }

    public function ReceiveData($data)
    {
        $this->SendDebug(__FUNCTION__, 'data=' . $data, 0);

        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $jdata = json_decode($data, true);
        if (isset($jdata['Payload'])) {
            $payload = json_decode($jdata['Payload'], true);
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
                case 'CURRENT-FAULTS':
                    $this->DecodeFaults($payload, false);
                    break;
                case 'FAULTS-CHANGE':
                    $this->DecodeFaults($payload, true);
                    break;
                default:
                    $this->SendDebug(__FUNCTION__, 'unknown msg=' . $msg . ', time=' . date('d.m.y H:i:s', $ts), 0);
                    break;
            }
        }

        $this->MaintainStatus(IS_ACTIVE);
    }

    private function ManualUpdateStatus()
    {
        $this->RequestStateCommand();
    }

    public function UpdateStatus()
    {
        $this->RequestStateCommand();

        $min = $this->ReadPropertyInteger('UpdateStatusInterval');
        $this->MaintainTimer('UpdateStatus', $min * 60 * 1000);
    }

    private function RequestStateCommand()
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $payload = [
            'msg'         => 'REQUEST-CURRENT-STATE',
            'time'        => strftime('%Y-%m-%dT%H:%M:%SZ', time()),
            'mode-reason' => 'LAPP',
        ];

        $this->SendCommand(__FUNCTION__, json_encode($payload));
    }

    private function SendCommand($func, $payload)
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent instance', 0);
            $this->LogMessage('has no active parent instance', KL_WARNING);
            return;
        }

        $serial = $this->ReadPropertyString('serial');
        $product_type = $this->ReadPropertyString('product_type');
        $topic = $product_type . '/' . $serial . '/command';

        $json = [
            'DataID'           => '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}',
            'PacketType'       => 3,
            'QualityOfService' => 0,
            'Retain'           => false,
            'Topic'            => $topic,
            'Payload'          => utf8_encode($payload)
        ];

        $this->SendDebug(__FUNCTION__, 'func=' . $func . ', cmd=' . print_r($json, true), 0);
        parent::SendDataToParent(json_encode($json));
    }

    private function checkAction($func, $verbose)
    {
        $enabled = false;

        $product_type = $this->ReadPropertyString('product_type');
        $options = $this->product2options($product_type);

        switch ($func) {
            case 'SwitchPower':
                if ($options['power']) {
                    $enabled = true;
                }
                $this->SendDebug(__FUNCTION__, 'field[\'power\']=' . $this->bool2str($options['power']) . ', enabled=' . $this->bool2str($enabled), 0);
                break;
            case 'SwitchAutomaticMode':
                if ($options['automatic_mode']) {
                    $enabled = true;
                }
                break;
            case 'SwitchNightMode':
                if ($options['night_mode']) {
                    $enabled = true;
                }
                break;
            case 'SetSleepTimer':
                if ($options['sleep_timer']) {
                    $enabled = true;
                }
                break;
            case 'SetAirflowRate':
                if ($options['airflow_rate']) {
                    $enabled = true;
                }
                break;
            case 'SwitchAirflowDirection':
                if ($options['airflow_direction']) {
                    $enabled = true;
                }
                break;
            case 'SwitchAirflowDistribution':
                if ($options['airflow_distribution']) {
                    $enabled = true;
                }
                break;
            case 'SwitchRotationMode':
                if ($options['rotation_mode']) {
                    $enabled = true;
                }
                break;
            case 'SwitchRotationMode2':
                if ($options['rotation_mode2']) {
                    $enabled = true;
                }
                break;
            case 'SetRotationAngle':
                if ($options['rotation_angle']) {
                    $enabled = true;
                }
                break;
            case 'SetRotationStart':
                if ($options['rotation_angle']) {
                    $enabled = true;
                }
                break;
            case 'SwitchStandbyMonitoring':
                if ($options['standby_monitoring']) {
                    $enabled = true;
                }
                break;
            case 'SwitchHeater':
                if ($options['heating']) {
                    $enabled = true;
                }
                break;
            case 'SetHeatingTemperature':
                if ($options['heating']) {
                    $enabled = true;
                }
                break;
            case 'SetAirQualityTarget':
                if ($options['air_quality_target']) {
                    $enabled = true;
                }
                break;
            case 'SwitchHumidification':
                if ($options['humidify']) {
                    $enabled = true;
                }
                break;
            case 'SwitchHumidifyAutomaticMode':
                if ($options['humidify']) {
                    $enabled = true;
                }
                break;
            case 'SetHumidifyTarget':
                if ($options['humidify']) {
                    $enabled = true;
                }
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'unsupported action "' . $func . '"', 0);
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

    private function ExecuteSetState(string $params)
    {
        $jparams = json_decode($params, true);
        $cmd = isset($jparams['cmd']) ? $jparams['cmd'] : '';

        $this->SendDebug(__FUNCTION__, 'cmd=' . print_r($cmd, true), 0);
        $data = json_decode($cmd, true);
        if ($data == '') {
            $this->SendDebug(__FUNCTION__, 'json_error=' . json_last_error_msg(), 0);
            return;
        }
        return $this->SetStateCommand(__FUNCTION__, $data);
    }

    private function SwitchPower(bool $mode)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $product_type = $this->ReadPropertyString('product_type');
        $options = $this->product2options($product_type);

        if ($options['power_use_fmod']) {
            $data = [
                'fmod' => ($mode ? 'FAN' : 'OFF')
            ];
        } else {
            $data = [
                'fpwr' => ($mode ? 'ON' : 'OFF')
            ];
        }

        return $this->SetStateCommand(__FUNCTION__, $data);
    }

    private function SwitchAutomaticMode(bool $mode)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $product_type = $this->ReadPropertyString('product_type');
        $options = $this->product2options($product_type);

        if ($options['automatic_mode_use_fmod']) {
            $power = (bool) $this->GetValue('Power');
            $data = [
                'fmod' => ($mode ? 'AUTO' : ($power ? 'FAN' : 'OFF'))
            ];
        } else {
            $data = [
                'auto' => ($mode ? 'ON' : 'OFF')
            ];
        }

        return $this->SetStateCommand(__FUNCTION__, $data);
    }

    private function SwitchNightMode(bool $mode)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $data = [
            'nmod' => ($mode ? 'ON' : 'OFF')
        ];

        return $this->SetStateCommand(__FUNCTION__, $data);
    }

    private function SetSleepTimer(int $min)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        if ($min < 0) {
            $min = 0;
        }
        if ($min > 540) {
            $min = 540;
        }

        $data = [
            'sltm' => sprintf('%04d', $min)
        ];

        $r = $this->SetStateCommand(__FUNCTION__, $data);

        $product_type = $this->ReadPropertyString('product_type');
        $options = $this->product2options($product_type);

        if ($options['sleep_timer_from_sensor']) {
            $this->RequestStateCommand();
        }

        return $r;
    }

    private function SetAirflowRate(int $val)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $product_type = $this->ReadPropertyString('product_type');
        $options = $this->product2options($product_type);

        switch ($val) {
            case -1:
                $data = [
                    'auto' => 'ON'
                ];
                break;
            case 0:
                if ($options['automatic_mode_use_fmod']) {
                    $data = [
                        'fmod' => 'OFF'
                    ];
                } else {
                    $data = [
                        'fpwr' => 'OFF'
                    ];
                }
                break;
            default:
                $data = [
                    'fnsp' => sprintf('%04d', $val)
                ];
                break;
        }

        return $this->SetStateCommand(__FUNCTION__, $data);
    }

    private function SwitchAirflowDirection(bool $mode)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $data = [
            'fdir' => ($mode ? 'ON' : 'OFF')
        ];

        return $this->SetStateCommand(__FUNCTION__, $data);
    }

    private function SwitchAirflowDistribution(bool $mode)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $data = [
            'ffoc' => ($mode ? 'ON' : 'OFF')
        ];

        return $this->SetStateCommand(__FUNCTION__, $data);
    }

    private function SwitchRotationMode(bool $mode)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $product_type = $this->ReadPropertyString('product_type');
        $options = $this->product2options($product_type);

        if ($options['rotation_angle']) {
            $start = (int) $this->GetValue('RotationStart');
            $angle = (int) $this->GetValue('RotationAngle');
            $end = $start + $angle;
            $this->adjust_rotation($angle, $start, $end);

            $data = [
                'oson' => ($mode ? 'ON' : 'OFF'),
                'ancp' => 'CUST',
                'osal' => sprintf('%04d', $start),
                'osau' => sprintf('%04d', $end),
            ];
        } else {
            $data = [
                'oson' => ($mode ? 'ON' : 'OFF'),
            ];
        }

        return $this->SetStateCommand(__FUNCTION__, $data);
    }

    private function SwitchRotationMode2(int $mode)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $product_type = $this->ReadPropertyString('product_type');
        $options = $this->product2options($product_type);

        switch ($mode) {
            case 0:
                $data = [
                    'oson' => 'OFF',
                ];
                break;
            case 360:
                $data = [
                    'oson' => 'ON',
                    'ancp' => 'BRZE',
                ];
                break;
            default:
                $data = [
                    'oson' => 'ON',
                    'ancp' => sprintf('%04d', $mode),
                ];
                break;
        }

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
            'ancp' => 'CUST',
            'osal' => sprintf('%04d', $start),
            'osau' => sprintf('%04d', $end),
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
            'ancp' => 'CUST',
            'osal' => sprintf('%04d', $start),
            'osau' => sprintf('%04d', $end),
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
            'rhtm' => ($mode ? 'ON' : 'OFF')
        ];

        return $this->SetStateCommand(__FUNCTION__, $data);
    }

    private function SwitchHeater(bool $mode)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $temp = (float) $this->GetValue('HeatingTemperature');
        $k = (int) $this->encode_temperature($temp);

        if ($mode) {
            $data = [
                'hmod' => 'HEAT',
                'hmax' => sprintf('%04d', $k),
            ];
        } else {
            $data = [
                'hmod' => 'OFF',
            ];
        }

        return $this->SetStateCommand(__FUNCTION__, $data);
    }

    private function SetHeatingTemperature(float $temp)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $k = (int) $this->encode_temperature($temp);

        $data = [
            'hmax' => sprintf('%04d', $k),
        ];

        return $this->SetStateCommand(__FUNCTION__, $data);
    }

    private function SetAirQualityTarget(int $val)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $data = [
            'qtar' => sprintf('%04d', $val),
        ];

        return $this->SetStateCommand(__FUNCTION__, $data);
    }

    private function SwitchHumidification(bool $mode)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $data = [
            'hume' => ($mode ? 'HUMD' : 'OFF')
        ];

        return $this->SetStateCommand(__FUNCTION__, $data);
    }

    private function SwitchHumidifyAutomaticMode(bool $mode)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $product_type = $this->ReadPropertyString('product_type');
        $options = $this->product2options($product_type);

        if ($mode) {
            $data = [
                'hume' => 'HUMD',
                'haut' => 'ON'
            ];
            $power = (bool) $this->GetValue('Power');
            if ($power == false) {
                $data[] = [
                    'fpwr' => 'ON'
                ];
            }
            $flowrate = (int) $this->GetValue('AirflowRate');
            if ($flowrate == 0) {
                $data[] = [
                    'fnsp' => sprintf('%04d', 1)
                ];
            }
            $target = (int) $this->GetValue('HumidifyTarget');
            if ($target == 0) {
                $data[] = [
                    'humt' => sprintf('%04d', 50)
                ];
            }
        } else {
            $data = [
                'haut' => 'OFF'
            ];
        }

        return $this->SetStateCommand(__FUNCTION__, $data);
    }

    private function SetHumidifyTarget(float $hum)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $product_type = $this->ReadPropertyString('product_type');
        $options = $this->product2options($product_type);

        $data = [
            'hume' => 'HUMD',
            'humt' => sprintf('%04d', (int) $hum)
        ];

        return $this->SetStateCommand(__FUNCTION__, $data);
    }

    private function LocalRequestAction($ident, $value)
    {
        $r = true;
        switch ($ident) {
            case 'ConvertSplitter':
                $this->ConvertSplitter();
                break;
            case 'ManualUpdateStatus':
                $this->ManualUpdateStatus();
                break;
            case 'ManualReloadConfig':
                $this->ManualReloadConfig();
                break;
            case 'ManualRelogin1':
                $this->ManualRelogin1();
                break;
            case 'ManualRelogin2':
                $this->ManualRelogin2($value);
                break;
            case 'ExecuteSetState':
                $this->ExecuteSetState($value);
                break;
            default:
        $r = false;
                break;
        }
        return $r;
    }

    public function RequestAction($ident, $value)
    {
        if ($this->LocalRequestAction($ident, $value)) {
            return;
        }
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }

        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $r = false;
        switch ($ident) {
            case 'Power':
                $r = $this->SwitchPower((bool) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            case 'AutomaticMode':
                $r = $this->SwitchAutomaticMode((bool) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            case 'NightMode':
                $r = $this->SwitchNightMode((bool) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            case 'SleepTimer':
                $r = $this->SetSleepTimer((int) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            case 'AirflowRate':
                $r = $this->SetAirflowRate((int) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            case 'AirflowDirection':
                $r = $this->SwitchAirflowDirection((bool) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            case 'AirflowDistribution':
                $r = $this->SwitchAirflowDistribution((bool) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            case 'RotationMode':
                $r = $this->SwitchRotationMode((bool) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            case 'RotationMode2':
                $r = $this->SwitchRotationMode2((int) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            case 'RotationAngle':
                $r = $this->SetRotationAngle((int) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            case 'RotationStart':
                $r = $this->SetRotationStart((int) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            case 'StandbyMonitoring':
                $r = $this->SwitchStandbyMonitoring((bool) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            case 'HeaterMode':
                $r = $this->SwitchHeater((bool) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            case 'HeatingTemperature':
                $r = $this->SetHeatingTemperature((float) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            case 'AirQualityTarget':
                $r = $this->SetAirQualityTarget((int) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            case 'Humidification':
                $r = $this->SwitchHumidification((bool) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            case 'HumidifyAutomaticMode':
                $r = $this->SwitchHumidifyAutomaticMode((bool) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            case 'HumidifyTarget':
                $r = $this->SetHumidifyTarget((float) $value);
                $this->SendDebug(__FUNCTION__, $ident . '=' . $value . ' => ret=' . $r, 0);
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
    }

    private function str2bool($s)
    {
        return $s != 'OFF';
    }

    private function product2options($product_type)
    {
        // STATUS
        $options['rssi'] = false;
        $options['power'] = false;
        $options['power_use_fmod'] = false;
        $options['airflow_rate'] = false;
        $options['airflow_off_use_fmod'] = false;
        $options['rotation_mode'] = false;
        $options['rotation_mode_use_oson'] = false;
        $options['rotation_mode2'] = false;
        $options['rotation_angle'] = false;
        $options['airflow_direction'] = false;
        $options['airflow_distribution'] = false;
        $options['automatic_mode'] = false;
        $options['automatic_mode_use_fmod'] = false;
        $options['night_mode'] = false;
        $options['sleep_timer'] = false;
        $options['sleep_timer_from_sensor'] = false;
        $options['humidify'] = false;

        $options['heating'] = false;

        $options['standby_monitoring'] = false;
        $options['carbon_filter'] = false;
        $options['hepa_filter'] = false;
        $options['filter_lifetime'] = false;
        $options['filter_lifetime_max'] = 100;

        // ENVIROMENTAL SENSOR DATA
        $options['temperature'] = false;
        $options['humidity'] = false;
        $options['pm25'] = false;
        $options['pm10'] = false;
        $options['dust_index'] = false;
        $options['voc'] = false;
        $options['voc_index'] = false;
        $options['nox'] = false;
        $options['hcho'] = false;
        $options['air_quality_target'] = false;

        switch ($product_type) {
            case '438':
            case '438E':
            case '520':
                $options['rssi'] = true;
                $options['power'] = true;
                $options['airflow_rate'] = true;
                $options['rotation_mode'] = true;
                $options['rotation_angle'] = true;
                $options['airflow_direction'] = true;
                $options['automatic_mode'] = true;
                $options['night_mode'] = true;
                $options['sleep_timer'] = true;

                $options['standby_monitoring'] = true;
                $options['carbon_filter'] = true;
                $options['hepa_filter'] = true;

                $options['temperature'] = true;
                $options['humidity'] = true;
                $options['pm25'] = true;
                $options['pm10'] = true;
                $options['voc'] = true;
                $options['nox'] = true;
                break;
            case '358':
                $options['rssi'] = true;
                $options['power'] = true;
                $options['airflow_rate'] = true;
                $options['rotation_mode'] = true;
                $options['rotation_angle'] = true;
                $options['airflow_direction'] = true;
                $options['automatic_mode'] = true;
                $options['night_mode'] = true;
                $options['sleep_timer'] = true;
                $options['humidify'] = true;

                $options['standby_monitoring'] = true;
                $options['hepa_filter'] = true;

                $options['temperature'] = true;
                $options['humidity'] = true;
                $options['pm25'] = true;
                $options['pm10'] = true;
                $options['voc'] = true;
                $options['nox'] = true;
                break;
            case '358E':
                $options['rssi'] = true;
                $options['power'] = true;
                $options['airflow_rate'] = true;
                $options['rotation_mode2'] = true;
                $options['airflow_direction'] = true;
                $options['automatic_mode'] = true;
                $options['night_mode'] = true;
                $options['sleep_timer'] = true;
                $options['humidify'] = true;

                $options['standby_monitoring'] = true;
                $options['hepa_filter'] = true;

                $options['temperature'] = true;
                $options['humidity'] = true;
                $options['pm25'] = true;
                $options['pm10'] = true;
                $options['voc'] = true;
                $options['nox'] = true;
                break;
            case '527':
                $options['rssi'] = true;
                $options['power'] = true;
                $options['airflow_rate'] = true;
                $options['rotation_mode'] = true;
                $options['rotation_angle'] = true;
                $options['airflow_direction'] = true;
                $options['automatic_mode'] = true;
                $options['night_mode'] = true;
                $options['sleep_timer'] = true;

                $options['heating'] = true;

                $options['standby_monitoring'] = true;
                $options['carbon_filter'] = true;
                $options['hepa_filter'] = true;

                $options['temperature'] = true;
                $options['humidity'] = true;
                break;
            case '455':
                $options['rssi'] = true;
                $options['power'] = true;
                $options['power_use_fmod'] = true;
                $options['automatic_mode'] = true;
                $options['automatic_mode_use_fmod'] = true;
                $options['airflow_rate'] = true;
                $options['rotation_mode'] = true;
                $options['rotation_mode_use_oson'] = true;
                $options['airflow_distribution'] = true;
                $options['night_mode'] = true;
                $options['sleep_timer'] = true;

                $options['heating'] = true;

                $options['standby_monitoring'] = true;
                $options['filter_lifetime'] = true;

                $options['temperature'] = true;
                $options['humidity'] = true;
                break;
            case '469':
            case '475':
                $options['rssi'] = true;
                $options['power'] = true;
                $options['power_use_fmod'] = true;
                $options['automatic_mode'] = true;
                $options['automatic_mode_use_fmod'] = true;
                $options['airflow_rate'] = true;
                $options['airflow_off_use_fmod'] = true;
                $options['rotation_mode'] = true;
                $options['rotation_mode_use_oson'] = true;
                $options['night_mode'] = true;
                $options['sleep_timer'] = true;
                $options['sleep_timer_from_sensor'] = true;

                $options['standby_monitoring'] = true;

                $options['filter_lifetime'] = true;
                $options['filter_lifetime_max'] = 4300;

                $options['temperature'] = true;
                $options['humidity'] = true;

                $options['dust_index'] = true;
                $options['voc_index'] = true;

                $options['air_quality_target'] = true;
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'unknown product ' . $product_type, 0);
                break;
        }
        return $options;
    }

    private function product2name($product_type)
    {
        $product2name = [
            '358'   => 'Dyson Pure Humidify+Cool desk fan (PH01)',
            '358E'  => 'Dyson Pure Humidify+Cool desk fan (PH03)',
            // Dyson Purifier Humidify+Cool Formaldehyde (PH04)
            // Dyson Purifier Humidify+Cool Autoreact Luftbefeuchter (PH3A)

            // Dyson Pure Cool TP00 fan tower (TP00)
            '475'   => 'Dyson Pure Cool purifier fan tower (TP02)',
            '438'   => 'Dyson Pure Cool purifier fan tower (TP04)',
            '438E'  => 'Dyson Pure Cool purifier fan tower (TP07)',
            // Dyson Purifier Cool Formaldehyde purifier fan tower (TP09)
            // Dyson Purifier Cool Autoreact purifier fan tower (TP7A)

            // Dyson Pure Hot+Cool (HP00)
            '455'   => 'Dyson Pure Hot+Cool purifier fan tower (HP02)',
            '527'   => 'Dyson Pure Hot+Cool purifying heater + fan (HP04)',
            // Dyson Purifier Hot+Cool (HP07)
            // Dyson Purifier Hot+Cool Formaldehyde (HP09)

            '469'   => 'Dyson Pure Cool purifier desk fan (DP02)',
            '520'   => 'Dyson Pure Cool purifier desk fan (DP04)',
        ];

        if (isset($product2name[$product_type])) {
            $name = $this->Translate($product2name[$product_type]);
        } else {
            $name = $this->Translate('unknown Dyson product') . ' ' . $product_type;
        }
        return $name;
    }

    private function fault2text($val)
    {
        $fault2text = [
            'tnke' => 'Tank empty',
            'tnkp' => 'Tank not detected',
            'uled' => 'Pump error',
        ];

        if (isset($fault2text[$val])) {
            $s = $this->Translate($fault2text[$val]);
        } else {
            $s = $this->Translate('Unknown error code') . ' "' . $val . '"';
        }
        return $s;
    }

    private function adjust_rotation(&$angle, &$start, &$end)
    {
        $s = 'angle=' . $angle . ', start=' . $start . ', end=' . $end;
        if ($angle < 68) {        // 45 + (90 - 45) / 2 = 67.5
            $angle = 45;
        } elseif ($angle < 135) {  // 90 + (180 - 90) / 2 = 135
            $angle = 90;
        } elseif ($angle < 265) {  // 180 + (350 - 180) / 2 = 265
            $angle = 180;
        } else {
            $angle = 350;
        }

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
        $this->SendDebug(__FUNCTION__, $s . ' => angle=' . $angle . ', start=' . $start . ', end=' . $end, 0);
    }

    private function encode_temperature(float $temp)
    {
        return (int) round(($temp + 273.15) * 10);
    }

    private function decode_temperature(string $str)
    {
        if ($str == '') {
            return false;
        }
        return (float) $str / 10 - 273.15;
    }

    private function AdjustActions($mode)
    {
        $product_type = $this->ReadPropertyString('product_type');
        $options = $this->product2options($product_type);

        $chg = false;
        if ($options['automatic_mode']) {
            $chg |= $this->AdjustAction('AutomaticMode', $mode);
        }
        if ($options['night_mode']) {
            $chg |= $this->AdjustAction('NightMode', $mode);
        }
        if ($options['sleep_timer']) {
            $chg |= $this->AdjustAction('SleepTimer', $mode);
        }
        if ($options['airflow_rate']) {
            $chg |= $this->AdjustAction('AirflowRate', $mode);
        }
        if ($options['rotation_mode']) {
            $chg |= $this->AdjustAction('RotationMode', $mode);
        }
        if ($options['rotation_mode2']) {
            $chg |= $this->AdjustAction('RotationMode2', $mode);
        }
        if ($options['rotation_angle']) {
            $chg |= $this->AdjustAction('RotationAngle', $mode);
            $chg |= $this->AdjustAction('RotationStart', $mode);
        }
        if ($options['airflow_direction']) {
            $chg |= $this->AdjustAction('AirflowDirection', $mode);
        }
        if ($options['airflow_distribution']) {
            $chg |= $this->AdjustAction('AirflowDistribution', $mode);
        }
        if ($options['heating']) {
            $chg |= $this->AdjustAction('HeatingTemperature', $mode);
        }
        if ($options['air_quality_target']) {
            $chg |= $this->AdjustAction('AirQualityTarget', $mode);
        }
        if ($options['humidify']) {
            $chg |= $this->AdjustAction('Humidification', $mode);
            $chg |= $this->AdjustAction('HumidifyAutomaticMode', $mode);
            $chg |= $this->AdjustAction('HumidifyTarget', $mode);
        }

        if ($chg) {
            $this->ReloadForm();
        }
    }

    private function IsInternalMQTTClient()
    {
        $inst = IPS_GetInstance($this->InstanceID);
        $cID = $inst['ConnectionID'];
        if ($cID == false) {
            return false;
        }
        $inst = IPS_GetInstance($cID);
        $moduleID = $inst['ModuleInfo']['ModuleID'];
        return $moduleID == '{F7A0DD2E-7684-95C0-64C2-D2A9DC47577B}';
    }

    private function ConvertSplitter()
    {
        if ($this->IsInternalMQTTClient()) {
            return;
        }

        // Parent vom MQTTClient auslesen
        $inst = IPS_GetInstance($this->InstanceID);
        $splitterID = $inst['ConnectionID'];

        $inst = IPS_GetInstance($splitterID);
        $ioID = $inst['ConnectionID'];

        // "User" und "Password" aus MQTTClient-Instanz holen
        $user = IPS_GetProperty($splitterID, 'User');
        $password = IPS_GetProperty($splitterID, 'Password');

        // Konfiguration der IO-Instanz
        $ioCfg = IPS_GetConfiguration($ioID);

        // Instanz anlegen
        $instID = IPS_CreateInstance('{F7A0DD2E-7684-95C0-64C2-D2A9DC47577B}');
        $inst = IPS_GetInstance($instID);
        $cID = $inst['ConnectionID'];
        IPS_SetProperty($instID, 'UserName', $user);
        IPS_SetProperty($instID, 'Password', $password);
        if (IPS_GetKernelVersion() >= 6) {
            IPS_SetProperty($instID, 'ClientID', 'symcon');
        }
        $product_type = $this->ReadPropertyString('product_type');
        $serial = $this->ReadPropertyString('serial');
        $topics = [];
        foreach (['current', 'faults', 'connection', 'software', 'summary'] as $sub) {
            $topics[] = [
                'Topic' => $product_type . '/' . $serial . '/status/' . $sub,
                'QoS'   => 0
            ];
        }
        IPS_SetProperty($instID, 'Subscriptions', json_encode($topics));
        IPS_ApplyChanges($instID);

        IPS_SetConfiguration($cID, $ioCfg);
        IPS_ApplyChanges($cID);

        // DysonDevice verbinden mit MQTT Client
        IPS_DisconnectInstance($this->InstanceID);
        IPS_ConnectInstance($this->InstanceID, $instID);

        // alte MQTT-Instanz löschen
        IPS_DeleteInstance($splitterID);

        // alte IO-Instanz löschen
        IPS_DeleteInstance($ioID);

        $this->ReloadForm();
    }
}
