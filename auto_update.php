<?php
include('config.php');

function get_products() {
    $context = stream_context_create(
        array(
            'http' => array(
                'header' => 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9\r\n' .
                            'Accept-Encoding: identity\r\n' .
                            'Accept-Language: en-GB,en-US;q=0.9,en;q=0.8\r\n' .
                            'Cache-Control: max-age=0\r\n' .
                            'User-Agent: Argos Tools - https://aaronrosser.xyz/argos - aaron@aaronrosser.xyz\r\n'
            )
        )
    );
    $res = file_get_contents('https://www.argos.co.uk/product.xml', false, $context);
    $xml = simplexml_load_string($res);

    return $xml;
}

// wtf why
$json = json_encode($xml);
$array = json_decode($json,TRUE)['url'];

$catNums = array();

for($i = 0; $i < sizeof($array); $i++) {
    if (!is_array($array[$i]) || !array_key_exists('loc', $array[$i])) continue;

    $text = $array[$i]['loc'];

    $catNum = str_replace('https://www.argos.co.uk/product/', '', $text);

    if (!is_numeric($catNum)) continue;

    if (end($catNums) === $catNum) continue;

    array_push($catNums, $catNum);
}
unset($i);

$fourDigitArray = array();

for ($i = 0; $i < sizeof($catNums); $i++) {
    $fourDigitNum = substr($catNums[$i], 3, 4);

    if (!array_key_exists($fourDigitNum, $fourDigitArray) ||
        !is_array($fourDigitArray[$fourDigitNum])) {
        $fourDigitArray[$fourDigitNum] = array();
    }
    array_push($fourDigitArray[$fourDigitNum], intval($catNums[$i]));
}

ksort($fourDigitArray);

$fourDigitJsFile = 'fourDigitCatNums = ' . json_encode($fourDigitArray);
file_put_contents($project_root . 'auto_generated_4digitCatNums.js', $fourDigitJsFile);

$catNumsJsFile = 'products = [' . implode(',', $catNums) . ']; ' . 'let updatedTimestamp = ' .time(). ';';
file_put_contents($project_root . 'auto_generated_products.js', $catNumsJsFile);