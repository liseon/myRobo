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
        $rateInfo = $this->API->getPairTicker($this->pair);
        $rateInfo = $rateInfo['ticker'];

        $sum = $this->isOrderEnabled($rateInfo['buy'], $rateInfo['avg']);
        if ($sum) {
            $this->addOrder($sum, $rateInfo['buy']);

            return true;
        }

    }

    /**
     *  Фиксируем прибыль
     */
    public function ordersCloser() {
        $rateInfo = $this->API->getPairTicker($this->pair);
        $rateInfo = $rateInfo['ticker'];
        $rate2 = $rateInfo['sell'];

        $res = $this->getMyActiveOrders('opened');
        foreach ($res->fetch_all() as $row) {
            if ($this->countProfit($row['rate'], $rate2) >= $this->config['PROFIT_LVL']) {
                $this->closeOrder($row['order_id'], $row['amount'],  $rate2);
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
        foreach ($res->fetch_all() as $row) {
            $new[$row['order_id']] = $row;
        }

        $res = $this->getMyActiveOrders('opened', true);
        foreach ($res->fetch_all() as $row) {
            $close[$row['closer_id']] = $row;
        }

        foreach ($orders['return'] as $id => $row) {
            if (
                $row['pair'] == $this->pair
                && !$row['status']
                && ((time() - $this->config['MAX_TIME']) > $row['timestamp_created'])
                && $this->oneOrderCancel($id)
            ) {
                echo "---Cancel: {$id} \n";
            } else {
                unset($new[$id]);
                unset($close[$id]);
            }
        }

        $this->updateNew($new);
        $this->updateOpened($close);

        return true;
    }
}