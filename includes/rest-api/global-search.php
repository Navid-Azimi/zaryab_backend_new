<?php
// Register the REST API route for global search.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/global-search', array(
        'methods' => 'GET',
        'callback' => 'zaryab_global_search',
    ));
});

/**
 * Perform a global search across multiple post types with category filtering.
 *
 * Query Parameters:
 * - keyword (search term, required)
 * - categories (comma-separated taxonomy slugs, optional)
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response JSON response with categorized search results.
 */
function zaryab_global_search(WP_REST_Request $request)
{
    $keyword = sanitize_text_field(urldecode($request->get_param('keyword')));
    $categories = $request->get_param('categories'); // Optional category filter

    // Post types to search in
    $post_types = array('stories', 'poem', 'articles', 'review', 'podcast', 'letters');

    // Taxonomy filtering
    $tax_query = array();
    if (!empty($categories)) {
        $category_slugs = explode(',', $categories);
        $tax_query[] = array(
            'taxonomy' => 'categories',
            'field' => 'slug',
            'terms' => $category_slugs,
            'operator' => 'IN',
        );
    }

    // Initialize results array
    $search_results = array();

    foreach ($post_types as $post_type) {
        $args = array(
            'post_type' => $post_type,
            'posts_per_page' => -1, // Fetch all matching posts
        );

        // Apply search filter only if a keyword is provided
        if (!empty($keyword)) {
            $args['s'] = $keyword;
            $args['sentence'] = true; // Force exact phrase search
        }

        // Apply category filtering if provided
        if (!empty($categories)) {
            $args['tax_query'] = $tax_query;
        }

        $query = new WP_Query($args);
        $posts = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $posts[] = array(
                    'title' => get_the_title(),
                    'featured_image' => get_the_post_thumbnail_url(get_the_ID(), 'full'),
                    'slug' => get_post_field('post_name', get_the_ID()),
                );
            }
            wp_reset_postdata();
        }

        // Store results by post type
        $search_results[$post_type] = array(
            'count' => (int)$query->found_posts,
            'posts' => $posts,
        );
    }

    return new WP_REST_Response($search_results, 200);
}

