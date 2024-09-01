<?php

namespace core\user\model;

use core\base\controller\Singleton;
use core\base\model\BaseModel;

class Model extends BaseModel {
    use Singleton;

    public function getGoods($set = [], &$catalogFilters = null, &$catalogPrices = null)
    {
        if (empty($set['join_structure'])) {
            $set['join_structure'] = true;
        }

        if (empty($set['where'])) {
            $set['where'] = [];
        }

        if (empty($set['pagination'])) {
            $set['pagination'] = [];
        }

        if (empty($set['order'])) {
            $set['order'] = [];

            if (!empty($this->showColumns('goods')['parent_id'])) {
                $set['order'][] = 'parent_id';
            }

            if (!empty($this->showColumns('goods')['price'])) {
                $set['order'][] = 'price';
            }
        }

        $goods = $this->get('goods', $set);

        if ($goods) {
            unset($set['join'], $set['join_structure'], $set['pagination']);
            // Получаем цены
            if ($catalogPrices !== false && !empty($this->showColumns('goods')['price'])) {
                $set['fields'] = ['MIN(price) as min_price', 'MAX(price) as max_price'];

                $catalogPrices = $this->get('goods', $set);

                if (!empty($catalogPrices[0])) {
                    $catalogPrices = $catalogPrices[0];
                }
            }
            // Получаем фильтры
            if ($catalogFilters !== false && in_array('filters', $this->showTables())) {
                $parentFiltersFields = [];
                $filtersWhere = [];
                $filtersOrder = [];

                foreach ($this->showColumns('filters') as $name => $item) {
                    if (!empty($item) && is_array($item)) {
                        $parentFiltersFields[] = $name . ' as f_' . $name;
                    }
                }

                if (!empty($this->showColumns('filters')['visible'])) {
                    $filtersWhere['visible'] = 1;
                }

                if (!empty($this->showColumns('filters')['menu_position'])) {
                    $filtersOrder[] = 'menu_position';
                }

                $filters = $this->get('filters', [
                    'where' => $filtersWhere,
                    'order' => $filtersOrder,
                    'join' => [
                        'filters f_name' => [
                            'type' => 'INNER',
                            'fields' => $parentFiltersFields,
                            'where' => $filtersWhere,
                            'on' => ['parent_id', 'id']
                        ],
                        'goods_filters' => [
                            'on' => [
                                'table' => 'filters',
                                'fields' => ['id', 'filters_id']
                            ],
                            'where' => [
                                'goods_id' => $this->get('goods', [
                                    'fields' => [$this->showColumns('goods')['id_row']],
                                    'where' => $set['where'] ?? null,
                                    'operand' => $set['operand'] ?? null,
                                    'return_query' => true
                                ])
                            ],
                            'operand' => ['IN'],
                        ]
                    ]
                ]);

                if (!empty($this->showColumns('goods')['discount'])) {
                    foreach ($goods as $key => $item) {
                        $this->applyDiscount($goods[$key], $item['discount']);
                    }
                }

                if ($filters) {
                    $filtersIds = implode(',', array_unique(array_column($filters, 'id')));
                    $goodsIds = implode(',', array_unique(array_column($filters, 'goods_id')));
                    $query = "SELECT `filters_id` as id, COUNT(goods_id) as count FROM goods_filters
                                WHERE filters_id IN ($filtersIds) AND goods_id IN ($goodsIds) GROUP BY filters_id";

                    $goodsCountOb = $this->query($query);

                    $goodsCount = [];

                    if ($goodsCountOb) {
                        foreach ($goodsCountOb as $item) {
                            $goodsCount[$item['id']] = $item;
                        }
                    }

                    $catalogFilters = [];

                    foreach ($filters as $item) {
                        $parent = [];
                        $child = [];

                        foreach ($item as $row => $rowValue) {
                            if (strpos($row, 'f_') === 0) {
                                $name = preg_replace('/^f_/', '', $row);
                                $parent[$name] = $rowValue;
                            } else {
                                $child[$row] = $rowValue;
                            }
                        }

                        if (isset($goodsCount[$child['id']]['count'])) {
                            $child['count'] = $goodsCount[$child['id']]['count'];
                        }

                        if (empty($catalogFilters[$parent['id']])) {
                            $catalogFilters[$parent['id']] = $parent;
                            $catalogFilters[$parent['id']]['values'] = [];
                        }

                        $catalogFilters[$parent['id']]['values'][$child['id']] = $child;

                        if (isset($goods[$item['goods_id']])) {
                            if (empty($goods[$item['goods_id']]['filters'][$parent['id']])) {
                                $goods[$item['goods_id']]['filters'][$parent['id']] = $parent;
                                $goods[$item['goods_id']]['filters'][$parent['id']]['values'] = [];
                            }

                            $goods[$item['goods_id']]['filters'][$parent['id']]['values'][$item['id']] = $child;
                        }
                    }
                }
            }
        }

        return $goods ?? null;
    }

    /**
     * Формируем скидку
     *
     * @param $data
     * @param $discount
     * @return void
     */
    public function applyDiscount(&$data, $discount)
    {
        if ($discount) {
            if (isset($data['price'])) {
                $data['old_price'] = $data['price'];
                $data['discount'] = $discount;
                $data['price'] = $data['old_price'] - ($data['old_price'] / 100 * $discount);
            }
        }
    }
}
