<?php
// Register the REST API route for listing stories.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/stories', array(
        'methods'  => 'GET',
        'callback' => 'zaryab_get_stories',
    ));
});

/**
 * Callback function for retrieving a paginated list of stories.
 *
 * Fields returned:
 * - featured_image, title, excerpt, slug, author name, date, duration, categories.
 *
 * Query Parameters:
 * - page (default: 1)
 * - per_page (default: 10)
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response JSON response with stories data and pagination.
 */
function zaryab_get_stories(WP_REST_Request $request) {
    $page     = (int) $request->get_param('page') ?: 1;
    $per_page = (int) $request->get_param('per_page') ?: 10;

    $args = array(
        'post_type'      => 'stories',
        'posts_per_page' => $per_page,
        'paged'          => $page,
    );

    $query = new WP_Query($args);
    $stories = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $story_id = get_the_ID();

            // Retrieve the author post object from ACF
            $author_field = get_field('author', $story_id);
            $author_name  = is_object($author_field) ? get_the_title($author_field->ID) : '';

            // Retrieve taxonomy terms (categories)
            $terms = get_the_terms($story_id, 'categories');
            $categories = zaryab_format_taxonomy($terms);

            $stories[] = array(
                'featured_image' => get_the_post_thumbnail_url($story_id, 'full'),
                'title'          => get_the_title(),
                'excerpt'        => get_the_excerpt(),
                'slug'           => get_post_field('post_name', $story_id),
                'author'         => $author_name,
                'date'           => get_field('date', $story_id),
                'duration'       => get_field('duration', $story_id),
                'categories'     => $categories,
            );
        }
        wp_reset_postdata();
    }

    // Prepare response with pagination meta
    $response = array(
        'data' => $stories,
        'meta' => array(
            'total'    => (int) $query->found_posts,
            'pages'    => (int) $query->max_num_pages,
            'page'     => $page,
            'per_page' => $per_page,
        ),
    );

    return new WP_REST_Response($response, 200);
}
