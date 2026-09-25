<?php
/**
 * Title: Pie de página
 * Slug: enciluz/pie
 * Categories: footer
 * Block Types: core/template-part/footer
 * Inserter: no
 */
$col1 = enciluz_img(enciluz_theme_asset('images/logo-mark.png'), '', 0, 'enciluz-footer__logo')
	. enciluz_p('Fundación de Bienestar Social Enciende una Luz. Protegemos y acompañamos a niñas, niños, adolescentes, mujeres y familias en Venezuela desde 2008.');
$col2 = enciluz_h('Explora') . enciluz_link_list([
	['Quiénes somos', '/quienes-somos/'],
	['Qué hacemos', '/que-hacemos/'],
	['Transparencia', '/transparencia/'],
	['Contacto y colabora', '/contacto/'],
], 'enciluz-footer__links');
$col3 = enciluz_h('Contacto')
	. enciluz_link_p('', 'fundacionenciluz@gmail.com', 'mailto:fundacionenciluz@gmail.com')
	. enciluz_link_p('', '0426-510-17-02', 'tel:+584265101702')
	. enciluz_link_p('', '0412-474-62-40', 'tel:+584124746240')
	. enciluz_p('La Dolorita, Municipio Sucre, Estado Miranda');
$social = '<!-- wp:social-links {"iconColor":"blanco","iconColorValue":"#ffffff","className":"is-style-logos-only","layout":{"type":"flex"}} -->
<ul class="wp-block-social-links has-icon-color is-style-logos-only"><!-- wp:social-link {"url":"https://www.instagram.com/enciluz","service":"instagram"} /-->

<!-- wp:social-link {"url":"https://www.tiktok.com/@enciluz","service":"tiktok"} /-->

<!-- wp:social-link {"url":"https://x.com/enciluz","service":"x"} /--></ul>
<!-- /wp:social-links -->
';
$col4 = enciluz_h('Síguenos') . $social . enciluz_p('@enciluz en Instagram, TikTok y X');

echo enciluz_group(
	enciluz_columns([[$col1, '34%'], [$col2, null], [$col3, null], [$col4, null]], '', 'wide')
	. enciluz_group(
		enciluz_p('RIF J-29721263-9 · Registrada el 26/09/2008 en el Registro Público del Primer Circuito del Municipio Sucre, Estado Miranda.')
		. enciluz_link_p('', 'Créditos de fotografías', '/creditos/'),
		['align' => 'wide', 'class' => 'enciluz-legal']
	),
	['align' => 'full', 'bg' => 'azul-oscuro', 'class' => 'enciluz-footer enciluz-seccion']
);
