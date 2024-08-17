<?php

namespace core\admin\controller;

class AjaxController extends BaseAdmin
{
    public function ajax()
    {
        if (isset($this->ajaxData['ajax'])) {
            $this->execBase();
            foreach ($this->ajaxData as $key => $item) {
                $this->ajaxData[$key] = $this->clearStr($item);
            }

            switch($this->ajaxData['ajax']) {
                case 'sitemap':
                    return (new CreatesitemapController())->inputData($this->ajaxData['links_counter'], false);
                    break;
                case 'editData':
                    $_POST['return_id'] = true;
                    $this->checkPost();
                    return json_encode(['success' => 1]);
                    break;
            }
        }

        return json_encode(['success' => '0', 'message' => 'No ajax variable']);
    }
}