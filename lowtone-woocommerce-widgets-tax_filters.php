<?php
/*
 * Plugin Name: WooCommerce Taxonomy Filters
 * Plugin URI: http://wordpress.lowtone.nl/plugins/woocommerce-widgets-tax_filters/
 * Description: Use non-attribute taxonomies in layered nav.
 * Version: 1.0
 * Author: Lowtone <info@lowtone.nl>
 * Author URI: http://lowtone.nl
 * License: http://wordpress.lowtone.nl/license
 */
/**
 * @author Paul van der Meijs <code@lowtone.nl>
 * @copyright Copyright (c) 2011-2012, Paul van der Meijs
 * @license http://wordpress.lowtone.nl/license/
 * @version 1.0
 * @package wordpress\plugins\lowtone\woocommerce\widgets\tax_filters
 */

namespace lowtone\woocommerce\widgets\tax_filters {

	use lowtone\content\packages\Package,
		lowtone\ui\forms\Form,
		lowtone\ui\forms\FieldSet,
		lowtone\ui\forms\Input,
		lowtone\net\URL,
		lowtone\wp\sidebars\Sidebar,
		lowtone\wp\widgets\simple\Widget;

	// Includes
	
	if (!include_once WP_PLUGIN_DIR . "/lowtone-content/lowtone-content.php") 
		return trigger_error("Lowtone Content plugin is required", E_USER_ERROR) && false;

	// Init

