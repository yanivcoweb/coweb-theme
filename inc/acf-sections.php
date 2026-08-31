<?php
/**
 * ACF field groups, registered in PHP.
 *
 * Never exported to JSON, never created in the admin UI. This file is the only
 * source of truth for fields, so field changes arrive through git like any
 * other change and a staging database never diverges from production.
 *
 * Adding a section means three coordinated changes:
 *   1. a layout here
 *   2. templates/partials/sections/<name>.twig
 *   3. a block in assets/scss/_blocks.scss
 *
 * ACF is snake_case, the filesystem is kebab-case. `hero_main` → `hero-main.twig`.
 *
 * @package CoWeb
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build a field array without repeating the key prefix on every line.
 *
 * @param string              $key   Unique suffix; becomes field_coweb_{$key}.
 * @param string              $name  Field name as it appears in Twig.
 * @param string              $label Admin label.
 * @param string              $type  ACF field type.
 * @param array<string,mixed> $extra Any further ACF settings.
 * @return array<string,mixed>
 */
function coweb_field( string $key, string $name, string $label, string $type, array $extra = array() ): array {
	return array_merge(
		array(
			'key'   => 'field_coweb_' . $key,
			'name'  => $name,
			'label' => $label,
			'type'  => $type,
		),
		$extra
	);
}

/**
 * Eyebrow + heading + intro, the trio behind the `section-header` component.
 *
 * @param string $prefix Layout name, to keep field keys unique.
 * @return array<int,array<string,mixed>>
 */
function coweb_section_header_fields( string $prefix ): array {
	return array(
		coweb_field( $prefix . '_eyebrow', 'eyebrow', 'תווית עליונה', 'text' ),
		coweb_field(
			$prefix . '_heading',
			'heading',
			'כותרת',
			'text',
			array( 'required' => 1 )
		),
		coweb_field(
			$prefix . '_intro',
			'intro',
			'פסקת פתיחה',
			'textarea',
			array(
				'rows'         => 3,
				'new_lines'    => '',
				'instructions' => 'שתי שורות לכל היותר. אם צריך יותר — כנראה שזה סקשן תוכן ולא כותרת סקשן.',
			)
		),
	);
}

/**
 * The Flexible Content layouts. One entry per section partial.
 *
 * @return array<string,array<string,mixed>>
 */
