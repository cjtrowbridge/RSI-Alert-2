<?php

$Filename = 'cache/'.date('Y-m-d').'.json';

if(!(file_exists($Filename))){
  //Need to fetch current quote.
  include('Config.php');
  $URL = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest?CMC_PRO_API_KEY='.$Key;
  $Data = file_get_contents($URL);
  file_put_contents($Filename,$Data);
}

$Coins = array();

//Check if we have the last 14 days of data
for ($i = 0; $i <= 14; $i++) {
  $Date = time() - (60*60*24*$i)  ;
  $Filename = 'cache/'.date('Y-m-d',$Date).'.json';
  if(file_exists($Filename)){
    $Data = file_get_contents($Filename);
    $JSON = json_decode($Data);
    foreach($JSON['data'] as $Coin){
      $Name = $Coin['name'];
      if(
        (!(isset($Coins[$Name]))) ||
        (!(is_array($Coins[$Name])))
      ){
        $Coins[$Name] = array();
      }
      
    }
  }else{
    die('Data Missing For Date: '.date('Y-m-d',$Date));
  }
}

var_dump($Coins);
