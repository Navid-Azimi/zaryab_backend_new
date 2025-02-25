<?php
// Register the REST API route for listing author reviews.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/author-reviews', array(
        'methods' => 'GET',
        'callback' => 'zaryab_get_author_reviews',
    ));
});

// Register the REST API route for similar reviews (excluding the provided review by slug).
add_action('rest_api_init', function () {
    register_rest_route('v1', '/author-reviews/similar/(?P<slug>[a-z0-9-]+)', array(
        'methods' => 'GET',
        'callback' => 'zaryab_get_similar_author_reviews',
    ));
});

/**
 * Helper function to assemble review data.
 *
 * @param int $post_id The ID of the review post.
 * @return array The review data.
 */
function get_author_review_data($post_id)
{
    // Featured image URL.
    $image_url = get_the_post_thumbnail_url($post_id, 'full');

    // Title, excerpt, and slug.
    $title = get_the_title($post_id);
    $excerpt = get_the_excerpt($post_id);
    $slug = get_post_field('post_name', $post_id);

    // ACF "author" field is a post object; return its title.
    $author_field = get_field('author', $post_id);
    if (is_object($author_field)) {
        $author_title = $author_field->post_title;
    } elseif (is_array($author_field) && isset($author_field['post_title'])) {
        $author_title = $author_field['post_title'];
    } else {
        $author_title = '';
    }

    // ACF fields.
    $date_shamsi = get_field('date_shamsi', $post_id);
    $time = get_field('time', $post_id);

    // Retrieve terms from the taxonomy "categories".
    $terms = get_the_terms($post_id, 'categories');
    $categories = array();
    if ($terms && !is_wp_error($terms)) {
        foreach ($terms as $term) {
            $categories[] = array(
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
            );
        }
    }

    return array(
        'image' => $image_url,
        'title' => $title,
        'excerpt' => $excerpt,
        'slug' => $slug,
        'author' => $author_title,
        'date_shamsi' => $date_shamsi,
        'time' => $time,
        'categories' => $categories,
    );
}

/**
 * Callback for listing author reviews.
 *
 * Supports pagination using:
 * - page (default: 1)
 * - per_page (default: 10)
 *
 * @param WP_REST_Request $request The current request object.
 * @return WP_REST_Response JSON response containing reviews data and meta information.
 */
function zaryab_get_author_reviews(WP_REST_Request $request)
{
    $page = (int)$request->get_param('page') ?: 1;
    $per_page = (int)$request->get_param('per_page') ?: 10;

    $args = array(
        'post_type' => 'review',
        'posts_per_page' => $per_page,
        'paged' => $page,
    );

    $query = new WP_Query($args);
    $reviews = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $reviews[] = get_author_review_data(get_the_ID());
        }
        wp_reset_postdata();
    }

    $response = array(
        'data' => $reviews,
        'meta' => array(
            'total' => (int)$query->found_posts,
            'pages' => (int)$query->max_num_pages,
            'page' => $page,
            'per_page' => $per_page,
        ),
    );

    return new WP_REST_Response($response, 200);
}

/**
 * Callback for retrieving similar author reviews.
 *
 * Excludes the review identified by the provided slug and supports pagination.
 *
 * @param WP_REST_Request $request The current request object.
 * @return WP_REST_Response|WP_Error JSON response containing similar reviews data or error if not found.
 */
function zaryab_get_similar_author_reviews(WP_REST_Request $request)
{
    // Retrieve the review slug from the URL.
    $slug = $request->get_param('slug');

    // Get the review post by slug.
    $review_post = get_page_by_path($slug, OBJECT, 'review');
    if (!$review_post) {
        return new WP_Error('no_review', 'No review found with the provided slug', array('status' => 404));
    }
    $exclude_id = $review_post->ID;

    $page = (int)$request->get_param('page') ?: 1;
    $per_page = (int)$request->get_param('per_page') ?: 10;

    $args = array(
        'post_type' => 'review',
        'posts_per_page' => $per_page,
        'paged' => $page,
        'post__not_in' => array($exclude_id),
    );

    $query = new WP_Query($args);
    $reviews = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $reviews[] = get_author_review_data(get_the_ID());
        }
        wp_reset_postdata();
    }

    $response = array(
        'data' => $reviews,
        'meta' => array(
            'total' => (int)$query->found_posts,
            'pages' => (int)$query->max_num_pages,
            'page' => $page,
            'per_page' => $per_page,
        ),
    );

    return new WP_REST_Response($response, 200);
}
