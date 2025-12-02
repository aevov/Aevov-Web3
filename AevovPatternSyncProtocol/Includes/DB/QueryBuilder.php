<?php
/**
 * Database query builder for easy and secure query construction
 * 
 * @package APS
 * @subpackage DB
 */

namespace APS\DB;

class QueryBuilder {
    private $table;
    private $select = '*';
    private $where = [];
    private $order_by = null;
    private $limit = null;
    private $offset = null;

    public function table($table) {
        $this->table = $table;
        return $this;
    }

    public function select($columns) {
        $this->select = $columns;
        return $this;
    }

    public function where($column, $value, $operator = '=') {
        $this->where[] = [$column, $value, $operator];
        return $this;
    }

    public function order_by($column, $direction = 'ASC') {
        $this->order_by = "$column $direction";
        return $this;
    }

    public function limit($limit) {
        $this->limit = $limit;
        return $this;
    }

    public function offset($offset) {
        $this->offset = $offset;
        return $this;
    }

    public function get_results() {
        global $wpdb;
        
        $query = "SELECT $this->select FROM $this->table";
        $params = [];

        if (!empty($this->where)) {
            $conditions = [];
            foreach ($this->where as $condition) {
                $conditions[] = "$condition[0] $condition[2] %s";
                $params[] = $condition[1];
            }
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }

        if ($this->order_by) {
            $query .= " ORDER BY $this->order_by";
        }

        if ($this->limit) {
            $query .= " LIMIT %d";
            $params[] = $this->limit;
        }

        if ($this->offset) {
            $query .= " OFFSET %d";
            $params[] = $this->offset;
        }

        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        return $wpdb->get_results($query, ARRAY_A);
    }
}