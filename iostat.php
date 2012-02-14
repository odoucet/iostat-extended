<?php

/************************
 * IOSTAT Extended
 * @author Olivier Doucet <olivier@oxeva.fr>
 * @version 0.01
 ************************/

// @todo argv

if (isset($argv[1])) {
    define('SLEEP', (int) $argv[1]);
} else {
    define('SLEEP', 1);
}

// Variables needed
$arrayDM    = array(); // DM name to ID mapping
$arrayDRBD  = array(); // DRBD name to DM id mapping
$arrayDRBDid= array(); // DRBD name to DRBD id

$arrayStats = array(); // /sys/block/xx/stat content
$arrayStatsDiff = array(); // copy of arrayStats for differentials

echo "Preparing data ...\n";

// Create mapping DM-X to LVM
$lvsOutput = explode("\n", shell_exec('lvs -o vg_name,lv_name,devices,lv_kernel_minor --noheadings --separator :'));

foreach ($lvsOutput as $row) {
    $infos = explode(':', trim($row));
    if ($infos[0] != '' && $infos[1] != '') {
        $arrayDM[$infos[0].'/'.$infos[1]] = (int) $infos[3];
        $arrayDRBD[substr($infos[2], 0, strpos($infos[2], '('))][] = (int) $infos[3];
    }
}
unset($lvsOutput, $infos, $row);

ksort($arrayDRBD);
ksort($arrayDM);

// mapping DRBDid
foreach ($arrayDRBD as $name => $array) {
    $infos = stat($name);
    $arrayDRBDid[$name] = $infos['rdev'] % 256;
}
unset($name, $array);

// Retrieve DM-X and DRBDX informations
do {
    $d = dir('/sys/block');
    while (false !== ($entry = $d->read())) {
        if ($entry == '.' || $entry == '..') continue;
        
        $file = @file_get_contents('/sys/block/'.$entry.'/stat');
        if ($file !== false) {
            $arrayStats[$entry] = preg_split('@\s+@', trim($file));    
        }
    }
    $d->close();
    
    if (count($arrayStatsDiff) == 0) {
        $arrayStatsDiff = $arrayStats;
        if (SLEEP > 1) echo "Waiting ".SLEEP." seconds before displaying results ...\n";
        usleep(SLEEP*1000000);
        continue;
    }
    
    // Print part
    printf("%s\n%45s : riops   wiops      rK/s       wK/s \n", str_repeat('=', 60), 'vg/lv');
    foreach ($arrayDRBD as $drbdName => $drbdIds) {
        show_stats($drbdName, $arrayStats['drbd'.$arrayDRBDid[$drbdName]], $arrayStatsDiff['drbd'.$arrayDRBDid[$drbdName]]);
        
        foreach ($arrayDM as $dmName => $dmId) {
            if (in_array($dmId, $drbdIds))
            show_stats('   '.$dmName, $arrayStats['dm-'.$dmId], $arrayStatsDiff['dm-'.$dmId]);
        }
    }
    
    $arrayStatsDiff = $arrayStats;
    usleep(SLEEP*1000000);
} while(true);

function show_stats($dmName, $arrayStats, $arrayStatsDiff) {
    $diffRIOPS = $arrayStats[0] - $arrayStatsDiff[0];
    $diffWIOPS = $arrayStats[4] - $arrayStatsDiff[4];
    $diffRK    = $arrayStats[2] - $arrayStatsDiff[2];
    $diffWK    = $arrayStats[6] - $arrayStatsDiff[6];
    
    printf("%-45s %6d   %6d    %6d    %6d\n", 
        $dmName, 
        ($diffRIOPS == 0) ? '-' : $diffRIOPS/SLEEP,
        ($diffWIOPS == 0) ? '-' : $diffWIOPS/SLEEP,
        ($diffRK == 0)    ? '-' : $diffRK*512/1024/SLEEP,
        ($diffWK == 0)    ? '-' : $diffWK*512/1024/SLEEP
    );
}
