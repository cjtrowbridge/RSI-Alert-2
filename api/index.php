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
  (!(
    isset($_GET['key']) ||
    isset($_POST['key'])
  ))
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
  if(!(
    ($_GET['key'] == $LocalKey) ||
    ($_POST['key'] == $LocalKey)
  )){
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
  (
    isset($_GET['key']) ||
    isset($_POST['key'])
  ) &&
  isset($_GET['action'])
){
  //Check API key
  include_once('Config.php');
  if(
    (!(
      $_GET['key'] == $LocalKey ||
      $_POST['key'] == $LocalKey
    ))
  ){
    die('Invalid Key.');
  }
  //User is authenticated for secure API requests
  switch($_GET['action']){
    case 'edit':
      if(isset($_POST['symbol'])){
        HandleEditPost($_REQUEST['symbol'],$Coins);
        die('Handle enter missing post.');
      }else{
        Edit($_REQUEST['symbol'],$Coins);
      }
      break;
  }
}


//Create Gain/Loss Table for each coin
$RS = array();
foreach($Coins as $Coin){
  $First = true;
  $Symbol = $Coin['Symbol'];
  $Missing = array();
  echo '<h2>Generating RS-14 Table For '.$Coin['Name'].'</h2>';
  $RS[$Symbol]=array(
    'summary' => array(),
    'data' => array()
  );
  $Close = '';
  $GainSum = 0;
  $GainCount = 0;
  $LossSum = 0;
  $LossCount = 0;
  //Skip today
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
    
    if($First){
      $First = false;
      $Close = $Open;
      continue;
    }
    
    $Change = $Close - $Open;
    if($Change > 0){
      $ThisGain = $Change;
      $ThisLoss = 0;
      $GainSum += $Change;
      $GainCount++;
    }else{
      $ThisGain = 0;
      $ThisLoss = $Change;
      $LossSum += (0-$Change);
      $LossCount++;
    }
    
    //Put the values into the table
    $RS[$Symbol]['data'][date('Y-m-d',$Date)]=array(
      'Open'  => $Open,
      'Close' => $Close,
      'Gain'  => $ThisGain,
      'Loss'  => $ThisLoss
    );
    //Carry the open price over to the previous close price
    $Close = $Open;
    //Done with this day for this coin
  } 
  
  echo '<p><a href="./?action=edit&symbol='.$Symbol.'">Click here</a> to edit data for '.$Symbol.'.</p>';
  
  $RS[$Symbol]['summary'] = array(
    'Gain Sum'          => $GainSum,
    'Gain Count'        => $GainCount,
    'Average Gain'      => $GainSum / $GainCount,
    'Loss Sum'          => $LossSum,
    'Loss Count'        => $LossCount,
    'Average Loss'      => $LossSum / $LossCount,
    'Relative Strength' => 1 + (( $GainSum / $GainCount ) / ($LossSum / $LossCount)),
    'RSI-14'            => 100 - (100/(1 + (( $GainSum / $GainCount ) / ($LossSum / $LossCount))))
  );
  pd($RS[$Symbol]);
  
  //Done with this coin
}


function Edit($Symbol,$Coins){
  echo $First = true;
  echo '<h2>Editing '.$Symbol.' Data...</h2>'."\n";
  echo '<form action="./?action=edit" method="post">'."\n";
  echo '<input type="hidden" name="symbol" value="'.$Symbol.'">'."\n";
  echo '<input type="hidden" name="key" value="'.$_GET['key'].'">'."\n";
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
            if($First){
              $Frist = false;
              echo '    <td>Open Price: <input id="first" type="text" name="'.$Symbol.date('Ymd',$Date).'" value="'.$Open.'"></td>'."\n";
            }else{
              echo '    <td>Open Price: <input type="text" name="'.$Symbol.date('Ymd',$Date).'" value="'.$Open.'"></td>'."\n";
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


function HandleEditPost($Symbol,$Coins){
 
  echo '<h2>Updating Cache</h2>';
  echo '<h3>Input Recieved;</h3>';
  pd($_REQUEST);
  echo '<h3>Working;</h3>';
  $Coin = $Coins[$Symbol];
  
    for($i = 0; $i <= 14; $i++){
      $Date = time() - (60*60*24*$i);
      $Open = '';

      //Try to find the open price for this symbol and date
      $Filename = 'cache/'.date('Y-m-d',$Date).'.json';
      if(file_exists($Filename)){
        $Data = file_get_contents($Filename);
        $JSON = json_decode($Data,true);
        $Found = false;
        foreach($JSON['data'] as $Key => $Coin){
          if($Coin['symbol'] == $Symbol){
            $Found = true;
            
            $PostKey = $Symbol.date('Ymd',$Date);
            if(
              isset($_POST[$PostKey]) &&
              (floatval($_POST[$PostKey])>0)
            ){
              
              //Okay let's update the file with this new value...
              $NewValue = $_POST[$PostKey];
              $JSON['data'][$Key]['quote']['USD']['price'] = $NewValue;
              $Updated = json_encode($JSON,JSON_PRETTY_PRINT);
              $Filename = 'cache/'.date('Y-m-d',$Date).'.json';
              $Result = file_put_contents($Filename,$Updated);
              echo '<p>File "'.$Filename.'" updated for coin '.$Symbol.'. ('.$Key.')('.var_export($Result,true).')</p>';
              
            }else{
              echo '<p>No update value submitted for date '.date('Y-m-d',$Date).', skipping.</p>';
            }
          }
        }
        if($Found == false){
          echo "<p>Can't find matching coin for '.$Symbol.' in '.$Filename.'</p>";
        }
      }else{
        echo '<p>Date file missing: '.$Filename.'. Try <a href="./?action=createMissing">creating missing files</a>.</p>';
      }
    }
  echo '<p>Done! <a href="./">Back To Home</a></p>';
  exit;
}
