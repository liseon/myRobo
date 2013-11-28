<?php
require_once('include.php');

$API = new BTCeAPI(
        $SECRET['KEY'],
        $SECRET['SECRET']
);

// Example getInfo
try {
    // Perform the API Call
    $getInfo = $API->apiQuery('getInfo');
    // Print so we can see the output
    print_r($getInfo);
} catch(BTCeAPIException $e) {
    echo $e->getMessage();
}

// Example Public API JSON Request (Such as Fee / BTC_USD Tickers etc) - The result you get back is JSON RESTed to PHP
// Fee Call
$btc_usd = array();
$btc_usd['fee'] = $API->getPairFee('btc_usd');
// Ticker Call
$btc_usd['ticker'] = $API->getPairTicker('btc_usd');
// Trades Call
$btc_usd['trades'] = $API->getPairTrades('btc_usd');
// Depth Call
$btc_usd['depth'] = $API->getPairDepth('btc_usd');
// Show all information
print_r($btc_usd);