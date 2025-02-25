<?php
// Register the REST API route for listing podcasts.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/podcasts', array(
        'methods'  => 'GET',
        'callback' => 'zaryab_get_podcasts',
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
