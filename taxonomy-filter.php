<?php 

function cftf_build_form($args) {
	$cftf = new CF_Taxonomy_Filter($args);
	$cftf->build_form();
}

class CF_Taxonomy_Filter {
	public $args;

	function __construct($args) {
		// These keys are always required so we don't have to think about them later.
		$default_keys = array(
			'form_options' => array(),
			'submit_options' => array(),
		);
		$this->options = array_merge($default_keys, $args);
	}

	static function add_actions() {
		// 11 to catch anyone registering post types and taxonomies on init 10
		add_action('pre_get_posts', array('CF_Taxonomy_Filter', 'pre_get_posts'), 11);
	}

	public function build_form() {
		self::start_form($this->options['form_options']);

		if (!empty($this->options['taxonomies'])) {
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
			self::author_select($this->options['authors']);
		}

		self::submit_button($this->options['submit_options']);

		self::end_form();
	}

	public static function date_filter($args) {
		echo '<input type="text" placeholder="Start Date" name="cftf_date[start]" class="cftf-date" /> to ';
		echo '<input type="text" placeholder="End Date" name="cftf_date[end]" class="cftf-date" />';
	}

	public static function tax_filter($taxonomy, $args = array()) {
		if (!taxonomy_exists($taxonomy)) {
			return;
		}

		$tax_obj = get_taxonomy($taxonomy);

		$defaults = array(
			'prefix' => '',
			'multiple' => true,
			'selected' => '',
			'data-placeholder' => $tax_obj->labels->name,
		);

		$args = array_merge($defaults, $args);

		// Always need cftf-tax-filter as a class so chosen can target it
		if (!empty($args['class'])) {
			$args['class'] .= ' cftf-tax-select';
		}
		else {
			$args['class'] = 'cftf-tax-select';
		}

		$terms = get_terms($taxonomy, array('hide_empty' => false));
		
		// Build the select form element
		$output = '<select name="'.esc_attr('cftf_tax['.$taxonomy.']').'"'.self::_build_attrib_string($args);
		if ($args['multiple']) {
			$output .= 'multiple ';
		}
		$output .= '>';

		foreach ($terms as $term) {
			// @TODO allow for multiple initially selected?
			$output .= '<option value="'.esc_attr($term->term_id).'"'.selected($args['selected'], $term->name, false).'>'.esc_html($args['prefix'].$term->name).'</option>';
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

		// Always need cftf-author-filter as a class so chosen can target it
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


		$output = '<select name="cftf_authors"'.self::_build_attrib_string($args).'>';

		foreach ($users as $user) {
			// @TODO allow for multiple select and selected? Would need to use an OR here in query
			$output .= '<option value="'.$user->ID.'"'.selected($args['selected'], $user->data->user_login, false).'>'.esc_html($user->data->display_name).'</option>';
		}

		$output .= '</select>';

		echo $output;
	}

	public static function submit_button($args = array()) {
		$defaults = array(
			'text' => __('Submit', 'cftf'),
			'class' => '',
			'id' => '',
		);
		$args = array_merge($defaults, $args);

		echo '<input type="submit"'.self::_build_attrib_string($args).' />';
	}

	public static function start_form($args = array()) {
		$defaults = array(
			'id' => 'cftf-filter',
			'class' => '',
			'action' => home_url('?s='),
		);

		$args = array_merge($defaults, $args);

		echo '
<form method="POST"'.self::_build_attrib_string($args).'>';
	}

	public static function end_form() {
		echo '
	<input type="hidden" name="cftf_override" value="0" />
	<input type="hidden" name="cftf_logic" value="AND" />
	<input type="hidden" name="cftf_action" value="filter" />
</form>';
	}

	// Attribute builder
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

	public static function posts_where($where) {
		remove_filter('posts_where', array('CF_Taxonomy_Filter', 'posts_where'));
		global $wpdb;
		
		if (!empty($_POST['cftf_date']['start'])) {
			$php_date = strtotime($_POST['cftf_date']['start']);
			$mysql_date = date('Y-m-d H:i:s', $php_date);
			$date_where = $wpdb->prepare("AND $wpdb->posts.post_date > %s", $mysql_date);
			if (!empty($where)) {
				$where .= ' '.$date_where;
			}
			else {
				$where = $date_where;
			}
		}

		if (!empty($_POST['cftf_date']['end'])) {
			$php_date = strtotime($_POST['cftf_date']['end']);
			$mysql_date = date('Y-m-d H:i:s', $php_date);
			$date_where = $wpdb->prepare("AND $wpdb->posts.post_date < %s", $mysql_date);
			if (!empty($where)) {
				$where .= ' '.$date_where;
			}
			else {
				$where = $date_where;
			}
		}

		return $where;
	}


	public static function pre_get_posts($query_obj) {
		global $cftl_previous, $wp_rewrite;
		if (!$query_obj->is_main_query() || !isset($_POST['cftf_action']) || $_POST['cftf_action'] != 'filter') {
			return;
		}
		remove_action('pre_get_posts', array('CF_Taxonomy_Filter', 'pre_get_posts'));
		$query_args = array(
			// @TODO figure out best way to support pagination
			'posts_per_page' => -1,
		);

		$query_obj->is_search = true;
		if (!empty($_POST['cftf_authors'])) {
			// WP_Query doesnt accept an array of authors, sad panda 8:(
			$query_obj->query_vars['author'] = implode(',', (array) $_POST['cftf_authors']);
		}

		if (!empty($_POST['cftf_tax']) && is_array($_POST['cftf_tax'])) {
			foreach ($_POST['cftf_tax'] as $taxonomy => $terms) {
				$query_obj->query_vars['tax_query'][] = array(
					'taxonomy' => $taxonomy,
					'field' => 'ids',
					'terms' => $terms,
					'include_children' => false,
					'operator' => 'AND',
				);
			}

			$query_obj->query_vars['tax_query']['relation'] = 'AND';
		}

		// Have to manually filter date range
		if (!empty($_POST['cftf_date']['start']) || !empty($_POST['cftf_date']['end'])) {
			$query_obj->query_vars['suppress_filters'] = 0;
			add_filter('posts_where', array('CF_Taxonomy_Filter', 'posts_where'));
		}

	}
}

CF_Taxonomy_Filter::add_actions();

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
			'prefix' => '',
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