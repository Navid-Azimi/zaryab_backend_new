<?php
// Register the REST API route for listing letters with filtering.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/letters', array(
        'methods' => 'GET',
        'callback' => 'zaryab_get_letters',
    ));
});


// Register the REST API route for retrieving a single letter by slug.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/letters/(?P<slug>[a-z0-9-]+)', array(
        'methods' => 'GET',
        'callback' => 'zaryab_get_single_letter',
    ));
});


/**
 * Retrieve a paginated list of letters filtered by `letter_type`.
 *
 * Supports filtering by:
 * - `all` (default): Retrieves all letters.
 * - `archive`: Retrieves only archived letters.
 * - `non-archive`: Retrieves only non-archived letters.
 * - Specific `letter_type` taxonomy slugs (comma-separated, optional).
 *
 * Query Parameters:
 * - page (default: 1)
 * - per_page (default: 10)
 * - type (letter type filter: all, archive, non-archive)
 * - letter_type (comma-separated taxonomy slugs, optional)
 *
 * @param WP_REST_Request $request The current request object.
 * @return WP_REST_Response JSON response containing letters data and meta information.
 */
function zaryab_get_letters(WP_REST_Request $request)
{
    // Retrieve pagination parameters.
    $page = (int)$request->get_param('page') ?: 1;
    $per_page = (int)$request->get_param('per_page') ?: 10;
    $type = $request->get_param('type') ?: 'all'; // Filter type: all, archive, non-archive
    $letter_type = $request->get_param('letter_type');   // Optional filter by letter_type taxonomy

    $args = array(
        'post_type' => 'letters',
        'posts_per_page' => $per_page,
        'paged' => $page,
    );

    // Initialize taxonomy filtering
    $tax_query = array();

    // Apply predefined filters for archive and non-archive letters
    if ($type === 'archive') {
        $tax_query[] = array(
            'taxonomy' => 'letter_type',
            'field' => 'slug',
            'terms' => 'archive',
        );
    } elseif ($type === 'non-archive') {
        $tax_query[] = array(
            'taxonomy' => 'letter_type',
            'field' => 'slug',
            'terms' => 'archive',
            'operator' => 'NOT IN',
        );
    }

    // Add letter_type taxonomy filtering if provided
    if (!empty($letter_type)) {
        $letter_type_slugs = explode(',', $letter_type);
        $tax_query[] = array(
            'taxonomy' => 'letter_type',
            'field' => 'slug',
            'terms' => $letter_type_slugs,
            'operator' => 'IN', // Allows filtering by multiple slugs
        );
    }

    // Apply taxonomy filters if any are set
    if (!empty($tax_query)) {
        $args['tax_query'] = $tax_query;
    }

    $query = new WP_Query($args);
    $letters = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $letter_id = get_the_ID();

            // Retrieve the ACF file field and extract the URL.
            $pdf_field = get_field('pdf', $letter_id);
            $pdf_url = is_array($pdf_field) && isset($pdf_field['url']) ? $pdf_field['url'] : '';

            $letters[] = array(
                'featured_image' => get_the_post_thumbnail_url($letter_id, 'full'),
                'title' => get_the_title(),
                'number' => get_field('number', $letter_id),
                'release_date' => get_field('release_date', $letter_id),
                'slug' => get_post_field('post_name', $letter_id),
                'pdf' => $pdf_url,
            );
        }
        wp_reset_postdata();
    }

    // Prepare the response with pagination meta data.
    $response = array(
        'data' => $letters,
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
 * Callback function for retrieving a single letter.
 *
 * Returns:
 * - number (ACF field)
 * - title (post title)
 * - images (ACF repeater field with image URL and number)
 *
 * @param WP_REST_Request $request The current request object.
 * @return WP_REST_Response|WP_Error JSON response containing letter details or an error if not found.
 */
function zaryab_get_single_letter(WP_REST_Request $request)
{
    // Retrieve the letter slug from the URL.
    $slug = $request->get_param('slug');

    // Query for the letter post using the slug.
    $args = array(
        'post_type' => 'letters',
        'name' => $slug,
        'posts_per_page' => 1,
    );

    $query = new WP_Query($args);

    if (!$query->have_posts()) {
        return new WP_Error('no_letter', 'No letter found with the provided slug', array('status' => 404));
    }

    // Retrieve the letter post data.
    $query->the_post();
    $letter_id = get_the_ID();

    // Retrieve images from the ACF repeater field.
    $images = array();
    if (have_rows('images', $letter_id)) {
        while (have_rows('images', $letter_id)) {
            the_row();
            $images[] = array(
                'number' => get_sub_field('number'),
                'image' => get_sub_field('image')['url'], // Only return image URL.
            );
        }
    }

    // Build the response.
    $data = array(
        'number' => get_field('number', $letter_id),
        'title' => get_the_title(),
        'images' => $images,
    );

    wp_reset_postdata();
    return new WP_REST_Response($data, 200);
}