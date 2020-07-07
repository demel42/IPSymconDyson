<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen
require_once __DIR__ . '/../libs/library.php';  // globale Funktionen

class DysonConfig extends IPSModule
{
    use DysonCommon;
    use DysonLibrary;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('ImportCategoryID', 0);

        $this->RegisterPropertyString('user', '');
        $this->RegisterPropertyString('password', '');
        $this->RegisterPropertyString('country', '');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $s = $this->CheckPrerequisites();
        if ($s != '') {
            $this->SetStatus(self::$IS_INVALIDPREREQUISITES);
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
        return $tree_position;
    }

    public function getConfiguratorValues()
    {
        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');
        $country = $this->ReadPropertyString('country');

        $config_list = [];

        $devices = $this->getDeviceList();
        $this->SendDebug(__FUNCTION__, 'devices=' . print_r($devices, true), 0);

        if ($devices != '') {
            $guid = '{D1A42861-0280-E373-A07E-EC51D3B43951}';
            $instIDs = IPS_GetInstanceListByModuleID($guid);
            foreach ($devices as $device) {
                $serial = $device['Serial'];
                $name = $device['Name'];
                $product_type = $device['ProductType'];
                $local_password = $this->decryptPassword($device['LocalCredentials']);

                $instanceID = 0;
                foreach ($instIDs as $instID) {
                    if (IPS_GetProperty($instID, 'serial') == $serial) {
                        $this->SendDebug(__FUNCTION__, 'controller found: ' . utf8_decode(IPS_GetName($instID)) . ' (' . $instID . ')', 0);
                        $instanceID = $instID;
                        break;
                    }
                }

                $create = [];
                $create[] = [
                    'moduleID'      => $guid,
                    'location'      => $this->SetLocation(),
                    'info'			       => 'Dyson ' . $product_type . ' (#' . $serial . ')',
                    'configuration' => [
                        'user'          => $user,
                        'password'      => $password,
                        'country'       => $country,
                        'serial'        => $serial,
                        'product_type'  => $product_type,
                    ]
                ];

                // MQTTClient = {EE0D345A-CF31-428A-A613-33CE98E752DD}
                // {"ClientID":"symcon","User":"VS9-EU-NAB1633A","Password":"Pn/BjnRz1Fedh2ARhj6BY/8NAf/gyuj4SAnkzadk2HD3LsCUqG7N1bCfUiNoBHOrh2wj/1lRj0a/jVu6eB2hLw==","ModuleType":2,"script":0,"TLS":false,"AutoSubscribe":true,"MQTTVersion":2,"PingInterval":30}
                $create[] = [
                    'moduleID'      => '{EE0D345A-CF31-428A-A613-33CE98E752DD}', // MQTTClient
                    'info'          => 'Dyson ' . $product_type . ' (#' . $serial . ')',
                    'configuration' => [
                        'User'          => $serial,
                        'Password'      => $local_password,
                        'ModuleType'    => 2,
                        'script'        => 0,
                        'TLS'           => false,
                        'AutoSubscribe' => false,
                        'MQTTVersion'   => 2
                    ],
                ];

                // Client Socket = {3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}
                // {"Open":true,"Host":"Dyson-Fan.damsky.home","Port":1883}
                $create[] = [
                    'moduleID'      => '{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}', // Client Socket
                    'info'          => 'Dyson ' . $product_type . ' (#' . $serial . ')',
                    'configuration' => [
                        'Port' => 1883
                    ],
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
            }
        }
        return $config_list;
    }

    public function GetFormElements()
    {
        $formElements = [];

        $formElements[] = ['type' => 'Label', 'caption' => 'Dyson Configurator'];

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
                    'caption' => 'Product Type',
                    'name'    => 'product_type',
                    'width'   => '400px'
                ]
            ],
            'values' => $entries
        ];
        $formElements[] = $configurator;

        return $formElements;
    }

    protected function GetFormActions()
    {
        $formActions = [];

        return $formActions;
    }
}
