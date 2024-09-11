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
            $this->delivery = $this->model->get('delivery', ['join_structure' => true]);
            $this->payments = $this->model->get('payments', ['join_structure' => true]);

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

        if (!($goods = $this->setOrdersGoods($order))) {
            $this->sendError('Ошибка сохранения товаров заказа. Обратитесь к администрации сайта');
        }

        $this->setOrdersGoods($order);

        $this->sendSuccess('Спасибо за заказ. В ближайшее время наши менеджеры свяжутся с Вами для уточнения деталей');

        $order['delivery'] = $this->delivery[$order['delivery_id']]['name'] ?? ''; 
        $order['payments'] = $this->payments[$order['payments_id']]['name'] ?? ''; 

        $this->sendOrderEmail(['order' => $order, 'visitor' => $visitor, 'goods' => $goods]);
        $this->clearCart();
        $this->redirect();
    }

    protected function setOrdersGoods(array $order): ?array
    {
        if (in_array('orders_goods', $this->model->showTables())) {
            $ordersGoods = [];
            $preparedGoods = [];

            foreach ($this->cart['goods'] as $key => $item) {
                $ordersGoods[$key]['orders_id'] = $order['id'];
                $preparedGoods[$key] = $item;
                $preparedGoods[$key]['total_sum'] = $item['qty'] * $item['price'];

                foreach ($item as $field => $value) {
                    if (!empty($this->model->showColumns('orders_goods')[$field])) {
                        if ($this->model->showColumns('orders_goods')['id_row'] === $field) {
                            if ($this->model->showColumns('orders_goods')['goods_id']) {
                                $ordersGoods[$key]['goods_id'] = $value;
                            }
                        } else {
                            $ordersGoods[$key][$field] = $value;
                        }
                    }
                }
            }

            if ($this->model->add('orders_goods', [
                'fields' => $ordersGoods
            ])) {
                return $preparedGoods;
            }
        }

        return null;
    }

    protected function sendOrderEmail(array $orderData)
    {
        $dir = TEMPLATE . 'include/orderTemplates/';
        $templatesArr = [];

        if (is_dir($dir)) {
            $list = scandir($dir);

            foreach ($orderData as $name => $item) {
                if (($file = preg_grep('/^' . $name . '\./', $list))) {
                    $file = array_shift($file);
                    $template = file_get_contents($dir . $file, FILE_USE_INCLUDE_PATH);

                    if (!is_numeric(key($item))) {
                        $templatesArr[] = $this->renderOrderMailTemplate($template, $item);
                    } else {
                        if ($common = preg_grep('/' . $name . 'Header\./', $list)) {
                            $common = array_shift($common);
                            $templatesArr[] = $this->renderOrderMailTemplate(file_get_contents($dir . $common), []);
                        }

                        foreach ($item as $value) {
                            $templatesArr[] = $this->renderOrderMailTemplate($template, $value);
                        }

                        if ($common = preg_grep('/' . $name . 'Footer\./', $list)) {
                            $common = array_shift($common);
                            $templatesArr[] = $this->renderOrderMailTemplate(file_get_contents($dir . $common), []);
                        }
                    }

                }
            }
        }

        $a = 1;
    }

    protected function renderOrderMailTemplate(string $template, array $data): string
    {
        foreach ($data as $key => $item) {
            $template = preg_replace('/#' . $key . '#/i', $item, $template);
        }

        return $template;
    }
}