function coweb_section_layouts(): array {
	return array(

		// ── hero_main ────────────────────────────────────────────────────────
		'layout_hero_main' => array(
			'key'        => 'layout_hero_main',
			'name'       => 'hero_main',
			'label'      => 'Hero ראשי',
			'display'    => 'block',
			'sub_fields' => array(
				coweb_field( 'hero_eyebrow', 'eyebrow', 'תווית עליונה', 'text' ),
				coweb_field(
					'hero_heading',
					'heading',
					'כותרת ראשית',
					'textarea',
					array(
						'rows'         => 2,
						'new_lines'    => '',
						'required'     => 1,
						'instructions' => 'זו ה־h1 של העמוד. סקשן אחד בלבד בעמוד אמור להכיל hero.',
					)
				),
				coweb_field(
					'hero_intro',
					'intro',
					'פסקת פתיחה',
					'textarea',
					array(
						'rows'      => 3,
						'new_lines' => '',
					)
				),
				coweb_field(
					'hero_actions',
					'actions',
					'כפתורים',
					'repeater',
					array(
						'max'          => 2,
						'layout'       => 'table',
						'button_label' => 'הוספת כפתור',
						'instructions' => 'שניים לכל היותר. הראשון הוא הפעולה המרכזית.',
						'sub_fields'   => array(
							coweb_field(
								'hero_action_link',
								'link',
								'קישור',
								'link',
								array( 'return_format' => 'array' )
							),
							coweb_field(
								'hero_action_variant',
								'variant',
								'סגנון',
								'select',
								array(
									'choices'       => array(
										'primary'   => 'ראשי',
										'secondary' => 'משני',
										'ghost'     => 'טקסט בלבד',
									),
									'default_value' => 'primary',
								)
							),
						),
					)
				),
			),
		),

		// ── logo_strip ───────────────────────────────────────────────────────
		'layout_logo_strip' => array(
			'key'        => 'layout_logo_strip',
			'name'       => 'logo_strip',
			'label'      => 'רצועת לקוחות',
			'display'    => 'block',
			'sub_fields' => array(
				coweb_field(
					'strip_label',
					'label',
					'תווית',
					'text',
					array( 'default_value' => 'עובד עם' )
				),
				coweb_field(
					'strip_clients',
					'clients',
					'לקוחות',
					'repeater',
					array(
						'layout'       => 'table',
						'button_label' => 'הוספת לקוח',
						'instructions' => 'שם הלקוח מוצג כטקסט עד שמעלים לוגו וקטורי.',
						'sub_fields'   => array(
							coweb_field(
								'strip_client_name',
								'name',
								'שם',
								'text',
								array( 'required' => 1 )
							),
							coweb_field(
								'strip_client_logo',
								'logo',
								'לוגו',
								'image',
								array(
									'return_format' => 'array',
									'preview_size'  => 'thumbnail',
									'mime_types'    => 'svg,png',
									'instructions'  => 'אופציונלי. SVG מועדף.',
								)
							),
						),
					)
				),
			),
		),

		// ── capabilities_grid ────────────────────────────────────────────────
		'layout_capabilities_grid' => array(
			'key'        => 'layout_capabilities_grid',
			'name'       => 'capabilities_grid',
			'label'      => 'רשת יכולות',
			'display'    => 'block',
			'sub_fields' => array_merge(
				coweb_section_header_fields( 'cap' ),
				array(
					coweb_field(
						'cap_cards',
						'cards',
						'כרטיסים',
						'repeater',
						array(
							'layout'       => 'block',
							'button_label' => 'הוספת יכולת',
							'instructions' => 'המספור נוצר אוטומטית לפי הסדר — אין שדה מספר, וזה בכוונה.',
							'sub_fields'   => array(
								coweb_field(
									'cap_card_title',
									'title',
									'כותרת',
									'text',
									array( 'required' => 1 )
								),
								coweb_field(
									'cap_card_body',
									'body',
									'תיאור',
									'textarea',
									array(
										'rows'      => 3,
										'new_lines' => '',
									)
								),
							),
						)
					),
				)
			),
		),

		// ── process_steps ────────────────────────────────────────────────────
		'layout_process_steps' => array(
			'key'        => 'layout_process_steps',
			'name'       => 'process_steps',
			'label'      => 'שלבי תהליך',
			'display'    => 'block',
			'sub_fields' => array_merge(
				coweb_section_header_fields( 'proc' ),
				array(
					coweb_field(
						'proc_steps',
						'steps',
						'שלבים',
						'repeater',
						array(
							'layout'       => 'block',
							'button_label' => 'הוספת שלב',
							'instructions' => 'המספור נוצר אוטומטית לפי הסדר.',
							'sub_fields'   => array(
								coweb_field(
									'proc_step_title',
									'title',
									'כותרת השלב',
									'text',
									array( 'required' => 1 )
								),
								coweb_field(
									'proc_step_body',
									'body',
									'תיאור',
									'textarea',
									array(
										'rows'      => 3,
										'new_lines' => '',
									)
								),
							),
						)
					),
				)
			),
		),

		// ── work_selected ────────────────────────────────────────────────────
		'layout_work_selected' => array(
			'key'        => 'layout_work_selected',
			'name'       => 'work_selected',
			'label'      => 'עבודות נבחרות',
			'display'    => 'block',
			'sub_fields' => array_merge(
				coweb_section_header_fields( 'work' ),
				array(
					coweb_field(
						'work_projects',
						'projects',
						'פרויקטים',
						'relationship',
						array(
							'post_type'     => array( 'work' ),
							'filters'       => array( 'search' ),
							'return_format' => 'object',
							'min'           => 1,
							'max'           => 6,
							'instructions'  => 'הסדר כאן הוא סדר התצוגה.',
						)
					),
					coweb_field(
						'work_link',
						'link',
						'קישור לכל העבודות',
						'link',
						array( 'return_format' => 'array' )
					),
				)
			),
		),

		// ── cta_band ─────────────────────────────────────────────────────────
		'layout_cta_band' => array(
			'key'        => 'layout_cta_band',
			'name'       => 'cta_band',
			'label'      => 'רצועת קריאה לפעולה',
			'display'    => 'block',
			'sub_fields' => array(
				coweb_field(
					'cta_heading',
					'heading',
					'כותרת',
					'text',
					array( 'required' => 1 )
				),
				coweb_field(
					'cta_sub',
					'sub',
					'שורת משנה',
					'textarea',
					array(
						'rows'      => 2,
						'new_lines' => '',
					)
				),
				coweb_field(
					'cta_link',
					'link',
					'כפתור',
					'link',
					array(
						'return_format' => 'array',
						'required'      => 1,
					)
				),
			),
		),

		// ── page_hero ────────────────────────────────────────────────────────
		'layout_page_hero' => array(
			'key'        => 'layout_page_hero',
			'name'       => 'page_hero',
			'label'      => 'Hero לעמוד פנימי',
			'display'    => 'block',
			'sub_fields' => array(
				coweb_field(
					'ph_title',
					'title',
					'כותרת העמוד',
					'text',
					array(
						'required'     => 1,
						'instructions' => 'זו ה־h1. פירורי הלחם נוצרים לבד מהיררכיית העמודים — אין שדה לזה, ובכוונה.',
					)
				),
				coweb_field(
					'ph_intro',
					'intro',
					'פסקת פתיחה',
					'textarea',
					array(
						'rows'      => 3,
						'new_lines' => '',
					)
				),
				coweb_field(
					'ph_tags',
					'tags',
					'תגיות',
					'repeater',
					array(
						'layout'       => 'table',
						'button_label' => 'הוספת תגית',
						'instructions' => 'לשימוש בעמוד פרויקט. אלה תוויות, לא קישורי סינון.',
						'sub_fields'   => array(
							coweb_field( 'ph_tag_label', 'label', 'תגית', 'text' ),
						),
					)
				),
			),
		),

		// ── media_full ───────────────────────────────────────────────────────
		'layout_media_full' => array(
			'key'        => 'layout_media_full',
			'name'       => 'media_full',
			'label'      => 'תמונה רחבה',
			'display'    => 'block',
			'sub_fields' => array(
				coweb_field(
					'mf_image',
					'image',
					'תמונה',
					'image',
					array(
						'return_format' => 'array',
						'required'      => 1,
						'instructions'  => 'הטקסט החלופי נלקח מספריית המדיה. מלאו אותו שם.',
					)
				),
				coweb_field( 'mf_caption', 'caption', 'כיתוב', 'text' ),
				coweb_field(
					'mf_width',
					'width',
					'רוחב',
					'select',
					array(
						'choices'       => array(
							'wide'   => 'רחב (1200)',
							'narrow' => 'מידת מאמר (760)',
						),
						'default_value' => 'wide',
					)
				),
			),
		),

		// ── stat_row ─────────────────────────────────────────────────────────
		'layout_stat_row' => array(
			'key'        => 'layout_stat_row',
			'name'       => 'stat_row',
			'label'      => 'שורת נתונים',
			'display'    => 'block',
			'sub_fields' => array(
				coweb_field(
					'sr_stats',
					'stats',
					'נתונים',
					'repeater',
					array(
						'layout'       => 'table',
						'max'          => 4,
						'button_label' => 'הוספת נתון',
						'instructions' => 'כל מספר כאן הוא טענה על עבודה אמיתית. אם אי אפשר למדוד אותו — עדיף למחוק את השורה מאשר לעגל.',
						'sub_fields'   => array(
							coweb_field(
								'sr_value',
								'value',
								'ערך',
								'text',
								array(
									'required'     => 1,
									'instructions' => 'למשל 4, ‎40+‎ או 1.4s',
								)
							),
							coweb_field(
								'sr_label',
								'label',
								'תווית',
								'text',
								array( 'required' => 1 )
							),
						),
					)
				),
			),
		),

		// ── text_blocks ──────────────────────────────────────────────────────
		'layout_text_blocks' => array(
			'key'        => 'layout_text_blocks',
			'name'       => 'text_blocks',
			'label'      => 'בלוקי טקסט',
			'display'    => 'block',
			'sub_fields' => array(
				coweb_field(
					'tb_blocks',
					'blocks',
					'בלוקים',
					'repeater',
					array(
						'layout'       => 'block',
						'button_label' => 'הוספת בלוק',
						'sub_fields'   => array(
							coweb_field( 'tb_heading', 'heading', 'כותרת', 'text' ),
							coweb_field(
								'tb_body',
								'body',
								'תוכן',
								'wysiwyg',
								array(
									'tabs'         => 'all',
									'media_upload' => 0,
									'toolbar'      => 'basic',
								)
							),
						),
					)
				),
			),
		),

		// ── media_text ───────────────────────────────────────────────────────
		'layout_media_text' => array(
			'key'        => 'layout_media_text',
			'name'       => 'media_text',
			'label'      => 'תמונה וטקסט',
			'display'    => 'block',
			'sub_fields' => array(
				coweb_field(
					'mt_body',
					'body',
					'תוכן',
					'wysiwyg',
					array(
						'tabs'         => 'all',
						'media_upload' => 0,
						'toolbar'      => 'basic',
					)
				),
				coweb_field(
					'mt_image',
					'image',
					'תמונה',
					'image',
					array( 'return_format' => 'array' )
				),
				coweb_field(
					'mt_media_start',
					'media_start',
					'תמונה בצד ההתחלה',
					'true_false',
					array(
						'ui'           => 1,
						'instructions' => 'כברירת מחדל התמונה בצד הסיום (שמאל בעברית).',
					)
				),
			),
		),

		// ── rich_text ────────────────────────────────────────────────────────
		'layout_rich_text' => array(
			'key'        => 'layout_rich_text',
			'name'       => 'rich_text',
			'label'      => 'תוכן חופשי',
			'display'    => 'block',
			'sub_fields' => array(
				coweb_field(
					'rt_content',
					'content',
					'תוכן',
					'wysiwyg',
					array(
						'tabs'         => 'all',
						'media_upload' => 1,
						'instructions' => 'לעמודי תוכן כמו הצהרת נגישות או תנאי שימוש.',
					)
				),
			),
		),

		// ── contact_block ────────────────────────────────────────────────────
		'layout_contact_block' => array(
			'key'        => 'layout_contact_block',
			'name'       => 'contact_block',
			'label'      => 'טופס יצירת קשר',
			'display'    => 'block',
			'sub_fields' => array(
				coweb_field(
					'cb_details',
					'details',
					'פרטי קשר',
					'repeater',
					array(
						'layout'       => 'table',
						'button_label' => 'הוספת פרט',
						'instructions' => 'שדות הטופס עצמם קבועים בתבנית — הם מחוברים למטפל בצד השרת ולא ניתנים לעריכה מכאן.',
						'sub_fields'   => array(
							coweb_field(
								'cb_label',
								'label',
								'תווית',
								'text',
								array( 'required' => 1 )
							),
							coweb_field(
								'cb_value',
								'value',
								'ערך',
								'text',
								array( 'required' => 1 )
							),
							coweb_field(
								'cb_url',
								'url',
								'קישור',
								'text',
								array( 'instructions' => 'אופציונלי. למשל mailto: או tel:' )
							),
							coweb_field(
								'cb_ltr',
								'ltr',
								'משמאל לימין',
								'true_false',
								array(
									'ui'           => 1,
									'instructions' => 'סמנו עבור אימייל, טלפון או כתובת אתר.',
								)
							),
						),
					)
				),
			),
		),
	);
}

