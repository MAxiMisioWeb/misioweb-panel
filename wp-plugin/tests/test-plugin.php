<?php
/**
 * Tests de integración del plugin MisioWeb Progreso.
 *
 * Se corren dentro de un WordPress real con WP-CLI (ver tests/README.md):
 *   wp eval-file wp-plugin/tests/test-plugin.php           (con WooCommerce y Mercado Pago activos)
 *   wp eval-file wp-plugin/tests/test-plugin.php sin-woo --skip-plugins=woocommerce,woocommerce-mercadopago
 *
 * Cada test crea lo que necesita y lo borra al terminar. Sale con código 1 si algo falla.
 */

if ( ! class_exists( 'MisioWeb_Progreso' ) ) {
	fwrite( STDERR, "El plugin misioweb-progreso no está activo.\n" );
	exit( 1 );
}

// eval-file corre este archivo dentro de una función: declarar las variables compartidas como globales.
global $wpdb, $mw_mode, $mw_failed, $mw_passed, $mw_trash, $mw_image;

$mw_mode   = isset( $args[0] ) ? $args[0] : 'con-woo';
$mw_failed = 0;
$mw_passed = 0;
$mw_trash  = array();

function mw_assert( $cond, $msg ) {
	global $mw_failed, $mw_passed;
	if ( $cond ) {
		$mw_passed++;
		echo "  ok   $msg\n";
	} else {
		$mw_failed++;
		echo "  FAIL $msg\n";
	}
}

function mw_test( $name, $fn ) {
	echo "$name\n";
	try {
		$fn();
	} catch ( Throwable $e ) {
		mw_assert( false, 'excepción: ' . $e->getMessage() );
	}
	mw_cleanup();
}

function mw_post( $args ) {
	global $mw_trash;
	$id         = wp_insert_post( $args + array( 'post_status' => 'publish', 'post_title' => 'Test' ), true );
	$mw_trash[] = $id;
	return $id;
}

function mw_full_post( $chars = 600, $thumb = true ) {
	$id = mw_post( array( 'post_type' => 'post', 'post_content' => '<p>' . str_repeat( 'á', $chars ) . '</p>' ) );
	if ( $thumb ) {
		global $mw_image;
		set_post_thumbnail( $id, $mw_image );
	}
	return $id;
}

/** Crea un JPEG real en uploads: set_post_thumbnail() rechaza adjuntos sin imagen. */
function mw_create_image() {
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$up   = wp_upload_dir();
	$file = trailingslashit( $up['path'] ) . 'mw-progreso-test.jpg';
	$img  = imagecreatetruecolor( 20, 20 );
	imagejpeg( $img, $file );
	imagedestroy( $img );
	$id = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => 'mw-progreso-test', 'post_status' => 'inherit' ), $file );
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
	return $id;
}

function mw_cleanup() {
	global $mw_trash;
	foreach ( $mw_trash as $id ) {
		wp_delete_post( $id, true );
	}
	$mw_trash = array();
	delete_option( MisioWeb_Progreso::OPT );
	delete_option( MisioWeb_Progreso::STATE );
	remove_all_filters( 'pre_http_request' );
}

function mw_check( $key ) {
	$c = MisioWeb_Progreso::checks();
	return isset( $c[ $key ] ) ? $c[ $key ] : null;
}

function mw_render() {
	ob_start();
	MisioWeb_Progreso::render();
	return ob_get_clean();
}

/** Simula el panel: guarda el pedido y responde lo indicado. */
function mw_fake_panel( $response, &$captured ) {
	add_filter(
		'pre_http_request',
		function ( $pre, $r, $url ) use ( $response, &$captured ) {
			$captured = array( 'url' => $url, 'args' => $r );
			return $response;
		},
		10,
		3
	);
}

function mw_configure( $token = 'tok-123' ) {
	update_option( MisioWeb_Progreso::OPT, array( 'api_url' => 'https://panel.test/api', 'site_id' => 'sankalpa', 'token' => $token ) );
}

// Arrancar sin páginas legales ni entradas que vengan de la instalación: se pasan a borrador
// con otro slug (para no chocar con los de los tests) y se restauran al final.
$mw_privacy_id = (int) get_option( 'wp_page_for_privacy_policy' );
update_option( 'wp_page_for_privacy_policy', 0 );
$mw_terms_id = (int) get_option( 'woocommerce_terms_page_id' );
update_option( 'woocommerce_terms_page_id', 0 );
$mw_old_posts = get_posts( array( 'post_type' => array( 'post', 'page', 'product' ), 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'numberposts' => -1 ) );
foreach ( $mw_old_posts as $mw_p ) {
	$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft', 'post_name' => $mw_p->post_name . '__mw' ), array( 'ID' => $mw_p->ID ) );
	clean_post_cache( $mw_p->ID );
}
$mw_image = mw_create_image();

