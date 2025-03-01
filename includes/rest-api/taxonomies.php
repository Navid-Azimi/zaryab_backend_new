<?php
// Register REST API routes for multiple taxonomies.
add_action('rest_api_init', function () {
    $taxonomies = array(
        'story_type',
        'poem_type',
        'letter_type',
        'review_type',
        'podcast_type',
        'article_type'
    );

    foreach ($taxonomies as $taxonomy) {
        register_rest_route('v1', "/$taxonomy", array(
            'methods'  => 'GET',
            'callback' => function () use ($taxonomy) {
                return zaryab_get_taxonomy_terms($taxonomy);
            }
        ));
    }
});

/**
 * Callback function to retrieve all terms from a given taxonomy.
 *
 * This endpoint returns:
 * - id (Term ID)
 * - name (Term name)
 * - slug (Term slug)
 * - count (Number of posts in the taxonomy)
 *
 * @param string $taxonomy The taxonomy slug.
 * @return WP_REST_Response JSON response containing the taxonomy terms.
 */
function zaryab_get_taxonomy_terms($taxonomy) {
    $terms = get_terms(array(
        'taxonomy'   => $taxonomy,
        'hide_empty' => false, // Include terms even if they have no posts.
    ));

    if (empty($terms) || is_wp_error($terms)) {
        return new WP_Error('no_terms', "No terms found in $taxonomy", array('status' => 404));
    }

    $term_list = array();
    foreach ($terms as $term) {
        $term_list[] = array(
            'id'    => $term->term_id,
            'name'  => $term->name,
            'slug'  => $term->slug,
            'count' => $term->count, // Number of posts in this taxonomy term.
        );
    }

    return new WP_REST_Response($term_list, 200);
}
