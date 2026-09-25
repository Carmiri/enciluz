<?php
/**
 * Cabeceras de seguridad y CSP con nonce para el sitio público.
 */
defined('ABSPATH') || exit;

function enciluz_csp_nonce(): string {
	static $nonce = null;
	if (null === $nonce) {
		$nonce = base64_encode(random_bytes(16));
	}
	return $nonce;
}

function enciluz_csp(): string {
	$nonce = enciluz_csp_nonce();
	return implode('; ', [
		"default-src 'self'",
		"script-src 'self' 'nonce-{$nonce}'",
		"style-src 'self' 'unsafe-inline'",
		"img-src 'self' data:",
		"font-src 'self' data:",
		"connect-src 'self'",
		"object-src 'none'",
		"base-uri 'self'",
		"form-action 'self'",
		"frame-ancestors 'none'",
	]);
}

// Todo <script> que imprima WordPress (externo o en línea) lleva el nonce.
add_filter('wp_script_attributes', static function (array $attrs): array {
	$attrs['nonce'] = enciluz_csp_nonce();
	return $attrs;
});
add_filter('wp_inline_script_attributes', static function (array $attrs): array {
	$attrs['nonce'] = enciluz_csp_nonce();
	return $attrs;
});

function enciluz_common_headers(): void {
	header_remove('X-Powered-By');
	header('X-Content-Type-Options: nosniff');
	header('Referrer-Policy: strict-origin-when-cross-origin');
	header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
	header('Cross-Origin-Opener-Policy: same-origin');
	if (is_ssl()) {
		header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
	}
}

add_action('send_headers', static function (): void {
	enciluz_common_headers();
	header('X-Frame-Options: DENY');
	// El editor del sitio carga el front en un iframe propio: sin CSP estricta para sesiones con permisos de edición.
	if (!current_user_can('edit_posts')) {
		header('Content-Security-Policy: ' . enciluz_csp());
	}
});

add_action('admin_init', static function (): void {
	enciluz_common_headers();
	header('X-Frame-Options: SAMEORIGIN');
});
add_action('login_init', static function (): void {
	enciluz_common_headers();
	header('X-Frame-Options: DENY');
});
