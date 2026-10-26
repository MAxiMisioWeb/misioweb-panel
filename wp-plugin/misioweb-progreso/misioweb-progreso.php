<?php
/**
 * Plugin Name: MisioWeb Progreso
 * Description: Muestra en el escritorio de WordPress qué falta para publicar el sitio y sincroniza el avance con el panel de proyecto de MisioWeb.
 * Version: 0.2.2
 * Author: MisioWeb
 * Requires at least: 5.3
 * Requires PHP: 7.4
 * Text Domain: misioweb-progreso
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MisioWeb_Progreso {

	const VERSION = '0.2.2';
	const OPT     = 'mw_progreso_settings';
	const STATE   = 'mw_progreso_state';
	const CRON    = 'mw_progreso_sync';
	const SOON    = 'mw_progreso_sync_soon';

	/** Panel de MisioWeb: se usa si la URL del panel queda vacía. */
	const DEFAULT_API_URL = 'https://panel.misioweb.com/api';

	/** Caracteres válidos en un token (alfanuméricos, base64 y base64url). */
	const TOKEN_CHARS = '/[^A-Za-z0-9._~+\/=-]/';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_init', array( __CLASS__, 'ensure_cron' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'widget' ) );
		add_action( 'admin_post_mw_progreso_sync', array( __CLASS__, 'handle_sync' ) );
		add_action( self::CRON, array( __CLASS__, 'sync' ) );
		add_action( self::SOON, array( __CLASS__, 'sync' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'on_publish' ), 10, 2 );
	}

	public static function load_textdomain() {
		load_plugin_textdomain( 'misioweb-progreso', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	public static function activate() {
		self::ensure_cron();
	}

	/**
	 * Programa la sincronización horaria si falta: cubre actualizaciones del plugin
	 * sin reactivar y sitios de una red multisitio activados desde la red.
	 */
	public static function ensure_cron() {
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
		$in   = is_array( $in ) ? $in : array();
		$prev = get_option( self::OPT, array() );
		$prev = is_array( $prev ) ? $prev : array();

		// El token no se vuelve a mostrar en el formulario: vacío significa "dejar el que estaba".
		$token = isset( $in['token'] ) ? preg_replace( self::TOKEN_CHARS, '', trim( (string) $in['token'] ) ) : '';
		if ( '' === $token && empty( $in['token_clear'] ) && isset( $prev['token'] ) ) {
			$token = $prev['token'];
		}

		$raw = isset( $in['api_url'] ) ? trim( (string) $in['api_url'] ) : '';
		$url = '' === $raw ? '' : esc_url_raw( $raw, array( 'https' ) );
		if ( '' !== $raw && '' === $url && function_exists( 'add_settings_error' ) ) {
			add_settings_error( self::OPT, 'api_url', __( 'La URL del panel tiene que empezar con https://, por ejemplo https://panel.misioweb.com/api', 'misioweb-progreso' ) );
		}

		return array(
			'api_url' => $url,
			'site_id' => isset( $in['site_id'] ) ? sanitize_text_field( $in['site_id'] ) : '',
			'token'   => $token,
		);
	}

	private static function opt( $key ) {
		if ( 'token' === $key && defined( 'MW_PROGRESO_TOKEN' ) ) {
			return MW_PROGRESO_TOKEN;
		}
		$o     = get_option( self::OPT, array() );
		$value = isset( $o[ $key ] ) ? $o[ $key ] : '';
		if ( 'api_url' === $key && '' === $value ) {
			return self::DEFAULT_API_URL;
		}
		return $value;
	}

	/**
	 * Datos de conexión que faltan (vacío si está todo).
	 *
	 * @return string[]
	 */
	public static function missing_config() {
		$labels = array(
			'api_url' => __( 'URL del panel', 'misioweb-progreso' ),
			'site_id' => __( 'ID del sitio', 'misioweb-progreso' ),
			'token'   => __( 'Token', 'misioweb-progreso' ),
		);
		$missing = array();
		foreach ( $labels as $key => $label ) {
			if ( '' === (string) self::opt( $key ) ) {
				$missing[] = $label;
			}
		}
		return $missing;
	}

	public static function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o = get_option( self::OPT, array() );
		$g = function ( $k ) use ( $o ) {
			return isset( $o[ $k ] ) ? $o[ $k ] : '';
		};
		echo '<div class="wrap"><h1>MisioWeb Progreso</h1>';
		settings_errors( self::OPT );
		$missing = self::missing_config();
		$state   = get_option( self::STATE );
		if ( $missing ) {
			printf( '<div class="notice notice-warning"><p>%s %s</p></div>', esc_html__( 'Falta configurar:', 'misioweb-progreso' ), esc_html( implode( ', ', $missing ) ) );
		} elseif ( ! empty( $state['error'] ) ) {
			printf( '<div class="notice notice-error"><p>%s %s</p></div>', esc_html__( 'Última sincronización con error:', 'misioweb-progreso' ), esc_html( $state['error'] ) );
		} elseif ( ! empty( $state['remote_at'] ) ) {
			/* translators: %s: fecha y hora. */
			printf( '<div class="notice notice-success"><p>%s</p></div>', esc_html( sprintf( __( 'Conectado con el panel. Última sincronización: %s', 'misioweb-progreso' ), wp_date( 'd/m H:i', $state['remote_at'] ) ) ) );
		}
		echo '<form method="post" action="options.php">';
		settings_fields( 'mw_progreso' );
		echo '<table class="form-table">';
		printf( '<tr><th>%s</th><td><input class="regular-text" type="url" name="%s[api_url]" value="%s"><p class="description">%s</p></td></tr>', esc_html__( 'URL del panel', 'misioweb-progreso' ), esc_attr( self::OPT ), esc_attr( self::opt( 'api_url' ) ), esc_html__( 'Normalmente no hay que cambiarla. Solo se aceptan direcciones HTTPS.', 'misioweb-progreso' ) );
		printf( '<tr><th>%s</th><td><input class="regular-text" type="text" name="%s[site_id]" value="%s"></td></tr>', esc_html__( 'ID del sitio', 'misioweb-progreso' ), esc_attr( self::OPT ), esc_attr( $g( 'site_id' ) ) );
		echo '<tr><th>' . esc_html__( 'Token', 'misioweb-progreso' ) . '</th><td>';
		if ( defined( 'MW_PROGRESO_TOKEN' ) ) {
			echo '<p>' . esc_html__( 'Definido en wp-config.php.', 'misioweb-progreso' ) . '</p>';
		} else {
			$saved = '' !== $g( 'token' );
			printf(
				'<input class="regular-text" type="password" name="%s[token]" value="" autocomplete="new-password" placeholder="%s">',
				esc_attr( self::OPT ),
				esc_attr( $saved ? __( 'Guardado. Dejar vacío para mantenerlo.', 'misioweb-progreso' ) : '' )
			);
			if ( $saved ) {
				printf( '<p><label><input type="checkbox" name="%s[token_clear]" value="1"> %s</label></p>', esc_attr( self::OPT ), esc_html__( 'Borrar el token guardado', 'misioweb-progreso' ) );
			}
			echo '<p class="description">' . esc_html__( 'Mejor definirlo en wp-config.php con', 'misioweb-progreso' ) . ' <code>define( \'MW_PROGRESO_TOKEN\', \'...\' );</code></p>';
		}
		echo '</td></tr></table>';
		submit_button();
		echo '</form><p><a class="button" href="' . esc_url( self::sync_url() ) . '">' . esc_html__( 'Sincronizar ahora', 'misioweb-progreso' ) . '</a></p></div>';
	}

	/* ---------- Verificaciones automáticas ---------- */

	/**
	 * Revisa el sitio y devuelve solo señales (sí/no y conteos). Nunca claves ni datos de clientes.
	 *
	 * Cada señal: label (solo para mostrar en el sitio), ok, detail (se envía al panel) y,
	 * opcional, na = true cuando no aplica a este sitio (no se muestra en la lista local).
	 */
	public static function checks() {
		$posts = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 30 ) );
		$full  = 0;
		foreach ( $posts as $p ) {
			if ( has_post_thumbnail( $p ) && mb_strlen( wp_strip_all_tags( $p->post_content ) ) >= 500 ) {
				$full++;
			}
		}
		$c = array();

		$c['blog_entradas'] = array( 'label' => __( 'Blog: 2 entradas completas (texto e imagen)', 'misioweb-progreso' ), 'ok' => $full >= 2, 'detail' => $full . ' completas' );

		$privacy = self::legal_page_published( (int) get_option( 'wp_page_for_privacy_policy' ), 'privacy' );
		$terms   = self::legal_page_published( (int) get_option( 'woocommerce_terms_page_id' ), 'terms' );

		$c['paginas_legales'] = array(
			'label'  => __( 'Privacidad y condiciones publicadas', 'misioweb-progreso' ),
			'ok'     => $privacy && $terms,
			'detail' => 'privacidad: ' . ( $privacy ? 'sí' : 'no' ) . ', condiciones: ' . ( $terms ? 'sí' : 'no' ),
		);

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
			$c['tienda_productos'] = array( 'label' => __( 'Tienda: productos cargados', 'misioweb-progreso' ), 'ok' => $products > 0, 'detail' => $products . ' productos' );
			$c['tienda_pasarela']  = array( 'label' => __( 'Tienda: Mercado Pago activo', 'misioweb-progreso' ), 'ok' => $mp, 'detail' => $mp ? 'activo' : 'sin activar' );
		} else {
			// Se envían igual para que el panel distinga "falta WooCommerce" de "no hay datos".
			$c['tienda_productos'] = array( 'label' => __( 'Tienda: productos cargados', 'misioweb-progreso' ), 'ok' => false, 'detail' => 'WooCommerce no activo', 'na' => true );
			$c['tienda_pasarela']  = array( 'label' => __( 'Tienda: Mercado Pago activo', 'misioweb-progreso' ), 'ok' => false, 'detail' => 'WooCommerce no activo', 'na' => true );
		}

		$plugins = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$plugins = array_merge( $plugins, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		$reels = false;
		foreach ( $plugins as $file ) {
			if ( false !== stripos( $file, 'reelsloop' ) ) {
				$reels = true;
			}
		}
		$c['reels_plugin'] = array( 'label' => __( 'Reels de Instagram: plugin activo', 'misioweb-progreso' ), 'ok' => $reels, 'detail' => $reels ? 'activo' : 'no instalado' );

		return apply_filters( 'mw_progreso_checks', $c );
	}

	/**
	 * ¿Hay una página legal publicada? Primero mira la página configurada en WordPress o
	 * WooCommerce y, si no, busca por slug.
	 *
	 * @param int    $page_id Página configurada (0 si no hay).
	 * @param string $kind    'privacy' o 'terms'.
	 */
	private static function legal_page_published( $page_id, $kind ) {
		if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
			return true;
		}
		$slugs = array(
			'privacy' => array( 'privacidad', 'politica-de-privacidad', 'privacy-policy' ),
			'terms'   => array( 'condiciones', 'terminos-y-condiciones', 'terminos', 'terms-and-conditions' ),
		);
		$slugs = apply_filters( 'mw_progreso_legal_slugs', $slugs );
		foreach ( isset( $slugs[ $kind ] ) ? (array) $slugs[ $kind ] : array() as $slug ) {
			$pg = get_page_by_path( $slug );
			if ( $pg && 'publish' === $pg->post_status ) {
				return true;
			}
		}
		return false;
	}

	/* ---------- Sincronización ---------- */

	public static function on_publish( $new, $old ) {
		if ( 'publish' === $new && 'publish' !== $old ) {
			self::sync_soon();
		}
	}

	private static function sync_soon() {
		if ( ! wp_next_scheduled( self::SOON ) ) {
			wp_schedule_single_event( time() + 60, self::SOON );
		}
	}

	public static function sync() {
		$state  = array( 'at' => time(), 'local' => self::checks() );
		$api    = self::opt( 'api_url' );
		$token  = self::opt( 'token' );
		$siteid = self::opt( 'site_id' );

		$missing = self::missing_config();
		if ( $missing ) {
			/* translators: %s: datos que faltan. */
			$state['error'] = sprintf( __( 'falta configurar %s en Ajustes → MisioWeb Progreso.', 'misioweb-progreso' ), implode( ', ', $missing ) );
		}

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
				/* translators: %d: código de respuesta HTTP. */
				$state['error'] = sprintf( __( 'El panel respondió con el código %d', 'misioweb-progreso' ), (int) wp_remote_retrieve_response_code( $res ) );
			} else {
				$body = json_decode( wp_remote_retrieve_body( $res ), true );
				if ( is_array( $body ) ) {
					$state['remote']    = $body;
					$state['remote_at'] = $state['at'];
				} else {
					$state['error'] = __( 'El panel devolvió una respuesta inválida', 'misioweb-progreso' );
				}
			}

			// Si falló, se conserva lo último que respondió el panel para no perder el avance.
			if ( isset( $state['error'] ) ) {
				$prev = get_option( self::STATE );
				if ( ! empty( $prev['remote'] ) ) {
					$state['remote']    = $prev['remote'];
					$state['remote_at'] = isset( $prev['remote_at'] ) ? $prev['remote_at'] : $prev['at'];
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
			wp_die( esc_html__( 'Sin permisos.', 'misioweb-progreso' ) );
		}
		check_admin_referer( 'mw_progreso_sync' );
		self::sync();
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/* ---------- Widget del escritorio ---------- */

	public static function widget() {
		if ( current_user_can( 'manage_options' ) ) {
			wp_add_dashboard_widget( 'mw_progreso', __( 'Avance de tu sitio · MisioWeb', 'misioweb-progreso' ), array( __CLASS__, 'render' ) );
		}
	}

	public static function render() {
		$s = get_option( self::STATE );
		if ( ! $s || ! isset( $s['local'] ) ) {
			// Primera vez: se muestra lo local sin esperar al panel y se sincroniza por cron.
			$s = array( 'at' => 0, 'local' => self::checks() );
			self::sync_soon();
		}
		$items = array();
		if ( ! empty( $s['remote']['items'] ) && is_array( $s['remote']['items'] ) ) {
			foreach ( $s['remote']['items'] as $it ) {
				$items[] = array( 'label' => isset( $it['label'] ) ? (string) $it['label'] : '', 'ok' => isset( $it['status'] ) && 'done' === $it['status'] );
			}
		} else {
			foreach ( $s['local'] as $it ) {
				if ( empty( $it['na'] ) ) {
					$items[] = array( 'label' => $it['label'], 'ok' => (bool) $it['ok'] );
				}
			}
		}
		$done = count( array_filter( wp_list_pluck( $items, 'ok' ) ) );
		$pct  = isset( $s['remote']['progress'] ) ? (int) $s['remote']['progress'] : ( $items ? (int) round( $done / count( $items ) * 100 ) : 0 );
		$pct  = max( 0, min( 100, $pct ) );

		/* translators: %d: porcentaje completado. */
		printf( '<p><strong>' . esc_html__( '%d%% completado', 'misioweb-progreso' ) . '</strong></p>', $pct );
		printf( '<div style="height:8px;background:#e5e5e5;border-radius:4px;overflow:hidden"><div style="height:100%%;width:%d%%;background:#007C75"></div></div><ul style="margin-top:12px">', $pct );
		foreach ( $items as $it ) {
			printf(
				'<li><span class="dashicons %s" style="color:%s" aria-hidden="true"></span> <span class="screen-reader-text">%s</span>%s</li>',
				$it['ok'] ? 'dashicons-yes-alt' : 'dashicons-clock',
				$it['ok'] ? '#007C75' : '#8c8f94',
				$it['ok'] ? esc_html__( 'Hecho:', 'misioweb-progreso' ) : esc_html__( 'Pendiente:', 'misioweb-progreso' ),
				esc_html( $it['label'] )
			);
		}
		echo '</ul>';
		if ( ! empty( $s['remote']['message'] ) ) {
			echo '<p>' . esc_html( $s['remote']['message'] ) . '</p>';
		}
		if ( ! empty( $s['error'] ) ) {
			echo '<p style="color:#b32d2e">' . esc_html__( 'No se pudo conectar con el panel:', 'misioweb-progreso' ) . ' ' . esc_html( $s['error'] );
			if ( ! empty( $s['remote_at'] ) ) {
				/* translators: %s: fecha y hora de la última sincronización correcta. */
				echo ' ' . esc_html( sprintf( __( '(se muestra lo sincronizado el %s)', 'misioweb-progreso' ), wp_date( 'd/m H:i', $s['remote_at'] ) ) );
			}
			echo '</p>';
		}
		$last = $s['at'] ? wp_date( 'd/m H:i', $s['at'] ) : __( 'pendiente', 'misioweb-progreso' );
		printf( '<p><a class="button" href="%s">%s</a> <span class="description">%s %s</span></p>', esc_url( self::sync_url() ), esc_html__( 'Sincronizar ahora', 'misioweb-progreso' ), esc_html__( 'Última vez:', 'misioweb-progreso' ), esc_html( $last ) );
	}
}

MisioWeb_Progreso::init();
register_activation_hook( __FILE__, array( 'MisioWeb_Progreso', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MisioWeb_Progreso', 'deactivate' ) );
