<?php


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require "../vendor/autoload.php";
require "../.config.php";

use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;

$auth = Authenticate::basic('neo4j', 'JOJOcratat4');

$client = ClientBuilder::create()
    ->withDriver('bolt', 'bolt://neo4j:'.PASSWORD.'@localhost')
    ->withDriver('http', 'http://localhost:7474', $auth)
    ->withDefaultDriver('http')
    ->build();

$entries = json_decode($_POST['entry'], true);

foreach($entries as $entry){

    $loc = $entry['loc'];
    $wifi = $entry['wifi'];


    $loc['lon'] = round($loc['lon'], 5);
    $loc['lat'] = round($loc['lat'], 5);

    $exsistingLoc = $client->run('MATCH (l:Location {lon: '.$loc['lon'].', lat: '.$loc['lat'].'}) RETURN l');

    if(sizeof($exsistingLoc)==0){
        $exsistingLoc = $client->run('CREATE (l:Location {lon: '.$loc['lon'].', lat: '.$loc['lat'].', alt: '.$loc['alt'].', address: \''.$loc['address'].'\'}) RETURN l');
    }

    $exsistingBSSID = $client->run('MATCH (b:BSSID {mac: \''.$wifi['bssid'].'\'}) RETURN b');

    if(sizeof($exsistingBSSID)==0){
        $exsistingSSID = $client->run('MATCH (s:SSID {name: \''.$wifi['ssid'].'\'}) RETURN s');

        if(sizeof($exsistingSSID)==0){
            $exsistingSSID = $client->run('CREATE (s:SSID {name: \''.$wifi['ssid'].'\'}) RETURN s');
        }

        $exsistingBSSID = $client->run('CREATE (b:BSSID {mac: \''.$wifi['bssid'].'\'}) RETURN b');

        $client->run('
        MATCH (s:SSID), (b:BSSID)
        WHERE s.name = \''.$wifi['ssid'].'\' AND b.mac = \''.$wifi['bssid'].'\'
        CREATE (s)-[:DiffusedBy]->(b)
        CREATE (b)-[:Named]->(s)
        ');
    }

    $client->run('
        MATCH (l:Location), (b:BSSID)
        WHERE l.lon = '.$loc['lon'].' AND l.lat = '.$loc['lat'].' AND b.mac = \''.$wifi['bssid'].'\'
        CREATE (b)-[:Present {level: '.$wifi['level'].', frequency: '.$wifi['freq'].', date: \'' . $loc['datetime'] . '\'}]->(l)
        CREATE (l)-[:receives {level: '.$wifi['level'].', frequency: '.$wifi['freq'].', date: \'' . $loc['datetime'] . '\'}]->(b)
        ');

}