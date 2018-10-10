<?php
/**
 * Skraper artikkler fra TU.no/artikler og dumper de til json
 */

include('simple_html_dom.php'); // for å traverse html dom

ini_set('default_socket_timeout', 5);

function getDataFromLink($target) {
    $html = file_get_html($target, false, null, 0 );
    
    // forfatter
    $authors = $html->find('a[rel=author]', 0);
    if($authors != null) {
        $authors = $authors->title;
    } else {
        $authors = $html->find('.authors li', 0);
        if ($authors != null) {
            $authors = $authors->innertext;
        }
    }
    
    if($authors == null) {
        $authors = "";
    }
    
    // tittel
    $headline = $html->find('.headline', 0);
    if($headline != null) {
        $headline = $headline->plaintext;
    } else {
        $headline = "";
    }
    

    // toppbilde
    $topImage = $html->find('.article-top-image img', 0);
    if ($topImage != null) {
        $topImage = $topImage->src;
    } else {
        $topImage = $html->find('.loaded-image', 0);
        if ($topImage != null) {
            $topImage = $topImage->getAttribute('data-src');
        }
    }
    if ($topImage == null) {
        $topImage = "";
    }

    // publiserings dato
    $date = $html->find('.published time', 0);
    if($date != null) {
        $date = $date->datetime;
    } else {
        $date = "";
    }
    
    
    $html->clear();
    unset($html);
    
    return ["tittel"=>$headline, "toppbilde"=>$topImage, "forfatter"=>trim($authors), "url"=>$target, "dato"=>$date];
}


function scrape($query) {
    $next = true;
    $data = [];
    $url = 'https://www.tu.no/artikler';
    $link = 'https://www.tu.no/artikler?q=' . $query;
    $html = file_get_html($link, false, null, 0 );
    
    while ($next){
        $html = file_get_html($link, false, null, 0 );
        foreach($html->find('tbody tr') as $link) {
            $time =  $link->find('time', 0);
            if ($time != null) {
                $year = substr($time->innertext, -4);
                if ($year == "2012") {
                    array_push($data, getDataFromLink('https://www.tu.no' . $link->find('.headline a', 0)->href));
                }
            }
        }
        if ($html->find('[rel=next]')) {
            $link = $url . $html->find('[rel=next]', 0)->href;
        } else {
            $next = false;
        }
    }
    $html->clear();
    unset($html);
    
    return $data;
}


// for sortering av multiarray
function arrayMultisortByValue($arrayToSort, $nameOfValue) {
    $ValueContainer = [];
    foreach ($arrayToSort as $arrayEntry) {
        $ValueContainer[] = $arrayEntry[$nameOfValue];
    }
    array_multisort($ValueContainer, $arrayToSort);
    return $arrayToSort;
}

// pretty print_r, for finere formatering i html
function pprint_r($data) {
    echo '<pre>';
    print_r($data);
    echo  '</pre>';
}

// returnerer array som er gruppert på forfatter
function groupByAuthor($data) {
    $arr = [];
    foreach($data as $sd) {
        $arr[$sd['forfatter']][] = ['tittel'=>$sd['tittel'], 'toppbilde'=>$sd['toppbilde'], 'forfatter'=>$sd['forfatter'], 'url'=>$sd['url'], 'dato'=>$sd['dato']];
    }
    return $arr;
}
$startTime = microtime(true);

$data = scrape('asfalt');

$sorted = arrayMultisortByValue($data, 'dato'); // sorter først på dato

$grouped = groupByAuthor($data); // så grupperer på forfatter

// pprint_r($grouped);

$json = json_encode($grouped, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);

file_put_contents('exampleOutput/scrapeResult.json', $json);
echo "Ferdig etter: ". (microtime(true) - $startTime) ." sekunder"; 
?>
