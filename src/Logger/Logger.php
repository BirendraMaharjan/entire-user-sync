<?php
/**
 * Logger for sync events stored in the database.
 *
 * Responsible for writing and querying sync logs. Uses the WPDB interface
 * and takes care to sanitize inputs and redact sensitive payload fields.
 *
 * @package EntireUserSync\Sync
 */

namespace EntireUserSync\Logger;

/**
 * Class Logger
 */
class Logger {

	public const TABLE = 'entireus_logs';

	/**
	 * Write a log entry.
	 *
	 * @param array $args Log arguments: event, direction, user_email, source_site, target_site, status, message, payload.
	 */
	public function log( array $args ): void {
		global $wpdb;

		$payload = $args['payload'] ?? array();
		self::strip_sensitive( $payload );

		$wpdb->insert(
			$wpdb->prefix . self::TABLE,
			array(
				'event'       => sanitize_key( $args['event'] ?? 'unknown' ),
				'direction'   => sanitize_key( $args['direction'] ?? 'outgoing' ),
				'user_email'  => sanitize_email( $args['user_email'] ?? '' ),
				'source_site' => esc_url_raw( $args['source_site'] ?? '' ),
				'target_site' => esc_url_raw( $args['target_site'] ?? '' ),
				'status'      => sanitize_key( $args['status'] ?? 'success' ),
				'message'     => sanitize_text_field( $args['message'] ?? '' ),
				'payload'     => wp_json_encode( $payload ),
				'created_at'  => current_time( 'mysql', true ),
				'modified_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Query log rows with filtering, sorting and pagination.
	 *
	 * @param array $args Filtering and pagination args.
	 *
	 * @return array{rows: array, total: int, pages: int}
	 */
	public static function query( array $args = array() ): array {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;

		$per_page = max( 1, (int) ( $args['per_page'] ?? 50 ) );
		$page     = max( 1, (int) ( $args['paged'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$allowed_order   = array( 'ASC', 'DESC' );
		$allowed_orderby = array(
			'id',
			'event',
			'direction',
			'user_email',
			'source_site',
			'target_site',
			'status',
			'created_at'
		);

		$orderby = in_array( $args['orderby'] ?? '', $allowed_orderby, true )
			? $args['orderby'] : 'id';
		$order   = in_array( strtoupper( $args['order'] ?? '' ), $allowed_order, true )
			? strtoupper( $args['order'] ) : 'DESC';

		$where  = array();
		$values = array();

		if ( ! empty( $args['event'] ) ) {
			$where[]  = 'event = %s';
			$values[] = $args['event'];
		}
		if ( ! empty( $args['direction'] ) ) {
			$where[]  = 'direction = %s';
			$values[] = $args['direction'];
		}
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}
		if ( ! empty( $args['user_email'] ) ) {
			$where[]  = 'user_email LIKE %s';
			$values[] = '%' . $wpdb->esc_like( $args['user_email'] ) . '%';
		}
		if ( ! empty( $args['search'] ) ) {
			$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';

			$where[] = '(
		        event LIKE %s
		        OR direction LIKE %s
		        OR user_email LIKE %s
		        OR source_site LIKE %s
		        OR target_site LIKE %s
		        OR status LIKE %s
		        OR message LIKE %s
		        OR payload LIKE %s
		    )';

			$values = array_merge(
				$values,
				array_fill( 0, 8, $like )
			);
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$values[] = $args['date_from'] . ' 00:00:00';
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$values[] = $args['date_to'] . ' 23:59:59';
		}

		$view = isset( $args['view'] ) ? sanitize_key( $args['view'] ) : '';

		if ( 'trash' === $view ) {
			$where[] = 'post_status = %s';
			$values[] = 'trash';
		} else {
			$where[] = 'post_status = %s';
			$values[] = 'publish';
		}

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

		if ( $values ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is built from $wpdb->prefix and $where_sql contains the placeholders corresponding to $values.
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` {$where_sql}", ...$values ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is built from $wpdb->prefix and internal constant.
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` {$where_sql}" );
		}

		/*
		 * $table, $orderby and $order are trusted internal values:
		 * - $table is built from $wpdb->prefix and an internal constant.
		 * - $orderby is restricted to $allowed_orderby.
		 * - $order is restricted to ASC/DESC.
		 *
		 * $where_sql contains only internally defined SQL placeholders.
		 * All values corresponding to those placeholders are passed to prepare().
		 */
		$query = "SELECT * FROM `{$table}` {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic identifiers are validated above; $where_sql contains only internal placeholders and values are passed to prepare().
		$query = $wpdb->prepare( $query, ...array_merge( $values, array( $per_page, $offset ) ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $query has been prepared above; dynamic identifiers are validated and all values are prepared.
		$rows = $wpdb->get_results( $query, ARRAY_A );

		return array(
			'rows'  => $rows ? $rows : array(),
			'total' => $total,
			'pages' => $total ? (int) ceil( $total / $per_page ) : 1,
		);
	}

	/**
	 * Prune old log entries.
	 *
	 * @param int $days Number of days to keep.
	 *
	 * @return int Number of rows deleted.
	 */
	public static function prune( int $days = 90 ): int {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is built from $wpdb->prefix and internal constant.
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM `{$table_name}` WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)", $days ) );
	}

	public static function trash( array $ids ): int {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

		if ( ! $ids ) {
			return 0;
		}

		$table        = $wpdb->prefix . self::TABLE;
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$modified_at  = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is trusted; placeholders match $ids.
		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}`
            SET post_status = 'trash', modified_at = %s
            WHERE id IN ({$placeholders}) AND post_status = 'publish'",
				...array_merge(
					array( $modified_at ),
					$ids
				)
			)
		);
	}

	public static function restore( array $ids ): int {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

		if ( ! $ids ) {
			return 0;
		}

		$table        = $wpdb->prefix . self::TABLE;
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$modified_at  = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is trusted; placeholders match $ids.
		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}`
            SET post_status = 'publish', modified_at = %s
            WHERE id IN ({$placeholders}) AND post_status = 'trash'",
				...array_merge(
					array( $modified_at ),
					$ids
				)
			)
		);
	}

	/**
	 * Delete specific log rows by ID.
	 *
	 * @param int[] $ids Row IDs to delete.
	 *
	 * @return int Number of rows deleted.
	 */
	public static function delete( array $ids ): int {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( ! $ids ) {
			return 0;
		}

		$table        = $wpdb->prefix . self::TABLE;
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table trusted; $placeholders count matches $ids, all values passed to prepare().
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE id IN ({$placeholders})", ...$ids ) );
	}

	/**
	 * Create the logs table if missing.
	 */
	public static function create_table(): void {
		global $wpdb;

		$table   = $wpdb->prefix . self::TABLE;
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
			    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			    `event`       VARCHAR(20)  NOT NULL DEFAULT '',
			    `direction`   VARCHAR(10)  NOT NULL DEFAULT 'outgoing',
			    `user_email`  VARCHAR(100) NOT NULL DEFAULT '',
			    `source_site` VARCHAR(255) NOT NULL DEFAULT '',
			    `target_site` VARCHAR(255) NOT NULL DEFAULT '',
			    `status`      VARCHAR(10)  NOT NULL DEFAULT 'success',
			    `post_status` VARCHAR(10)  NOT NULL DEFAULT 'publish',
			    `message`     VARCHAR(500) NOT NULL DEFAULT '',
			    `payload`     LONGTEXT,
			    `created_at`  DATETIME     NOT NULL,
			    `modified_at` DATETIME     NOT NULL
			
			    PRIMARY KEY (`id`),
			    KEY `event`      (`event`),
			    KEY `status`     (`status`),
    			KEY `post_status` (`post_status`),
			    KEY `user_email` (`user_email`(50)),
			    KEY `created_at` (`created_at`),
			    KEY `modified_at` (`modified_at`)
			) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function get_log_counts(): array {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is built from $wpdb->prefix and an internal constant.
		$results = $wpdb->get_results(
			"SELECT
            COUNT(*) AS all_count,
            SUM( post_status = 'publish' ) AS publish_count,
            SUM( post_status = 'trash' ) AS trash_count,
            SUM( post_status = 'publish' AND status = 'success' ) AS success_count,
            SUM( post_status = 'publish' AND status = 'error' ) AS error_count
        FROM `{$table}`",
			ARRAY_A
		);

		return array(
			'all'     => (int) $results[0]['publish_count'],
			'success' => (int) $results[0]['success_count'],
			'error'   => (int) $results[0]['error_count'],
			'trash'   => (int) $results[0]['trash_count'],
		);
	}

	/**
	 * Drop the logs table.
	 */
	public static function drop_table(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is safe.
		$wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" );
	}

	/**
	 * Redact sensitive keys from data arrays.
	 *
	 * @param array $data Passed by reference and modified in-place.
	 */
	private static function strip_sensitive( array &$data ): void {
		$blocked = array( 'user_pass', 'password' );
		foreach ( $blocked as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$data[ $key ] = '[redacted]';
			}
		}

		if ( isset( $data['meta'] ) && is_array( $data['meta'] ) ) {
			foreach ( $blocked as $key ) {
				if ( isset( $data['meta'][ $key ] ) ) {
					$data['meta'][ $key ] = '[redacted]';
				}
			}
		}

		if ( isset( $data['user']['data'] ) && is_array( $data['user']['data'] ) ) {
			foreach ( $blocked as $key ) {
				if ( isset( $data['user']['data'][ $key ] ) ) {
					$data['user']['data'][ $key ] = '[redacted]';
				}
			}
		}
	}
}