echo "== Modo: $mw_mode ==\n";

/* ---------- Blog ---------- */

mw_test( 'Blog: cuenta solo entradas con imagen y 500 caracteres o más', function () {
	mw_assert( false === mw_check( 'blog_entradas' )['ok'], 'sin entradas no está ok' );
	mw_full_post();
	mw_assert( '1 completas' === mw_check( 'blog_entradas' )['detail'], 'una entrada completa' );
	mw_full_post( 600, false );
	mw_full_post( 100 );
	mw_assert( false === mw_check( 'blog_entradas' )['ok'], 'sin imagen o con texto corto no cuenta' );
	mw_full_post();
	mw_assert( true === mw_check( 'blog_entradas' )['ok'], 'dos entradas completas dan ok' );
} );

mw_test( 'Blog: 500 caracteres con acentos cuentan como 500, no por bytes', function () {
	mw_full_post( 500 );
	mw_full_post( 499 );
	mw_assert( '1 completas' === mw_check( 'blog_entradas' )['detail'], '500 «á» cuentan, 499 no' );
} );

/* ---------- Páginas legales ---------- */

mw_test( 'Legales: dos páginas de privacidad sin condiciones no alcanzan', function () {
	mw_post( array( 'post_type' => 'page', 'post_name' => 'privacidad' ) );
	mw_post( array( 'post_type' => 'page', 'post_name' => 'politica-de-privacidad' ) );
	$c = mw_check( 'paginas_legales' );
	mw_assert( false === $c['ok'], 'no está ok' );
	mw_assert( 'privacidad: sí, condiciones: no' === $c['detail'], 'el detalle dice qué falta' );
} );

mw_test( 'Legales: privacidad y condiciones por slug', function () {
	mw_post( array( 'post_type' => 'page', 'post_name' => 'privacidad' ) );
	mw_post( array( 'post_type' => 'page', 'post_name' => 'terminos-y-condiciones', 'post_status' => 'draft' ) );
	mw_assert( false === mw_check( 'paginas_legales' )['ok'], 'condiciones en borrador no cuentan' );
	mw_post( array( 'post_type' => 'page', 'post_name' => 'condiciones' ) );
	mw_assert( true === mw_check( 'paginas_legales' )['ok'], 'con las dos publicadas está ok' );
} );

mw_test( 'Legales: usa la página de privacidad de WordPress y la de condiciones de WooCommerce', function () {
	$p = mw_post( array( 'post_type' => 'page', 'post_name' => 'mi-privacidad' ) );
	$t = mw_post( array( 'post_type' => 'page', 'post_name' => 'reglas-de-la-tienda' ) );
	update_option( 'wp_page_for_privacy_policy', $p );
	update_option( 'woocommerce_terms_page_id', $t );
	mw_assert( true === mw_check( 'paginas_legales' )['ok'], 'páginas configuradas con cualquier slug' );
	update_option( 'wp_page_for_privacy_policy', 0 );
	update_option( 'woocommerce_terms_page_id', 0 );
} );

mw_test( 'Legales: los slugs se pueden cambiar con el filtro mw_progreso_legal_slugs', function () {
	mw_post( array( 'post_type' => 'page', 'post_name' => 'privacidad' ) );
	mw_post( array( 'post_type' => 'page', 'post_name' => 'bases' ) );
	$f = function ( $s ) {
		$s['terms'][] = 'bases';
		return $s;
	};
	add_filter( 'mw_progreso_legal_slugs', $f );
	mw_assert( true === mw_check( 'paginas_legales' )['ok'], 'slug agregado por filtro' );
	remove_filter( 'mw_progreso_legal_slugs', $f );
} );

/* ---------- Tienda ---------- */

