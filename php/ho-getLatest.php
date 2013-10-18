<?php
require 'arrToCSV.php';
set_time_limit (0);
$conf =  json_decode(file_get_contents('hasoffers.conf'),true);
if(isset($_GET))
{

  $ids = explode(',', $_GET['g']);
  $base = 'https://api.hasoffers.com/Api?'; 
    $params = array(
          'Format' => 'json'
          ,'Target' => 'Report'
          ,'Method' => 'getReferrals'
          ,'Service' => 'HasOffers'
          ,'Version' => 2
          ,'NetworkId' => $conf['NetworkId']
          ,'NetworkToken' => $conf['NetworkToken']
          ,'fields' => array('Stat.url', 'Stat.affiliate_id', 'Stat.offer_id',  'Offer.name', 'Stat.clicks', 'Stat.count', 'Stat.date')
          ,'groups' => array('Stat.url', 'Stat.affiliate_id', 'Stat.offer_id')
          ,'filters' => array('Stat.date' => array('conditional' => 'BETWEEN', 'values' => $ids)
            , 'Stat.url' => array(
        'conditional' => 'LIKE'
        ,'values' => array('%v=%')
      ))
          ,'sort' => array('Stat.clicks' => 'DESC')
          ,'limit' => 50000
    );
 
  //
  $url = $base . http_build_query($params);
   
  $result = file_get_contents($url);
  $resultarray =  json_decode($result);
    // echo '<pre>' . print_r($resultarray, true) . '</pre>';

  // echo 'get:' . $_GET['g'];
  // echo 'url:' . $url;

  $queryId = array();
  $vidstats = array();
  $clicks = 0; $conversions; $oldclicks = 0; $oldconversions = 0; $ctr = 0;
  $searchFinalResponse = array("count" => $resultarray->response->data->count);
  foreach ( $resultarray->response->data->data as $stat){
    $url = parse_yturl($stat->Stat->url);
    if($url&&!in_array($url, $queryId)){
      array_push($queryId, $url);
      array_push($vidstats, array('url'=>$url, 'clicks'=> 0, 'Sources' => array("Names" => array(), 'clicks' => array())));
      // var_dump($vidstats);
    }
  }
  
  $duplicate = $vidstats;
  foreach ( $resultarray->response->data->data as $stat){
    $url = parse_yturl($stat->Stat->url);
    $offers = array();
    if($url){
      foreach ($queryId as $key => $value) {
        if($queryId[$key]==$url){
           $vidstats[$key]['clicks'] += $stat->Stat->clicks;
           if(!in_array($stat->Offer->name, $vidstats[$key]['Sources']['Names'])){

             array_push($vidstats[$key]['Sources']['Names'], $stat->Offer->name);
             array_push($vidstats[$key]['Sources']['clicks'], $stat->Stat->clicks);
           }
           else {
              $oldkey = array_search($stat->Offer->name, $vidstats[$key]['Sources']['Names']);
              $vidstats[$key]['Sources']['clicks'][$oldkey] += $stat->Stat->clicks;
           }
        }
      }
    }
  }
  $temporary = array();
  $searchFinalResponse['items'] = array();
  
  $loops = sizeof($queryId)/50;
  $start = 0;
  $limit = 50;
  
  for($i=0;$i<$loops;$i++)
  {
    $st = $i * 50;
    $trimmed = array_slice($queryId, $st,$limit);
    $temporary[$i] = queryVideos($trimmed);
    // array_push($searchFinalResponse['items'],queryVideos($trimmed));
  }

  array_push($searchFinalResponse['items'],$temporary[0]);
  for($i=1;$i<sizeof($temporary);$i++)
  {
    foreach($temporary[$i] as $eachData)
    {
      array_push($searchFinalResponse['items'][0],$eachData);
    }
  }

   $ctr = 0;
  $response_array = array();
  foreach ( $searchFinalResponse['items'][0] as $vid){
    // var_dump($vid);
   foreach ($vidstats as $stat) {
    // var_dump($stat);

      if($vid['id']==$stat['url']){
         // array_push($vid, $stat);
        $vid['stat'] = ($stat);
         // array_push($vid['stat'], $stat);
        // var_dump($stat);
         break;
      }     
   }
    array_push($response_array, $vid);
  }
   // echo '<pre>'  . sizeof($response_array) . '</pre>';
  $arr = array();
  foreach ($response_array as $k => $v) {
    $input = date('d/m/Y', strtotime($response_array[$k]['snippet']['publishedAt']));
    if(isBetween($input)==false){
      unset($response_array[$k]);
    }
    else {
      array_push($arr, $response_array[$k]);
    }
  }

     // echo '<hr>AFTER<pre>' . sizeof($response_array) . '</pre>';
  // echo 'count' . count($searchFinalResponse['items'][0]) ."<BR>";
  // echo json_encode($searchFinalResponse);
  // echo json_encode($response_array);

  // $id = new MongoId($_GET['gamemongoid']);

  // $m = new MongoClient();
  // $games = $m->games->hasoffers;

  // // var_dump($games->findOne(array("_id"=>$id)));

  // $document = array(
  //           '$set' => array(
  //                       "videos" => $response_array
  //               )
  //           );

  // // echo $id;
  // $res = $games->update(array("_id"=>$id),$document);

   // echo '<pre>' . print_r($response_array, true) . '</pre>';
   echo json_encode($arr, true);


  // echo '<pre>' . print_r($searchFinalResponse, true) . '</pre>';
}
function isBetween($date){
  $paymentDate = DateTime::createFromFormat('d/m/Y', $date);
  $contractDateBegin = DateTime::createFromFormat('d/m/Y',date('d/m/Y',strtotime("-5 days")) );
  $contractDateEnd = DateTime::createFromFormat('d/m/Y', date('d/m/Y'));

  if ($paymentDate >= $contractDateBegin && $paymentDate <= $contractDateEnd)
  {
    return true;
  }
  else{
    return false;
  }
}

function parse_yturl($url) {
    $pattern = '#^(?:https?://)?(?:www\.|m\.)?(?:youtu\.be/|youtube\.com(?:/embed/|/v/|/watch\?v=|/watch\?.+&v=))([\w-]{11})(?:.+)?$#x';
    // $pattern = '#^(?:https?://)?(?:www\.)?(?:youtu\.be/|youtube\.com(?:/embed/|/v/|/watch\?v=|/watch\?.+&v=))([\w-]{11})(?:.+)?$#x';
    preg_match($pattern, $url, $matches);
    return (isset($matches[1])) ? $matches[1] : false;
  }
function queryVideos($trimmed) {
    try{
        require 'include/instance.php';
        $csv_ids = generateCsv($trimmed);
        // echo print_r ($csv_ids);
        $searchResponse = $youtube->videos->listVideos('id,snippet,statistics',
          array('id' => $csv_ids,
            'fields' => 'items(id,snippet(title,publishedAt,channelId,channelTitle,thumbnails(default)),statistics(viewCount))'
        ));
      
        return $searchResponse['items'];
    } 
    catch (Google_ServiceException $e) {
     echo htmlspecialchars($e->getMessage());
    } 
    catch (Google_Exception $e) {
     echo htmlspecialchars($e->getMessage());
    }
}
?>