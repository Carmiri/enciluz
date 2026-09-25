<?php
/**
 * Siembra el contenido inicial desde /seed/content.json.
 * Uso: wp eval-file /scripts/seed.php            (solo crea lo que falta)
 *      ENCILUZ_SEED_FORCE=1 wp eval-file ...      (reescribe las páginas sembradas)
 *
 * No toca páginas que el cliente haya creado. Es idempotente.
 */
defined('ABSPATH') || exit;

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once get_theme_file_path('inc/sections.php');

$force = (bool) getenv('ENCILUZ_SEED_FORCE');
$c     = json_decode((string) file_get_contents('/seed/content.json'), true, 512, JSON_THROW_ON_ERROR);
$s     = $c['settings'];

/* ---------- Medios ---------- */

function enciluz_seed_media(string $file, string $alt): int {
	$found = get_posts(['post_type' => 'attachment', 'meta_key' => '_enciluz_seed', 'meta_value' => $file, 'fields' => 'ids', 'numberposts' => 1]);
	if ($found) {
		return (int) $found[0];
	}
	$src = is_file("/seed/images/{$file}") ? "/seed/images/{$file}" : get_theme_file_path("assets/images/{$file}");
	$tmp = wp_tempnam($file);
	copy($src, $tmp);
	$id = media_handle_sideload(['name' => $file, 'tmp_name' => $tmp], 0);
	if (is_wp_error($id)) {
		WP_CLI::error("No se pudo importar {$file}: " . $id->get_error_message());
	}
	update_post_meta($id, '_enciluz_seed', $file);
	update_post_meta($id, '_wp_attachment_image_alt', $alt);
	return (int) $id;
}

$alts = [
	'hero.jpg'                      => 'Niñas, niños y adolescentes sonríen juntos en una plaza',
	'historia.jpg'                  => 'Mujeres de la comunidad reunidas',
	'cta.jpg'                       => 'Manos unidas de personas voluntarias',
	'vidas-con-valor.jpg'           => 'Familia acompañada en un espacio de acogida',
	'gestion-documentos.jpg'        => 'Madre junto a su hija',
	'habilidades-emocionales.jpg'   => 'Grupo de mujeres conversando en un taller',
	'habilidades-economicas.jpg'    => 'Mujer atendiendo su propio negocio',
	'aulas-integrales.jpg'          => 'Niñas y niños aprendiendo en un aula',
	'salud-integral.jpg'            => 'Atención médica a una persona de la comunidad',
	'salud-sexual-reproductiva.jpg' => 'Profesional de salud conversando con una mujer',
	'proteccion-nna.jpg'            => 'Niñas y niños jugando acompañados por una persona adulta',
	'servicios-emergencia.jpg'      => 'Personas voluntarias entregando ayuda',
];
$img = [];
foreach ($alts as $file => $alt) {
	$id                                   = enciluz_seed_media($file, $alt);
	$img[basename($file, '.jpg')]         = ['id' => $id, 'url' => wp_get_attachment_image_url($id, 'large'), 'alt' => $alt];
}

// Logo y favicon.
$logo_id = enciluz_seed_media('logo-horizontal.png', 'Fundación de Bienestar Social Enciende una Luz');
set_theme_mod('custom_logo', $logo_id);
update_option('site_icon', enciluz_seed_media('icon.png', 'ENCILUZ'));

/* ---------- Páginas ---------- */

function enciluz_seed_page(string $slug, string $title, string $content, bool $force, string $template = 'page-ancho', string $excerpt = ''): int {
	$page = get_page_by_path($slug, OBJECT, 'page');
	$data = [
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => $slug,
		'post_title'   => $title,
		'post_content' => $content,
		'post_excerpt' => $excerpt,
		'page_template' => $template ?: 'default',
	];
	// Solo se reescriben páginas creadas por esta siembra (meta _enciluz_seed), nunca las del cliente.
	if ($page && (!$force || !get_post_meta($page->ID, '_enciluz_seed', true))) {
		WP_CLI::log("Existe (sin cambios): {$slug}");
		return $page->ID;
	}
	if ($page) {
		$data['ID'] = $page->ID;
	}
	$id = $page ? wp_update_post(wp_slash($data), true) : wp_insert_post(wp_slash($data), true);
	if (is_wp_error($id)) {
		WP_CLI::error($id->get_error_message());
	}
	update_post_meta($id, '_enciluz_seed', $slug);
	WP_CLI::log(($page ? 'Actualizada' : 'Creada') . ": {$slug}");
	return (int) $id;
}