if ( 'sin-woo' === $mw_mode ) {
	mw_test( 'Tienda: sin WooCommerce se envían las señales con ok = false', function () {
		$p = mw_check( 'tienda_productos' );
		$g = mw_check( 'tienda_pasarela' );
		mw_assert( $p && false === $p['ok'] && 'WooCommerce no activo' === $p['detail'], 'tienda_productos presente' );
		mw_assert( $g && false === $g['ok'] && 'WooCommerce no activo' === $g['detail'], 'tienda_pasarela presente' );
		mw_assert( false === strpos( mw_render(), 'Tienda' ), 'no se listan en el escritorio' );
	} );
} else {
	mw_test( 'Tienda: cuenta productos publicados', function () {
		mw_assert( '0 productos' === mw_check( 'tienda_productos' )['detail'], 'sin productos' );
		mw_post( array( 'post_type' => 'product' ) );
		mw_post( array( 'post_type' => 'product', 'post_status' => 'draft' ) );
		$c = mw_check( 'tienda_productos' );
		mw_assert( true === $c['ok'] && '1 productos' === $c['detail'], 'un producto publicado, el borrador no cuenta' );
	} );

	mw_test( 'Tienda: detecta Mercado Pago activo', function () {
		$ids = array_keys( WC()->payment_gateways()->payment_gateways() );
		mw_assert( in_array( 'woo-mercado-pago-basic', $ids, true ), 'el plugin de Mercado Pago registra woo-mercado-pago-basic' );
		mw_assert( false === mw_check( 'tienda_pasarela' )['ok'], 'recién instalado no está activo' );

		$key      = 'woocommerce_woo-mercado-pago-basic_settings';
		$original = get_option( $key );
		update_option( $key, array_merge( (array) $original, array( 'enabled' => 'yes' ) ) );
		WC()->payment_gateways()->init();
		mw_assert( true === mw_check( 'tienda_pasarela' )['ok'], 'activado en WooCommerce > Pagos queda ok' );

		false === $original ? delete_option( $key ) : update_option( $key, $original );
		WC()->payment_gateways()->init();
	} );
}

/* ---------- ReelsLoop ---------- */

mw_test( 'ReelsLoop: detecta el plugin activo por nombre de archivo', function () {
	mw_assert( false === mw_check( 'reels_plugin' )['ok'], 'sin el plugin' );
	$f = function ( $v ) {
		return array_merge( (array) $v, array( 'reelsloop/reelsloop.php' ) );
	};
	add_filter( 'option_active_plugins', $f );
	mw_assert( true === mw_check( 'reels_plugin' )['ok'], 'con reelsloop/reelsloop.php activo' );
	remove_filter( 'option_active_plugins', $f );
} );

/* ---------- Sincronización ---------- */

mw_test( 'Sync: sin configurar no llama al panel', function () {
	$cap = null;
	mw_fake_panel( new WP_Error( 'x', 'no debería llamarse' ), $cap );
	$s = MisioWeb_Progreso::sync();
	mw_assert( null === $cap, 'no hubo pedido HTTP' );
	mw_assert( isset( $s['local']['blog_entradas'] ), 'guarda el estado local' );
	mw_assert( isset( $s['error'] ) && false !== strpos( $s['error'], 'falta configurar ID del sitio, Token' ), 'avisa qué datos faltan (la URL viene por defecto)' );
	mw_assert( false !== strpos( mw_render(), 'falta configurar' ), 'el escritorio muestra el aviso' );
} );

mw_test( 'Sync: envía solo señales y el token va en el encabezado', function () {
	mw_configure();
	$cap = null;
	mw_fake_panel( array( 'response' => array( 'code' => 200 ), 'body' => '{"progress":40,"message":"Faltan fotos","items":[{"label":"Logo","status":"done"},{"label":"Fotos","status":"pending"}]}' ), $cap );
	$s    = MisioWeb_Progreso::sync();
	$body = json_decode( $cap['args']['body'], true );
	mw_assert( 'https://panel.test/api/v1/sync' === $cap['url'], 'URL /v1/sync' );
	mw_assert( 'Bearer tok-123' === $cap['args']['headers']['Authorization'], 'Authorization: Bearer' );
	mw_assert( array( 'site_id', 'site', 'plugin', 'wp', 'signals' ) === array_keys( $body ), 'cuerpo con las claves de la spec' );
	mw_assert( false === strpos( $cap['args']['body'], 'tok-123' ), 'el token no viaja en el cuerpo' );
	$sig_ok = true;
	foreach ( $body['signals'] as $sig ) {
		$sig_ok = $sig_ok && array( 'ok', 'detail' ) === array_keys( $sig ) && is_bool( $sig['ok'] );
	}
	mw_assert( $sig_ok, 'cada señal tiene solo ok (bool) y detail' );
	mw_assert( 40 === $s['remote']['progress'] && ! isset( $s['error'] ), 'guarda la respuesta del panel' );

	$html = mw_render();
	mw_assert( false !== strpos( $html, '40% completado' ), 'el escritorio muestra el avance del panel' );
	mw_assert( false !== strpos( $html, 'Faltan fotos' ), 'y el mensaje del panel' );
} );

