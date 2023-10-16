<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class DysonConfig extends IPSModule
{
    use Dyson\StubsCommonLib;
    use DysonLocalLib;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->CommonContruct(__DIR__);
    }

    public function __destruct()
    {
        $this->CommonDestruct();
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('ImportCategoryID', 0);

        $this->RegisterPropertyString('user', '');
        $this->RegisterPropertyString('password', '');
        $this->RegisterPropertyString('country', '');

        $this->RegisterAttributeString('Auth', '');

        $this->RegisterAttributeString('UpdateInfo', json_encode([]));
        $this->RegisterAttributeString('ModuleStats', json_encode([]));
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

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $propertyNames = ['ImportCategoryID'];
        $this->MaintainReferences($propertyNames);

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $this->MaintainStatus(IS_ACTIVE);
    }

    private function getConfiguratorValues()
    {
        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');
        $country = $this->ReadPropertyString('country');

        $config_list = [];

        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return $config_list;
        }

        $this->MaintainStatus(IS_ACTIVE);

        $catID = $this->ReadPropertyInteger('ImportCategoryID');

        $devices = $this->getDeviceList();
        $this->SendDebug(__FUNCTION__, 'devices=' . print_r($devices, true), 0);

        $guid = '{D1A42861-0280-E373-A07E-EC51D3B43951}';
        $instIDs = IPS_GetInstanceListByModuleID($guid);

        if (is_array($devices) && count($devices)) {
            foreach ($devices as $device) {
                $this->SendDebug(__FUNCTION__, 'device=' . print_r($device, true), 0);
                $serial = $device['Serial'];
                $name = $device['Name'];
                $product_type = $device['ProductType'];
                $local_credentials = $device['LocalCredentials'];
                $local_password = $local_credentials ? $this->decryptPassword($local_credentials) : false;
                $this->SendDebug(__FUNCTION__, 'local_password=' . $local_password, 0);
                $topics = [];
                foreach (['current', 'faults', 'connection', 'software', 'summary'] as $sub) {
                    $topics[] = [
                        'Topic' => $product_type . '/' . $serial . '/status/' . $sub,
                        'QoS'   => 0
                    ];
                }

                $instanceID = 0;
                foreach ($instIDs as $instID) {
                    if (IPS_GetProperty($instID, 'serial') == $serial) {
                        $this->SendDebug(__FUNCTION__, 'device found: ' . IPS_GetName($instID) . ' (' . $instID . ')', 0);
                        $instanceID = $instID;
                        break;
                    }
                }

                if ($instanceID && IPS_GetProperty($instanceID, 'user') != $user) {
                    continue;
                }

                $entry = [
                    'instanceID'           => $instanceID,
                    'serial'               => $serial,
                    'name'                 => $name,
                    'product_type'         => $product_type,
                    'create'               => [
                        [
                            'moduleID'      => $guid,
                            'location'      => $this->GetConfiguratorLocation($catID),
                            'info'          => 'Dyson ' . $product_type . ' (#' . $serial . ')',
                            'configuration' => [
                                'user'          => $user,
                                'password'      => $password,
                                'country'       => $country,
                                'serial'        => $serial,
                                'product_type'  => $product_type,
                            ]
                        ],
                        [
                            'moduleID'      => '{F7A0DD2E-7684-95C0-64C2-D2A9DC47577B}', // MQTT Client
                            'info'          => 'Dyson ' . $product_type . ' (#' . $serial . ')',
                            'configuration' => [
                                'UserName'      => $serial,
                                'Password'      => $local_password,
                                'ClientID'      => 'symcon',
                                'Subscriptions' => json_encode($topics)
                            ],
                        ],
                        [
                            'moduleID'      => '{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}', // Client Socket
                            'info'          => 'Dyson ' . $product_type . ' (#' . $serial . ')',
                            'configuration' => [
                                'Port' => 1883
                            ],
                        ]
                    ],
                ];

                $config_list[] = $entry;
                $this->SendDebug(__FUNCTION__, 'entry=' . print_r($entry, true), 0);
            }
        }
        foreach ($instIDs as $instID) {
            $fnd = false;
            foreach ($config_list as $entry) {
                if ($entry['instanceID'] == $instID) {
                    $fnd = true;
                    break;
                }
            }
            if ($fnd) {
                continue;
            }

            if (IPS_GetProperty($instID, 'user') != $user) {
                continue;
            }

            $serial = IPS_GetProperty($instID, 'serial');
            $name = IPS_GetName($instID);
            $product_type = IPS_GetProperty($instID, 'product_type');

            $entry = [
                'instanceID'     => $instID,
                'name'           => $name,
                'serial'         => $serial,
                'product_type'   => $product_type,
            ];

            $config_list[] = $entry;
            $this->SendDebug(__FUNCTION__, 'missing entry=' . print_r($entry, true), 0);
        }
        return $config_list;
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('Dyson configurator');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
        }

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
            ],
            'caption' => 'Dyson Account-Details',
        ];

        $formElements[] = [
            'name'    => 'ImportCategoryID',
            'type'    => 'SelectCategory',
            'caption' => 'category for products to be created'
        ];

        $entries = $this->getConfiguratorValues();
        $configurator = [
            'type'    => 'Configurator',
            'name'    => 'Product',
            'caption' => 'Product',

            'rowCount' => count($entries),

            'add'     => false,
            'delete'  => false,
            'columns' => [
                [
                    'caption' => 'Serial',
                    'name'    => 'serial',
                    'width'   => '200px'
                ],
                [
                    'caption' => 'Name',
                    'name'    => 'name',
                    'width'   => 'auto'
                ],
                [
                    'caption' => 'Product type',
                    'name'    => 'product_type',
                    'width'   => '400px'
                ]
            ],
            'values' => $entries
        ];
        $formElements[] = $configurator;

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            $formActions[] = $this->GetCompleteUpdateFormAction();

            $formActions[] = $this->GetInformationFormAction();
            $formActions[] = $this->GetReferencesFormAction();

            return $formActions;
        }

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'perform login',
            'expanded'  => false,
            'items'     => [
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

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    private function LocalRequestAction($ident, $value)
    {
        $r = true;
        switch ($ident) {
            case 'ManualRelogin1':
                $this->ManualRelogin1();
                break;
            case 'ManualRelogin2':
                $this->ManualRelogin2($value);
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

        switch ($ident) {
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
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
}