add_action(
	'acf/include_fields',
	static function (): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		// ── Page sections ────────────────────────────────────────────────────
		acf_add_local_field_group(
			array(
				'key'                   => 'group_coweb_sections',
				'title'                 => 'סקשנים',
				'menu_order'            => 0,
				'position'              => 'normal',
				'style'                 => 'seamless',
				'label_placement'       => 'top',
				'hide_on_screen'        => array( 'the_content' ),

				// Pages and case studies both compose from the same section
				// stack. Posts do not — an article body is prose.
				'location'              => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'page',
						),
					),
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'work',
						),
					),
				),
				'fields'                => array(
					coweb_field(
						'sections',
						'sections',
						'סקשנים',
						'flexible_content',
						array(
							'button_label' => 'הוספת סקשן',
							'layouts'      => coweb_section_layouts(),
						)
					),
				),
			)
		);

		// ── Site settings ────────────────────────────────────────────────────
		acf_add_local_field_group(
			array(
				'key'       => 'group_coweb_options',
				'title'     => 'הגדרות אתר',
				'location'  => array(
					array(
						array(
							'param'    => 'options_page',
							'operator' => '==',
							'value'    => 'coweb-settings',
						),
					),
				),
				'fields'    => array(
					coweb_field(
						'opt_header_cta',
						'header_cta',
						'כפתור בהדר',
						'link',
						array( 'return_format' => 'array' )
					),
					coweb_field( 'opt_email', 'contact_email', 'אימייל', 'email' ),
					coweb_field(
						'opt_phone',
						'contact_phone',
						'טלפון',
						'text',
						array( 'instructions' => 'מוצג כפי שהוקלד, ומוצג תמיד משמאל לימין.' )
					),
					coweb_field( 'opt_linkedin', 'linkedin_url', 'לינקדאין', 'url' ),
					coweb_field(
						'opt_404_link',
						'not_found_link',
						'קישור משני בעמוד 404',
						'link',
						array(
							'return_format' => 'array',
							'instructions'  => 'לרוב עמוד העבודות. אם ריק — מוצג רק הכפתור לעמוד הבית.',
						)
					),
				),
			)
		);
	}
);
