<?php
// Register the REST API route for listing authors archive.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/authors-archive', array(
        'methods'  => 'GET',
        'callback' => 'zaryab_get_authors_archive',
    ));
});

// Register the REST API route for retrieving a single author archive by slug.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/authors-archive/(?P<slug>[a-z0-9-]+)', array(
        'methods'  => 'GET',
        'callback' => 'zaryab_get_single_author_archive',
    ));
});

/**
 * Retrieve a paginated list of authors archive.
 *
 * Fields returned:
 * - title, slug, excerpt, featured image.
 *
 * Query Parameters:
 * - page (default: 1)
 * - per_page (default: 10)
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response JSON response with authors archive list and pagination.
 */
function zaryab_get_authors_archive(WP_REST_Request $request) {
    $page     = (int) $request->get_param('page') ?: 1;
    $per_page = (int) $request->get_param('per_page') ?: 10;

    $args = array(
        'post_type'      => 'authors_archive',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'order'          => 'DESC',
        'orderby'        => 'date',
    );

    $query = new WP_Query($args);
    $authors = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $author_id = get_the_ID();

            $authors[] = array(
                'title'          => get_the_title(),
                'slug'           => get_post_field('post_name', $author_id),
                'excerpt'        => get_the_excerpt(),
                'featured_image' => get_the_post_thumbnail_url($author_id, 'full'),
            );
        }
        wp_reset_postdata();
    }

    // Prepare response with pagination meta
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
 * Retrieve a single author archive by slug.
 *
 * Fields returned:
 * - featured image, title, location, age, job, total_letters, content.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response JSON response with author archive details.
 */
function zaryab_get_single_author_archive(WP_REST_Request $request) {
    $slug = $request->get_param('slug');

    // Retrieve the author archive post by slug
    $author = get_page_by_path($slug, OBJECT, 'authors_archive');
    if (!$author) {
        return new WP_Error('no_author', 'No author archive found with the provided slug', array('status' => 404));
    }
    $author_id = $author->ID;

    // Build response
    $data = array(
        'featured_image' => get_the_post_thumbnail_url($author_id, 'full'),
        'title'          => get_the_title($author_id),
        'location'       => get_field('location', $author_id),
        'age'            => get_field('age', $author_id),
        'job'            => get_field('job', $author_id),
        'total_letters'  => get_field('total_letters', $author_id),
        'content'        => apply_filters('the_content', get_the_content(null, false, $author_id)),
    );

    return new WP_REST_Response($data, 200);
}

