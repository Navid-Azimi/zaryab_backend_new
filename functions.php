<?php
require_once get_template_directory() . '/includes/custom-post-types.php';
require_once get_template_directory() . '/includes/acf-fields.php';

// Load all REST API files dynamically
foreach (glob(get_template_directory() . "/includes/rest-api/*.php") as $file) {
    require_once $file;
}

/**
 * Format taxonomy terms into a structured array.
 *
 * @param array|null|WP_Error $terms Taxonomy terms from `get_the_terms()`.
 * @return array Structured list of terms.
 */
function zaryab_format_taxonomy($terms)
{
    $formatted = array();
    if ($terms && !is_wp_error($terms)) {
        foreach ($terms as $term) {
            $formatted[] = array(
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
            );
        }
    }
    return $formatted;
}