mw_test( 'Sync: MW_PROGRESO_TOKEN de wp-config tiene prioridad', function () {
	if ( ! defined( 'MW_PROGRESO_TOKEN' ) ) {
		define( 'MW_PROGRESO_TOKEN', 'tok-wpconfig' );
	}
	mw_configure( 'tok-opcion' );
	$cap = null;
	mw_fake_panel( array( 'response' => array( 'code' => 200 ), 'body' => '{}' ), $cap );
	MisioWeb_Progreso::sync();
	mw_assert( 'Bearer tok-wpconfig' === $cap['args']['headers']['Authorization'], 'usa el token de la constante' );
} );

mw_test( 'Sync: si el panel falla se conserva lo último que respondió', function () {
	mw_configure();
	$cap = null;
	mw_fake_panel( array( 'response' => array( 'code' => 200 ), 'body' => '{"progress":70,"items":[]}' ), $cap );
	$ok = MisioWeb_Progreso::sync();
	remove_all_filters( 'pre_http_request' );

	mw_fake_panel( array( 'response' => array( 'code' => 500 ), 'body' => '' ), $cap );
	$s = MisioWeb_Progreso::sync();
	mw_assert( 'El panel respondió con el código 500' === $s['error'], 'registra el código de error' );
	mw_assert( 70 === $s['remote']['progress'] && $ok['at'] === $s['remote_at'], 'mantiene el avance anterior y su fecha' );
	remove_all_filters( 'pre_http_request' );

	mw_fake_panel( new WP_Error( 'http_request_failed', 'Tiempo agotado' ), $cap );
	$s = MisioWeb_Progreso::sync();
	mw_assert( 'Tiempo agotado' === $s['error'] && 70 === $s['remote']['progress'], 'idem con error de conexión' );
	mw_assert( false !== strpos( mw_render(), 'se muestra lo sincronizado' ), 'el escritorio avisa que son datos anteriores' );
	remove_all_filters( 'pre_http_request' );

	mw_fake_panel( array( 'response' => array( 'code' => 200 ), 'body' => 'no es json' ), $cap );
	$s = MisioWeb_Progreso::sync();
	mw_assert( isset( $s['error'] ) && 70 === $s['remote']['progress'], 'respuesta inválida cuenta como error' );
} );

/* ---------- Escritorio ---------- */

mw_test( 'Escritorio: limita el porcentaje a 0-100 y escapa el texto del panel', function () {
	update_option( MisioWeb_Progreso::STATE, array( 'at' => time(), 'local' => array(), 'remote' => array( 'progress' => 150, 'items' => array( array( 'label' => '<script>x</script>', 'status' => 'done' ) ) ) ) );
	$html = mw_render();
	mw_assert( false !== strpos( $html, '100% completado' ) && false !== strpos( $html, 'width:100%' ), '150 se muestra como 100' );
	mw_assert( false === strpos( $html, '<script>' ), 'etiquetas escapadas' );
	update_option( MisioWeb_Progreso::STATE, array( 'at' => time(), 'local' => array(), 'remote' => array( 'progress' => -5 ) ) );
	mw_assert( false !== strpos( mw_render(), '0% completado' ), '-5 se muestra como 0' );
} );

mw_test( 'Escritorio: la primera vez no bloquea esperando al panel', function () {
	mw_configure();
	$cap = null;
	mw_fake_panel( new WP_Error( 'x', 'no debería llamarse' ), $cap );
	wp_clear_scheduled_hook( MisioWeb_Progreso::SOON );
	$html = mw_render();
	mw_assert( null === $cap, 'no llama al panel al dibujar' );
	mw_assert( false !== wp_next_scheduled( MisioWeb_Progreso::SOON ), 'programa la sincronización' );
	mw_assert( false !== strpos( $html, 'Blog: 2 entradas' ) && false !== strpos( $html, 'pendiente' ), 'muestra las verificaciones locales' );
	wp_clear_scheduled_hook( MisioWeb_Progreso::SOON );
} );

/* ---------- Ajustes ---------- */

mw_test( 'Ajustes: valida URL y token', function () {
	$o = MisioWeb_Progreso::sanitize( array( 'api_url' => 'http://panel.test/api', 'site_id' => ' sankalpa ', 'token' => " ab%41<b>c+/=_-.~ \n" ) );
	mw_assert( '' === $o['api_url'], 'rechaza URL sin HTTPS' );
	mw_assert( 'sankalpa' === $o['site_id'], 'limpia el ID del sitio' );
	mw_assert( 'ab41bc+/=_-.~' === $o['token'], 'el token conserva solo caracteres válidos' );
	$o = MisioWeb_Progreso::sanitize( null );
	mw_assert( array( 'api_url' => '', 'site_id' => '', 'token' => '' ) === $o, 'tolera datos vacíos' );
} );

