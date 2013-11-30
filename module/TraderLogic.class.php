<?php
/**
 *  Основная логика трейдинга
 */

class TraderLogic extends Trader
{
    protected  function __construct() {
        parent::__construct();
    }

    /**
     * @return Trader
     */
    public static function getInstance() {
        static $Trader = null;
        if (is_null($Trader)) {
            $Trader = new self();
        }

        return $Trader;
    }

    /**
     * Попробуем сделать ордеры по текущему курсу.
     * Проверим условия допустимости ордера и получим сумму покупки
     * @return bool
     */
    public function ordersCreate() {
        $rateInfo = $this->API->getPairTicker($this->bill->getPair());
        $rateInfo = $rateInfo['ticker'];

        if (!($rateInfo['buy'] > 0 && $rateInfo['sell'] > 0)) {

            return false;
        }

        $sum = $this->isOrderEnabled($rateInfo['buy'], $rateInfo['sell']);
        if ($sum) {
            $this->addOrder($sum, $rateInfo['buy']);

            return true;
        }

    }

    /**
     *  Фиксируем прибыль
     */
    public function ordersCloser() {
        $rateInfo = $this->API->getPairTicker($this->bill->getPair());
        $rateInfo = $rateInfo['ticker'];
        $rate2 = $rateInfo['sell'];
        $rate1 = $rateInfo['buy'];
        if (!($rate1 > 0 && $rate2 > 0)) {

            return false;
        }
        $rate = ($rate1 + $rate2) / 2;

        $res = $this->getMyActiveOrders('opened');
        while ($row = $res->fetch_array()) {
            if (
                MyBill::countProfit($row['rate'], $rate2) >= $this->config->get('PROFIT_LVL')
                && Adviser::getInstance()->sellSignal($rate)
            ) {
                $this->closeOrder($row['id'], $row['amount'],  $rate2, $row['rate']);
            }
        }

        return true;
    }



    /**
     * Отменяет все устаревшие ордеры, чтобы не занимали свободные средства
     * Заодно синхронизируем все открытые ордеры
     * @return bool
     */
    public function ordersUpdate() {
        $orders = array('return' => array());
        $new = array();
        $close = array();

        try {
            $orders = $this->API->apiQuery('ActiveOrders');
        } catch(BTCeAPIException $e) {
        }

        $res = $this->getMyActiveOrders('new');
        while ($row = $res->fetch_array()) {
            $new[$row['order_id']] = $row;
        }

        $res = $this->getMyActiveOrders('opened', true);
        while ($row = $res->fetch_array()) {
            $close[$row['closer_id']] = $row;
        }

        foreach ($orders['return'] as $id => $row) {
            if (
                $row['pair'] == $this->bill->getPair()
                && !$row['status']
                && (
                    (time() - $this->config->get('MAX_TIME') > $row['timestamp_created'])
                    && $this->oneOrderCancel($id)
                )
            ) {
                echo "---Cancel: {$id} \n";
            } else {
                unset($new[$id]);
                unset($close[$id]);
            }
        }

        unset($new[0]);
        unset($close[0]);

        $this->updateNew($new);
        $this->updateOpened($close);

        return true;
    }
}