<?php

namespace EntireUserSync\Sync;

class Logger {

	use SyncHelper;

	public const TABLE = 'entireus_logs';

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
				'source_site' => esc_url_raw( $args['source_site'] ?? get_site_url() ),
				'target_site' => esc_url_raw( $args['target_site'] ?? '' ),
				'status'      => sanitize_key( $args['status'] ?? 'success' ),
				'message'     => sanitize_text_field( $args['message'] ?? '' ),
				'payload'     => wp_json_encode( $payload ),
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	public static function query( array $args = array() ): array {
		global $wpdb;

		$table    = $wpdb->prefix . self::TABLE;
		$per_page = max( 1, (int) ( $args['per_page'] ?? 50 ) );
		$page     = max( 1, (int) ( $args['paged'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$allowed_order   = array( 'ASC', 'DESC' );
		$allowed_orderby = array( 'id', 'event', 'direction', 'user_email', 'source_site', 'target_site', 'status', 'created_at' );

		$orderby = in_array( $args['orderby'] ?? '', $allowed_orderby, true )
		? $args['orderby'] : 'created_at';
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
		if ( ! empty( $args['site'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['site'] ) . '%';
			$where[]  = '( source_site LIKE %s OR target_site LIKE %s )';
			$values[] = $like;
			$values[] = $like;
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$values[] = $args['date_from'] . ' 00:00:00';
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$values[] = $args['date_to'] . ' 23:59:59';
		}

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$count_sql = "SELECT COUNT(*) FROM `{$table}` {$where_sql}";
		$total     = (int) ( $values
		? $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) )
		: $wpdb->get_var( $count_sql ) );

		$rows_sql   = "SELECT * FROM `{$table}` {$where_sql} ORDER BY `{$orderby}` {$order} LIMIT %d OFFSET %d";
		$all_values = array_merge( $values, array( $per_page, $offset ) );
		$rows       = $wpdb->get_results( $wpdb->prepare( $rows_sql, $all_values ), ARRAY_A );

		return array(
			'rows'  => $rows ?: array(),
			'total' => $total,
			'pages' => $total ? (int) ceil( $total / $per_page ) : 1,
		);
	}

	public static function prune( int $days = 90 ): int {
		global $wpdb;
		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `{$wpdb->prefix}" . self::TABLE . '` WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)',
				$days
			)
		);
	}

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
			`message`     VARCHAR(500) NOT NULL DEFAULT '',
			`payload`     LONGTEXT,
			`created_at`  DATETIME     NOT NULL,
			PRIMARY KEY  (`id`),
			KEY `event`      (`event`),
			KEY `status`     (`status`),
			KEY `user_email` (`user_email`(50)),
			KEY `created_at` (`created_at`)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function drop_table(): void {
		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}" . self::TABLE . '`' );
	}

	private static function strip_sensitive( array &$data ): void {
		$blocked = array( 'password_hash', 'user_pass', 'password', 'pass' );
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
	}
}
