<?php
// Register the REST API route for listing podcasts.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/podcasts', array(
        'methods'  => 'GET',
        'callback' => 'zaryab_get_podcasts',
    ));
});

// Register the REST API route for retrieving a single podcast by slug.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/podcasts/(?P<slug>[a-z0-9-]+)', array(
        'methods'  => 'GET',
        'callback' => 'zaryab_get_single_podcast',
    ));
});
// Register the REST API route for similar podcasts (excluding the provided podcast by slug).
add_action('rest_api_init', function () {
    register_rest_route('v1', '/podcasts/similar/(?P<slug>[a-z0-9-]+)', array(
        'methods'  => 'GET',
        'callback' => 'zaryab_get_similar_podcasts',
    ));
});

/**
 * Callback function for retrieving a list of podcasts.
 *
 * Supports pagination using query parameters:
 * - page (default: 1)
 * - per_page (default: 10)
 *
 * Each podcast includes:
 * - image (from the ACF field "image")
 * - slug
 * - name (post title)
 * - host
 * - guest
 * - duration
 * - date (post publish date)
 *
 * @param WP_REST_Request $request The current request object.
 * @return WP_REST_Response JSON response containing podcasts data and meta information.
 */
function zaryab_get_podcasts(WP_REST_Request $request) {
    // Retrieve pagination parameters.
    $page     = (int) $request->get_param('page') ?: 1;
    $per_page = (int) $request->get_param('per_page') ?: 21;

    $args = array(
        'post_type'      => 'podcast',
        'posts_per_page' => $per_page,
        'paged'          => $page,
    );

    $query = new WP_Query($args);
    $podcasts = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $podcast_id = get_the_ID();

            // Retrieve the image field; if it's an array (ACF image field), extract the URL.
            $image_field = get_field('image', $podcast_id);
            $image_url = (is_array($image_field) && isset($image_field['url'])) ? $image_field['url'] : $image_field;

            $podcasts[] = array(
                'image' => get_the_post_thumbnail_url(),
                'slug'     => get_post_field('post_name', $podcast_id),
                'name'     => get_the_title(),
                'host'     => get_field('host', $podcast_id),
                'guest'    => get_field('guest', $podcast_id),
                'duration' => get_field('duration', $podcast_id),
                'date'     => get_the_date('Y-m-d', $podcast_id),
            );
        }
        wp_reset_postdata();
    }

    // Prepare the response including pagination meta data.
    $response = array(
        'data' => $podcasts,
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
 * Callback function for retrieving a single podcast by slug.
 *
 * It returns the following fields:
 * - image (featured image)
 * - slug
 * - name (post title)
 * - host
 * - guest
 * - duration
 * - date (post's published date)
 * - content (the post content)
 * - mp3_file (the file URL from the ACF field)
 *
 * @param WP_REST_Request $request The current request object.
 * @return WP_REST_Response|WP_Error JSON response with the podcast data or error if not found.
 */
function zaryab_get_single_podcast(WP_REST_Request $request) {
    // Retrieve the podcast slug from the URL.
    $slug = $request->get_param('slug');

    // Query for the podcast post using the slug.
    $args = array(
        'post_type'      => 'podcast',
        'name'           => $slug,
        'posts_per_page' => 1,
    );
    $query = new WP_Query($args);

    // If no podcast is found, return a 404 error.
    if (!$query->have_posts()) {
        return new WP_Error('no_podcast', 'No podcast found with the provided slug', array('status' => 404));
    }

    // Set up the post data.
    $query->the_post();
    $podcast_id = get_the_ID();

    // Build the response data.
    $data = array(
        'image'      => get_the_post_thumbnail_url($podcast_id, 'full'), // Featured image.
        'slug'       => get_post_field('post_name', $podcast_id),
        'name'       => get_the_title(),
        'host'       => get_field('host', $podcast_id),
        'guest'      => get_field('guest', $podcast_id),
        'duration'   => get_field('duration', $podcast_id),
        'date'   => get_field('date', $podcast_id),
        'content'    => apply_filters('the_content', get_the_content()),
        'mp3_file'   => get_field('mp3_file', $podcast_id), // Assumes ACF returns the file URL.
    );

    // Reset post data to avoid conflicts.
    wp_reset_postdata();

    // Return the podcast data with a 200 status.
    return new WP_REST_Response($data, 200);
}

/**
 * Callback function for retrieving similar podcasts.
 *
 * This endpoint excludes the podcast identified by the provided slug and supports pagination.
 *
 * Query Parameters:
 * - page: (optional) The page number (default: 1)
 * - per_page: (optional) Number of podcasts per page (default: 10)
 *
 * Each podcast returned includes:
 * - image (ACF field 'image')
 * - slug (post slug)
 * - name (post title)
 * - host (ACF field)
 * - guest (ACF field)
 * - duration (ACF field)
 * - date (post published date)
 *
 * @param WP_REST_Request $request The current request object.
 * @return WP_REST_Response|WP_Error JSON response containing similar podcasts data or an error.
 */
function zaryab_get_similar_podcasts(WP_REST_Request $request) {
    // Retrieve the provided podcast slug.
    $slug = $request->get_param('slug');

    // Get the podcast post by slug to determine the ID to exclude.
    $current_podcast = get_page_by_path($slug, OBJECT, 'podcast');
    if (!$current_podcast) {
        return new WP_Error('no_podcast', 'No podcast found with the provided slug', array('status' => 404));
    }
    $exclude_id = $current_podcast->ID;

    // Retrieve pagination parameters (defaults: page 1, 10 posts per page).
    $page     = (int) $request->get_param('page') ?: 1;
    $per_page = (int) $request->get_param('per_page') ?: 10;

    // Query for podcasts excluding the one identified by the provided slug.
    $args = array(
        'post_type'      => 'podcast',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'post__not_in'   => array($exclude_id),
    );
    $query = new WP_Query($args);
    $similar_podcasts = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $podcast_id = get_the_ID();

            $similar_podcasts[] = array(
                'image' => get_the_post_thumbnail_url(),
                'slug'     => get_post_field('post_name', $podcast_id),
                'name'     => get_the_title(),
                'host'     => get_field('host', $podcast_id),
                'guest'    => get_field('guest', $podcast_id),
                'duration' => get_field('duration', $podcast_id),
                'date'     => get_the_date('Y-m-d', $podcast_id),
            );
        }
        wp_reset_postdata();
    }

    // Prepare and return the response with pagination meta data.
    $response = array(
        'data' => $similar_podcasts,
        'meta' => array(
            'total'    => (int) $query->found_posts,
            'pages'    => (int) $query->max_num_pages,
            'page'     => $page,
            'per_page' => $per_page,
        ),
    );

    return new WP_REST_Response($response, 200);
}