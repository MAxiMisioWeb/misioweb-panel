<?php
/**
 * Plugin Name: MisioWeb Progreso
 * Description: Muestra en el escritorio de WordPress qué falta para publicar el sitio y sincroniza el avance con el panel de proyecto de MisioWeb.
 * Version: 0.1.0
 * Author: MisioWeb
 * Requires PHP: 7.4
 * Text Domain: misioweb-progreso
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MisioWeb_Progreso {

	const VERSION = '0.1.0';
	const OPT     = 'mw_progreso_settings';
	const STATE   = 'mw_progreso_state';
	const CRON    = 'mw_progreso_sync';
	const SOON    = 'mw_progreso_sync_soon';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'widget' ) );
		add_action( 'admin_post_mw_progreso_sync', array( __CLASS__, 'handle_sync' ) );
		add_action( self::CRON, array( __CLASS__, 'sync' ) );
		add_action( self::SOON, array( __CLASS__, 'sync' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'on_publish' ), 10, 2 );
	}

	public static function activate() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::CRON );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON );
		wp_clear_scheduled_hook( self::SOON );
	}

	/* ---------- Ajustes ---------- */

	public static function menu() {
		add_options_page( 'MisioWeb Progreso', 'MisioWeb Progreso', 'manage_options', 'mw-progreso', array( __CLASS__, 'settings_page' ) );
	}

	public static function register() {
		register_setting( 'mw_progreso', self::OPT, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
	}

	public static function sanitize( $in ) {
		$url = isset( $in['api_url'] ) ? esc_url_raw( trim( $in['api_url'] ), array( 'https' ) ) : '';
		return array(
			'api_url' => $url,
			'site_id' => isset( $in['site_id'] ) ? sanitize_text_field( $in['site_id'] ) : '',
			'token'   => isset( $in['token'] ) ? sanitize_text_field( $in['token'] ) : '',
		);
	}

	private static function opt( $key ) {
		if ( 'token' === $key && defined( 'MW_PROGRESO_TOKEN' ) ) {
			return MW_PROGRESO_TOKEN;
		}
		$o = get_option( self::OPT, array() );
		return isset( $o[ $key ] ) ? $o[ $key ] : '';
	}

	public static function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o = get_option( self::OPT, array() );
		$g = function ( $k ) use ( $o ) {
			return isset( $o[ $k ] ) ? $o[ $k ] : '';
		};
		echo '<div class="wrap"><h1>MisioWeb Progreso</h1><form method="post" action="options.php">';
		settings_fields( 'mw_progreso' );
		echo '<table class="form-table">';
		printf( '<tr><th>URL del panel</th><td><input class="regular-text" type="url" name="%s[api_url]" value="%s" placeholder="https://panel.misioweb.com/api"></td></tr>', esc_attr( self::OPT ), esc_attr( $g( 'api_url' ) ) );
		printf( '<tr><th>ID del sitio</th><td><input class="regular-text" type="text" name="%s[site_id]" value="%s"></td></tr>', esc_attr( self::OPT ), esc_attr( $g( 'site_id' ) ) );
		printf( '<tr><th>Token</th><td><input class="regular-text" type="password" name="%s[token]" value="%s" autocomplete="off"><p class="description">Mejor definirlo en wp-config.php con <code>define( \'MW_PROGRESO_TOKEN\', \'...\' );</code></p></td></tr>', esc_attr( self::OPT ), esc_attr( $g( 'token' ) ) );
		echo '</table>';
		submit_button();
		echo '</form><p><a class="button" href="' . esc_url( self::sync_url() ) . '">Sincronizar ahora</a></p></div>';
	}

	/* ---------- Verificaciones automáticas ---------- */

	/**
	 * Revisa el sitio y devuelve solo señales (sí/no y conteos). Nunca claves ni datos de clientes.
	 */
	public static function checks() {
		$posts = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 30 ) );
		$full  = 0;
		foreach ( $posts as $p ) {
			if ( has_post_thumbnail( $p ) && strlen( wp_strip_all_tags( $p->post_content ) ) >= 500 ) {
				$full++;
			}
		}
		$c = array();

		$c['blog_entradas'] = array( 'label' => 'Blog: 2 entradas completas (texto e imagen)', 'ok' => $full >= 2, 'detail' => $full . ' completas' );

		$legal = 0;
		foreach ( array( 'privacidad', 'politica-de-privacidad', 'condiciones', 'terminos-y-condiciones' ) as $slug ) {
			$pg = get_page_by_path( $slug );
			if ( $pg && 'publish' === $pg->post_status ) {
				$legal++;
			}
		}
		$c['paginas_legales'] = array( 'label' => 'Privacidad y condiciones publicadas', 'ok' => $legal >= 2, 'detail' => $legal . ' páginas' );

		if ( class_exists( 'WooCommerce' ) ) {
			$products = (int) wp_count_posts( 'product' )->publish;
			$mp       = false;
			if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
				foreach ( WC()->payment_gateways()->payment_gateways() as $id => $gw ) {
					if ( false !== stripos( $id, 'mercado' ) && 'yes' === $gw->enabled ) {
						$mp = true;
					}
				}
			}
			$c['tienda_productos'] = array( 'label' => 'Tienda: productos cargados', 'ok' => $products > 0, 'detail' => $products . ' productos' );
			$c['tienda_pasarela']  = array( 'label' => 'Tienda: Mercado Pago activo', 'ok' => $mp, 'detail' => $mp ? 'activo' : 'sin activar' );
		}

		$reels = false;
		foreach ( (array) get_option( 'active_plugins', array() ) as $file ) {
			if ( false !== stripos( $file, 'reelsloop' ) ) {
				$reels = true;
			}
		}
		$c['reels_plugin'] = array( 'label' => 'Reels de Instagram: plugin activo', 'ok' => $reels, 'detail' => $reels ? 'activo' : 'no instalado' );

		return apply_filters( 'mw_progreso_checks', $c );
	}

	/* ---------- Sincronización ---------- */

	public static function on_publish( $new, $old ) {
		if ( 'publish' === $new && 'publish' !== $old && ! wp_next_scheduled( self::SOON ) ) {
			wp_schedule_single_event( time() + 60, self::SOON );
		}
	}

	public static function sync() {
		$state  = array( 'at' => time(), 'local' => self::checks() );
		$api    = self::opt( 'api_url' );
		$token  = self::opt( 'token' );
		$siteid = self::opt( 'site_id' );

		if ( $api && $token && $siteid ) {
			$signals = array();
			foreach ( $state['local'] as $k => $v ) {
				$signals[ $k ] = array( 'ok' => (bool) $v['ok'], 'detail' => (string) $v['detail'] );
			}
			$res = wp_remote_post(
				trailingslashit( $api ) . 'v1/sync',
				array(
					'timeout' => 15,
					'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode( array(
						'site_id' => $siteid,
						'site'    => home_url(),
						'plugin'  => self::VERSION,
						'wp'      => get_bloginfo( 'version' ),
						'signals' => $signals,
					) ),
				)
			);
			if ( is_wp_error( $res ) ) {
				$state['error'] = $res->get_error_message();
			} elseif ( 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
				$state['error'] = 'El panel respondió con el código ' . (int) wp_remote_retrieve_response_code( $res );
			} else {
				$body = json_decode( wp_remote_retrieve_body( $res ), true );
				if ( is_array( $body ) ) {
					$state['remote'] = $body;
				}
			}
		}
		update_option( self::STATE, $state, false );
		return $state;
	}

	private static function sync_url() {
		return wp_nonce_url( admin_url( 'admin-post.php?action=mw_progreso_sync' ), 'mw_progreso_sync' );
	}

	public static function handle_sync() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sin permisos.' );
		}
		check_admin_referer( 'mw_progreso_sync' );
		self::sync();
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/* ---------- Widget del escritorio ---------- */

	public static function widget() {
		if ( current_user_can( 'manage_options' ) ) {
			wp_add_dashboard_widget( 'mw_progreso', 'Avance de tu sitio · MisioWeb', array( __CLASS__, 'render' ) );
		}
	}

	public static function render() {
		$s = get_option( self::STATE );
		if ( ! $s ) {
			$s = self::sync();
		}
		$items = array();
		if ( ! empty( $s['remote']['items'] ) && is_array( $s['remote']['items'] ) ) {
			foreach ( $s['remote']['items'] as $it ) {
				$items[] = array( 'label' => isset( $it['label'] ) ? $it['label'] : '', 'ok' => isset( $it['status'] ) && 'done' === $it['status'] );
			}
		} else {
			foreach ( $s['local'] as $it ) {
				$items[] = array( 'label' => $it['label'], 'ok' => $it['ok'] );
			}
		}
		$done = count( array_filter( wp_list_pluck( $items, 'ok' ) ) );
		$pct  = isset( $s['remote']['progress'] ) ? (int) $s['remote']['progress'] : ( $items ? (int) round( $done / count( $items ) * 100 ) : 0 );

		printf( '<p><strong>%d%%</strong> completado</p><div style="height:8px;background:#e5e5e5;border-radius:4px;overflow:hidden"><div style="height:100%%;width:%d%%;background:#007C75"></div></div><ul style="margin-top:12px">', $pct, $pct );
		foreach ( $items as $it ) {
			printf( '<li>%s %s</li>', $it['ok'] ? '✅' : '⏳', esc_html( $it['label'] ) );
		}
		echo '</ul>';
		if ( ! empty( $s['remote']['message'] ) ) {
			echo '<p>' . esc_html( $s['remote']['message'] ) . '</p>';
		}
		if ( ! empty( $s['error'] ) ) {
			echo '<p style="color:#b32d2e">No se pudo conectar con el panel: ' . esc_html( $s['error'] ) . '</p>';
		}
		printf( '<p><a class="button" href="%s">Sincronizar ahora</a> <span class="description">Última vez: %s</span></p>', esc_url( self::sync_url() ), esc_html( wp_date( 'd/m H:i', $s['at'] ) ) );
	}
}

MisioWeb_Progreso::init();
register_activation_hook( __FILE__, array( 'MisioWeb_Progreso', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MisioWeb_Progreso', 'deactivate' ) );
