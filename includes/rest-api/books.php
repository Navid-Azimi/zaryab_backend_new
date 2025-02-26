<?php
// Register the REST API route for the featured book.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/books/featured', array(
        'methods'  => 'GET',
        'callback' => 'zaryab_get_featured_book',
    ));
});

/**
 * Callback function for retrieving the latest added book.
 *
 * This endpoint returns:
 * - title (post title)
 * - featured_image (featured image URL)
 * - excerpt (post excerpt)
 * - slug (post slug)
 * - pdf (only the file URL from the ACF field)
 *
 * @return WP_REST_Response JSON response containing the latest book.
 */
function zaryab_get_featured_book() {
    // Query for the latest book.
    $args = array(
        'post_type'      => 'book',
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    $query = new WP_Query($args);

    if (!$query->have_posts()) {
        return new WP_Error('no_book', 'No book found', array('status' => 404));
    }

    // Retrieve the latest book post.
    $query->the_post();
    $book_id = get_the_ID();

    // Retrieve the ACF file field and extract the URL.
    $pdf_field = get_field('pdf', $book_id);
    $pdf_url = is_array($pdf_field) && isset($pdf_field['url']) ? $pdf_field['url'] : '';

    // Build the response data.
    $data = array(
        'title'         => get_the_title(),
        'featured_image' => get_the_post_thumbnail_url($book_id, 'full'),
        'excerpt'       => get_the_excerpt(),
        'slug'          => get_post_field('post_name', $book_id),
        'pdf'           => $pdf_url,
    );

    wp_reset_postdata();
    return new WP_REST_Response($data, 200);
}


// Register the REST API route for retrieving a single book by slug.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/books/(?P<slug>[a-z0-9-]+)', array(
        'methods'  => 'GET',
        'callback' => 'zaryab_get_single_book',
    ));
});

/**
 * Callback function for retrieving a single book by slug.
 *
 * Returns the following fields:
 * - featured_image (featured image URL)
 * - collection (ACF text field)
 * - date_shamsi (ACF field)
 * - time (ACF field)
 * - categories (taxonomy terms)
 * - content (post content)
 * - author (post object fields: featured_image, name, location, job, total_letters, age, facebook, instagram, telegram, youtube)
 *
 * @param WP_REST_Request $request The current request object.
 * @return WP_REST_Response|WP_Error JSON response containing book details or an error if not found.
 */
function zaryab_get_single_book(WP_REST_Request $request) {
    // Retrieve the book slug from the URL.
    $slug = $request->get_param('slug');

    // Query for the book post using the slug.
    $args = array(
        'post_type'      => 'book',
        'name'           => $slug,
        'posts_per_page' => 1,
    );

    $query = new WP_Query($args);

    if (!$query->have_posts()) {
        return new WP_Error('no_book', 'No book found with the provided slug', array('status' => 404));
    }

    // Retrieve the book post data.
    $query->the_post();
    $book_id = get_the_ID();

    // Retrieve taxonomy terms (categories).
    $terms = get_the_terms($book_id, 'categories');
    $categories = array();
    if ($terms && !is_wp_error($terms)) {
        foreach ($terms as $term) {
            $categories[] = array(
                'id'   => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
            );
        }
    }

    // Retrieve the author field (post object).
    $author_field = get_field('author', $book_id);
    $author_data = null;
    if ($author_field) {
        $author_id = $author_field->ID;
        $author_data = array(
            'featured_image' => get_the_post_thumbnail_url($author_id, 'full'),
            'name'           => get_the_title($author_id),
            'location'       => get_field('location', $author_id),
            'job'            => get_field('job', $author_id),
            'total_letters'  => get_field('total_letters', $author_id),
            'age'            => get_field('age', $author_id),
            'facebook'       => get_field('facebook', $author_id),
            'instagram'      => get_field('instagram', $author_id),
            'telegram'       => get_field('telegram', $author_id),
            'youtube'        => get_field('youtube', $author_id),
        );
    }

    // Build the response data.
    $data = array(
        'featured_image' => get_the_post_thumbnail_url($book_id, 'full'),
        'collection'     => get_field('collection', $book_id),
        'date_shamsi'    => get_field('date_shamsi', $book_id),
        'time'           => get_field('time', $book_id),
        'categories'     => $categories,
        'content'        => apply_filters('the_content', get_the_content()),
        'author'         => $author_data,
    );

    wp_reset_postdata();
    return new WP_REST_Response($data, 200);
}