mw_test( 'Ajustes: avisa si la URL no es https y qué falta configurar', function () {
	global $wp_settings_errors;
	$wp_settings_errors = array();
	$o = MisioWeb_Progreso::sanitize( array( 'api_url' => 'panel.misioweb.com/api', 'site_id' => '1', 'token' => 'x' ) );
	mw_assert( 'https://panel.misioweb.com/api' === $o['api_url'], 'sin protocolo, completa con https://' );
	mw_assert( array() === get_settings_errors( MisioWeb_Progreso::OPT ), 'y no muestra error' );
	$o = MisioWeb_Progreso::sanitize( array( 'api_url' => 'http://panel.misioweb.com/api', 'site_id' => '1', 'token' => 'x' ) );
	mw_assert( '' === $o['api_url'], 'descarta la URL con http://' );
	mw_assert( 'api_url' === ( get_settings_errors( MisioWeb_Progreso::OPT )[0]['code'] ?? '' ), 'muestra el error de la URL' );
	$wp_settings_errors = array();

	update_option( MisioWeb_Progreso::OPT, array( 'api_url' => '', 'site_id' => '', 'token' => 'x' ) );
	wp_set_current_user( 1 );
	ob_start();
	MisioWeb_Progreso::settings_page();
	$html = ob_get_clean();
	wp_set_current_user( 0 );
	mw_assert( false !== strpos( $html, 'Falta configurar: ID del sitio' ), 'la página de ajustes dice qué falta' );
	mw_assert( false !== strpos( $html, 'value="https://panel.misioweb.com/api"' ), 'la URL del panel viene cargada por defecto' );

	$cap = null;
	update_option( MisioWeb_Progreso::OPT, array( 'api_url' => '', 'site_id' => '7', 'token' => 'tok' ) );
	mw_fake_panel( array( 'response' => array( 'code' => 200 ), 'body' => '{}' ), $cap );
	MisioWeb_Progreso::sync();
	mw_assert( 'https://panel.misioweb.com/api/v1/sync' === $cap['url'], 'con la URL vacía sincroniza contra el panel de MisioWeb' );
} );

mw_test( 'Ajustes: el token guardado no se muestra y se mantiene si el campo queda vacío', function () {
	mw_configure( 'secreto-xyz' );
	$o = MisioWeb_Progreso::sanitize( array( 'api_url' => 'https://panel.test/api', 'site_id' => 's', 'token' => '' ) );
	mw_assert( 'secreto-xyz' === $o['token'], 'vacío mantiene el token' );
	$o = MisioWeb_Progreso::sanitize( array( 'api_url' => 'https://panel.test/api', 'site_id' => 's', 'token' => '', 'token_clear' => '1' ) );
	mw_assert( '' === $o['token'], 'la casilla lo borra' );

	wp_set_current_user( 1 );
	ob_start();
	MisioWeb_Progreso::settings_page();
	$html = ob_get_clean();
	mw_assert( false === strpos( $html, 'secreto-xyz' ), 'el HTML no contiene el token' );
	wp_set_current_user( 0 );
} );

/* ---------- Cron ---------- */

mw_test( 'Cron: ensure_cron programa la sincronización horaria si falta', function () {
	wp_clear_scheduled_hook( MisioWeb_Progreso::CRON );
	MisioWeb_Progreso::ensure_cron();
	mw_assert( 'hourly' === wp_get_schedule( MisioWeb_Progreso::CRON ), 'queda programada cada hora' );
} );

mw_test( 'Publicar contenido programa una sincronización en un minuto', function () {
	wp_clear_scheduled_hook( MisioWeb_Progreso::SOON );
	mw_post( array( 'post_type' => 'post' ) );
	$t = wp_next_scheduled( MisioWeb_Progreso::SOON );
	mw_assert( $t && $t <= time() + 60, 'evento mw_progreso_sync_soon programado' );
	wp_clear_scheduled_hook( MisioWeb_Progreso::SOON );
} );

/* ---------- Restaurar ---------- */

foreach ( $mw_old_posts as $mw_p ) {
	$wpdb->update( $wpdb->posts, array( 'post_status' => $mw_p->post_status, 'post_name' => $mw_p->post_name ), array( 'ID' => $mw_p->ID ) );
	clean_post_cache( $mw_p->ID );
}
wp_delete_attachment( $mw_image, true );
update_option( 'wp_page_for_privacy_policy', $mw_privacy_id );
update_option( 'woocommerce_terms_page_id', $mw_terms_id );

echo "\n$mw_passed ok, $mw_failed fallas\n";
exit( $mw_failed ? 1 : 0 );
