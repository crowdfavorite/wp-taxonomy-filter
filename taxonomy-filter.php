<?php 

function cftf_build_form($args) {
	$cftf = new CF_Taxonomy_Filter($args);
	$cftf->build_form();
}

class CF_Taxonomy_Filter {

	function __construct($args) {
		// These keys are always required so we don't have to think about them later.
		$default_keys = array(
			'form_options' => array(),
			'submit_options' => array(),
		);
		$this->options = array_merge($default_keys, $args);
	}

	static function add_actions() {
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

		self::the_content();

		self::end_form();
	}

	public static function date_filter($args) {
		echo '<input type="text" placeholder="Start Date" name="cftf_date[start]" class="cftf-date" /> to ';
		echo '<input type="text" placeholder="End Date" name="cftf_date[end]" class="cftf-date" />';
	}


	/**
	 * Echo a taxonomy filter form element.
	 *
	 * @param $taxonomy string The taxonomy slug to generate the form for
	 * @param $args array Optional array of arguments. 
	 *		'data-placehold' is placeholder text for the input
	 *		'prefix' is a prefix added to the term dropdown. For typeahead support, users will
	 *			have to type the prefix as well.
	 *		'multiple' Determines whether or not multiple terms can be selected
	 *		'selected' is an array of term names which are preselected on initial form generation
	 * 		all additional arguments are attributes of the select box. see allowed_attributes();
	 * @return void
	 **/
	public static function tax_filter($taxonomy, $args = array()) {
		if (!taxonomy_exists($taxonomy)) {
			return;
		}

		$tax_obj = get_taxonomy($taxonomy);

		$defaults = array(
			'prefix' => '',
			'multiple' => true,
			'selected' => array(),
			'data-placeholder' => $tax_obj->labels->name,
		);

		$args = array_merge($defaults, $args);

		// Set the initially selected arguments. Try for previous queried, if none exists, get the if of the term names
		
		if (!empty($_POST['cftf_action'])) {
			$args['selected'] = isset($_POST['cftf_taxonomies'][$taxonomy]) ? (array) $_POST['cftf_taxonomies'][$taxonomy] : array();
		}
		else if (!empty($args['selected'])) {
			$selected_names = (array) $args['selected'];
			$args['selected'] = array();
			foreach ($selected_names as $term_name) {
				$term = get_term_by('name', $term_name, $taxonomy);
				if ($term) {
					$args['selected'][] = $term->term_id;
				}
			}
		}


		// Always need cftf-tax-filter as a class so chosen can target it
		if (!empty($args['class'])) {
			$args['class'] .= ' cftf-tax-select';
		}
		else {
			$args['class'] = 'cftf-tax-select';
		}

		$terms = get_terms($taxonomy, array('hide_empty' => false));
		
		// Build the select form element
		$output = '<select name="'.esc_attr('cftf_taxonomies['.$taxonomy.'][]').'"'.self::_build_attrib_string($args);
		if ($args['multiple']) {
			$output .= 'multiple ';
		}
		$output .= '>';

		foreach ($terms as $term) {
			// @TODO allow for multiple initially selected?
			$output .= '<option value="'.esc_attr($term->term_id).'"'.selected(in_array($term->term_id, $args['selected']), true, false).'>'.esc_html($args['prefix'].$term->name).'</option>';
		}

		$output .= '</select>';

		echo $output;

	}

	/**
	 * Echo a submit form element. 
	 *
	 * @param $args array Optional array of arguments. 
	 *		'data-placehold' is placeholder text for the input
	 *		'user_query' is an array of WP_User_Query arguments to override which
	 *			 users are selectable (no backend enforcing of these)
	 *		'selected' is an array of user ids which are preselected on initial form generation
	 * 		all additional arguments are attributes of the select box. see allowed_attributes();
	 * @return void
	 **/
	public static function author_select($args = array()) {
		$defaults = array(
			'selected' => array(),
			'data-placeholder' => __('Author', 'cftf'),
			'user_query' => array(
				'orderby' => 'display_name',
			)
		);

		$args = array_merge($defaults, $args);

		// Already queried, repopulate the form with selected items
		if (!empty($_POST['cftf_action'])) {
			$args['selected'] = isset($_POST['cftf_authors']) ? $_POST['cftf_authors'] : array();
		}
		$args['selected'] = (array) $args['selected'];

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


		$output = '<select name="cftf_authors[]"'.self::_build_attrib_string($args).'>';

		foreach ($users as $user) {
			// @TODO allow for multiple select and selected? Would need to use an OR here in query
			$output .= '<option value="'.$user->ID.'"'.selected(in_array($user->ID, $args['selected']), true, false).'>'.esc_html($user->data->display_name).'</option>';
		}

		$output .= '</select>';

		echo $output;
	}

