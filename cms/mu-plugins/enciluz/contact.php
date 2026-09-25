<?php
/**
 * Formulario de contacto propio (bloque "Formulario de contacto").
 * Sin plugins ni base de datos: valida, limita envíos y manda un correo con wp_mail.
 * Pruebas: cms/tests/contact.sh
 */
defined('ABSPATH') || exit;

const ENCILUZ_CONTACT_REASONS = [
	'informacion'  => 'Información',
	'voluntariado' => 'Voluntariado',
	'alianza'      => 'Alianza institucional',
	'donacion'     => 'Donación en especie',
	'denuncia'     => 'Denuncia confidencial',
];
const ENCILUZ_CONTACT_MIN_SECONDS = 3;
const ENCILUZ_CONTACT_MAX_PER_WINDOW = 5;
const ENCILUZ_CONTACT_WINDOW = 10 * MINUTE_IN_SECONDS;

/** Token firmado con la hora en que se mostró el formulario: "timestamp.hmac". */
function enciluz_contact_token(int $time): string {
	return $time . '.' . hash_hmac('sha256', (string) $time, wp_salt('nonce') . 'enciluz_contact');
}

function enciluz_contact_token_age(string $token): ?int {
	if (!preg_match('/^(\d{9,11})\.([a-f0-9]{64})$/', $token, $m)) {
		return null;
	}
	if (!hash_equals(enciluz_contact_token((int) $m[1]), $token)) {
		return null;
	}
	return time() - (int) $m[1];
}

function enciluz_contact_to(): string {
	$to = (string) get_option('enciluz_contact_to', '');
	return is_email($to) ? $to : 'fundacionenciluz@gmail.com';
}

/** true si la IP todavía puede enviar; registra el intento. */
function enciluz_contact_rate_ok(): bool {
	$ip  = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'desconocida';
	$key = 'enciluz_rl_' . substr(hash_hmac('sha256', $ip, wp_salt('auth')), 0, 32);
	$now = time();
	$log = array_filter((array) get_transient($key), static fn($t) => is_int($t) && $now - $t < ENCILUZ_CONTACT_WINDOW);
	if (count($log) >= ENCILUZ_CONTACT_MAX_PER_WINDOW) {
		set_transient($key, array_values($log), ENCILUZ_CONTACT_WINDOW);
		return false;
	}
	$log[] = $now;
	set_transient($key, array_values($log), ENCILUZ_CONTACT_WINDOW);
	return true;
}

/**
 * Valida los datos enviados. Devuelve [datos, null] o [null, código de error].
 * @return array{0: ?array, 1: ?string}
 */
function enciluz_contact_validate(array $in): array {
	$get = static fn(string $k): string => isset($in[$k]) && is_string($in[$k]) ? wp_unslash($in[$k]) : '';

	if ('' !== $get('website')) {
		return [null, 'datos'];
	}
	$age = enciluz_contact_token_age($get('enciluz_t'));
	if (null === $age || $age < ENCILUZ_CONTACT_MIN_SECONDS || $age > DAY_IN_SECONDS) {
		return [null, 'datos'];
	}
	if (!wp_verify_nonce($get('_wpnonce'), 'enciluz_contact')) {
		return [null, 'caducado'];
	}
	$name    = trim($get('nombre'));
	$email   = trim($get('correo'));
	$reason  = $get('motivo');
	$message = trim(str_replace("\r\n", "\n", $get('mensaje')));

	if (preg_match('/[\r\n]/', $name) || mb_strlen($name) < 2 || mb_strlen($name) > 100) {
		return [null, 'nombre'];
	}
	if (mb_strlen($email) > 150 || !is_email($email) || preg_match('/[\r\n]/', $email)) {
		return [null, 'correo'];
	}
	if (!isset(ENCILUZ_CONTACT_REASONS[$reason])) {
		return [null, 'motivo'];
	}
	if (mb_strlen($message) < 5 || mb_strlen($message) > 3000) {
		return [null, 'mensaje'];
	}
	return [['nombre' => sanitize_text_field($name), 'correo' => sanitize_email($email), 'motivo' => $reason, 'mensaje' => sanitize_textarea_field($message)], null];
}

// Procesa el envío (patrón POST → redirección → GET) antes de pintar la página.
add_action('template_redirect', static function (): void {
	if ('POST' !== ($_SERVER['REQUEST_METHOD'] ?? '') || empty($_POST['enciluz_contact'])) {
		return;
	}
	$back = remove_query_arg(['enviado', 'error'], wp_get_referer() ?: get_permalink() ?: home_url('/contacto/'));
	$back = wp_validate_redirect($back, home_url('/contacto/'));
	$go   = static function (array $args) use ($back): void {
		wp_safe_redirect(add_query_arg($args, $back) . '#formulario', 303);
		exit;
	};

	if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 10000) {
		$go(['error' => 'mensaje']);
	}
	if (!enciluz_contact_rate_ok()) {
		$go(['error' => 'limite']);
	}
	[$data, $error] = enciluz_contact_validate($_POST);
	if ($error) {
		$go(['error' => $error]);
	}

	$reason  = ENCILUZ_CONTACT_REASONS[$data['motivo']];
	$subject = sprintf('[Web ENCILUZ] %s — %s', $reason, $data['nombre']);
	$body    = "Nombre: {$data['nombre']}\nCorreo: {$data['correo']}\nMotivo: {$reason}\n\n{$data['mensaje']}\n";
	$sent    = wp_mail(enciluz_contact_to(), $subject, $body, ['Reply-To: ' . $data['correo']]);
	$go($sent ? ['enviado' => '1'] : ['error' => 'envio']);
});

