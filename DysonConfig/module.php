<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php'; // globale Funktionen
require_once __DIR__ . '/../libs/local.php';  // lokale Funktionen

class DysonConfig extends IPSModule
{
    use DysonCommonLib;
    use DysonLocalLib;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('ImportCategoryID', 0);

        $this->RegisterPropertyString('user', '');
        $this->RegisterPropertyString('password', '');
        $this->RegisterPropertyString('country', '');

        $this->RegisterAttributeString('Auth', '');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $s = $this->CheckPrerequisites();
        if ($s != '') {
            $this->SetStatus(self::$IS_INVALIDPREREQUISITES);
            $this->LogMessage($s, KL_WARNING);
            return;
        }

        $refs = $this->GetReferenceList();
        foreach ($refs as $ref) {
            $this->UnregisterReference($ref);
        }
        $propertyNames = ['ImportCategoryID'];
        foreach ($propertyNames as $name) {
            $oid = $this->ReadPropertyInteger($name);
            if ($oid > 0) {
                $this->RegisterReference($oid);
            }
        }

        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');
        if ($user == '' || $password == '') {
            $this->SetStatus(self::$IS_UNAUTHORIZED);
            return;
        }

        if ($this->checkLogin() == false) {
            $this->SetStatus(self::$IS_NOLOGIN);
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

    private function SetLocation()
    {
        $category = $this->ReadPropertyInteger('ImportCategoryID');
        $tree_position = [];
        if ($category > 0 && IPS_ObjectExists($category)) {
            $tree_position[] = IPS_GetName($category);
            $parent = IPS_GetObject($category)['ParentID'];
            while ($parent > 0) {
                if ($parent > 0) {
                    $tree_position[] = IPS_GetName($parent);
                }
                $parent = IPS_GetObject($parent)['ParentID'];
            }
            $tree_position = array_reverse($tree_position);
        }
        $this->SendDebug(__FUNCTION__, 'tree_position=' . print_r($tree_position, true), 0);
        return $tree_position;
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

        $this->SetStatus(IS_ACTIVE);

        $devices = $this->getDeviceList();
        $this->SendDebug(__FUNCTION__, 'devices=' . print_r($devices, true), 0);

        if ($devices != '') {
            $guid = '{D1A42861-0280-E373-A07E-EC51D3B43951}';
            $instIDs = IPS_GetInstanceListByModuleID($guid);
            foreach ($devices as $device) {
                $this->SendDebug(__FUNCTION__, 'device=' . print_r($device, true), 0);
                $serial = $device['Serial'];
                $name = $device['Name'];
                $product_type = $device['ProductType'];
                $local_credentials = $device['LocalCredentials'];
                $local_password = $local_credentials ? $this->decryptPassword($local_credentials) : false;

                $instanceID = 0;
                foreach ($instIDs as $instID) {
                    if (IPS_GetProperty($instID, 'serial') == $serial) {
                        $this->SendDebug(__FUNCTION__, 'device found: ' . utf8_decode(IPS_GetName($instID)) . ' (' . $instID . ')', 0);
                        $instanceID = $instID;
                        break;
                    }
                }

                $create = [
                    [
                        'moduleID'      => $guid,
                        'location'      => $this->SetLocation(),
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
                        'moduleID'      => '{EE0D345A-CF31-428A-A613-33CE98E752DD}', // MQTTClient
                        'info'          => 'Dyson ' . $product_type . ' (#' . $serial . ')',
                        // siehe DysonDevice::GetConfigurationForParent
                        'configuration' => [
                            'User'          => $serial,
                            'Password'      => $local_password,
                            'ModuleType'    => 2,
                            'script'        => 0,
                            'TLS'           => false,
                            'AutoSubscribe' => false,
                            'MQTTVersion'   => 2,
                            'ClientID'      => 'symcon',
                            'PingInterval'  => 30
                        ],
                    ],
                    [
                        'moduleID'      => '{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}', // Client Socket
                        'info'          => 'Dyson ' . $product_type . ' (#' . $serial . ')',
                        'configuration' => [
                            'Port' => 1883
                        ],
                    ]
                ];

                $entry = [
                    'instanceID'           => $instanceID,
                    'serial'               => $serial,
                    'name'                 => $name,
                    'product_type'         => $product_type,
                    'create'               => $create
                ];

                $config_list[] = $entry;
                $this->SendDebug(__FUNCTION__, 'entry=' . print_r($entry, true), 0);
                $this->SendDebug(__FUNCTION__, 'entry=' . json_encode($entry, JSON_PRETTY_PRINT), 0);
            }
        }
        return $config_list;
    }

    private function GetFormElements()
    {
        $formElements = [];

        $s = $this->CheckPrerequisites();
        if ($s != '') {
            $formElements[] = [
                'type'    => 'Label',
                'caption' => $s,
            ];
            $formElements[] = [
                'type'    => 'Label',
            ];
        }

        $formElements[] = ['type' => 'Label', 'caption' => 'Dyson configurator'];

        $items = [];
        $items[] = ['type' => 'ValidationTextBox', 'name' => 'user', 'caption' => 'User'];
        $items[] = ['type' => 'ValidationTextBox', 'name' => 'password', 'caption' => 'Password'];
        $opts_country = [
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
            'caption' => 'Dyson Account-Details'
        ];

        $formElements[] = ['name' => 'ImportCategoryID', 'type' => 'SelectCategory', 'caption' => 'category'];

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

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'perform login',
            'expanded ' => false,
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
                            'onClick' => 'Dyson_ManualRelogin1($id);'
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
                            'onClick' => 'Dyson_ManualRelogin2($id, $otpCode);'
                        ]
                    ]
                ]
            ]
        ];

        return $formActions;
    }

    public function ManualRelogin1()
    {
        $this->SendDebug(__FUNCTION__, '', 0);
        $msg = '';
        $ret = $this->doLogin_2fa_1(true, $msg);
        $this->SendDebug(__FUNCTION__, 'ret=' . $ret . ', msg=' . $msg, 0);
        if ($msg != false) {
            echo $this->Translate($msg);
        }
    }

    public function ManualRelogin2(string $otpCode)
    {
        $this->SendDebug(__FUNCTION__, 'otpCode=' . $otpCode, 0);
        $msg = '';
        $ret = $this->doLogin_2fa_2($otpCode, $msg);
        $this->SendDebug(__FUNCTION__, 'ret=' . $ret . ', msg=' . $msg, 0);
        if ($msg != false) {
            echo $this->Translate($msg);
        }
    }
}
