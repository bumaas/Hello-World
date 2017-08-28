<?php

// Quelle: http://www.ip-symcon.de/forum/99682-post76.html

// HM Inventory 1.3b

// Anpassung für IPS v 2.5 27.10.2011 by Raketenschnecke
// Anpassung für IPS v 4.0 16.03.2016 by bumaas

//require_once ("_Global_functions.ips.php");
//TraceStart(basename(__FILE__, '.ips.php'));

//$IP_adr_BidCos_Service = IP_HOMEMATIC;   // Change IP-address if the BidCos-service is remote
$IP_adr_BidCos_Service = '192.168.2.25';   // Change IP-address if the BidCos-service is remote
$IP_adr_BidCos_Service = '192.168.178.54';   // Change IP-address if the BidCos-service is remote

//define ("ID_HM_DEV_LIST", 27977);
define('ID_HM_DEV_LIST', 44627);

// Choose the mode for this script:  if set to something else than "HTMLbox" it will output a PopUp-window
$ScriptMode = 'HTMLbox';
// If "HTMLbox" is selected this script needs to be started once from the management console for installation

// Save output as HTML_file: name of file or null for no HTML file
// $Save_as_HTMLfile = null;
$Save_as_HTMLfile = IPS_GetKernelDir().'HM_inventory.html';

// choose Option how the device list should be sorted:
//     possible options:  "by_IPS-id"   "by_IPS-dev_name"   "by_HM-address"   "by_HM-device"   "by_HM-type"
$HM_list_sort_option = 'by_HM-device';

$Show_Virtual_Key_Entries = false;  // TRUE  if somebody is interested in Virtual_Key HM-Entries
$Show_Maintenance_Entries = false;  // TRUE  if somebody is interested in Maintenance HM-Entries

define('Show_Long_IPS_device_names', ' ');

// Show device names used in HM_konfigurator (only device names which have been set once are shown)
// define ("Show_HM_konfigurator_device_names", " " );

// To force wireless communication for RF-level update the following define() must be uncommented
//  use carefully:  very slow, puts heavy traffic on your wireless environment, might cause collisions
//  battery powered HM-devices can't be forced by BidCos-service to communicate
// define ("RequestStatus_for_LevelUpdate", " " );

// Some color options for the HTML output
$Bgcolor_Global = '#181818';      // Global background color
$Bgcolor_Ifcs = '#223344';        // Background color for the interface list
$Bgcolor_Headline = '#334455';    // Background color for the header line of the device list
$Bgcolor_OddLine = '#181818';     // Background color for the odd lines of the device list
$Bgcolor_EvenLine = '#1A2B3C';    // Background color for the even lines of the device list

// We need the "xmlrpc" include file: to be adapted if not found in PHP's "include_path"
include_once 'xmlrpc.inc';

// --------------------------------------------------------------------------------------------
//   Nothing to change below this line
// --------------------------------------------------------------------------------------------
//
// HM-Inventory  -  Version 1.3  -  2010-10-29
//
// written by Andreas Bahrdt
//
// Public domain
//

$Version = '1.3';
$StartTime = time();

// Get the required data from the BidCos-Service

$BidCos_Service_adr = sprintf('http://%s:2001', $IP_adr_BidCos_Service);
$xml_client = new xmlrpc_client($BidCos_Service_adr);

$hm_dev_list = [];

$xml_reqmsg = new xmlrpcmsg('listDevices');
$xml_rtnmsg = $xml_client->send($xml_reqmsg);
if ($xml_rtnmsg->errno == 0) {
    $hm_dev_list = php_xmlrpc_decode($xml_rtnmsg->value());
    //	print_r ($hm_dev_list);
} else {
    //  TraceEnd(basename(__FILE__, '.ips.php'));
    die("Fatal error: Can't get HM-device information from the BidCos-Service ($BidCos_Service_adr) &nbsp&nbsp&nbsp - &nbsp&nbsp&nbsp ($xml_rtnmsg->errstr)<br>\n");
}

SetValueString(ID_HM_DEV_LIST, serialize($hm_dev_list));  //array in String variable speichern

