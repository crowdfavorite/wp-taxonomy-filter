<?php 

function cftf_build_form($args) {
	$cftf = new CF_Taxonomy_Filter($args);
	$cftf->build_form();
}

class CF_Taxonomy_Filter {
	public $args;

	function __construct($args) {
		$this->options = $args;
	}

	public function build_form() {

		self::start_form($args);

		if (isset($this->options['taxonomies']) && is_array($this->options['taxonomies'])) {
			foreach ($this->options['taxonomies'] as $taxonomy => $args) {
				if (is_array($args)) {
					self::tax_filter($taxonomy, $args);
				}
				// Just passed in taxonomy name with no options
				else {
					self::tax_filter($args);
				}
			}
		}

		if (!empty($this->options['authors'])) {
			$author_options = !empty($this->options['author_options']) ? $this->options['author_options'] : array();
			self::author_select($author_options);
		}

		$submit_options = !empty($this->options['submit_options']) ? $this->options['submit_options'] : array();

		
		self::submit_button($submit_options);

		self::end_form();
	}

	public static function date_filter($args) {
		echo '<input type="text" placeholder="Start Date" name="cftf_start_date" class="cftf-date" /> to ';
		echo '<input type="text" placeholder="End Date" name="cftf_end_date" class="cftf-date" />';
	}

	public static function tax_filter($taxonomy, $args = array()) {
		if (!taxonomy_exists($taxonomy)) {
			return;
		}

		$tax_obj = get_taxonomy($taxonomy);

		$defaults = array(
			'multiple' => true,
			'selected' => '',
			'data-placeholder' => $tax_obj->labels->name,
		);

		$args = array_merge($defaults, $args);

		// Always need cftf-tax-filter as a class so choson can target it
		if (!empty($args['class'])) {
			$args['class'] .= ' cftf-tax-select';
		}
		else {
			$args['class'] = 'cftf-tax-select';
		}


		$terms = get_terms($taxonomy);
		
		// Build the select form element
		$output = '<select'.self::_build_attrib_string($args);
		if ($args['multiple']) {
			$output .= 'multiple ';
		}
		$output .= '>';

		foreach ($terms as $term) {
			// @TODO allow for multiple selected?
			$output .= '<option value=""'.selected($args['selected'], $term->name, false).'>'.esc_html($term->name).'</option>';
		}

		$output .= '</select>';

		echo $output;

	}

	public static function author_select($args = array()) {
		$defaults = array(
			'selected' => '',
			'data-placeholder' => __('Author', 'cftf'),
			'user_query' => array(
				'orderby' => 'display_name',

			)
		);

		$args = array_merge($defaults, $args);

		// Always need cftf-author-filter as a class so choson can target it
		if (!empty($args['class'])) {
			$args['class'] .= ' cftf-author-select';
		}
		else {
			$args['class'] = 'cftf-author-select';
		}


		$user_query = new WP_User_Query($args['user_query']);
		if (!empty($user_query->results)) {
			$users = apply_filters('cftf_users', $user_query->results);
		}


		$output = '<select'.self::_build_attrib_string($args).'>';

		foreach ($users as $user) {
			// @TODO allow for multiple selected?
			$output .= '<option value=""'.selected($args['selected'], $user->data->user_login, false).'>'.esc_html($user->data->display_name).'</option>';
		}

		$output .= '</select>';

		echo $output;


	}

	public static function submit_button($args = array()) {

	}

	public static function start_form($args) {
		$defaults = array(
			'id' => 'cftf-filter',
			'class' => '',
			'method' => 'POST',
			'action' => home_url(),
		);

		$args = array_merge($defaults, $args);

		echo '
<form'.self::_build_attrib_string($args).'>';
	}

	public static function end_form() {
		echo '
	<input type="hidden" name="cftf_action" value="filter">
</form>';
	}

	// Request handler

	// Attributes

	static function _build_attrib_string($attributes) {
		if (!is_array($attributes)) {
			return '';
		}
		
		$components = array();

		$allowed_attributes = self::allowed_attributes();

		foreach ($attributes as $attribute => $value) {
			if (!empty($value) && in_array($attribute, $allowed_attributes)) {
				$components[] = esc_attr($attribute).'="'.esc_attr($value).'"';	
			}
		}

		$string = implode(' ', $components);
		if (!empty($string)) {
			$string = ' '.$string.' ';
		}

		return $string;
	}

	static function allowed_attributes() {
		return apply_filters('cftf_allowed_attributes', array(
			'class',
			'id', 
			'method',
			'action',
			'value',
			'name',
			'style',
			'placeholder',
			'data-placeholder',
			'tabindex',
		));
	}
}

function cftf_enqueue_scripts() {

	// Figure out the URL for this file.
	$parent_dir = trailingslashit(get_template_directory());
	$child_dir = trailingslashit(get_stylesheet_directory());

	$plugin_dir = trailingslashit(basename(__DIR__));
	$file = basename(__FILE__);

	if (file_exists($parent_dir.'functions/'.$plugin_dir.$file)) {
		$url = trailingslashit(get_template_directory_uri()).'functions/'.$plugin_dir;
	}
	else if (file_exists($parent_dir.'plugins/'.$plugin_dir.$file)) {
		$url = trailingslashit(get_template_directory_uri()).'plugins/'.$plugin_dir;
	}
	else if ($child_dir !== $parent_dir && file_exists($child_dir.'functions/'.$plugin_dir.$file)) {
		$url = trailingslashit(get_stylesheet_directory_uri()).'functions/'.$plugin_dir;
	}
	else if ($child_dir !== $parent_dir && file_exists($child_dir.'plugins/'.$plugin_dir.$file)) {
		$url = trailingslashit(get_stylesheet_directory_uri()).'plugins/'.$plugin_dir;
	}
	else {
		$url = plugin_dir_url(__FILE__);
	}

	wp_enqueue_script('jquery-ui-datepicker');
	wp_enqueue_script('jquery');
	wp_enqueue_script('chosen', $url.'lib/chosen/chosen/chosen.jquery.min.js', array('jquery'), null, true);
	wp_enqueue_script('cftf', $url.'/taxonomy-filter.js', array('jquery', 'chosen', 'jquery-ui-datepicker'), '1.0', true);

	wp_enqueue_style('chosen', $url.'/lib/chosen/chosen/chosen.css', array(), null, 'all');
}
add_action('wp_enqueue_scripts', 'cftf_enqueue_scripts');

/* Potential arguments for constructor
$args = array(
	'form_options' => array(
		'id' => '',
		'classes' => '',
		'method' => '',
		'action' => '',
		'onsubmit' => '',
	),
	'taxonomies' => array(
		'projects' => array(
			'id' => '',
			'class' => '',
			'selected' => '', // Term name
		),
		'code' => array(),
		'post_tag' => array(),
	),
	'authors' => 1,
	'author_options' => array(
		'user_query',
	),
	'submit_options' => array(
		'text' => 'Submit',
		'class' => '',
		'id' => '',
	),
	'date' => 1,
	'date_options' => array(
		
	),
)
*/


?>