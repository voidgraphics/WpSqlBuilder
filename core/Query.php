<?php

namespace WpSqlBuilder;

use WpSqlBuilder\Composants\Table;
use WpSqlBuilder\Composants\Column;
use WpSqlBuilder\Composants\Joint;
use WpSqlBuilder\Composants\Conditions\Simple as Condition;
use WpSqlBuilder\Composants\Conditions\Complex as ComplexCondition;
use WpSqlBuilder\Composants\Operations\Select;

/**
* Dynamic query container
*/
class Query
{
      protected $operation;
      protected $base;
      protected $columns = [];
      protected $joints = [];
      protected $conditions = [];
      protected $groupBy;
      protected $tables = [];


      /**
       * Sets the query operation to select, adds wanted columns from specific table
       * @param  string $table
       * @param  string $columns
       * @param  string $distinct
       * @return object $this
       */

      public function select( $table, $columns = ['*'], $distinct = false )
      {
            if($this->operation && $this->operation->type !== 'select') throw new Exception('WpSqlBuilder - Cannot perform select on "'.$this->operation->type.'" operation.', 1);
            $table = $this->addTable($table);
            if(!$this->operation){
                  $this->setOperation('select', $distinct);
                  $this->base = $table;
            }
            $this->addColumns( $table, $columns );
            return $this;
      }


      /**
       * Adds a where condition to the query. Can be called multiple times
       * @param  string $column
       * @param  (optional) string $condition
       * @param  mixed $value
       * @return object $this
       */

      public function where(...$args)
      {
            $this->addCondition('and', $args, $this->conditions);
            return $this;
      }


      /**
       * Alias of "where".
       * @param  string $column
       * @param  (optional) string $condition
       * @param  mixed $value
       * @return object $this
       */

      public function andWhere(...$args)
      {
            return call_user_func_array([$this, 'where'], $args);
      }


      /**
       * chains an "OR WHERE" condition to the query. Can be called multiple times, but should not be called as first condition.
       * @param  string $column
       * @param  (optional) string $condition
       * @param  mixed $value
       * @return object $this
       */

      public function orWhere(...$args)
      {
            $this->addCondition('or', $args, $this->conditions);
            return $this;
      }


      /**
       * Adds a complex where condition to the query.
       * @return WpSqlBuilder\Composants\Conditions\Complex
       */

      public function whereComplex()
      {
            return $this->addComplexCondition('and', $this->conditions);
      }


      /**
       * Alias of "whereComplex".
       * @return WpSqlBuilder\Composants\Conditions\Complex
       */

      public function andWhereComplex()
      {
            return $this->whereComplex();
      }


      /**
       * chains an "OR WHERE (...)" condition to the query. Should not be called as first condition.
       * @return WpSqlBuilder\Composants\Conditions\Complex
       */

      public function orWhereComplex()
      {
            return $this->addComplexCondition('or', $this->conditions);
      }


      /**
       * Adds a joint to the query
       * @param  string $table
       * @param  string $leftColumn
       * @param  string $rightColumn
       * @return object $this
       */

      public function join( $table, $leftColumn, $rightColumn )
      {
            $table = $this->addTable($table);
            $this->addJoint($table, $this->getConditionColumn($leftColumn), $this->getConditionColumn($rightColumn));
            return $this;
      }


      /**
       * Adds a groupBy action to the query
       * @param  string $table
       * @param  string $column
       * @return object $this
       */

      public function groupBy( $table, $column )
      {
            $this->groupBy = new Column($column, false, $this->addTable($table) );
            return $this;
      }


      /**
       * Executes the query
       * @return mixed $results
       */

      public function get()
      {
            return $this->execute( $this->generate() );
      }


      /**
       * Gets the SQL string from this query
       * @return string $sql
       */

      public function generate()
      {
            if($this->operation) return $this->buildGrammar();
            else throw new Exception("WpSqlBuilder - Could not build sql string without valid operation.", 1);
      }


      /**
       * Adds a table to the tables array if it could not find an existing reference
       * @param  string $table
       * @return int $table
       */

      protected function addTable($table, $isRoot = true)
      {
            $table = new Table($table, $isRoot);
            $snap = $this->checkTable($table);
            if(is_object($snap)) return $snap;
            if($snap){
                  if(strpos($table->alias, '_') === 0) $table->setAlias($table->alias . $snap, false);
                  else $table->setAlias($snap);
            }
            array_push($this->tables, $table);
            return $table;
      }