$xml_reqmsg = new xmlrpcmsg('listBidcosInterfaces');
$xml_rtnmsg = $xml_client->send($xml_reqmsg);
if ($xml_rtnmsg->errno == 0) {
    $hm_BidCos_Ifc_list = php_xmlrpc_decode($xml_rtnmsg->value());
} else {
    TraceEnd(basename(__FILE__, '.ips.php'));
    die("Fatal error: Can't get HM-interface information from the BidCos-Service ($BidCos_Service_adr) &nbsp&nbsp&nbsp - &nbsp&nbsp&nbsp ($xml_rtnmsg->errstr)<br>\n");
}

$IPS_device_num = 0;
$IPS_HM_channel_num = 0;
$HM_module_num = 0;
$HM_array = [];
$HM_interface_num = 0;
$HM_interface_connected_num = 0;

foreach ($hm_BidCos_Ifc_list as $hm_ifce) {
    $HM_interface_num += 1;
    if ($hm_ifce['CONNECTED']) {
        $HM_interface_connected_num += 1;
    }
}

// Fill array with all HM-devices found in IP-Symcon
//
foreach (IPS_GetInstanceListByModuleID('{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}') as $id) {
    $HM_module_num += 1;
    $IPS_device_num += 1;
    $IPS_HM_channel_already_assigned = false;
    $HM_address = IPS_GetProperty($id, 'Address');
    $HM_Par_address = substr($HM_address, 0, 10);
    $HM_device = '-';
    $HM_devname = '-';
    $HM_FWversion = ' ';
    $HM_devtype = '-';
    $HM_direction = '-';
    $HM_AES_active = '-';
    $hm_chld_dev = null;
    $hm_par_dev = null;

    foreach ($hm_dev_list as $hm_dev) {
        if ($hm_dev['ADDRESS'] == $HM_address) {
            $hm_chld_dev = $hm_dev;
        }
        if ($hm_dev['ADDRESS'] == $HM_Par_address) {
            $hm_par_dev = $hm_dev;
        }
        if ($hm_chld_dev != null) {
            $HM_device = $hm_dev['PARENT_TYPE'];
            if (defined('Show_HM_konfigurator_device_names')) {
                $xml_reqmsg = new xmlrpcmsg('getAllMetadata', [new xmlrpcval($HM_Par_address, 'string')]);
                $xml_rtnmsg = $xml_client->send($xml_reqmsg);
                if ($xml_rtnmsg->errno == 0) {
                    $Metadata = php_xmlrpc_decode($xml_rtnmsg->value());
                    $HM_devname = $Metadata['NAME'];
                }
            }
            $HM_FWversion = ($hm_par_dev != null) ? $hm_par_dev['FIRMWARE'] : ' ';
            $HM_Interface = ($hm_par_dev != null) ? $hm_par_dev['INTERFACE'] : '';
            $HM_Roaming = ($hm_par_dev != null) ? ($hm_par_dev['ROAMING'] ? '+' : '-') : ' ';
            $HM_devtype = $hm_dev['TYPE'];
            if ($hm_dev['DIRECTION'] == 1) {
                $HM_direction = 'TX';
            } elseif ($hm_dev['DIRECTION'] == 2) {
                $HM_direction = 'RX';
            }
            if ($hm_dev['AES_ACTIVE'] != 0) {
                $HM_AES_active = '+';
            }
            break;
        }
    }

    if ($HM_address != '') {
        foreach ($HM_array as &$HM_dev) {
            if ($HM_dev['HM_address'] == $HM_address) {
                $HM_dev['IPS_HM_d_assgnd'] = true;
                $IPS_HM_channel_already_assigned = true;
                break;
            }
        }
        if (!$IPS_HM_channel_already_assigned) {
            $IPS_HM_channel_num += 1;
        }
    }

    $IPS_name = (defined('Show_Long_IPS_device_names')) ? IPS_GetLocation($id) : IPS_GetName($id);
    $HM_array[] = ['IPS_occ'             => $HM_module_num, 'IPS_id'=>$id, 'IPS_name'=>$IPS_name,
                      'IPS_HM_d_assgnd'=> $IPS_HM_channel_already_assigned, 'HM_address'=>$HM_address,
                      'HM_device'      => $HM_device, 'HM_devname'=>$HM_devname, 'HM_FWversion'=>$HM_FWversion, 'HM_devtype'=>$HM_devtype,
                      'HM_direction'   => $HM_direction, 'HM_AES_active'=>$HM_AES_active, 'HM_Interface'=>$HM_Interface, 'HM_Roaming'=>$HM_Roaming, ];
}

