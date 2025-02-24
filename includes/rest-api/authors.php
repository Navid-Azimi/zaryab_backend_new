<?php
// Register the REST API route for listing authors.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/authors', array(
        'methods'  => 'GET',
        'callback' => 'zaryab_get_authors',
    ));
});

// Register the REST API route for retrieving a single author by slug.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/authors/(?P<slug>[a-z0-9-]+)', array(
        'methods'  => 'GET',
        'callback' => 'zaryab_get_single_author',
    ));
});

/**
 * Callback function for the /authors endpoint.
 *
 * This endpoint supports pagination via query parameters:
 * - page (default: 1)
 * - per_page (default: 10)
 *
 * It returns an array of authors with the following fields:
 * - name
 * - job
 * - location
 * - total_letters
 *
 * @param WP_REST_Request $request The current request object.
 * @return WP_REST_Response JSON response containing authors data and meta info.
 */
function zaryab_get_authors(WP_REST_Request $request) {
    // Retrieve pagination parameters (defaults: page 1, 10 posts per page)
    $page     = (int) $request->get_param('page') ?: 1;
    $per_page = (int) $request->get_param('per_page') ?: 10;

    $args = array(
        'post_type'      => 'authors',
        'posts_per_page' => $per_page,
        'paged'          => $page,
    );

    $query = new WP_Query($args);
    $authors = array();


    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $author_id = get_the_ID();
            $authors[] = array(
                'name'          => get_the_title(),
                'image'          => get_the_post_thumbnail_url(),
                'job'           => get_field('job', $author_id),
                'location'      => get_field('location', $author_id),
                'total_letters' => get_field('total_letters', $author_id),
            );
        }
        wp_reset_postdata();
    }

    $response = array(
        'data' => $authors,
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
 * Callback function for retrieving a single author by slug.
 *
 * @param WP_REST_Request $request The current request object.
 * @return WP_REST_Response|WP_Error JSON response containing the author data or error if not found.
 */
function zaryab_get_single_author(WP_REST_Request $request) {
    // Get the author slug from the URL parameters.
    $slug = $request->get_param('slug');

    // Query for the author post using the slug.
    $args = array(
        'post_type'      => 'authors',
        'name'           => $slug,
        'posts_per_page' => 1,
    );
    $query = new WP_Query($args);

    // If no post is found, return a 404 error.
    if (!$query->have_posts()) {
        return new WP_Error('no_author', 'No author found with the provided slug', array('status' => 404));
    }

    // Retrieve the author post data.
    $query->the_post();
    $author_id = get_the_ID();

    // Build the response data using the ACF fields.
    $data = array(
        'name'           => get_the_title(),
        'content'           => get_the_content(),
        'featured_image' => get_the_post_thumbnail_url($author_id, 'full'),
        'location'       => get_field('location', $author_id),
        'job'            => get_field('job', $author_id),
        'total_letters'  => get_field('total_letters', $author_id),
        'age'            => get_field('age', $author_id),
        'facebook'       => get_field('facebook', $author_id),
        'instagram'      => get_field('instagram', $author_id),
        'telegram'       => get_field('telegram', $author_id),
        'youtube'        => get_field('youtube', $author_id),
    );

    // Reset post data to avoid conflicts.
    wp_reset_postdata();

    // Return the author data with a 200 status.
    return new WP_REST_Response($data, 200);
}