	/**
	 * Echo a submit form element. 
	 *
	 * @param $args array Optional array of arguments. 'text' is the submit button value,
	 * all additional arguments are attributes of the input. see allowed_attributes();
	 * @return void
	 **/
	public static function submit_button($args = array()) {
		$defaults = array(
			'text' => __('Submit', 'cftf'),
			'class' => '',
			'id' => '',
		);
		$args = array_merge($defaults, $args);

		echo '<input type="submit"'.self::_build_attrib_string($args).' />';
	}

	/**
	 * Generates and echos a hidden form based on submitted filter data
	 **/ 
	public static function queried_form() {
		// The existing form can be modified for a new search, need to keep original data around so we can paginate it
		if (isset($_POST['cftf_action']) && $_POST['cftf_action'] == 'filter') {
			$output = '
<form id="cftf-query" method="POST" style="display:none;">';
			if (!empty($_POST['cftf_date']['start'])) {
				$output .= '<input type="hidden" name="cftf_date[start]" value="'.esc_attr($_POST['cftf_date']['start']).'" />';
			}
			if (!empty($_POST['cftf_date']['end'])) {
				$output .= '<input type="hidden" name="cftf_date[start]" value="'.esc_attr($_POST['cftf_date']['start']).'" />';
			}
			if (!empty($_POST['cftf_authors'])) {
				foreach ((array) $_POST['cftf_authors'] as $author_id) {
					$output .= '<input type="hidden" name="cftf_authors[]" value="'.esc_attr($author_id).'" />';
				}
			}
			if (!empty($_POST['cftf_taxonomies']) && is_array($_POST['cftf_taxonomies'])) {
				foreach ($_POST['cftf_taxonomies'] as $taxonomy => $terms) {
					if (is_array($terms)) {
						foreach ($terms as $term_id) {
							$output .= '<input type="hidden" name="'.esc_attr('cftf_taxonomies['.$taxonomy.'][]').'" value="'.esc_attr($term_id).'" />';
						}
					}
				}
			}
			$output .='
</form>';
			echo $output;
		}
	}

	/**
	 * Opens the form tag, as well as creating a hidden form of previous
	 * filter data which is utilized for pagination
	 * @param $args array Option argument array, each of which are just attributes on the form element
	 * @return void
	 **/
	public static function start_form($args = array()) {
		$defaults = array(
			'id' => 'cftf-filter',
			'class' => '',
			'action' => home_url('?s='),
		);

		$args = array_merge($defaults, $args);

		self::queried_form();

		echo '
<form method="POST"'.self::_build_attrib_string($args).'>';
	}

	public static function end_form() {
		echo '
	<input type="hidden" name="cftf_action" value="filter" />
</form>';
	}

	/**
     * Build an attribute string for an HTML element, only attributes from
     * allowed_attributes will be allowed
     **/
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

	/**
     * What attributes can be placed on the various form elements, filterable
     **/
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

	/**
     * Filter the WHERE clause in the query as WP_Query does not support a range function as of 3.5
     **/
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

	/**
     * Override default query with the filtered values
     **/
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

		if (!empty($_POST['cftf_taxonomies']) && is_array($_POST['cftf_taxonomies'])) {
			foreach ($_POST['cftf_taxonomies'] as $taxonomy => $terms) {
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

	function the_content() {
		do_action('cftf_content');	
	}

	function navigation() {
		// Controlled by sending previous data in a hidden form (see JS)

	}

	function navigation_next() {

	}

	function navigation_previous() {

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