// Add HM_devices known by BidCos but not present in IP-Symcon
//
foreach ($hm_dev_list as $hm_dev) {
    $HM_address = $hm_dev['ADDRESS'];
    $hm_dev_in_array = false;
    foreach ($HM_array as $HM_dev_a) {
        if ($hm_dev['ADDRESS'] == $HM_dev_a['HM_address']) {
            $hm_dev_in_array = true;
            break;
        }
    }
    if ($hm_dev_in_array == false) {
        if (array_key_exists('PARENT_TYPE', $hm_dev)) {
            if ($hm_dev['TYPE'] == 'VIRTUAL_KEY' && $Show_Virtual_Key_Entries != true) {
                continue;
            }
            if ($hm_dev['TYPE'] == 'MAINTENANCE' && $Show_Maintenance_Entries != true) {
                continue;
            }
            $hm_chld_dev = $hm_dev;
            $hm_par_dev = null;
            $HM_Par_address = substr($HM_address, 0, 10);
            foreach ($hm_dev_list as $hm_p_dev) {
                if ($hm_p_dev['ADDRESS'] == $HM_Par_address) {
                    $hm_par_dev = $hm_p_dev;
                }
            }
            if ($hm_chld_dev != null) {
                $HM_module_num += 1;
                $HM_device = $hm_chld_dev['PARENT_TYPE'];
                $HM_devname = '-';
                if (defined('Show_HM_konfigurator_device_names')) {
                    $xml_reqmsg = new xmlrpcmsg('getAllMetadata', [new xmlrpcval($HM_Par_address, 'string')]);
                    $xml_rtnmsg = $xml_client->send($xml_reqmsg);
                    if ($xml_rtnmsg->errno == 0) {
                        $Metadata = php_xmlrpc_decode($xml_rtnmsg->value());
                        $HM_devname = $Metadata['NAME'];
                    }
                }
                $HM_FWversion = ($hm_par_dev != null) ? $hm_par_dev['FIRMWARE'] : ' ';
                $HM_Interface = ($hm_par_dev != null) ? $hm_par_dev['INTERFACE'] : '';
                $HM_Roaming = ($hm_par_dev != null) ? ($hm_par_dev['ROAMING'] ? '+' : '-') : ' ';
                $HM_devtype = $hm_chld_dev['TYPE'];
                $HM_direction = '-';
                $HM_AES_active = '-';
                if ($hm_dev['DIRECTION'] == 1) {
                    $HM_direction = 'TX';
                } elseif ($hm_dev['DIRECTION'] == 2) {
                    $HM_direction = 'RX';
                }
                if ($hm_chld_dev['AES_ACTIVE'] != 0) {
                    $HM_AES_active = '+';
                }
                $HM_array[] = ['IPS_occ'             => $HM_module_num, 'IPS_id'=>'-', 'IPS_name'=>'-',
                            'IPS_HM_d_assgnd'=> false, 'HM_address'=>$HM_address,
                            'HM_device'      => $HM_device, 'HM_devname'=>$HM_devname, 'HM_FWversion'=>$HM_FWversion, 'HM_devtype'=>$HM_devtype,
                            'HM_direction'   => $HM_direction, 'HM_AES_active'=>$HM_AES_active, 'HM_Interface'=>$HM_Interface, 'HM_Roaming'=>$HM_Roaming, ];
            }
        }
    }
}

// Force communication for RF-level update if requested
//
if (defined('RequestStatus_for_LevelUpdate')) {
    foreach ($HM_array as &$HM_dev) {
        if (substr($HM_dev['HM_address'], 10, 2) == ':1') {
            $xml_method = new xmlrpcmsg('getParamset', [new xmlrpcval($HM_dev['HM_address'], 'string'), new xmlrpcval('VALUES', 'string')]);
            $xml_rtnmsg = $xml_client->send($xml_method);
            if ($xml_rtnmsg->errno == 0) {
                $HM_ParamSet = php_xmlrpc_decode($xml_rtnmsg->value());
            }
        }
    }
}

// Request tx/rx RF-levels from BidCos-Service
//
$xml_reqmsg = new xmlrpcmsg('rssiInfo');
$xml_rtnmsg = $xml_client->send($xml_reqmsg);
$hm_lvl_list = [];
if ($xml_rtnmsg->errno == 0) {
    $hm_lvl_list = php_xmlrpc_decode($xml_rtnmsg->value());
} else {
    print "Warning: Can't get RF-level information from the BidCos-Service ($BidCos_Service_adr) &nbsp&nbsp&nbsp - &nbsp&nbsp&nbsp ($xml_rtnmsg->errstr)<br>\n";
}

