<?php

/**
 * Класс хранит данные и методы о текущем вирутальном счете
 */

class MyBill
{
    private $bill;

    private $currency_from;

    private $currency_to;

    private $pair;

    private function __construct() {
        $this->bill = array(
            "rur" => 2800,
            "btc" => 0,
            "ltc" => 0,
        );
    }

    public static function getInstance() {
        static $bill = null;
        if (is_null($bill)) {
           $bill = new self();
        }

        return $bill;
    }

    public function setPair($pair) {
        $this->pair = $pair;
        $curr = explode("_", $this->pair);
        $this->currency_from = $curr[1];
        $this->currency_to = $curr[0];
    }

    public function getPair() {
        return $this->pair;
    }

    public function getCurrFrom() {
        return $this->currency_from;
    }

    public function  getCurrTo () {
        return $this->currency_to;
    }

    /**
     * Вернет кол-во свободных денег
     * @return int
     */
    public function myBill($from = false) {
        if (!$from) {

            return $this->bill;
        } elseif ($from == 'from') {
            return $this->bill[$this->currency_from];
        } elseif ($from == 'to') {
            return $this->bill[$this->currency_to];
        }
    }

    public function changeMyBillFrom($val) {
        $this->bill[$this->currency_from] += $val;
    }

    public function changeMyBillTo($val) {
        $this->bill[$this->currency_to] += $val;
    }

    /**
     * @param $amount
     * @param $rate
     * @param string $type
     * @return bool
     */
    public function createVirtualTransaction($amount, $rate, $type = "buy", $profit =0) {
        $date = date("m-d-Y H:i");
        $com = 1 - ConfigHelper::getInstance()->get('COMISSION');
        if ($type == "buy") {
            $sum = $amount * $rate;
            $this->changeMyBillFrom(-1 * $sum);
            $this->changeMyBillTo($amount * $com);

            echo "{$date}: Buy sum: {$sum} rate: {$rate} amount: {$amount} \n";
        } else {
            $this->changeMyBillTo(-1 * $amount);
            $this->changeMyBillFrom($amount * $rate * $com);

            echo "{$date}: Sell: rate: {$rate} amount: {$amount} profit: {$profit} RUR \n";
        }

        return true;
    }

    /**
     * @param $rate1
     * @param $rate2
     * @return float
     */
    public static function countProfit($rate1, $rate2) {
        $buy = 1 - ConfigHelper::getInstance()->get('COMISSION');
        $pull = ($buy * $rate2) * $buy;

        return ($pull - $rate1) / $rate1;
    }

    public static function countRealProfit($amount, $rate1, $rate2) {
        $com = 1 - ConfigHelper::getInstance()->get('COMISSION');
        $sum = $amount * $rate1;
        $amount *= $com;
        $pull = $amount * $rate2 * $com;

        return $pull - $sum;
    }

    /**
     * @return bool|array
     */
    public static function getInfo() {
        try {
            $getInfo = ApiHelper::getInstance()->apiQuery('getInfo');

            return $getInfo['return'];
        } catch (BTCeAPIException $e) {
            echo $e->getMessage();
            echo "getInfo \n";

            return false;
        }
    }
}