	Package::init(array(
			Package::INIT_MERGED_PATH => __NAMESPACE__,
			Package::INIT_SUCCESS => function() {

				add_action("init", function() {
					if (is_admin() || !is_active_widget(false, false, 'lowtone_woocommerce_widgets_tax_filters', true))
						return;

					global $lowtone_woocommerce_widgets_tax_filters, $woocommerce;

					$selected_terms = 
						$taxonomies_array = 
						array();

					foreach (taxonomies() as $taxonomy) {
						$name = $taxonomy->query_var;

						$taxonomies_array[] = $name;

						$filterArg = "filter_" . $name;

						if (!(isset($_GET[$filterArg]) && taxonomy_exists($name))) 
							continue;

						$queryTypeArg = "query_type_" . $name;

						$selected_terms[$name] = array(
								"terms" => explode(",", $_GET[$filterArg]),
								"query_type" => isset($_GET[$queryTypeArg]) && in_array(($queryType = strtolower($_GET[$queryTypeArg])), array("and", "or")) ? $queryType : apply_filters("lowtone_woocommerce_widgets_tax_filters_default_query_type", "and")
							);
					}

					$lowtone_woocommerce_widgets_tax_filters = compact("selected_terms", "taxonomies_array");

					// Filter posts

					add_filter("loop_shop_post_in", function($filteredPosts) use ($lowtone_woocommerce_widgets_tax_filters) {
						$selectedTaxonomies = $lowtone_woocommerce_widgets_tax_filters["selected_terms"];

						if (count($selectedTaxonomies) < 1)
							return $filteredPosts;

						$matchedProducts = NULL;

						foreach ($selectedTaxonomies as $taxonomy => $options) {
							$function = "or" == $options["query_type"] ? "array_merge" : "array_intersect";

							$matchedProductsFromTaxonomy = NULL;

							foreach ($options["terms"] as $term) {
								$posts = get_posts(array(
										"post_type" => "product",
										"numberposts" => -1,
										"post_status" => "publish",
										"fields" => "ids",
										"no_found_rows" => true,
										"tax_query" => array(
											array(
												"taxonomy" => $taxonomy,
												"terms" => $term,
												"field" => "id",
											)
										)
									));

								if (is_wp_error($posts))
									continue;

								$matchedProductsFromTaxonomy = is_array($matchedProductsFromTaxonomy) ? $function($posts, $matchedProductsFromTaxonomy) : $posts;
							}

							$matchedProducts = is_array($matchedProducts) ? array_intersect($matchedProductsFromTaxonomy, $matchedProducts) : $matchedProductsFromTaxonomy;
						}

						if (is_array($matchedProducts)) {

							$filteredPosts = $filteredPosts ? array_intersect($filteredPosts, $matchedProducts) : $matchedProducts;

							$filteredPosts[] = 0;

						}

						return $filteredPosts;
					});
				}, 1);

				add_action("widgets_init", function() {

					Widget::register(array(
							Widget::PROPERTY_ID => "lowtone_woocommerce_widgets_tax_filters",
							Widget::PROPERTY_NAME => __("WooCommerce Layered Nav for Taxonomies", "lowtone_woocommerce_widgets_tax_filters"),
							Widget::PROPERTY_DESCRIPTION => __("Lets you narrow down the list of products by brands.", "lowtone_woocommerce_widgets_tax_filters"),
							Widget::PROPERTY_FORM => function($instance) {
								$form = new Form();

								$taxonomies = array();

								foreach (taxonomies() as $taxonomy)
									$taxonomies[$taxonomy->query_var] = $taxonomy->labels->singular_name;

								$form
									->appendChild(
										$form->createInput(Input::TYPE_TEXT, array(
												Input::PROPERTY_NAME => "title",
												Input::PROPERTY_LABEL => __("Title", "lowtone_woocommerce_widgets_tax_filters")
											))
									)
									->appendChild(
										$form->createInput(Input::TYPE_SELECT, array(
												Input::PROPERTY_NAME => "taxonomy",
												Input::PROPERTY_LABEL => __("Taxonomy", "lowtone_woocommerce_widgets_tax_filters"),
												Input::PROPERTY_VALUE => array_keys($taxonomies),
												Input::PROPERTY_ALT_VALUE => array_values($taxonomies),
											))
									)
									->appendChild(
										$form->createInput(Input::TYPE_SELECT, array(
												Input::PROPERTY_NAME => "display_type",
												Input::PROPERTY_LABEL => __("Display Type", "lowtone_woocommerce_widgets_tax_filters"),
												Input::PROPERTY_VALUE => array("list", "dropdown"),
												Input::PROPERTY_ALT_VALUE => array(__("List", "lowtone_woocommerce_widgets_tax_filters"), __("Dropdown", "lowtone_woocommerce_widgets_tax_filters"))
											))
									)
									->appendChild(
										$form->createInput(Input::TYPE_SELECT, array(
												Input::PROPERTY_NAME => "query_type",
												Input::PROPERTY_LABEL => __("Query Type", "lowtone_woocommerce_widgets_tax_filters"),
												Input::PROPERTY_VALUE => array("and", "or"),
												Input::PROPERTY_ALT_VALUE => array(__("AND", "lowtone_woocommerce_widgets_tax_filters"), __("OR", "lowtone_woocommerce_widgets_tax_filters"))
											))
									)
									->appendChild(
										$form->createFieldSet(array(
												FieldSet::PROPERTY_LEGEND => __("Sorting", "lowtone_woocommerce_widgets_tax_filters"),
											))
											->appendChild(
												$form->createInput(Input::TYPE_SELECT, array(
														Input::PROPERTY_NAME => "sort_by",
														Input::PROPERTY_LABEL => __("Sort by", "lowtone_woocommerce_widgets_tax_filters"),
														Input::PROPERTY_VALUE => array("name", "num_products"),
														Input::PROPERTY_ALT_VALUE => array(__("Name", "lowtone_woocommerce_widgets_tax_filters"), __("Number of products", "lowtone_woocommerce_widgets_tax_filters")),
													))
											)
											->appendChild(
												$form->createInput(Input::TYPE_CHECKBOX, array(
														Input::PROPERTY_NAME => "selected_at_top",
														Input::PROPERTY_LABEL => __("Move selected terms to top", "lowtone_woocommerce_widgets_tax_filters"),
														Input::PROPERTY_VALUE => "1",
													))
											)
									)
									->setValues($instance);

								return $form;
							},
							Widget::PROPERTY_WIDGET => function($args, $instance, $widget) {
								global $lowtone_woocommerce_widgets_tax_filters, $woocommerce;

								$taxonomiesArray = $lowtone_woocommerce_widgets_tax_filters["taxonomies_array"];

								// How about brand pages

								if (!is_post_type_archive("product") && !is_tax(array_merge($taxonomiesArray, array("product_cat", "product_tag"))))
									return;

								if (false === ($taxonomy = get_taxonomy($instance["taxonomy"])))
									return;

								$terms = get_terms($taxonomy->query_var, array("hide_empty" => true));

								$selectedTerms = isset($lowtone_woocommerce_widgets_tax_filters["selected_terms"][$taxonomy->query_var]["terms"]) ? (array) $lowtone_woocommerce_widgets_tax_filters["selected_terms"][$taxonomy->query_var]["terms"] : array();

								if (count($terms) < 1) 
									return;

								$title = isset($instance["title"]) && ($title = trim($instance["title"])) ? $title : $taxonomy->labels->singular_name;

								$title = apply_filters("widget_title", $title, $instance, $widget->id_base);

								echo $args[Sidebar::PROPERTY_BEFORE_WIDGET] . 
									$args[Sidebar::PROPERTY_BEFORE_TITLE] . $title . $args[Sidebar::PROPERTY_AFTER_TITLE];

								$currentTerm = NULL;
								$currentTaxonomy = NULL;

								if ($taxonomiesArray && is_tax($taxonomiesArray)) {
									$queriedObject = get_queried_object();

									$currentTerm = $queriedObject->term_id;
									$currentTaxonomy = $queriedObject->taxonomy;
								}

								$selectedToTop = function() use (&$terms, $selectedTerms) {
									$top = array();
									$bottom = array();

									foreach ($terms as $term) {
										if (in_array($term->term_id, $selectedTerms))
											$top[] = $term;
										else 
											$bottom[] = $term;
									}

									$terms = array_merge($top, $bottom);
								};

								switch ($instance["display_type"]) {
									case "dropdown":
										break;

									default:
										echo '<ul>';

										if (isset($instance["selected_at_top"]) && $instance["selected_at_top"]) 
											$selectedToTop();

										foreach ($terms as $term) {

											// Skip the current term
											
											if ($currentTerm == $term->term_id)
												continue;

											// Get product IDs for term

											$transientName = "wc_ln_count_" . md5(sanitize_key($taxonomy->query_var) . sanitize_key($term->term_id));

											if (false === ($productsInTerm = get_transient($transientName))) {
												$productsInTerm = get_objects_in_term($term->term_id, $taxonomy->query_var);

												set_transient($transientName, $productsInTerm);
											}

											// Check if the term is selected

											$termSelected = in_array($term->term_id, $selectedTerms);

											switch ($instance["query_type"]) {
												case "and":
													$count = sizeof(array_intersect($productsInTerm, $woocommerce->query->filtered_product_ids));

													break;

												default:
													$count = sizeof(array_intersect($productsInTerm, $woocommerce->query->unfiltered_product_ids));

											}

											if ($count < 1 && !$termSelected)
												continue;

											// Create link

											if (defined("SHOP_IS_ON_FRONT")) 
												$link = home_url();
											elseif (is_post_type_archive("product") || is_page(woocommerce_get_page_id("shop"))) 
												$link = get_post_type_archive_link("product");
											else 
												$link = get_term_link(get_query_var("term"), get_query_var("taxonomy"));

											$link = URL::fromString($link);

											// Build query

											$query = filterArgs();

											if (get_search_query())
												$query["s"] = get_search_query();

											$filterArg = "filter_" . $taxonomy->query_var;
											$queryTypeArg = "query_type_" . $taxonomy->query_var;

											$currentFilter = isset($_GET[$filterArg]) && ($currentFilter = explode(",", $_GET[$filterArg])) ? $currentFilter : array();

											$currentFilter = array_map("esc_attr", $currentFilter);

											$class = "";

											if (in_array($term->term_id, $currentFilter)) {
												$currentFilter = array_diff($currentFilter, array($term->term_id));

												$class = 'class="chosen"';
											} else
												$currentFilter[] = $term->term_id;

											if ($currentFilter) {
												$query[$filterArg] = implode(",", $currentFilter);

												if ("or" == $instance["query_type"])
													$query[$queryTypeArg] = "or";

											} else {
												unset($query[$filterArg]);
												unset($query[$queryTypeArg]);
											}

											$link->appendQuery($query);

											echo '<li ' . $class . '>' .
												(($count > 0 || $termSelected) 
													? '<a href="' . esc_url(apply_filters("lowtone_woocommerce_widgets_tax_filters_nav_link", $link)) . '">' . $term->name . '</a>'
													: '<span>' . $term->name . '</span>') .
												' <small class="count">' . $count . '</small></li>';
										}

										echo '</ul>';
								}

								echo $args[Sidebar::PROPERTY_AFTER_WIDGET];
							},
							"classname" => "woocommerce widget_layered_nav",
						));

				});

				add_filter("woocommerce_layered_nav_link", function($link) {
					foreach (taxonomies() as $taxonomy) {
						$arg = "filter_" . $taxonomy->query_var;

						if (!isset($_GET[$arg]))
							continue;

						$link = add_query_arg($arg, $_GET[$arg], $link);
					}

					return $link;
				});
				
			}
		));

	// Functions
	
	function taxonomies() {
		return apply_filters("lowtone_woocommerce_widgets_tax_filters_taxonomies", array_filter(
				get_taxonomies(array(
					"object_type" => array("product"),
				), "objects"),
				function($taxonomy) {
					if (!($name = $taxonomy->query_var))
						return false;

					/*if ("pa_" == substr($name, 0, 3))
						return false;*/

					return true;
				}
			));
	}

	function filterArgs() {
		$args = array();

		$checkName = function($name) {
			if ("min_price" == $name) 
				return true;

			if ("max_price" == $name)
				return true;

			if ("post_type" == $name)
				return true;

			if ("filter_" == substr($name, 0, 7))
				return true;

			if ("query_type_" == substr($name, 0, 11))
				return true;

			return false;
		};

		foreach ($_GET as $name => $val) {
			if (!$checkName($name))
				continue;

			$args[$name] = $val;
		}

		return $args;
	}

}