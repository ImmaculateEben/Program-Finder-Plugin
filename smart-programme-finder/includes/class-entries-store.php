<?php
/**
 * Submission storage abstraction.
 *
 * Moves high-churn entry data out of serialized wp_options storage and into
 * a dedicated table, while keeping a legacy fallback path for older data.
 *
 * @package SmartProgrammeFinder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SPF_Entries_Store {

    private const LEGACY_OPTION = 'spf_entries';
    private const STORAGE_VERSION = 1;
    private const STORAGE_VERSION_OPTION = 'spf_entries_storage_version';

    private static ?self $instance = null;

    private string $table_name;

    private bool $table_checked = false;

    private bool $table_exists = false;

    private function __construct() {
        global $wpdb;

        $this->table_name = $wpdb->prefix . 'spf_entries';
    }

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate(): void {
        self::instance()->maybe_upgrade_storage();
    }

    public static function maybe_upgrade(): void {
        self::instance()->maybe_upgrade_storage();
    }

    public function add_entry( array $entry ): int {
        $normalized = $this->normalize_entry( $entry );
        $fields_json = wp_json_encode( $normalized['fields'] );
        if ( false === $fields_json ) {
            $fields_json = '[]';
        }

        if ( ! $this->table_exists() ) {
            return $this->append_legacy_entry( $normalized );
        }

        global $wpdb;

        $inserted = $wpdb->insert(
            $this->table_name,
            array(
                'form_id'    => $normalized['form_id'],
                'fields'     => $fields_json,
                'result'     => $normalized['result'],
                'matched'    => $normalized['matched'] ? 1 : 0,
                'created_at' => $normalized['created_at'],
                'ip'         => $normalized['ip'],
                'user_agent' => $normalized['user_agent'],
            ),
            array( '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
        );

        return false === $inserted ? 0 : (int) $wpdb->insert_id;
    }

    public function delete_entry( int $entry_id ): bool {
        if ( $entry_id <= 0 ) {
            return false;
        }

        if ( ! $this->table_exists() ) {
            $entries = $this->get_legacy_entries();
            $before  = count( $entries );

            $entries = array_values( array_filter( $entries, function ( $entry ) use ( $entry_id ) {
                return (int) ( $entry['id'] ?? 0 ) !== $entry_id;
            } ) );

            update_option( self::LEGACY_OPTION, $entries, false );
            return $before !== count( $entries );
        }

        global $wpdb;

        $deleted = $wpdb->delete( $this->table_name, array( 'id' => $entry_id ), array( '%d' ) );
        return false !== $deleted && $deleted > 0;
    }

    public function get_entry( int $entry_id ): ?array {
        if ( $entry_id <= 0 ) {
            return null;
        }

        if ( ! $this->table_exists() ) {
            foreach ( $this->get_legacy_entries() as $entry ) {
                if ( (int) ( $entry['id'] ?? 0 ) === $entry_id ) {
                    return $entry;
                }
            }

            return null;
        }

        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d LIMIT 1",
                $entry_id
            ),
            ARRAY_A
        );

        return is_array( $row ) ? $this->map_db_row( $row ) : null;
    }

    public function get_entries_for_form( int $form_id ): array {
        if ( $form_id <= 0 ) {
            return array();
        }

        if ( ! $this->table_exists() ) {
            return array_values( array_filter( $this->get_legacy_entries(), function ( $entry ) use ( $form_id ) {
                return (int) ( $entry['form_id'] ?? 0 ) === $form_id;
            } ) );
        }

        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE form_id = %d ORDER BY created_at ASC, id ASC",
                $form_id
            ),
            ARRAY_A
        );

        return array_map( array( $this, 'map_db_row' ), is_array( $rows ) ? $rows : array() );
    }

    public function get_all_entries(): array {
        if ( ! $this->table_exists() ) {
            return $this->get_legacy_entries();
        }

        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} ORDER BY created_at ASC, id ASC",
            ARRAY_A
        );

        return array_map( array( $this, 'map_db_row' ), is_array( $rows ) ? $rows : array() );
    }

    public function count_entries( int $form_id = 0 ): int {
        if ( ! $this->table_exists() ) {
            $entries = $this->get_legacy_entries();
            if ( $form_id <= 0 ) {
                return count( $entries );
            }

            return count( array_filter( $entries, function ( $entry ) use ( $form_id ) {
                return (int) ( $entry['form_id'] ?? 0 ) === $form_id;
            } ) );
        }

        global $wpdb;

        if ( $form_id > 0 ) {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_name} WHERE form_id = %d",
                    $form_id
                )
            );
        }

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );
    }

    public function count_entries_since( int $form_id, string $since ): int {
        if ( ! $this->table_exists() ) {
            $count = 0;
            foreach ( $this->get_entries_for_form( $form_id ) as $entry ) {
                if ( ( $entry['created_at'] ?? '' ) >= $since ) {
                    $count++;
                }
            }

            return $count;
        }

        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE form_id = %d AND created_at >= %s",
                $form_id,
                $since
            )
        );
    }

    /**
     * Remove the oldest entries so the total count stays below $limit.
     *
     * Called before inserting a new entry to enforce the storage cap without
     * ever rejecting a submission. A limit of 10,000 means at most 9,999 rows
     * exist when we start inserting, so exactly one old row is evicted per call
     * when the table is full.
     *
     * @param int $limit Maximum number of entries to KEEP (the new one is not yet stored).
     */
    public function trim_to_limit( int $limit ): void {
        if ( $limit <= 1 ) {
            return;
        }

        if ( ! $this->table_exists() ) {
            $entries = $this->get_legacy_entries();
            if ( count( $entries ) < $limit ) {
                return;
            }
            // Keep the most-recent ($limit - 1) entries to leave room for the new one.
            $entries = array_values( array_slice( $entries, -( $limit - 1 ) ) );
            update_option( self::LEGACY_OPTION, $entries, false );
            return;
        }

        global $wpdb;

        // Delete all rows except the ($limit - 1) most recent to make room.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM `{$this->table_name}` WHERE id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM `{$this->table_name}` ORDER BY created_at DESC, id DESC LIMIT %d
                    ) AS _keep
                )",
                $limit - 1
            )
        );
    }

    public function get_last_entry_date( int $form_id ): string {
        if ( ! $this->table_exists() ) {
            $entries = $this->get_entries_for_form( $form_id );
            if ( empty( $entries ) ) {
                return '';
            }

            $last = end( $entries );
            return is_array( $last ) ? (string) ( $last['created_at'] ?? '' ) : '';
        }

        global $wpdb;

        $date = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT created_at FROM {$this->table_name} WHERE form_id = %d ORDER BY created_at DESC, id DESC LIMIT 1",
                $form_id
            )
        );

        return is_string( $date ) ? $date : '';
    }

    public function get_counts_by_form( string $since = '' ): array {
        if ( ! $this->table_exists() ) {
            $counts = array();
            foreach ( $this->get_legacy_entries() as $entry ) {
                $created_at = (string) ( $entry['created_at'] ?? '' );
                if ( '' !== $since && $created_at < $since ) {
                    continue;
                }

                $form_id = (int) ( $entry['form_id'] ?? 0 );
                if ( $form_id <= 0 ) {
                    continue;
                }

                if ( ! isset( $counts[ $form_id ] ) ) {
                    $counts[ $form_id ] = 0;
                }
                $counts[ $form_id ]++;
            }

            return $counts;
        }

        global $wpdb;

        if ( '' !== $since ) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT form_id, COUNT(*) AS total FROM {$this->table_name} WHERE created_at >= %s GROUP BY form_id",
                    $since
                ),
                ARRAY_A
            );
        } else {
            $rows = $wpdb->get_results(
                "SELECT form_id, COUNT(*) AS total FROM {$this->table_name} GROUP BY form_id",
                ARRAY_A
            );
        }

        $counts = array();
        foreach ( is_array( $rows ) ? $rows : array() as $row ) {
            $counts[ (int) $row['form_id'] ] = (int) $row['total'];
        }

        return $counts;
    }

    public function get_last_entry_dates_by_form(): array {
        if ( ! $this->table_exists() ) {
            $dates = array();
            foreach ( $this->get_legacy_entries() as $entry ) {
                $form_id    = (int) ( $entry['form_id'] ?? 0 );
                $created_at = (string) ( $entry['created_at'] ?? '' );

                if ( $form_id <= 0 || '' === $created_at ) {
                    continue;
                }

                if ( ! isset( $dates[ $form_id ] ) || $created_at > $dates[ $form_id ] ) {
                    $dates[ $form_id ] = $created_at;
                }
            }

            return $dates;
        }

        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT form_id, MAX(created_at) AS last_date FROM {$this->table_name} GROUP BY form_id",
            ARRAY_A
        );

        $dates = array();
        foreach ( is_array( $rows ) ? $rows : array() as $row ) {
            $dates[ (int) $row['form_id'] ] = (string) $row['last_date'];
        }

        return $dates;
    }

    public function get_daily_counts( int $days ): array {
        $days = max( 1, $days );
        $chart_data = array();

        for ( $i = $days - 1; $i >= 0; $i-- ) {
            $day = gmdate( 'Y-m-d', strtotime( "-{$i} days" ) );
            $chart_data[ $day ] = 0;
        }

        if ( ! $this->table_exists() ) {
            foreach ( $this->get_legacy_entries() as $entry ) {
                $day = substr( (string) ( $entry['created_at'] ?? '' ), 0, 10 );
                if ( isset( $chart_data[ $day ] ) ) {
                    $chart_data[ $day ]++;
                }
            }

            return $chart_data;
        }

        global $wpdb;

        $since = gmdate( 'Y-m-d 00:00:00', strtotime( '-' . ( $days - 1 ) . ' days' ) );
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(created_at) AS entry_day, COUNT(*) AS total FROM {$this->table_name} WHERE created_at >= %s GROUP BY DATE(created_at)",
                $since
            ),
            ARRAY_A
        );

        foreach ( is_array( $rows ) ? $rows : array() as $row ) {
            $day = (string) ( $row['entry_day'] ?? '' );
            if ( isset( $chart_data[ $day ] ) ) {
                $chart_data[ $day ] = (int) $row['total'];
            }
        }

        return $chart_data;
    }

    private function maybe_upgrade_storage(): void {
        $installed_version = (int) get_option( self::STORAGE_VERSION_OPTION, 0 );

        if ( $installed_version >= self::STORAGE_VERSION && $this->table_exists() ) {
            return;
        }

        $this->create_table();

        if ( $this->table_exists() && $this->migrate_legacy_entries() ) {
            update_option( self::STORAGE_VERSION_OPTION, self::STORAGE_VERSION, false );
        }
    }

    private function create_table(): void {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            form_id bigint(20) unsigned NOT NULL DEFAULT 0,
            fields longtext NOT NULL,
            result longtext NOT NULL,
            matched tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            ip varchar(45) NOT NULL DEFAULT '',
            user_agent varchar(255) NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            KEY form_id (form_id),
            KEY created_at (created_at),
            KEY form_created_at (form_id, created_at)
        ) {$charset_collate};";

        dbDelta( $sql );
        $this->table_checked = false;
    }

    private function migrate_legacy_entries(): bool {
        $legacy_entries = $this->get_legacy_entries();
        if ( empty( $legacy_entries ) ) {
            return true;
        }

        global $wpdb;

        $success = true;
        foreach ( $legacy_entries as $entry ) {
            $normalized = $this->normalize_entry( $entry );
            if ( $normalized['id'] <= 0 ) {
                continue;
            }

            $fields_json = wp_json_encode( $normalized['fields'] );
            if ( false === $fields_json ) {
                $fields_json = '[]';
            }

            $stored = $wpdb->replace(
                $this->table_name,
                array(
                    'id'         => $normalized['id'],
                    'form_id'    => $normalized['form_id'],
                    'fields'     => $fields_json,
                    'result'     => $normalized['result'],
                    'matched'    => $normalized['matched'] ? 1 : 0,
                    'created_at' => $normalized['created_at'],
                    'ip'         => $normalized['ip'],
                    'user_agent' => $normalized['user_agent'],
                ),
                array( '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
            );

            if ( false === $stored ) {
                $success = false;
            }
        }

        if ( $success ) {
            update_option( self::LEGACY_OPTION, $legacy_entries, false );
        }

        return $success;
    }

    private function append_legacy_entry( array $entry ): int {
        $entries   = $this->get_legacy_entries();
        $next_id   = empty( $entries ) ? 1 : max( array_column( $entries, 'id' ) ) + 1;
        $entry['id'] = $next_id;
        $entries[] = $entry;

        update_option( self::LEGACY_OPTION, $entries, false );

        return $next_id;
    }

    private function get_legacy_entries(): array {
        $entries = get_option( self::LEGACY_OPTION, array() );
        if ( ! is_array( $entries ) ) {
            return array();
        }

        return array_values( array_map( array( $this, 'normalize_entry' ), $entries ) );
    }

    private function normalize_entry( array $entry ): array {
        $fields = isset( $entry['fields'] ) && is_array( $entry['fields'] ) ? $entry['fields'] : array();

        return array(
            'id'         => isset( $entry['id'] ) ? absint( $entry['id'] ) : 0,
            'form_id'    => isset( $entry['form_id'] ) ? absint( $entry['form_id'] ) : 0,
            'fields'     => $fields,
            'result'     => isset( $entry['result'] ) ? (string) $entry['result'] : '',
            'matched'    => ! empty( $entry['matched'] ),
            'created_at' => ! empty( $entry['created_at'] ) ? (string) $entry['created_at'] : current_time( 'mysql' ),
            'ip'         => isset( $entry['ip'] ) ? sanitize_text_field( (string) $entry['ip'] ) : '',
            'user_agent' => isset( $entry['user_agent'] ) ? sanitize_text_field( (string) $entry['user_agent'] ) : '',
        );
    }

    private function map_db_row( array $row ): array {
        $fields = json_decode( (string) ( $row['fields'] ?? '[]' ), true );

        return array(
            'id'         => (int) ( $row['id'] ?? 0 ),
            'form_id'    => (int) ( $row['form_id'] ?? 0 ),
            'fields'     => is_array( $fields ) ? $fields : array(),
            'result'     => (string) ( $row['result'] ?? '' ),
            'matched'    => ! empty( $row['matched'] ),
            'created_at' => (string) ( $row['created_at'] ?? '' ),
            'ip'         => (string) ( $row['ip'] ?? '' ),
            'user_agent' => (string) ( $row['user_agent'] ?? '' ),
        );
    }

    private function table_exists(): bool {
        if ( $this->table_checked ) {
            return $this->table_exists;
        }

        global $wpdb;

        $found = $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $this->table_name
            )
        );

        $this->table_exists  = is_string( $found ) && $found === $this->table_name;
        $this->table_checked = true;

        return $this->table_exists;
    }
}