// Add tx/rx RF-levels for each device/interface
//
if (is_array($hm_lvl_list)) {
    foreach ($HM_array as &$HM_dev) {
        if (($hm_adr = substr($HM_dev['HM_address'], 0, 10)) != '') {
            if (array_key_exists($hm_adr, $hm_lvl_list)) {
                $HM_lvl_array = [];
                $hm_levels = $hm_lvl_list[$hm_adr];
                $best_lvl_ifce = -1;
                $ifce_no = 0;
                foreach ($hm_BidCos_Ifc_list as $hm_ifce) {
                    if ($hm_ifce['CONNECTED']) {
                        if (array_key_exists($hm_ifce['ADDRESS'], $hm_levels)) {
                            $HM_lvl_array[] = [$hm_levels[$hm_ifce['ADDRESS']][0], $hm_levels[$hm_ifce['ADDRESS']][1], (($HM_dev['HM_Interface'] == $hm_ifce['ADDRESS']) ? true : false), false];
                            if ($best_lvl_ifce == -1 && $hm_levels[$hm_ifce['ADDRESS']][1] != 65536) {
                                $best_lvl_ifce = $ifce_no;
                            } elseif ($hm_levels[$hm_ifce['ADDRESS']][1] != 65536 && $HM_lvl_array[$best_lvl_ifce][1] < $hm_levels[$hm_ifce['ADDRESS']][1]) {
                                $best_lvl_ifce = $ifce_no;
                            }
                        } else {
                            $HM_lvl_array[] = [65536, 65536, false, false];
                        }
                        $ifce_no++;
                    }
                }
                if ($best_lvl_ifce != -1) {
                    $best_lvl = $HM_lvl_array[$best_lvl_ifce][1];
                    foreach ($HM_lvl_array as &$hm_lvl) {
                        $hm_lvl[3] = ($hm_lvl[1] == $best_lvl) ? true : false;
                    }
                }
                $HM_dev['HM_levels'] = $HM_lvl_array;
            }
        }
    }
}

// Sort device list
//
switch ($HM_list_sort_option) {
  case 'by_IPS-id' :
    usort($HM_array, 'usort_IPS_id');
    break;
  case 'by_IPS-dev_name' :
    usort($HM_array, 'usort_IPS_dev_name');
    break;
  case 'by_HM-address' :
    usort($HM_array, 'usort_HM_address');
    break;
  case 'by_HM-device':
    usort($HM_array, 'usort_HM_device_adr');
    break;
  case 'by_HM-type':
    usort($HM_array, 'usort_HM_type');
    break;
  default:
    break;
}

// Generate HTML output code

$HTML_intro = "<table width='100%' border='0' align='center' bgcolor=".$Bgcolor_Global.'>';

$HTML_ifcs = "<tr valign='top' width='100%'>";
$HTML_ifcs .= "<td><table align='left'><tr><td><font size='3' color='#99AABB'><b>HM Inventory ($Version) </font></b>";
$HTML_ifcs .= "<font size='3' color='#CCCCCC'><b>&nbsp found at ".strftime('%d.%m.%Y %X', $StartTime).'</font></b></td></tr>';
$HTML_ifcs .= "<tr><td><font size='2' color='#CCCCCC'>".$HM_interface_num.' HomeMatic interfaces ('.$HM_interface_connected_num.' connected)</td>';
$HTML_ifcs .= "<tr><td><font size='2' color='#CCCCCC'>".$IPS_device_num.' IPS instances (connected to '.$IPS_HM_channel_num.' HM channels)</td>';
$HTML_ifcs .= '</table></td>';
$HTML_ifcs .= "<td valign='top'>&nbsp;</td>";