$programs = [];
foreach ($c['programs'] as $p) {
	$programs[$p['slug']] = $p + ['image' => $img[$p['slug']]['url'], 'image_id' => $img[$p['slug']]['id'], 'alt' => $img[$p['slug']]['alt']];
}
$stats   = array_map(static fn($x) => [$x['value'], $x['label']], $s['stats']);
$sectors = array_map(static fn($x) => [$x['icon'], $x['title'], $x['summary']], $c['sectors']);
$allies  = [
	'Donantes 2024–2025'   => [],
	'Redes a las que pertenecemos' => [],
];
foreach ($c['allies'] as $a) {
	$allies['donante' === $a['kind'] ? 'Donantes 2024–2025' : 'Redes a las que pertenecemos'][] = [$a['name'], $a['url']];
}
$cta = enciluz_section_cta(
	'Súmate a encender una luz',
	'Con tu tiempo, tus aportes en especie o una alianza institucional, más familias reciben protección, salud y educación.',
	[['Quiero colaborar', '/contacto/#colabora', 'oscuro'], ['Escríbenos', '/contacto/', 'outline']]
);

// Inicio
$featured = [];
foreach (['salud-integral', 'vidas-con-valor', 'habilidades-emocionales'] as $slug) {
	$featured[] = $programs[$slug] + ['link' => "/que-hacemos/#{$slug}"];
}
$home = enciluz_section_hero([
	'title'    => $s['heroTitle'],
	'text'     => $s['heroSubtitle'],
	'image'    => $img['hero']['url'],
	'image_id' => $img['hero']['id'],
	'alt'      => $img['hero']['alt'],
	'buttons'  => [['Ver nuestros programas', '/que-hacemos/'], ['Dona o colabora', '/contacto/#colabora', 'outline']],
])
	. enciluz_section_stats($stats)
	. enciluz_section_sectors($sectors, 'Cómo acompañamos a las comunidades', $s['identity'])
	. enciluz_section_programs($featured, 'Programas que transforman vidas', '', [['Ver todos los programas', '/que-hacemos/', 'oscuro']])
	. enciluz_section_allies($allies, 'Quienes nos acompañan')
	. $cta;
$home_id = enciluz_seed_page('inicio', 'Inicio', $home, $force, 'page-ancho', $s['tagline']);

// Quiénes somos
$values = array_map(static fn($x) => [$x['title'], $x['text']], $c['values']);
$team   = array_map(static fn($x) => [$x['name'], $x['role']], $c['team']);
$about  = enciluz_section_intro('Quiénes somos', $s['identity'])
	. enciluz_section_text_image('Nuestra historia', $c['history'], $img['historia']['url'], $img['historia']['id'], $img['historia']['alt'])
	. enciluz_section_text('Nuestra misión', [$s['mission']], 'celeste')
	. enciluz_section_values($values, 'Nuestros valores')
	. enciluz_section_team($team, 'Nuestro equipo', 'Profesionales de la salud, la educación, la protección y la gestión que hacen posible cada programa.')
	. enciluz_section_allies($allies, 'Redes y aliados')
	. $cta;
$about_id = enciluz_seed_page('quienes-somos', 'Quiénes somos', $about, $force, 'page-ancho', $s['identity']);

// Qué hacemos
$details = '';
$flip    = false;
foreach ($programs as $p) {
	$details .= enciluz_program_detail($p, $flip);
	$flip     = !$flip;
}
$coverage = array_map(static fn($x) => [$x['state'], $x['municipalities'], $x['ethnicities']], $c['coverage']);
$what     = enciluz_section_intro('Qué hacemos', 'Trabajamos en seis sectores de intervención y desarrollamos programas que llegan a comunidades urbanas, rurales e indígenas de todo el país.')
	. enciluz_section_sectors($sectors, 'Sectores de intervención')
	. enciluz_group(enciluz_h('Nuestros programas') . $details, ['tag' => 'section', 'align' => 'full', 'class' => 'enciluz-seccion'])
	. enciluz_section_coverage($coverage, 'Dónde estamos', 'Presencia en estados y municipios, incluidas comunidades de pueblos indígenas.')
	. $cta;
$what_id = enciluz_seed_page('que-hacemos', 'Qué hacemos', $what, $force, 'page-ancho', 'Sectores, programas y cobertura de ENCILUZ.');

