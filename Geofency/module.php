<?php

declare(strict_types=1);

include_once __DIR__ . '/../libs/WebHookModule.php';

class Geofency extends WebHookModule
{
    public function __construct($InstanceID)
    {
        parent::__construct($InstanceID, 'geofency');
    }

    public function Create()
    {

        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString('Username', '');
        $this->RegisterPropertyString('Password', '');

        $this->RegisterProfile('Geofency.Distance.m', 'Distance', '', ' m', 0, 0, 0, 2, 2);
        $this->RegisterProfileAssociation('Geofency.Orientation', 'WindDirection', '', '', 0, 360, 1, 0, [
            [0, 'N',  '', -1],
            [22, 'NNO',  '', -1],
            [45, 'NO',  '', -1],
            [67, 'ONO',  '', -1],
            [90, 'O',  '', -1],
            [112, 'OSO',  '', -1],
            [135, 'SO',  '', -1],
            [157, 'SSO',  '', -1],
            [180, 'S',  '', -1],
            [202, 'SSW',  '', -1],
            [225, 'SW',  '', -1],
            [247, 'WSW',  '', -1],
            [270, 'W',  '', -1],
            [292, 'WNW',  '', -1],
            [315, 'NW',  '', -1],
            [337, 'NNW',  '', -1]
        ], 1);

        $this->RegisterProfileAssociation(
            'Geofency.Motion',
            'Motion',
            '',
            '',
            0,
            5,
            0,
            0,
            [[0, 'Unbekannt', '', -1],
                [1, 'Stationär', '', -1],
                [2, 'Gehen', '', -1],
                [3, 'Laufen', '', -1],
                [4, 'Autofahren', '', -1],
                [5, 'Radfahren', '', -1]
            ],
            1
        );
    }

    public function ApplyChanges()
    {

        //Never delete this line!
        parent::ApplyChanges();

        //Cleanup old hook script
        $id = @IPS_GetObjectIDByIdent('Hook', $this->InstanceID);
        if ($id > 0) {
            IPS_DeleteScript($id, true);
        }
    }

    /**
     * This function will be called by the hook control. Visibility should be protected!
     */
    protected function ProcessHookData()
    {

        //Never delete this line!
        parent::ProcessHookData();

        $this->SendDebug('Data', print_r($_POST, true), 0);

        if ((IPS_GetProperty($this->InstanceID, 'Username') != '') || (IPS_GetProperty($this->InstanceID, 'Password') != '')) {
            if (!isset($_SERVER['PHP_AUTH_USER'])) {
                $_SERVER['PHP_AUTH_USER'] = '';
            }
            if (!isset($_SERVER['PHP_AUTH_PW'])) {
                $_SERVER['PHP_AUTH_PW'] = '';
            }

            if (($_SERVER['PHP_AUTH_USER'] != IPS_GetProperty($this->InstanceID, 'Username')) || ($_SERVER['PHP_AUTH_PW'] != IPS_GetProperty($this->InstanceID, 'Password'))) {
                header('WWW-Authenticate: Basic Realm="Geofency WebHook"');
                header('HTTP/1.0 401 Unauthorized');
                echo 'Authorization required';
                $this->SendDebug('Unauthorized', print_r($_POST, true), 0);
                return;
            }
        }

        if (!isset($_POST['device']) || !isset($_POST['id']) || !isset($_POST['name'])) {
            $this->SendDebug('Malformed', print_r($_POST, true), 0);
            return;
        }

        $deviceID = $this->CreateInstanceByIdent($this->InstanceID, $this->ReduceGUIDToIdent($_POST['device']), 'Device');
        SetValue($this->CreateVariableByIdent($deviceID, 'Latitude', 'Latitude', 2), floatval($_POST['latitude']));
        SetValue($this->CreateVariableByIdent($deviceID, 'Longitude', 'Longitude', 2), floatval($_POST['longitude']));
        SetValue($this->CreateVariableByIdent($deviceID, 'Timestamp', 'Timestamp', 1, '~UnixTimestamp'), intval(strtotime($_POST['date'])));
        SetValue($this->CreateVariableByIdent($deviceID, $this->ReduceGUIDToIdent($_POST['id']), $_POST['name'], 0, '~Presence'), intval($_POST['entry']) > 0);
        if (isset($_POST['wifiBSSID'], $_POST['wifiSSID'])) { //ab Version 5.7 im Webhook enthalten
            SetValue($this->CreateVariableByIdent($deviceID, 'WifiBSSID', 'WifiBSSID', 3), $_POST['wifiBSSID']);
            SetValue($this->CreateVariableByIdent($deviceID, 'WifiSSID', 'WifiSSID', 3), $_POST['wifiSSID']);
        }

        $currentLatitudeID = $this->CreateVariableByIdent($deviceID, 'CurrentLatitude', 'Current Latitude', 2);
        $currentLongitude = $this->CreateVariableByIdent($deviceID, 'CurrentLongitude', 'Current Longitude', 2);
        $directionID = $this->CreateVariableByIdent($deviceID, 'Direction', 'Direction', 1, '~WindDirection');
        $orientationID = $this->CreateVariableByIdent($deviceID, 'Orientation', 'Orientation', 1, 'Geofency.Orientation');
        $distanceID = $this->CreateVariableByIdent($deviceID, 'Distance', 'Distance', 2, 'Geofency.Distance.m');
        $motionID = $this->CreateVariableByIdent($deviceID, 'Motion', 'Motion', 1, 'Geofency.Motion'); // kann ab Version 5.7 aktiviert werden

        if (isset($_POST['currentLatitude']) && $_POST['currentLatitude'] > 0 && isset($_POST['currentLongitude']) && $_POST['currentLongitude'] > 0) {
            SetValue($currentLatitudeID, floatval($_POST['currentLatitude']));
            SetValue($currentLongitude, floatval($_POST['currentLongitude']));
            SetValue($directionID, $this->GetDirectionToCenter(floatval($_POST['latitude']), floatval($_POST['longitude']), floatval($_POST['currentLatitude']), floatval($_POST['currentLongitude'])));
            SetValue($orientationID, $this->GetDirectionToCenter(floatval($_POST['latitude']), floatval($_POST['longitude']), floatval($_POST['currentLatitude']), floatval($_POST['currentLongitude'])));
            SetValue($distanceID, $this->GetDistanceToCenter(floatval($_POST['latitude']), floatval($_POST['longitude']), floatval($_POST['currentLatitude']), floatval($_POST['currentLongitude']), 'm'));
        } else {
            SetValue($currentLatitudeID, 0);
            SetValue($currentLongitude, 0);
            SetValue($directionID, 0);
            SetValue($orientationID, 0);
            SetValue($distanceID, 0);
        }

        if (isset($_POST['motion'])) {
            SetValueInteger($motionID, $this->getValueOfMotion($_POST['motion']));
        } else {
            SetValueInteger($motionID, 0);
        }
    }

