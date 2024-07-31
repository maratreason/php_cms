<?php

namespace core\admin\expansion;

use core\base\controller\Singleton;

class TeachersExpansion
{
    use Singleton;

    public function expansion($args = [])
    {   // Если нужно переименовать какое-то свойство, или посчитать сумму массива, то не обязательно создавать такой класс.
        // Можно сделать просто файл teachers.php, название совпадает с таблицей. И там просто прописать $this->title = 'Lalala title';
        // $this->title = 'Lalala title';
    }
    
}
