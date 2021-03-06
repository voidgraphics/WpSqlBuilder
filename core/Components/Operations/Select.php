<?php

namespace WpSqlBuilder\Composants\Operations;

use WpSqlBuilder\Composants\Operation;

class Select extends Operation
{
      public $type = 'select';
      protected $grammar = [
                  'getColumns' => ['columns'],
                  'getFrom' => ['base'],
                  'getJoints' => ['joints'],
                  'getWhere' => ['conditions'],
                  'getGroupBy' => ['groupBy']
            ];

      public function getBase()
      {
            $s = parent::getBase();
            if($this->options['isDistinct']) $s .= ' DISTINCT';
            return $s;
      }
}