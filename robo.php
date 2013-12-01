<?php

require_once('include.php');

if ($pair = array_search("-pair", $argv)) {
    $pair = $argv[$pair + 1];
} else {
    $pair = "ltc_rur";
}

$trader = TraderLogic::getInstance();
$trader->setPair($pair);

if (array_search("-real", $argv)) {
    $trader->setVirtual(false);
}

$i=0;

echo "Start \n";
echo "MIN_AMOUNT: " . $trader->getOrderMinAmount() . "\n";
echo "Pair: {$pair} \n";

$trader->printStat();

while (1 == 1) {
    //Закупаем валюту
    $trader->ordersCreate();

    //Фиксим прибыль по открытым ордерам!
  //  $trader->ordersCloser();

    //Синхронизируем открытые ордера
 //   $trader->ordersUpdate();

    $i++;

    if ($i % 10 == 0) {
        Adviser::getInstance()->echoTrend();
    }

    if ($i>=100) {
        $i = 0;
        $trader->printStat();
    }

    //Отдыхаем
    sleep(2);
}



