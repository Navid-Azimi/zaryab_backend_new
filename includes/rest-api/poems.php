<?php
// Register the REST API route for listing poems.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/poems', array(
        'methods'  => 'GET',
        'callback' => 'zaryab_get_poems',
    ));
});

/**
 * Callback function for retrieving a paginated list of poems.
 *
 * Fields returned:
 * - title, featured image, custom excerpt (first 3 lines), author name, slug, date, time, taxonomy (poem_type).
 *
 * Query Parameters:
 * - page (default: 1)
 * - per_page (default: 10)
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response JSON response.
 */
function zaryab_get_poems(WP_REST_Request $request) {
    $page     = (int) $request->get_param('page') ?: 1;
    $per_page = (int) $request->get_param('per_page') ?: 10;

    $args = array(
        'post_type'      => 'poem',
        'posts_per_page' => $per_page,
        'paged'          => $page,
    );

    $query = new WP_Query($args);
    $poems = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $poem_id = get_the_ID();

            // Extract the first 3 lines of the content (up to the third <br> tag)
            $content = get_the_content();
            $excerpt = zaryab_get_excerpt_by_br($content, 3);

            // Retrieve the author post object
            $author_field = get_field('author', $poem_id);
            $author_name  = is_object($author_field) ? get_the_title($author_field->ID) : '';

            // Retrieve taxonomy terms
            $terms = get_the_terms($poem_id, 'poem_type');
            $poem_types = array();
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $poem_types[] = array(
                        'id'   => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                    );
                }
            }

            $poems[] = array(
                'title'          => get_the_title(),
                'featured_image' => get_the_post_thumbnail_url($poem_id, 'full'),
                'excerpt'        => $excerpt,
                'author'         => $author_name,
                'slug'           => get_post_field('post_name', $poem_id),
                'date'           => get_field('date', $poem_id),
                'time'           => get_field('time', $poem_id),
                'poem_type'      => $poem_types,
            );
        }
        wp_reset_postdata();
    }

    // Prepare the response with pagination metadata
    $response = array(
        'data' => $poems,
        'meta' => array(
            'total'    => (int) $query->found_posts,
            'pages'    => (int) $query->max_num_pages,
            'page'     => $page,
            'per_page' => $per_page,
        ),
    );

    return new WP_REST_Response($response, 200);
}

/**
 * Extract the first `n` lines of the content based on `<br>` tags.
 *
 * @param string $content Full post content.
 * @param int $num_br The number of `<br>` tags to extract up to.
 * @return string Extracted content.
 */
function zaryab_get_excerpt_by_br($content, $num_br = 3) {
    // Ensure the content has <br> tags
    $content = wpautop($content);

    // Split content by <br> tags
    $parts = preg_split('/<br[^>]*>/i', $content);

    // Return the first n lines joined by <br>
    return implode('<br>', array_slice($parts, 0, $num_br));
}