/** HTML del formulario. */
function enciluz_contact_render(): string {
	$errors = [
		'datos'    => 'No pudimos procesar el formulario. Recarga la página e inténtalo de nuevo.',
		'caducado' => 'El formulario caducó. Recarga la página e inténtalo de nuevo.',
		'nombre'   => 'Escribe tu nombre (entre 2 y 100 caracteres).',
		'correo'   => 'Revisa tu correo electrónico: parece incompleto.',
		'motivo'   => 'Elige el motivo de tu mensaje.',
		'mensaje'  => 'Escribe un mensaje de entre 5 y 3000 caracteres.',
		'limite'   => 'Recibimos varios mensajes seguidos desde tu conexión. Espera unos minutos o escríbenos a fundacionenciluz@gmail.com.',
		'envio'    => 'No se pudo enviar tu mensaje. Escríbenos a fundacionenciluz@gmail.com o por WhatsApp.',
	];
	$notice = '';
	if (isset($_GET['enviado'])) {
		$notice = '<p class="enciluz-aviso enciluz-aviso--ok" role="status">Mensaje enviado. Te responderemos a tu correo lo antes posible.</p>';
	} elseif (isset($_GET['error'])) {
		$code   = sanitize_key((string) $_GET['error']);
		$notice = '<p class="enciluz-aviso enciluz-aviso--error" role="alert">' . esc_html($errors[$code] ?? $errors['datos']) . '</p>';
	}
	$selected = isset($_GET['motivo']) ? sanitize_key((string) $_GET['motivo']) : 'informacion';
	$options  = '';
	foreach (ENCILUZ_CONTACT_REASONS as $value => $label) {
		$options .= sprintf('<option value="%s"%s>%s</option>', esc_attr($value), selected($selected, $value, false), esc_html($label));
	}

	ob_start();
	?>
	<?php echo $notice; // phpcs:ignore -- escapado arriba ?>
	<form class="enciluz-form" method="post" action="<?php echo esc_url(get_permalink() ?: home_url('/contacto/')); ?>" novalidate>
		<input type="hidden" name="enciluz_contact" value="1">
		<input type="hidden" name="enciluz_t" value="<?php echo esc_attr(enciluz_contact_token(time())); ?>">
		<?php wp_nonce_field('enciluz_contact', '_wpnonce', false); ?>
		<div>
			<label for="enciluz-nombre">Nombre</label>
			<input id="enciluz-nombre" name="nombre" type="text" autocomplete="name" required minlength="2" maxlength="100">
		</div>
		<div>
			<label for="enciluz-correo">Correo electrónico</label>
			<input id="enciluz-correo" name="correo" type="email" autocomplete="email" required maxlength="150">
		</div>
		<div>
			<label for="enciluz-motivo">Motivo</label>
			<select id="enciluz-motivo" name="motivo" required><?php echo $options; // phpcs:ignore -- escapado arriba ?></select>
		</div>
		<div>
			<label for="enciluz-mensaje">Mensaje</label>
			<textarea id="enciluz-mensaje" name="mensaje" required minlength="5" maxlength="3000"></textarea>
		</div>
		<div class="enciluz-hp" aria-hidden="true">
			<label for="enciluz-website">No completes este campo</label>
			<input id="enciluz-website" name="website" type="text" tabindex="-1" autocomplete="off">
		</div>
		<small>No guardamos tus datos: tu mensaje llega solo al correo de la fundación. Las denuncias se tratan con confidencialidad.</small>
		<button type="submit">Enviar mensaje</button>
	</form>
	<?php
	return (string) ob_get_clean();
}

add_action('init', static function (): void {
	wp_register_script(
		'enciluz-contact-form-editor',
		WPMU_PLUGIN_URL . '/enciluz/contact-form-editor.js',
		['wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-block-editor'],
		'1.0.0',
		true
	);
	register_block_type('enciluz/contact-form', [
		'api_version'     => 3,
		'title'           => 'Formulario de contacto',
		'description'     => 'Formulario seguro que envía los mensajes al correo de la fundación (Ajustes → Generales).',
		'category'        => 'widgets',
		'icon'            => 'email',
		'supports'        => ['html' => false, 'multiple' => false],
		'editor_script'   => 'enciluz-contact-form-editor',
		'render_callback' => static fn() => '<div class="wp-block-enciluz-contact-form">' . enciluz_contact_render() . '</div>',
	]);
});

// Destinatario editable en Ajustes → Generales.
add_action('admin_init', static function (): void {
	register_setting('general', 'enciluz_contact_to', ['type' => 'string', 'sanitize_callback' => 'sanitize_email', 'default' => '']);
	add_settings_field('enciluz_contact_to', 'Correo que recibe el formulario de contacto', static function (): void {
		printf('<input type="email" class="regular-text" name="enciluz_contact_to" value="%s" placeholder="fundacionenciluz@gmail.com">', esc_attr((string) get_option('enciluz_contact_to', '')));
	}, 'general');
});

// En local no se envían correos reales: se escriben en /tmp/enciluz-mail.log (lo usan las pruebas).
if ('local' === wp_get_environment_type()) {
	add_filter('pre_wp_mail', static function ($return, array $atts) {
		$headers = is_array($atts['headers']) ? implode("\n", $atts['headers']) : (string) $atts['headers'];
		file_put_contents('/tmp/enciluz-mail.log', "TO: {$atts['to']}\nSUBJECT: {$atts['subject']}\n{$headers}\n\n{$atts['message']}\n----\n", FILE_APPEND);
		return true;
	}, 10, 2);
}
