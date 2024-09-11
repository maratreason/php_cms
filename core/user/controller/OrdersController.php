<?php

namespace core\user\controller;

use core\base\model\UserModel;
use core\user\helpers\ValidationHelper;

class OrdersController extends BaseUser
{
    use ValidationHelper;

    protected $delivery = [];
    protected $payments = [];

    protected function inputData()
    {
        parent::inputData();

        if ($this->isPost()) {
            $this->delivery = $this->model->get('delivery');
            $this->payments = $this->model->get('payments');

            $this->order();
        }
    }

    protected function order()
    {
        if (empty($this->cart['goods']) || empty($_POST)) {
            $this->sendError('Отсутствуют данные для оформления заказа');
        }

        $validation = [
            'name' => [
                'translate' => 'Ваше имя',
                'methods' => ['emptyField']
            ],
            'phone' => [
                'translate' => 'Телефон',
                'methods' => ['emptyField', 'phoneField', 'numericField']
            ],
            'email' => [
                'translate' => 'E-mail',
                'methods' => ['emptyField', 'emailField']
            ],
            'delivery_id' => [
                'translate' => 'Способ доставки',
                'methods' => ['emptyField', 'numericField']
            ],
            'payments_id' => [
                'translate' => 'Способ оплаты',
                'methods' => ['emptyField', 'numericField']
            ]
        ];
        // Заказы
        $order = [];
        // Посетители
        $visitor = [];
        // Колонки заказа
        $columnsOrders = $this->model->showColumns('orders');
        // Колонки посетителей
        $columnsVisitors = $this->model->showColumns('visitors');

        foreach ($_POST as $key => $item) {
            if (!empty($validation[$key]['methods'])) {
                foreach ($validation[$key]['methods'] as $method) {
                    $_POST[$key] = $item = $this->$method($item, $validation[$key]['translate'] ?? $key);
                }
            }

            if (!empty($columnsOrders[$key])) {
                $order[$key] = $item;
            }

            if (!empty($columnsVisitors[$key])) {
                $visitor[$key] = $item;
            }
        }

        if (empty($visitor['email']) && empty($visitor['phone'])) {
            $this->sendError('Отсутствуют данные пользователя для оформления заказа');
        }

        $visitorsWhere = $visitorsCondition = [];

        if (!empty($visitor['email']) && !empty($visitor['phone'])) {
            $visitorsWhere = [
                'email' => $visitor['email'],
                'phone' => $visitor['phone'],
            ];

            $visitorsCondition = ['OR'];
        } else {
            $visitorsKey = !empty($visitor['email']) ? 'email' : 'phone';
            $visitorsWhere[$visitorsKey] = $visitor[$key];
        }

        $resVisitor = $this->model->get('visitors', [
            'where' => $visitorsWhere,
            'condition' => $visitorsCondition,
            'limit' => 1
        ]);

        if ($resVisitor) {
            $resVisitor = $resVisitor[0];
            $order['visitors_id'] = $resVisitor[0]['id'];
        } else {
            // Регистрируем пользователя
            $order['visitors_id'] = $this->model->add('visitors', [
                'fields' => $visitor,
                'return_id' => true
            ]);
        }

        $order['total_sum'] = $this->cart['total_sum'];
        $order['total_qty'] = $this->cart['total_qty'];
        $order['total_old_sum'] = $this->cart['total_old_sum'] ?? 0;

        $baseStatus = $this->model->get('orders_statuses', [
            'fields' => ['id'],
            'order' => ['menu_position'],
            'limit' => 1
        ]);

        $baseStatus && $order['orders_statuses_id'] = $baseStatus[0]['id'];

        $order['id'] = $this->model->add('orders', [
            'fields' => $order,
            'return_id' => true
        ]);

        if (!$order['id']) {
            $this->sendError('Ошибка сохранения заказа. Свяжитесь с администрацией сайта по телефону - ' . $this->set['phone']);
        }

        if (!$resVisitor) {
            UserModel::instance()->checkUser($order['visitors_id']);
        }

        if (!$this->setOrdersGoods($order)) {
            $this->sendError('Ошибка сохранения товаров заказа. Обратитесь к администрации сайта');
        }

        $this->setOrdersGoods($order);

        $this->sendSuccess('Спасибо за заказ. В ближайшее время наши менеджеры свяжутся с Вами для уточнения деталей');

        $this->sendOrderEmail(['order' => $order, 'visitor' => $visitor]);
        $this->clearCart();
        $this->redirect();
    }

    protected function setOrdersGoods(array $order): bool
    {
        if (in_array('orders_goods', $this->model->showTables())) {
            $ordersGoods = [];

            foreach ($this->cart['goods'] as $key => $item) {
                $ordersGoods[$key]['orders_id'] = $order['id'];

                foreach ($item as $field => $value) {
                    if (!empty($this->model->showColumns('orders_goods')[$field])) {
                        if ($this->model->showColumns('orders_goods')['id_row'] === $field) {
                            if (!empty($this->model->showColumns('orders_goods')['goods_id'])) {
                                $ordersGoods[$key]['goods_id'] = $value;
                            }
                        } else {
                            $ordersGoods[$key][$field] = $value;
                        }
                    }
                }
            }

            return $this->model->add('orders_goods', [
                'fields' => $ordersGoods
            ]);
        }

        return false;
    }

    protected function sendOrderEmail(array $orderData)
    {

    }
}