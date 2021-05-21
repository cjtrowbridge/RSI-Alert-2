<?php

function pd($Arr){
  echo '<pre>';
  var_dump($Arr);
  echo '</pre>';
}

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
  foreach($_GET as $Key => $Value){
    echo '  <input type="hidden" name="'.$Key.'" value="'.$Value.'">';
  }
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
      $Slug = $Coin['slug'];
      if(
        (!(isset($Coins[$Symbol]))) ||
        (!(is_array($Coins[$Symbol])))
      ){
        $Coins[$Symbol] = array(
          'Name' => $Name, 
          'Slug' => $Slug, 
          'Symbol' => $Symbol,
          'Timeseries' => array(
            date('Y-m-d',$Date) => $Coin['quote']['USD']['price']
          )
        );
      }
      
    }
  }else{
    if($CreateMissing == true){
      echo '<p>Creating missing file: '.date('Y-m-d',$Date).'</p>';
      $Data = array(
        'status' => array(
          'timestamp' => date('Y-m-d 00:00:00',$Date)
        ),
        'data' => array()
      );
      foreach($Coins as $Symbol => $Coin){
        $New = array(
          'name'   => $Coin['Name'],
          'symbol' => $Symbol,
          'slug'   => $Coin['Slug'],
          'quote' => array(
            'USD' => array(
              'price' => ''
            )
          )
        );
        $Data['data'][]=$New;
      }
      $JSON = json_encode($Data,JSON_PRETTY_PRINT);
      $Filename = 'cache/'.date('Y-m-d',$Date).'.json';
      file_put_contents($Filename,$JSON);
    }else{
      echo '<p>Data Missing For Date: '.date('Y-m-d',$Date).'</p>';
      $Missing = true;
    }
  }
}

if($Missing == true){
  echo '<p><a href="./?action=createMissing">Click here</a> to create empty files for missing dates.</p>';
}


//Offer post-startup api calls
if(
  isset($_GET['key']) &&
  isset($_GET['action'])
){
  //Check API key
  include_once('Config.php');
  if($_GET['key'] != $LocalKey){
    die('Invalid Key.');
  }
  //User is authenticated for secure API requests
  
  if(isset($_GET['enterMissing'])){
    $_GET['action'] = 'enterMissing';
  }
  
  switch($_GET['action']){
    case 'enterMissing':
      if(isset($_POST['symbol'])){
        
        die('Handle enter missing post.');
      }else{
        EnterMissing($_REQUEST['symbol'],$Coins);
      }
      break;
  }
}


//Create Gain/Loss Table for each coin
$RS = array();
foreach($Coins as $Coin){
  $Symbol = $Coin['Symbol'];
  $Missing = array();
  echo '<h2>Generating RS-14 Table For '.$Coin['Name'].'</h2>';
  $RS[$Symbol]=array();
  $Close = '';
  for($i = 0; $i <= 14; $i++){
    $Date = time() - (60*60*24*$i);
    $Open = '';
    
    //Try to find the open price for this symbol and date
    $Filename = 'cache/'.date('Y-m-d',$Date).'.json';
    if(file_exists($Filename)){
      $Data = file_get_contents($Filename);
      $JSON = json_decode($Data,true);
      foreach($JSON['data'] as $Coin){
        if($Coin['symbol'] == $Symbol){
          $Open = $Coin['quote']['USD']['price'];
        }
      }
    }
   
    //Put the values into the table
    $RS[$Symbol][date('Y-m-d',$Date)]=array('Open' => $Open,'Close' => $Close);
    //Carry the open price over to the previous close price
    $Close = $Open;
    //Done with this day for this coin
  } 
  //If we couldn't some data then prompt the user to input it.
  if(count($Missing)==0){
    echo '<p><a href="./?action=enterMissing&symbol='.$Symbol.'">Click here</a> to enter missing data for '.$Symbol.'.</p>';
  }
  pd($RS[$Symbol]);
  //Done with this coin
}


function EnterMissing($Symbol,$Coins){
  echo $First = true;
  echo '<h2>Checking For Missing '.$Symbol.' Data...</h2>'."\n";
  echo '<form action="./?action=enterMissing" method="post">'."\n";
  echo '<input type="hidden" name="symbol" value="'.$Symbol.'">'."\n";
  echo '<input type="key" name="key" value="'.$_GET['key'].'">'."\n";
  echo '<table>'."\n";
  
  $Coin = $Coins[$Symbol];
  
  //foreach($Coins as $Coin){
    for($i = 0; $i <= 14; $i++){
      $Date = time() - (60*60*24*$i);
      $Open = '';

      //Try to find the open price for this symbol and date
      $Filename = 'cache/'.date('Y-m-d',$Date).'.json';
      if(file_exists($Filename)){
        $Data = file_get_contents($Filename);
        $JSON = json_decode($Data,true);
        $Found = false;
        foreach($JSON['data'] as $Coin){
          if($Coin['symbol'] == $Symbol){
            $Found = true;
            $Open = $Coin['quote']['USD']['price'];
            
            echo '  <tr>'."\n";
            echo '    <td>Date: '.date('Y-m-d',$Date).'</td>'."\n";
            if($Open==''){
              //We need to get this value
              if($First){
                $Frist = false;
                echo '    <td>Open Price: <input id="first" type="text" name="'.$Symbol.date('Ymd',$Date).'"></td>'."\n";
              }else{
                echo '    <td>Open Price: <input type="text" name="'.$Symbol.date('Ymd',$Date).'"></td>'."\n";
              }
            }else{
              //We have this value already
              echo '    <td>Open Price: '.$Open.'</td>'."\n";
            }
            echo '  </tr>'."\n";
            
          }
        }
        
        //Check to make sure we found it
        if($Found == false){
          echo '<p>'.$Symbol.' not found in '.$Filename.'.</p>';
        }
        
      }else{
        echo '<p>Date file missing: '.$Filename.'. Try <a href="./?action=createMissing">creating missing files</a>.</p>';
      }
    }
  //}
  echo '</table>'."\n";
  echo '<input type="submit">'."\n";
  echo '</form>';
  echo '<script>document.getElementById("first").focus();</script>';
  exit;
}
