<?php

namespace APS\DB;

use APS\Core\Logger;

class APS_Reward_DB {
    private $wpdb;
    private $table_name;
    private $logger;

    public function __construct($wpdb = null) {
        $this->wpdb = $wpdb ?? $GLOBALS['wpdb'];
        $this->table_name = $this->wpdb->prefix . 'aps_contributor_balances';
        $this->logger = Logger::get_instance();
    }

    public function create_table() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            contributor_id bigint(20) UNSIGNED NOT NULL,
            balance decimal(10,2) NOT NULL DEFAULT '0.00',
            last_rewarded_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (contributor_id)
        ) $charset_collate;";

        dbDelta($sql);
        $this->logger->log('info', 'Contributor balances table created or updated.');
    }

    public function get_contributor_balance($contributor_id) {
        try {
            if (empty($contributor_id)) {
                $this->logger->log('error', 'Empty contributor ID provided for get_contributor_balance');
                return false;
            }

            $balance = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT balance FROM {$this->table_name} WHERE contributor_id = %d",
                    $contributor_id
                )
            );

            if ($balance === null) {
                // Contributor not found, return 0
                return 0.00;
            }

            if ($balance === false) {
                $this->logger->log('error', 'Failed to retrieve contributor balance from database', [
                    'contributor_id' => $contributor_id,
                    'error' => $this->wpdb->last_error
                ]);
                return false;
            }

            return (float) $balance;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Exception occurred while retrieving contributor balance', [
                'contributor_id' => $contributor_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function update_contributor_balance($contributor_id, $amount) {
        try {
            if (empty($contributor_id) || !is_numeric($amount)) {
                $this->logger->log('error', 'Invalid contributor ID or amount provided for update_contributor_balance', [
                    'contributor_id' => $contributor_id,
                    'amount' => $amount
                ]);
                return false;
            }

            // Check if contributor exists
            $existing_balance = $this->get_contributor_balance($contributor_id);

            if ($existing_balance === false) {
                // Error occurred during retrieval
                return false;
            }

            if ($existing_balance === 0.00) {
                // Contributor does not exist, insert new record
                $result = $this->wpdb->insert(
                    $this->table_name,
                    [
                        'contributor_id' => $contributor_id,
                        'balance' => $amount,
                        'last_rewarded_at' => current_time('mysql')
                    ],
                    ['%d', '%f', '%s']
                );
            } else {
                // Contributor exists, update balance
                $result = $this->wpdb->query(
                    $this->wpdb->prepare(
                        "UPDATE {$this->table_name} SET balance = balance + %f, last_rewarded_at = %s WHERE contributor_id = %d",
                        $amount,
                        current_time('mysql'),
                        $contributor_id
                    )
                );
            }

            if ($result === false) {
                $this->logger->log('error', 'Failed to update contributor balance in database', [
                    'contributor_id' => $contributor_id,
                    'amount' => $amount,
                    'error' => $this->wpdb->last_error
                ]);
                return false;
            }

            $this->logger->log('info', 'Contributor balance updated successfully', [
                'contributor_id' => $contributor_id,
                'amount' => $amount,
                'new_balance' => $this->get_contributor_balance($contributor_id) // Get updated balance
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->log('error', 'Exception occurred while updating contributor balance', [
                'contributor_id' => $contributor_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}