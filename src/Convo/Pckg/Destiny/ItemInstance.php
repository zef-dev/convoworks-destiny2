<?php declare(strict_types=1);

namespace Convo\Pckg\Destiny;

class ItemInstance
{
    private $_data;

    public function __construct($data)
    {
        $this->_data = $data;    
    }

    public function getData()
    {
        return $this->_data;
    }
}