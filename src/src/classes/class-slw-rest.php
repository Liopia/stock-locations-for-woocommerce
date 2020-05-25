<?php
/**
 * SLW Product Rest Class
 *
 * @since 1.0.0
 */

namespace SLW\SRC\Classes;

/**
 * If this file is called directly, abort.
 *
 * @since 1.0.0
 */
if ( !defined( 'WPINC' ) ) {
    die;
}

if(!class_exists('SlwProductRest')) {

    class SlwProductRest
    {

        /**
         * Construct.
         *
         * @since 1.1.0
         */
        public function __construct()
        {
            add_filter('woocommerce_rest_prepare_product', array($this, 'prepare_product'), 10, 2); // WC 2.6.x
            add_filter('woocommerce_rest_prepare_product_object', array($this, 'prepare_product'), 10, 2); // WC 3.x
            add_action('woocommerce_rest_insert_product', array($this, 'insert_product'), 10, 3); // WC 2.6.x
            add_action('woocommerce_rest_insert_product_object', array($this, 'insert_product'), 10, 3); // WC 3.x
        }

        /**
         * @param $response
         * @param $post
         *
         * @return mixed
         */
        public function prepare_product($response, $post)
        {
            $post_id = is_callable(array($post, 'get_id')) ? $post->get_id() : (!empty($post->ID) ? $post->ID : null);

            if (empty($response->data[SlwProductTaxonomy::get_tax_names('plural')])) {
                $terms = array();

                foreach (wp_get_post_terms($post_id, SlwProductTaxonomy::get_tax_names('singular')) as $term) {
                    $terms[] = array(
                        'id'   => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                        'quantity' => get_post_meta($post_id, '_stock_at_' . $term->term_id, true)
                    );
                }

                $response->data[SlwProductTaxonomy::get_tax_names('plural')] = $terms;
            }

            return $response;
        }

        /**
         * @param $post
         * @param $request
         */
        public function insert_product($post, $request)
        {
            // There is nothing to do
            if (!isset($request[SlwProductTaxonomy::get_tax_names('plural')])) {
                return;
            }

            // location data
            $locations = $request[SlwProductTaxonomy::get_tax_names('plural')];

            // Data is not valid or empty, nothing to do
            if (!is_array($locations) || !sizeof($locations)) {
                return;
            }

            $stockLocationTermIds = array();
            foreach ($locations as $location) {
                $locationId = (isset($location['id'])) ? absint($location['id']) : get_term_by('slug', $location['slug'], SlwProductTaxonomy::get_tax_names('singular'))->term_id;
                $quantity = (isset($location['quantity'])) ? $location['quantity'] : 0;

                // It is possible to provide a null quantity to delete product from location
                if (is_null($quantity)) {
                    // Delete post meta
                    delete_post_meta($post->id, '_stock_at_' . $locationId);
                } else {
                    // Set locations stock level
                    update_post_meta($post->id, '_stock_at_' . $locationId, $quantity);
                }

                // We must only keep location IDs we wish to keep as valid locations
                if (!is_null($quantity)) {
                    $stockLocationTermIds[] = $locationId;
                }
            }

            // Set terms
            wp_set_object_terms($post->id, $stockLocationTermIds, SlwProductTaxonomy::get_tax_names('singular'));
        }

    }

}
