<?php
set_time_limit(10);

function formatCareName($careName) {
    $matches = array();
    preg_match('/([0-9]+)(yrs )/', $careName, $matches);
    $years = '';

    if (count($matches) > 0) {
        $years = 'Upto ' . $matches[0];
    }

    if (strpos($careName, 'Furniture Care') !== false) {
        return $years . 'Furniture Care';
    }
    else if (strpos($careName, 'Breakdown Care') !== false) {
        return $years . 'Breakdown Care';
    }
    else if (strpos($careName, 'Replacement Care') !== false) {
        return $years . 'Replacement Care';
    }
    return $careName;
}

function fetchAndProcessUrls(array $catNums, callable $f) {

    $multi = curl_multi_init();
    $reqs  = [];

    foreach ($catNums as $catNum) {
        $url = "https://www.argos.co.uk/product/$catNum";
        $req = curl_init();
        curl_setopt($req, CURLOPT_URL, $url);
        curl_setopt($req, CURLOPT_HEADER, 0);
        curl_setopt($req, CURLOPT_RETURNTRANSFER, 1);
        curl_multi_add_handle($multi, $req);
        $reqs[] = $req;
    }

    // While we're still active, execute curl
    $active = null;

    // Execute the handles
    do {
        $status = curl_multi_exec($multi, $active);
        if ($active) {
            // Wait a short time for more activity
            curl_multi_select($multi);
        }
    } while ($active && $status == CURLM_OK);

    // Close the handles
    foreach ($reqs as $pos => $req) {
        $f(curl_multi_getcontent($req), $catNums[$pos]);
        curl_multi_remove_handle($multi, $req);
    }
    curl_multi_close($multi);
}

if (file_get_contents('php://input') === '') {
    http_response_code(400);
    die();
}

$catNumArr = json_decode(file_get_contents('php://input'), true);

if (!is_array($catNumArr)) {
    http_response_code(400);
    die();
}

$catNums = array();
foreach($catNumArr as $catNum) {
    if (!is_int($catNum) || $catNum < 1000000 || $catNum > 9999999) {
        http_response_code(400);
        die();
    }

    array_push($catNums, $catNum);
}

$respArr = array();
fetchAndProcessUrls($catNums, function($html, $catNum) { 
    global $respArr;

    if ($html === false) {
        return;
    }
    
    libxml_use_internal_errors(true);
    $doc = new DOMDocument(); 
    $doc->loadHTML($html);
    
    //Title
    $titles = $doc->getElementsByTagName('title');
    if ($titles->length === 0) {
        return;
    }
    
    $title = $titles[0]->textContent;
    $title = str_replace('Buy ', '', $title);
    $title = trim(explode('|', $title)[0]);

    $finder = new DomXPath($doc);
    //Price
    $prices = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), 'product-price-primary')]");
    $price = '';
    if ($prices->length > 0) {
        $price = str_replace('*', '', $prices[0]->textContent);
        
    }
    $oldPrices = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), 'price-was')]");
    $oldPrice = '';
    if ($oldPrices->length > 0) {
        $oldPrice = str_replace('Was ', '', $oldPrices[0]->textContent);
    }

    //Care
    $careNames = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), 'product-care__name')]");
    $carePrices = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), 'product-care__price')]");
    $cares = array();
    if ($careNames->length > 0 && $carePrices->length > 0 && $careNames->length === $carePrices->length) {
        for ($i = 0; $i < $careNames->length; $i++) {
            array_push($cares, array(
                'price' => str_replace('+', '', $carePrices[$i]->textContent),
                'name' => formatCareName($careNames[$i]->textContent)
            ));
        }
    }
    
    array_push($respArr, array(
        'catNum' => $catNum,
        'title' => $title,
        'price' => $price,
        'oldPrice' => $oldPrice,
        'cares' => $cares
    ));
});

header('Content-Type: application/json');
echo json_encode($respArr, JSON_UNESCAPED_UNICODE);