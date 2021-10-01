<?php
include('config.php');

$xml = simplexml_load_string(file_get_contents('https://www.argos.co.uk/product.xml'));

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