$dtifc_td_b_n = "<td><font size='2' color='#EEEEEE'>";
$dtifc_td_e_n = '</font></td>';
$HTML_ifcs .= "<td width='40%' valign='bottom'><table width='100%' align='right' bgcolor=".$Bgcolor_Ifcs.'>';
//	print_r($hm_BidCos_Ifc_list);
foreach ($hm_BidCos_Ifc_list as $hm_ifce) {
    $dtifc_td_b = "<td><font size='2' color='#EEEEEE'>".(($hm_ifce['DEFAULT']) ? '<i>' : '');
    $dtifc_td_e = (($hm_ifce['DEFAULT']) ? '</i>' : '').'</font></td>';
    $dsc_strg = sprintf('%sconnected', ($hm_ifce['CONNECTED']) ? '' : 'Not ');
    $HTML_ifcs .= '<tr>'.$dtifc_td_b.'Interface: '.$hm_ifce['ADDRESS'].'&nbsp'.$dtifc_td_e;
    $HTML_ifcs .= $dtifc_td_b.$hm_ifce['DESCRIPTION'].$dtifc_td_e.$dtifc_td_b.$dsc_strg.$dtifc_td_e.'</tr>';
}
$HTML_ifcs .= '</table></td></tr>';

$HTML_sep = "<tr><td colspan=3><table width='100%' align='left'> <hr><tr><td> </td></tr></table></td></tr>";

$dthdr_td_b = "<td><font size='2' color='#EEEEEE'><b>";
$dthdr_td_b_r = "<td align='right'><font size='2' color='#EEEEEE'><b>";
$dthdr_td_e = '</font></b></td>';
$dthdr_td_eb = $dthdr_td_e.$dthdr_td_b;
$HTML_dvcs = "<tr><td colspan=3><table width='100%' align='left'>";
$HTML_dvcs .= '<tr bgcolor='.$Bgcolor_Headline.'>';
$HTML_dvcs .= $dthdr_td_b_r.'&nbsp##&nbsp'.$dthdr_td_eb.'IPS-ID'.$dthdr_td_eb.'IPS device name&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp'.$dthdr_td_eb.'HM-address'.$dthdr_td_e;
if (defined('Show_HM_konfigurator_device_names')) {
    $HTML_dvcs .= $dthdr_td_b.'HM device name'.$dthdr_td_e;
}
$HTML_dvcs .= $dthdr_td_b.'HM-device'.$dthdr_td_eb.'Fw.'.$dthdr_td_eb.'HM-type'.$dthdr_td_eb.'Dir.'.$dthdr_td_eb.'AES'.$dthdr_td_e;
$HTML_dvcs .= "<td width='2%' align='center'><font size='2' color='#EEEEEE'> Roa- ming".'</font></td>';
foreach ($hm_BidCos_Ifc_list as $hm_ifce) {
    if ($hm_ifce['CONNECTED']) {
        $HTML_dvcs .= "<td width='6%' align='center'><font size='2' color='#EEEEEE'>".$hm_ifce['ADDRESS'].' tx/rx&nbsp(db&micro;V)'.'</font></td>';
    }
}
$HTML_dvcs .= '</tr>';