// Transparencia
$docs = ['Protocolos' => [], 'Manuales' => [], 'Códigos' => []];
$map  = ['protocolo' => 'Protocolos', 'manual' => 'Manuales', 'codigo' => 'Códigos'];
foreach ($c['documents'] as $d) {
	$docs[$map[$d['category']]][] = $d['title'];
}
$t     = $c['transparency'];
$trans = enciluz_section_intro('Transparencia', $t['intro'])
	. enciluz_section_text('Datos legales', ["Fundación de Bienestar Social Enciende una Luz (ENCILUZ). RIF {$s['rif']}.", $s['registry']])
	. enciluz_section_documents($docs, 'Protocolos, manuales y códigos', 'Documentos que orientan nuestra gestión. Donantes, aliados y auditores pueden solicitar cualquiera de ellos.')
	. enciluz_group(enciluz_buttons([['Solicitar un documento', 'mailto:fundacionenciluz@gmail.com?subject=' . rawurlencode('Solicitud de documento de transparencia'), 'oscuro']]), ['align' => 'full', 'class' => 'enciluz-seccion enciluz-seccion--pegada'])
	. enciluz_section_text('Prevención de la explotación y el abuso sexual (PEAS)', [$t['peas']], 'celeste', 'peas')
	. enciluz_group(
		enciluz_h('Canal de denuncias') . enciluz_p($t['complaints']) . enciluz_buttons([['Hacer una denuncia confidencial', '/contacto/?motivo=denuncia#formulario', 'oscuro']]),
		['tag' => 'section', 'align' => 'full', 'class' => 'enciluz-seccion', 'anchor' => 'denuncias']
	);
$trans_id = enciluz_seed_page('transparencia', 'Transparencia', $trans, $force, 'page-ancho', $t['intro']);

// Contacto
$collab  = array_map(static fn($x) => [$x['title'], $x['text']], $c['collaborate']);
$form    = enciluz_h('Envíanos un mensaje', 2) . "<!-- wp:enciluz/contact-form /-->\n";
$contact = enciluz_section_intro('Contacto', 'Escríbenos para pedir información, sumarte como voluntaria o voluntario, proponer una alianza o hacer una denuncia confidencial.')
	. enciluz_group(
		enciluz_columns([
			[enciluz_section_contact(['email' => $s['email'], 'phones' => $s['phones'], 'whatsapp' => $s['whatsapp'], 'address' => $s['address']]), '40%'],
			[enciluz_group($form, ['anchor' => 'formulario']), '60%'],
		], '', 'wide'),
		['tag' => 'section', 'align' => 'full', 'class' => 'enciluz-seccion']
	)
	. enciluz_group(
		enciluz_heading_block('Cómo colaborar', 'Hay muchas formas de sumarte a nuestro trabajo.')
		. enciluz_group(implode('', array_map(static fn($x) => enciluz_group(enciluz_h($x[0], 3) . enciluz_p($x[1]), ['class' => 'is-style-tarjeta']), $collab)), ['align' => 'wide', 'layout' => 'grid', 'min' => '15rem']),
		['tag' => 'section', 'align' => 'full', 'bg' => 'celeste', 'class' => 'enciluz-seccion', 'anchor' => 'colabora']
	);
$contact_id = enciluz_seed_page('contacto', 'Contacto', $contact, $force, 'page-ancho', 'Datos de contacto y formas de colaborar.');

// Créditos de fotografías
$credits = [];
foreach (file('/seed/images/CREDITS.md') as $line) {
	$cells = array_map('trim', explode('|', trim($line, " |\n")));
	if (count($cells) >= 4 && str_ends_with($cells[0], '.jpg')) {
		$credits[] = [$cells[1] . ' — ' . $cells[3], $cells[2]];
	}
}
enciluz_seed_page(
	'creditos',
	'Créditos de fotografías',
	enciluz_p('Las fotografías de este sitio son de uso libre (licencias de Unsplash y Pexels). Agradecemos a sus autoras y autores:') . enciluz_link_list($credits),
	$force,
	''
);

/* ---------- Portada, menú ---------- */

update_option('show_on_front', 'page');
update_option('page_on_front', $home_id);

$nav_items = [['Inicio', $home_id, '/'], ['Quiénes somos', $about_id, '/quienes-somos/'], ['Qué hacemos', $what_id, '/que-hacemos/'], ['Transparencia', $trans_id, '/transparencia/'], ['Contacto', $contact_id, '/contacto/']];
$nav_markup = '';
foreach ($nav_items as [$label, $id, $url]) {
	$nav_markup .= '<!-- wp:navigation-link ' . wp_json_encode(['label' => $label, 'type' => 'page', 'id' => $id, 'url' => home_url($url), 'kind' => 'post-type'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . " /-->\n";
}
$nav = get_posts(['post_type' => 'wp_navigation', 'name' => 'menu-principal', 'numberposts' => 1, 'post_status' => 'publish']);
if (!$nav || $force) {
	$nav_data = ['post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_title' => 'Menú principal', 'post_name' => 'menu-principal', 'post_content' => $nav_markup];
	if ($nav) {
		$nav_data['ID'] = $nav[0]->ID;
	}
	$nav ? wp_update_post($nav_data) : wp_insert_post($nav_data);
	WP_CLI::log('Menú principal listo');
}

WP_CLI::success('Contenido sembrado.');
