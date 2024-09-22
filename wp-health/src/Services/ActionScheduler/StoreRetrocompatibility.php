<?php
namespace WPUmbrella\Services\ActionScheduler;

use ActionScheduler;
use ActionScheduler_Store;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Need for retrocompatbility ActionScheduler before 3.3.0
 */
class StoreRetrocompatibility
{
    /** @var int */
    protected static $max_args_length = 8000;

    /** @var int */
    protected static $max_index_length = 191;

    public function query_action($query)
    {
        $query['per_page'] = 1;
        $query['offset'] = 0;
        $results = $this->query_actions($query);

        if (empty($results)) {
            return null;
        } else {
            return (int) $results[0];
        }
    }

    public function query_actions($query = [], $query_type = 'select')
    {
        /** @var wpdb $wpdb */
        global $wpdb;

        $sql = $this->get_query_actions_sql($query, $query_type);

        return ('count' === $query_type) ? $wpdb->get_var($sql) : $wpdb->get_col($sql);
    }

    /**
     * Generate a hash from json_encoded $args using MD5 as this isn't for security.
     *
     * @param string $args JSON encoded action args.
     * @return string
     */
    protected function hash_args($args)
    {
        return md5($args);
    }

    /**
     * Get action args query param value from action args.
     *
     * @param array $args Action args.
     * @return string
     */
    protected function get_args_for_query($args)
    {
        $encoded = wp_json_encode($args);
        if (strlen($encoded) <= static::$max_index_length) {
            return $encoded;
        }
        return $this->hash_args($encoded);
    }

    /**
     * Returns the SQL statement to query (or count) actions.
     *
     * @since x.x.x $query['status'] accepts array of statuses instead of a single status.
     *
     * @param array  $query Filtering options.
     * @param string $select_or_count  Whether the SQL should select and return the IDs or just the row count.
     *
     * @return string SQL statement already properly escaped.
     */
    protected function get_query_actions_sql(array $query, $select_or_count = 'select')
    {
        if (!in_array($select_or_count, ['select', 'count'])) {
            throw new \InvalidArgumentException(__('Invalid value for select or count parameter. Cannot query actions.', 'action-scheduler'));
        }

        $query = wp_parse_args($query, [
            'hook' => '',
            'args' => null,
            'date' => null,
            'date_compare' => '<=',
            'modified' => null,
            'modified_compare' => '<=',
            'group' => '',
            'status' => '',
            'claimed' => null,
            'per_page' => 5,
            'offset' => 0,
            'orderby' => 'date',
            'order' => 'ASC',
        ]);

        /** @var \wpdb $wpdb */
        global $wpdb;
        $sql = ('count' === $select_or_count) ? 'SELECT count(a.action_id)' : 'SELECT a.action_id';
        $sql .= " FROM {$wpdb->actionscheduler_actions} a";
        $sql_params = [];

        if (!empty($query['group']) || 'group' === $query['orderby']) {
            $sql .= " LEFT JOIN {$wpdb->actionscheduler_groups} g ON g.group_id=a.group_id";
        }

        $sql .= ' WHERE 1=1';

        if (!empty($query['group'])) {
            $sql .= ' AND g.slug=%s';
            $sql_params[] = $query['group'];
        }

        if ($query['hook']) {
            $sql .= ' AND a.hook=%s';
            $sql_params[] = $query['hook'];
        }
        if (!is_null($query['args'])) {
            $sql .= ' AND a.args=%s';
            $sql_params[] = $this->get_args_for_query($query['args']);
        }

        if ($query['status']) {
            $statuses = (array) $query['status'];
            $placeholders = array_fill(0, count($statuses), '%s');
            $sql .= ' AND a.status IN (' . join(', ', $placeholders) . ')';
            $sql_params = array_merge($sql_params, array_values($statuses));
        }

        if ($query['date'] instanceof \DateTime) {
            $date = clone $query['date'];
            $date->setTimezone(new \DateTimeZone('UTC'));
            $date_string = $date->format('Y-m-d H:i:s');
            $comparator = $this->validate_sql_comparator($query['date_compare']);
            $sql .= " AND a.scheduled_date_gmt $comparator %s";
            $sql_params[] = $date_string;
        }

        if ($query['modified'] instanceof \DateTime) {
            $modified = clone $query['modified'];
            $modified->setTimezone(new \DateTimeZone('UTC'));
            $date_string = $modified->format('Y-m-d H:i:s');
            $comparator = $this->validate_sql_comparator($query['modified_compare']);
            $sql .= " AND a.last_attempt_gmt $comparator %s";
            $sql_params[] = $date_string;
        }

        if ($query['claimed'] === true) {
            $sql .= ' AND a.claim_id != 0';
        } elseif ($query['claimed'] === false) {
            $sql .= ' AND a.claim_id = 0';
        } elseif (!is_null($query['claimed'])) {
            $sql .= ' AND a.claim_id = %d';
            $sql_params[] = $query['claimed'];
        }

        if (!empty($query['search'])) {
            $sql .= ' AND (a.hook LIKE %s OR (a.extended_args IS NULL AND a.args LIKE %s) OR a.extended_args LIKE %s';
            for ($i = 0; $i < 3; $i++) {
                $sql_params[] = sprintf('%%%s%%', $query['search']);
            }

            $search_claim_id = (int) $query['search'];
            if ($search_claim_id) {
                $sql .= ' OR a.claim_id = %d';
                $sql_params[] = $search_claim_id;
            }

            $sql .= ')';
        }

        if ('select' === $select_or_count) {
            if ('ASC' === strtoupper($query['order'])) {
                $order = 'ASC';
            } else {
                $order = 'DESC';
            }
            switch ($query['orderby']) {
                case 'hook':
                    $sql .= " ORDER BY a.hook $order";
                    break;
                case 'group':
                    $sql .= " ORDER BY g.slug $order";
                    break;
                case 'modified':
                    $sql .= " ORDER BY a.last_attempt_gmt $order";
                    break;
                case 'none':
                    break;
                case 'action_id':
                    $sql .= " ORDER BY a.action_id $order";
                    break;
                case 'date':
                default:
                    $sql .= " ORDER BY a.scheduled_date_gmt $order";
                    break;
            }

            if ($query['per_page'] > 0) {
                $sql .= ' LIMIT %d, %d';
                $sql_params[] = $query['offset'];
                $sql_params[] = $query['per_page'];
            }
        }

        if (!empty($sql_params)) {
            $sql = $wpdb->prepare($sql, $sql_params);
        }

        return $sql;
    }
}