$ci = 0;
foreach ($HM_array as $HM_dev) {
    $font_tag = "<font size='2' color=".(($HM_dev['IPS_HM_d_assgnd'] == false) ? '#DDDDDD' : '#FFAAAA').'>';
    $dtdvc_td_b = '<td>'.$font_tag;
    $dtdvc_td_ar_b = "<td align='right'>".$font_tag;
    $dtdvc_td_ac_b = "<td align='center'>".$font_tag;
    $dtdvc_td_e = '</font></td>';
    $dtdvc_td_eb = $dtdvc_td_e.$dtdvc_td_b;
    $r_bgcolor = (($ci++ % 2) == 0) ? $Bgcolor_OddLine : $Bgcolor_EvenLine;
    $entry_no = $ci;   // use $HM_dev[IPS_occ] to indicate order of occurence in IPS
    $HTML_dvcs .= '<tr bgcolor='.$r_bgcolor.'>'.$dtdvc_td_ar_b.$entry_no.'&nbsp&nbsp'.$dtdvc_td_eb;
    $HTML_dvcs .= $HM_dev['IPS_id'].$dtdvc_td_eb.utf8_decode($HM_dev['IPS_name']).$dtdvc_td_eb.$HM_dev['HM_address'].$dtdvc_td_eb;
    if (defined('Show_HM_konfigurator_device_names')) {
        $HTML_dvcs .= $HM_dev['HM_devname'].$dtdvc_td_eb;
    }
    $HTML_dvcs .= $HM_dev['HM_device'].$dtdvc_td_eb.$HM_dev['HM_FWversion'].$dtdvc_td_eb.$HM_dev['HM_devtype'].$dtdvc_td_eb;
    $HTML_dvcs .= $HM_dev['HM_direction'].$dtdvc_td_e.$dtdvc_td_ac_b.$HM_dev['HM_AES_active'].$dtdvc_td_e.$dtdvc_td_ac_b.$HM_dev['HM_Roaming'].$dtdvc_td_e;

    if (array_key_exists('HM_levels', $HM_dev)) {
        foreach ($HM_dev['HM_levels'] as $ifce_dev_lvls) {
            if (array_key_exists(2, $ifce_dev_lvls)) {
                $rx_lvl = $ifce_dev_lvls[0];
                $tx_lvl = $ifce_dev_lvls[1];
                // Interface with best levels gets different color
                $lvl_strg_color = '<font color='.(($ifce_dev_lvls[3]) ? ((($HM_dev['HM_Roaming'] == '+') || $ifce_dev_lvls[2]) ? '#DDDD66' : '#FFFF88') : '#DDDDDD').'>';
//        $fmt_strg = "%s".((($HM_dev['HM_Roaming']=='+')||$ifce_dev_lvls[2])?"<i>%s &#047 %s</i>":"%s &#047 %s")."</font>";
                $fmt_strg = '%s'.((($HM_dev['HM_Roaming'] == '+') || $ifce_dev_lvls[2]) ? '<ins>%s &#047 %s</ins>' : '%s &#047 %s').'</font>';
                $lvl_strg = sprintf($fmt_strg, $lvl_strg_color, ($rx_lvl != 65536) ? ((string) $rx_lvl) : '--', ($tx_lvl != 65536) ? ((string) $tx_lvl) : '--');
            } else {
                $lvl_strg = '-- &#047 --';
            }
            $HTML_dvcs .= $dtdvc_td_ac_b.$lvl_strg.$dtdvc_td_e;
        }
    } else {
        for ($lci = 0; $lci < $HM_interface_connected_num; $lci++) {
            $HTML_dvcs .= '<td> </td>';
        }
    }
}

if ($HM_module_num == 0) {
    $HTML_dvcs .= "<tr><td colspan=20 align='center'><br/><font size='4' color='#DDDDDD'>No HomeMatic devices found!</font></td></tr>";
}

$HTML_dvcs .= '</td></tr>';

// Some comments
//
$HTML_notes = "<tr><td colspan=20><table width='100%' align='left'><hr><color='#666666'><tr><td> </td></tr></table></td></tr>";
$HTML_notes .= "<tr><td colspan=20><table width='100%' align='left'><tr><td><font size='3' color='#DDDDDD'>Notes:</font></td></tr>";
$HTML_notes .= "<tr><td><font size='2' color='#DDDDDD'><ol>";
$HTML_notes .= '<li>Currently only BidCos-RF devices are supported</li>';
$HTML_notes .= '<li>Interfaces: bold letters indicate the default BidCos-Interface.</li>';
$HTML_notes .= '<li>Level-pairs: the left value is showing the last signal level received by the device from the interface,';
$HTML_notes .= ' while the right value is showing the last signal level received by the interface from the device.</li>';
//$HTML_notes .= "<li>Level-pairs: italic letters of the level-pair indicate the BidCos-Interface associated with the device";
$HTML_notes .= '<li>Level-pairs: underlined letters of the level-pair indicate the BidCos-Interface associated with the device';
$HTML_notes .= ' (or all interfaces when Roaming is enabled for the device).</li>';
$HTML_notes .= '<li>Level-pairs: the yellow level-pair indicates the BidCos-Interface with best signal quality.</li>';
$HTML_notes .= "<li>Devices without level-pairs haven't send/received anything since last start of the BidCos-service.</li>";
$HTML_notes .= '<li>BidCos channels assigned to more than one IPS-device are shown in red.</li>';
$HTML_notes .= '</ol></font></td></tr>';
$HTML_notes .= '</table></td></tr>';

$HTML_end = '</table>';

// Output the results

