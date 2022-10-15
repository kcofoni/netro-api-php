#!/usr/bin/php
<?php

require_once dirname(__FILE__) . '/netroControler.class.php';

const DEBUG_MODE = false; 

$controllerKey = getenv('NPA_CTRL');
$sensorKey = getenv('NPA_SENS');
if ($controllerKey == '' || $sensorKey == '') {
	echo 'Les variables d\'environnement NPA_CTRL et NPA_SENS doivent être renseignées avant de lancer ce programme' . "\n";
	exit;
}
$verbose = false;
$action = '';
$moisture = 10;
$zone = '';
$duration = 30; // 30 mn pas défaut
$start_time = '';
$delay = 0;
$cfg = getOpt('va:z:d:s:e:m:');
if (array_key_exists('v', $cfg)) {
    $verbose = true;
}
if (array_key_exists('a', $cfg))
    $action = $cfg['a'];
if (array_key_exists('z', $cfg))
    $zone = $cfg['z'];
if (array_key_exists('d', $cfg))
    $duration = $cfg['d'];
if (array_key_exists('s', $cfg))
    $start_time = $cfg['s'];
if (array_key_exists('e', $cfg))
    $delay = $cfg['e'];
if (array_key_exists('m', $cfg))
    $moisture = $cfg['m'];


if ($verbose == true) {
    echo 'action : ' . $action . "\n";
    echo 'zone : ' . $zone . "\n";
    echo 'duration : ' . $duration . " mn\n";
    echo 'start_time : ' . $start_time . " \n";
    echo 'delay : ' . $delay . " \n";
}

// création du controleur et du capteur
$nc = new netroController($controllerKey);
$nc->loadInfo();
$nc->loadMoistures();
$nc->loadSchedules();

$ns = new netroSensor($sensorKey);
$ns->loadSensorData();

// réalisation de l'action demandée
switch($action){
    case 'enable':
        echo 'activation du controleur' . "\n";
        $nc->enable();
        break;
    case 'disable':
        echo 'désactivation du controleur' . "\n";
        $nc->disable();
        break;
    case 'begin':
        echo 'démarrage de l\'arrosage' . "\n";
        $nc->startWatering($duration, array("$zone"), $delay, $start_time);
        sleep(5);
        break;
    case 'startzone':
        echo 'démarrage de l\'arrosage d\'une zone' . "\n";
        $nc->active_zones[$zone]->startWatering($duration);
        sleep(5);
        break;
    case 'moisture':
        echo 'récupération de la moisture d\'une zone' . "\n";
        echo "moisture de la zone $zone : " . $nc->active_zones[$zone]->getMoisture()["moisture"] . " % \n";
        break;
    case 'nowater':
        echo "arrêter l'arrosage dans les 48h" . "\n";
        $nc->noWater(2);
        break;
    case 'setmoisture':
        echo "appliquer une moisture de $moisture % à la zone $zone" . "\n";
        $nc->setMoisture($moisture, array("$zone"));
        break;
    case 'end':
        echo 'arrêt de l\'arrosage' . "\n";
        $nc->stopWatering();
        sleep(5);
        break;
    case '':
        echo 'aucune action demandée' . "\n";
}

try {
    $nc->loadInfo();

    if (DEBUG_MODE) {
        var_dump($nc);        
    }

    echo "\n";
    echo "meta données de netro : \n";
    echo 'time : ' . $nc->getMeta()["time"] . "\n";
    echo 'tid : ' . $nc->getMeta()["tid"] . "\n";
    echo 'version : ' . $nc->getMeta()["version"] . "\n";
    echo 'token_limit : ' . $nc->getMeta()["token_limit"] . "\n";
    echo 'token_remaining : ' . $nc->getMeta()["token_remaining"] . "\n";
    echo 'last_active : ' . $nc->getMeta()["last_active"] . "\n";
    echo 'token_reset : ' . $nc->getMeta()["token_reset"] . "\n";

    echo "\n";
    echo "propriétés du capteur : \n";
    echo 'time : ' . $ns->time . "\n";
    echo 'local_date : ' . $ns->local_date . "\n";
    echo 'local_time : ' . $ns->local_time . "\n";
    echo 'moisture : ' . $ns->moisture . "% \n";
    echo 'sunlight : ' . $ns->sunlight . "K lux\n";
    echo 'celsius : ' . $ns->celsius . "°C \n";
    echo 'fahrenheit : ' . $ns->fahrenheit . "°F \n";
    echo 'battery_level : ' . $ns->battery_level . "% \n";

    echo "\n";
    echo "propriétés du controleur : \n";
    echo 'name : ' . $nc->name . "\n";
    echo 'status : ' . $nc->status . "\n";
    echo 'version : ' . $nc->version . "\n";
    echo 'sw_version : ' . $nc->sw_version . "\n";

    echo 'number of zones : ' . $nc->zone_number . "\n";
    echo 'last active time : ' . $nc->last_active_time . "\n";
    echo 'le controleur est ' . ($nc->active_flag ? 'actif' : 'inactif') . "\n";
    echo ($nc->watering_flag ? 'arrosage en cours...' : 'pas d\'arrosage en cours') . "\n";

    echo 'il y a ' . count($nc->active_zones) . ' zones actives' . "\n\n";
    foreach ($nc->active_zones as $id => $zone) {
        echo 'zone ' . $id . ' de nom ' . $zone->name . ' et de type ' . $zone->smart . "\n";
        echo 'humidité de ' . $zone->getMoisture()["moisture"] . '% estimée le ' . $zone->getMoisture()["date"] . ' pour la zone' . "\n"; 
        if ($zone->isCurrentlyWatering()) {
            $lastRun = $zone->getLastRun();
            echo 'la zone est en cours d\'arrosage depuis le ' . $lastRun["local_date"] . ' à ' . $lastRun["local_start_time"]
                . ', fin prévu à ' . $lastRun["local_end_time"] . ', de source ' . $lastRun["source"] . "\n";
        }
        else {
            if (($lastRun = $zone->getLastRun()) != false)
            echo 'le dernier arrosage date du ' . $lastRun["local_date"] . ' à ' . $lastRun["local_start_time"]
                . ', s\'est terminé à ' . $lastRun["local_end_time"] . ', était de source ' . $lastRun["source"] . "\n";
        }
        if (($nextRun = $zone->getNextRun()) != false) {
            echo 'le prochain arrosage est prévu le ' . $nextRun["local_date"] . ' à ' . $nextRun["local_start_time"]
                . ', se terminera à ' . $nextRun["local_end_time"] . ', prévu de source ' . $nextRun["source"] . "\n";
        }
        else
            echo 'pas d\'arrosage prévu pour cette zone, vérifier les exclusions' . "\n";

        echo "\n";
    }
}
catch (Exception $ex) {
    echo 'une erreur s\'est produite : ' . $ex->getMessage();
}
?>