    protected function RegisterProfile($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits, $Vartype)
    {
        if (!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, $Vartype);
        } else {
            $profile = IPS_GetVariableProfile($Name);
            if ($profile['ProfileType'] != $Vartype) {
                throw new Exception('Variable profile type does not match for profile ' . $Name);
            }
        }

        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        IPS_SetVariableProfileDigits($Name, $Digits);
        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
    }

    protected function RegisterProfileAssociation($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $Stepsize, $Digits, $Associations, $VarType)
    {
        if (count($Associations) === 0) {
            $MinValue = 0;
            $MaxValue = 0;
        }

        $this->RegisterProfile($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $Stepsize, $Digits, $VarType);

        foreach ($Associations as $Association) {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
        }
    }

    protected function GetDistanceToCenter($center_latitude, $center_longitude, $current_latitude, $current_longitude, $unit)
    {
        $theta = $center_longitude - $current_longitude;
        $distance = sin(deg2rad($center_latitude)) * sin(deg2rad($current_latitude)) + cos(deg2rad($center_latitude)) * cos(deg2rad($current_latitude)) * cos(deg2rad($theta));
        $distance = acos($distance);
        $distance = rad2deg($distance);
        $miles = $distance * 60 * 1.1515;
        $unit = strtoupper($unit);
        if ($unit == 'KM') { // Kilometer
            $distance = round(($miles * 1.609344), 2);
        } elseif ($unit == 'NM') { // Nautic Mile NM
            $distance = round(($miles * 0.8684), 2);
        } elseif ($unit == 'M') { // Meter m
            $distance = round(($miles * 1.609344 * 1000), 2);
        } else {
            $distance = round($miles, 2);
        }
        return $distance;
    }

    protected function GetDirectionToCenter($center_latitude, $center_longitude, $current_latitude, $current_longitude)
    {
        //difference in longitudinal coordinates
        $dLon = deg2rad($current_longitude) - deg2rad($center_longitude); // Δlon = abs( lonA - lonB )
        //difference in the phi of latitudinal coordinates
        $dPhi = log(tan(deg2rad($current_latitude) / 2 + pi() / 4) / tan(deg2rad($center_latitude) / 2 + pi() / 4)); // Δφ = ln( tan( latB / 2 + π / 4 ) / tan( latA / 2 + π / 4) )
        //we need to recalculate $dLon if it is greater than pi
        if (abs($dLon) > pi()) {
            if ($dLon > 0) {
                $dLon = (2 * pi() - $dLon) * -1;
            } else {
                $dLon = 2 * pi() + $dLon;
            }
        }
        //return the angle, normalized
        $angle = (rad2deg(atan2($dLon, $dPhi)) + 360) % 360; // tragen :  θ = atan2( Δlon ,  Δφ )
        return $angle;
    }

    private function getValueOfMotion($motion)
    {
        switch ($motion) {
            case 'unknown':
                return 0;
            case 'stationary':
                return 1;
            case 'walking':
                return 2;
            case 'running':
                return 3;
            case 'automotive':
                return 4;
            case 'cycling':
                return 5;
            default:
                throw new InvalidArgumentException('Unknown motion: ' . $motion);
        }
    }

    private function ReduceGUIDToIdent($guid)
    {
        return str_replace(['{', '-', '}'], '', $guid);
    }

    private function CreateVariableByIdent($id, $ident, $name, $type, $profile = '')
    {
        $vid = @IPS_GetObjectIDByIdent($ident, $id);
        if ($vid === false) {
            $vid = IPS_CreateVariable($type);
            IPS_SetParent($vid, $id);
            IPS_SetName($vid, $name);
            IPS_SetIdent($vid, $ident);
            if ($profile != '') {
                IPS_SetVariableCustomProfile($vid, $profile);
            }
        }
        return $vid;
    }

    private function CreateInstanceByIdent($id, $ident, $name, $moduleid = '{485D0419-BE97-4548-AA9C-C083EB82E61E}')
    {
        $iid = @IPS_GetObjectIDByIdent($ident, $id);
        if ($iid === false) {
            $iid = IPS_CreateInstance($moduleid);
            IPS_SetParent($iid, $id);
            IPS_SetName($iid, $name);
            IPS_SetIdent($iid, $ident);
        }
        return $iid;
    }
}
