<?php

$Filename = 'cache/'.date('Y-m-d').'.json';

if(!(file_exists($Filename))){
  //Need to fetch current quote.
  include_once('Config.php');
  $URL = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest?CMC_PRO_API_KEY='.$CoinMarketCapKey;
  $Data = file_get_contents($URL);
  file_put_contents($Filename,$Data);
}

$CreateMissing = false;
if(
  isset($_GET['action']) &&
  (!(isset($_GET['key'])))
){
  //Prompt for missing API key
  echo '<!DOCTYPE html>';
  echo '<p>Authentication Required. Please enter API Key:</p>';
  echo '<form action=./? method="get">';
  echo '  <input type="hidden" name="action" value="'.$_GET['action'].'">';
  echo '  <input type="password" name="key" id="key">';
  echo '  <input type="submit">';
  echo '</form>';
  echo '<script>document.getElementById("key").focus();</script>';
  exit;
}elseif(
  isset($_GET['key']) &&
  isset($_GET['action'])
){
  //Check API key
  include_once('Config.php');
  if($_GET['key'] != $LocalKey){
    die('Invalid Key.');
  }
  //User is authenticated for secure API requests
  switch($_GET['action']){
    case 'createMissing':
      $CreateMissing = true;
      break;
  }
}

$Coins = array();
$Missing = false;

//Check if we have the last 14 days of data
for ($i = 0; $i <= 14; $i++) {
  $Date = time() - (60*60*24*$i)  ;
  $Filename = 'cache/'.date('Y-m-d',$Date).'.json';
  if(file_exists($Filename)){
    $Data = file_get_contents($Filename);
    $JSON = json_decode($Data,true);
    foreach($JSON['data'] as $Coin){
      $Name = $Coin['name'];
      $Symbol = $Coin['symbol'];
      if(
        (!(isset($Coins[$Symbol]))) ||
        (!(is_array($Coins[$Symbol])))
      ){
        $Coins[$Symbol] = array(
          'Name' => $Name, 
          'Timeseries' => array(
            date('Y-m-d',$Date) => $Coin['quote']['USD']['price']
          )
        );
      }
      
    }
  }else{
    if($CreateMissing == true){
      echo '<p>Creating missing file: '.date('Y-m-d',$Date).'</p>';
      var_dump($Coins);
    }else{
      echo '<p>Data Missing For Date: '.date('Y-m-d',$Date).'</p>';
      $Missing = true;
    }
  }
}

if($Missing == true){
  echo '<p><a href="./?action=createMissing">Click here</a> to create empty files for missing dates.</p>';
}

echo '<pre>';
var_dump($Coins);
