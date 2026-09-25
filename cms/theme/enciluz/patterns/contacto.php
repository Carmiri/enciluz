<?php
/**
 * Title: Contacto con formulario
 * Slug: enciluz/contacto
 * Categories: enciluz
 */
echo enciluz_group(
	enciluz_columns([
		[enciluz_section_contact(['email' => 'fundacionenciluz@gmail.com', 'phones' => ['0426-510-17-02'], 'whatsapp' => '584265101702', 'address' => 'La Dolorita, Municipio Sucre, Estado Miranda']), '40%'],
		[enciluz_group(enciluz_h('Envíanos un mensaje') . "<!-- wp:enciluz/contact-form /-->\n", ['anchor' => 'formulario']), '60%'],
	], '', 'wide'),
	['tag' => 'section', 'align' => 'full', 'class' => 'enciluz-seccion']
);