if ($ScriptMode == 'HTMLbox') {
    $object = IPS_GetObject($_IPS['SELF']);
    $parentID = $object['ParentID'];

    // Installer for HTMLbox
    if ($_IPS['SENDER'] == 'Execute') {
        IPS_SetHidden($_IPS['SELF'], false);
        IPS_SetName($_IPS['SELF'], 'Refresh HM inventory');
        IPS_SetIcon($_IPS['SELF'], 'Information');
        $parentObject = IPS_GetObject($parentID);
        if ($parentObject['ObjectType'] !== 1) {
            $instanceID = IPS_CreateInstance('{485D0419-BE97-4548-AA9C-C083EB82E61E}');
            IPS_SetParent($instanceID, $parentID);
            $parentID = $instanceID;
            IPS_SetParent($_IPS['SELF'], $parentID);
            IPS_SetName($instanceID, 'HM inventory');
        }
    }
    $HTMLbox_HMi = CreateVariableByName_ex($parentID, 'HM inventory', 3, 'Information', '~HTMLBox', 2);
    $HTMLbox_str = $HTML_intro.$HTML_ifcs.$HTML_sep.$HTML_dvcs.$HTML_notes.$HTML_end;
    SetValue($HTMLbox_HMi, $HTMLbox_str);

    //  $HTML_file = fopen(IPS_GetKernelDir()."HMinventory_test.html",'wb');
//  fwrite($HTML_file,$HTMLbox_str);
//  fclose($HTML_file);
} else {
    echo $HTML_intro;
    echo $HTML_ifcs;
    echo $HTML_sep;
    echo $HTML_dvcs;
    echo $HTML_notes;
    echo $HTML_end;
}

if ($Save_as_HTMLfile != null) {
    $HTML_file = fopen($Save_as_HTMLfile, 'wb');
    fwrite($HTML_file, '<html><head><style type="text/css">');
    fwrite($HTML_file, 'html,body {font-family:Arial,Helvetica,sans-serif;font-size:12px;background-color:#000000;color:#dddddd;}');
    fwrite($HTML_file, '</style></head><body>');
    fwrite($HTML_file, $HTML_intro);
    fwrite($HTML_file, "<tr><td colspan=3><table width='100%' align='left'bgcolor=#112233><tr><td><h1>HM inventory</h1></td></tr></table></td></tr>");
    fwrite($HTML_file, $HTML_ifcs);
    fwrite($HTML_file, $HTML_sep);
    fwrite($HTML_file, $HTML_dvcs);
    fwrite($HTML_file, $HTML_notes);
    fwrite($HTML_file, $HTML_end);
    fwrite($HTML_file, '</body></html>');
    fclose($HTML_file);
}

//TraceEnd(basename(__FILE__, '.ips.php'));

// --------------------------------------------------------------------------------------------

function CreateVariableByName_ex($id, $name, $type, $icon, $profile, $pos)
{
    $vid = @IPS_GetVariableIDByName($name, $id);
    if ($vid === false) {
        $vid = IPS_CreateVariable($type);
        IPS_SetParent($vid, $id);
        IPS_SetName($vid, $name);
        IPS_SetPosition($vid, $pos);
        IPS_SetIcon($vid, $icon);
        IPS_SetVariableCustomProfile($vid, $profile);
        IPS_SetInfo($vid, 'this variable was created by script #'.$_IPS['SELF']);
    }

    return $vid;
}

function usort_HM_address($a, $b)
{
    $result = strcasecmp($a['HM_address'], $b['HM_address']);

    $a_adr = explode(':', $a['HM_address']);
    $b_adr = explode(':', $b['HM_address']);
    if (count($a_adr) == 2 && count($b_adr) == 2) {
        if (strcasecmp($a_adr[0], $b_adr[0]) == 0) {
            $result = (int) $a_adr[1] > $b_adr[1];
        }
    }

    return $result;
}
function usort_IPS_id($a, $b)
{
    return $a['IPS_id'] > $b['IPS_id'];
}
function usort_IPS_dev_name($a, $b)
{
    if (($result = strcasecmp($a['IPS_name'], $b['IPS_name'])) == 0) {
        $result = usort_HM_address($a, $b);
    }

    return $result;
}
function usort_HM_device_adr($a, $b)
{
    if (($result = strcasecmp($a['HM_device'], $b['HM_device'])) == 0) {
        $result = usort_HM_address($a, $b);
    }

    return $result;
}
function usort_HM_type($a, $b)
{
    if (($result = strcasecmp($a['HM_devtype'], $b['HM_devtype'])) == 0) {
        $result = usort_HM_address($a, $b);
    }

    return $result;
}
