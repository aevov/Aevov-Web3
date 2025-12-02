<?php


/**
 * includes/DB/class-aps-db.php
 */
namespace APS\DB;

class APS_DB {
    protected wpdb $wpdb;
    protected string $table_name;
    protected string $primary_key = 'id';
    protected APS_Cache $cache;

    public function __construct(wpdb $wpdb, string $table_name, APS_Cache $cache) {
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . $table_name;
        $this->cache = $cache;
    }

    public function get($id) {
        $cached = $this->cache->get($this->cache_key($id));
        if ($cached !== false) {
            return $cached;
        }

        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE {$this->primary_key} = %s",
            $id
        );

        $result = $this->wpdb->get_row($query);
        if ($result) {
            $this->cache->set($this->cache_key($id), $result);
        }

        return $result;
    }

    public function create($data) {
        $inserted = $this->wpdb->insert(
            $this->table_name,
            $this->prepare_data($data),
            $this->get_format($data)
        );

        if ($inserted) {
            $id = $this->wpdb->insert_id;
            $this->cache->delete($this->cache_key($id));
            return $id;
        }

        return false;
    }

    public function update($id, $data) {
        $updated = $this->wpdb->update(
            $this->table_name,
            $this->prepare_data($data),
            [$this->primary_key => $id],
            $this->get_format($data),
            ['%d']
        );

        if ($updated) {
            $this->cache->delete($this->cache_key($id));
            return true;
        }

        return false;
    }

    public function delete($id) {
        $deleted = $this->wpdb->delete(
            $this->table_name,
            [$this->primary_key => $id],
            ['%d']
        );

        if ($deleted) {
            $this->cache->delete($this->cache_key($id));
            return true;
        }

        return false;
    }

    protected function cache_key($id) {
        return $this->table_name . '_' . $id;
    }

    protected function prepare_data($data) {
        return array_map(function($value) {
            if (is_array($value) || is_object($value)) {
                return json_encode($value);
            }
            return $value;
        }, $data);
    }

    protected function get_format($data) {
        $formats = [];
        foreach ($data as $value) {
            if (is_int($value)) {
                $formats[] = '%d';
            } elseif (is_float($value)) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }
        return $formats;
    }
}
