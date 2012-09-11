<?php
/**
 * IOSTAT Extended
 * @author Olivier Doucet <olivier@oxeva.fr>
 * @version 0.01
 **/

// Linux only
if (!file_exists('/sys/block')) {
	echo "This script runs only under Linux or similar OS (need /sys/block)\n";
	exit(1);
}

// @todo argv

if (isset($argv[1])) {
    define('SLEEP', (int) $argv[1]);
} else {
	echo "No arg given, we will play at 1 second interval (default value)\n";
    define('SLEEP', 1);
}

// Variables needed
$arrayDM        = array(); // DM name to ID mapping
$arrayDRBD      = array(); // DRBD name to DM id mapping
$arrayDisks     = array(); // if no DRBD, DM id mapping will be put here
$arrayDRBDid    = array(); // DRBD name to DRBD id

$arrayStats     = array(); // /sys/block/xx/stat content
$arrayStatsDiff = array(); // copy of arrayStats for differentials


echo "Preparing data ...\n";

// Create mapping DM-X to LVM
$lvsOutput = explode(
    "\n", 
    shell_exec(
        'lvs -o vg_name,lv_name,devices,lv_kernel_minor '.
        '--noheadings --separator :'
    )
);
foreach ($lvsOutput as $row) {
    $infos = explode(':', trim($row));
    if ($infos[0] != '' && $infos[1] != '') {
        $arrayDM[$infos[0].'/'.$infos[1]] = (int) $infos[3];
		
		// DRBD used ?
		if (strpos($infos[2], 'drbd') !== false)
			$arrayDRBD[substr($infos[2], 0, strpos($infos[2], '('))][] = (int) $infos[3];
		else
			$arrayDisks[] = (int) $infos[3];
    }
}
if (count($arrayDM) == 0) die("ERROR: lvs not present or no lvs in this system\n");

unset($lvsOutput, $infos, $row);

ksort($arrayDRBD);
ksort($arrayDM);

// mapping DRBDid
$maxSizeName = 0;
foreach ($arrayDRBD as $name => $array) {
    $infos = stat($name);
    $arrayDRBDid[$name] = $infos['rdev'] % 256;
}
foreach($arrayDM as $name => $id) {
    if (strlen($name) > $maxSizeName) $maxSizeName = strlen($name);
}

$bashCols = (int) exec('tput cols');
if ($bashCols === 0) {
	// error extracting
	define('NAMEMAXLEN', $maxSizeName+3);
	
} elseif ($bashCols < (36+$maxSizeName+3)) {
	// not enough space
	if ($bashCols <= 40) {
		echo "Your shell must have a width of 40 characters minimum.\n";
		exit(1);
	} else {
		define('NAMEMAXLEN', $bashCols - 35);
	}
	
} else {
	define('NAMEMAXLEN', $maxSizeName+3);
	
}

unset($name, $array, $maxSizeName);

// @todo : check shell height if we can print all lines
//         $bashRows = exec('tput lines');


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
    printf(
        "%s\n riops    wiops      rK/s      wK/s %-".NAMEMAXLEN."s\n", 
            str_repeat('=', floor(NAMEMAXLEN+37-21)/2).
            date('[Y-m-d H:i:s]').
            str_repeat('=', floor(NAMEMAXLEN+37-21)/2),
        ' '
    );
	if (count($arrayDRBD) == 0) {
        foreach ($arrayDM as $dmName => $dmId) {
            show_stats($dmName, $arrayStats['dm-'.$dmId], $arrayStatsDiff['dm-'.$dmId]);
        }
	} else
    foreach ($arrayDRBD as $drbdName => $drbdIds) {
        show_stats(
            $drbdName, 
            $arrayStats['drbd'.$arrayDRBDid[$drbdName]], 
            $arrayStatsDiff['drbd'.$arrayDRBDid[$drbdName]],
			"\033[0;30m\033[44m"
        );
        
        foreach ($arrayDM as $dmName => $dmId) {
            if (in_array($dmId, $drbdIds))
            show_stats('   '.$dmName, $arrayStats['dm-'.$dmId], $arrayStatsDiff['dm-'.$dmId]);
        }
    }
    
    $arrayStatsDiff = $arrayStats;
    usleep(SLEEP*1000000);
} while (true);

/** 
 * Print pretty output
 * @var $dmName  device name
 * @var $arrayStats latest values
 * @var $arrayStats old values
 */
function show_stats($dmName, $arrayStats, $arrayStatsDiff, $color = false)
{
    if ($arrayStats === null || $arrayStatsDiff == null) {
        printf("%-".NAMEMAXLEN."s\n", $dmName);
        return;
    }
    $diffRIOPS = $arrayStats[0] - $arrayStatsDiff[0];
    $diffWIOPS = $arrayStats[4] - $arrayStatsDiff[4];
    $diffRK    = $arrayStats[2] - $arrayStatsDiff[2];
    $diffWK    = $arrayStats[6] - $arrayStatsDiff[6];
	if ($color === false) {
		$color = '';
		$colorEnd = '';
	} else {
		$color = $color.'';
		$colorEnd = " \033[0m";
	}
	
    
    printf(
        $color."%6d   %6d    %6d    %6d %-".NAMEMAXLEN."s ".$colorEnd."\n", 
        ($diffRIOPS == 0) ? '-' : $diffRIOPS/SLEEP,
        ($diffWIOPS == 0) ? '-' : $diffWIOPS/SLEEP,
        ($diffRK == 0)    ? '-' : $diffRK*512/1024/SLEEP,
        ($diffWK == 0)    ? '-' : $diffWK*512/1024/SLEEP,
		substr($dmName, 0, NAMEMAXLEN)
    );
}
