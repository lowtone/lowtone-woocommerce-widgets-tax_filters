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

	add_filter("woocommerce_attribute_taxonomies", function($taxonomies) {
		if (!isLayeredNav())
			return $taxonomies;

		$productTaxonomies = get_taxonomies(array(
				"object_type" => array("product"),
			), "object");

		foreach ($productTaxonomies as $taxonomy) {
			if (!($name = $taxonomy->query_var))
				continue;

			if ("pa_" == substr($name, 0, 3))
				continue;

			array_unshift($taxonomies, (object) array(
					"attribute_name" => $taxonomy->query_var,
					"attribute_label" => $taxonomy->labels->singular_name,
					"attribute_type" => "select",
					"attribute_orderby" => "menu_order",
				));
		}

		return $taxonomies;
	});

	// Functions
	
	function isLayeredNav() {
		foreach (debug_backtrace() as $trace) {
			if (!(isset($trace["function"]) && "woocommerce_layered_nav_init" == $trace["function"]) &&
				!(isset($trace["class"]) && "WC_Widget_Layered_Nav" == $trace["class"]))
					continue;

			return true;
		}

		return false;
	}

}