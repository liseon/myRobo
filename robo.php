<?php

require_once('include.php');

$trader = TraderLogic::getInstance();
$trader->setPair('ltc_rur');
$i=0;

echo $trader->getOrderMinAmount() . "\n";

while (1 == 1) {
    //Закупаем валюту
    $trader->ordersCreate();

    //Фиксим прибыль по открытым ордерам!
    $trader->ordersCloser();

    //Синхронизируем открытые ордера
    $trader->ordersUpdate();


    $i++;
    if ($i>=300) {
        $i = 0;
        $bill = $this->myBill();
        $date = date("m-d-Y H:i");
        echo "{$date}: rur: {$bill['rur']}  ltc: {$bill['ltc']} \n";
    }

    //Отдыхаем
    sleep(2);
}



