<?php

abstract class Red_FileIO {
	public static function create( $type ) {
		$exporter = false;

		if ( $type === 'rss' ) {
			include_once dirname( dirname( __FILE__ ) ) . '/fileio/rss.php';
			$exporter = new Red_Rss_File();
		} elseif ( $type === 'csv' ) {
			include_once dirname( dirname( __FILE__ ) ) . '/fileio/csv.php';
			$exporter = new Red_Csv_File();
		} elseif ( $type === 'apache' ) {
			include_once dirname( dirname( __FILE__ ) ) . '/fileio/apache.php';
			$exporter = new Red_Apache_File();
		} elseif ( $type === 'nginx' ) {
			include_once dirname( dirname( __FILE__ ) ) . '/fileio/nginx.php';
			$exporter = new Red_Nginx_File();
		} elseif ( $type === 'json' ) {
			include_once dirname( dirname( __FILE__ ) ) . '/fileio/json.php';
			$exporter = new Red_Json_File();
		} elseif ( $type === 'redirects' ) {
			include_once dirname( dirname( __FILE__ ) ) . '/fileio/redirects.php';
			$exporter = new Red_Redirects_File();
		}

		return $exporter;
	}

	public static function import( $group_id, $file ) {
		$parts = pathinfo( $file['name'] );
		$extension = isset( $parts['extension'] ) ? $parts['extension'] : '';
		$extension = strtolower( $extension );
		$basename = strtolower( $parts['basename'] );

		if ( $extension === 'csv' || $extension === 'txt' ) {
			include_once dirname( dirname( __FILE__ ) ) . '/fileio/csv.php';
			$importer = new Red_Csv_File();
			$data = '';
		} elseif ( $extension === 'json' ) {
			include_once dirname( dirname( __FILE__ ) ) . '/fileio/json.php';
			$importer = new Red_Json_File();
			$data = @file_get_contents( $file['tmp_name'] );
		} elseif ( $basename === '_redirects' ) {
			include_once dirname( dirname( __FILE__ ) ) . '/fileio/redirects.php';
			$importer = new Red_Redirects_File();
			$data = @file_get_contents( $file['tmp_name'] );
		} else {
			include_once dirname( dirname( __FILE__ ) ) . '/fileio/apache.php';
			$importer = new Red_Apache_File();
			$data = @file_get_contents( $file['tmp_name'] );
		}

		if ( $extension !== 'json' ) {
			$group = Red_Group::get( $group_id );
			if ( ! $group ) {
				return false;
			}
		}

		return $importer->load( $group_id, $file['tmp_name'], $data );
	}

	public function force_download() {
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Expires: Mon, 26 Jul 1997 05:00:00 GMT' );
	}

	protected function export_filename( $extension ) {
		$name = wp_parse_url( home_url(), PHP_URL_HOST );

		$name = sanitize_text_field( $name );
		$name = str_replace( '.', '-', $name );
		$date = strtolower( date_i18n( get_option( 'date_format' ) ) );
		$date = str_replace( [ ',', ' ', '--' ], '-', $date );

		return 'redirection-' . $name . '-' . $date . '.' . sanitize_text_field( $extension );
	}

	public static function export( $module_name_or_id, $format ) {
		$groups = false;
		$items = false;

		if ( $module_name_or_id === 'all' || $module_name_or_id === 0 ) {
			$groups = Red_Group::get_all();
			$items = Red_Item::get_all();

			$groups = self::filter_groups_to_export( 'all', $groups );
			$items = self::filter_items_to_export( 'all', $items, $groups );
		} else {
			$module_name_or_id = is_numeric( $module_name_or_id ) ? $module_name_or_id : Red_Module::get_id_for_name( $module_name_or_id );
			$module = Red_Module::get( intval( $module_name_or_id, 10 ) );

			if ( $module ) {
				$groups = Red_Group::get_all_for_module( $module->get_id() );
				$items = Red_Item::get_all_for_module( $module->get_id() );

				$groups = self::filter_groups_to_export( $module->get_name(), $groups );
				$items = self::filter_items_to_export( $module->get_name(), $items, $groups );
			}
		}

		$exporter = self::create( $format );
		if ( $exporter && $items !== false && $groups !== false ) {
			return [
				'data' => $exporter->get_data( $items, $groups ),
				'total' => count( $items ),
				'exporter' => $exporter,
			];
		}

		return false;
	}

	private static function filter_items_to_export( $module_name, $items, $groups ) {
		$items = apply_filters( 'redirection_export_items', $items, $groups );
		$items = apply_filters( 'redirection_export_items_' . $module_name, $items, $groups );

		return $items;
	}

	private static function filter_groups_to_export( $module_name, $groups ) {
		$groups = apply_filters( 'redirection_export_groups', $groups );
		$groups = apply_filters( 'redirection_export_groups_' . $module_name, $groups );

		return $groups;
	}

	abstract public function get_data( array $items, array $groups );
	abstract public function load( $group, $filename, $data );
}