      /**
       * Checks if given table already exists (on alias or table name) and if it is re-usable
       * @param  object $table
       * @return (int|object) $table
       */

      protected function checkTable($table)
      {
            $a = [];
            foreach ($this->tables as $t) {
                  if($t->alias == '_' . $table->basename) return $t;
                  elseif($t->alias == $table->basename) return $t;
                  elseif($t->basename == $table->basename){
                        if($t->isRoot && $table->isRoot) return $t;
                        elseif($table->isDefinedAlias) return false;
                        array_push($a, $t);
                  }
            }
            return count($a);
      }


      /**
       * Adds a columns to columns array
       * @param  object $table
       * @param  array $cols
       * @return void
       */

      protected function addColumns($table, $cols)
      {
            foreach ($cols as $arg1 => $arg2) {
                  $this->addColumn($table, is_integer($arg1) ? $arg2 : $arg1, is_integer($arg1) ? null : $arg2);
            }
      }


      /**
       * Adds a single column to columns array
       * @param  object $table
       * @param  string $column
       * @param  string $alias
       * @return void
       */

      protected function addColumn($table, $column, $alias)
      {
            if(!$this->checkColumn($table, $column, $alias)){
                  array_push($this->columns, new Column($column, $alias, $table));
            }
      }


      /**
       * Checks if column is already in use
       * @param  object $table
       * @param  string $column
       * @param  string $alias
       * @return boolean
       */

      protected function checkColumn($table, $column, $alias)
      {
            foreach ($this->columns as $col) {
                  if($col->table->alias == $table->alias && $col->name == $column) return true;
                  if($col->alias == $alias) throw new Exception('WpSqlBuilder - Trying to add two different columns with same alias "'.$alias.'".', 1);
            }
            return false;
      }


      /**
       * Adds a simple condition
       * @param  string $chain
       * @param  array $arguments
       * @return void
       */

      public function addCondition($chain, $arguments, &$array)
      {
            array_push($array, new Condition($chain, $arguments, $this));
      }


      /**
       * Adds a simple condition
       * @param  string $chain
       * @param  array $arguments
       * @return void
       */

      public function addComplexCondition($chain, &$array)
      {
            array_push($array, new ComplexCondition($chain, $this));
            return $array[count($array) - 1];
      }


      /**
       * Parse and find column/table from condition column
       * @param  string $string
       * @return object $columns
       */

      public function getConditionColumn($str)
      {
            $a = explode('.', $str);
            if (count($a) == 1) {
                  if(!$this->base) throw new Exception('WpSqlBuilder - Cannot guess table for condition if no base operation has been defined', 1);
                  return new Column($str, false, $this->base );
            }
            return new Column($a[1], false, $this->addTable($a[0]) );
      }


      /**
       * Adds a joint to the query
       * @param  object $table
       * @param  object $leftCol
       * @param  object $rightCol
       * @return void
       */

      protected function addJoint($table, $leftCol, $rightCol)
      {
            array_push($this->joints, new Joint($table, $leftCol, $rightCol));
      }


      /**
       * Sets and makes an instance of the wanted query operation
       * @return void
       */

      protected function setOperation( $op, $opt = null )
      {
            if(!$this->operation) {
                  switch ($op) {
                        case 'select':
                              $this->operation = new Select(['isDistinct' => $opt]);
                              break;
                  }
            }
            else throw new Exception("WpSqlBuilder - Trying to add new operation to existing query.", 1);
      }


      /**
       * Generates an SQL string based on the operation's grammar
       * @return string $sql
       */

      protected function buildGrammar()
      {
            $sql = $this->operation->getBase();
            foreach ($this->operation->getGrammar() as $fn => $args) {
                  $sql .= forward_static_call_array(['WpSqlBuilder\Grammar',$fn], $this->getArguments($args));
            }
            return $sql;
      }


      /**
       * Gathers the arguments data required by a specific operation's grammar function
       * @param  array $args
       * @return array $arguments
       */

      protected function getArguments( $args )
      {
            $arguments = [];
            foreach ($args as $key) {
                  array_push($arguments, $this->$key);
            }
            return $arguments;
      }


      /**
       * Sends the sql query to $wpdb object, then parses the results
       * @param  string $sql
       * @return mixed $response
       */

      protected function execute( $sql )
      {
            global $wpdb;
            return $wpdb->get_results( $sql );
      }
}