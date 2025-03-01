<?php
// Register the REST API route for listing poems.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/poems', array(
        'methods'  => 'GET',
        'callback' => 'zaryab_get_poems',
    ));
});

// Register the REST API route for retrieving a single poem by slug.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/poems/(?P<slug>[a-z0-9-]+)', array(
        'methods'  => 'GET',
        'callback' => 'zaryab_get_single_poem',
    ));
});


// Register the REST API route for similar poems.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/poems/similar/(?P<slug>[a-z0-9-]+)', array(
        'methods'  => 'GET',
        'callback' => 'zaryab_get_similar_poems',
    ));
});

// Register the REST API route for poem collection.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/poems/collection/(?P<slug>[a-z0-9-]+)', array(
        'methods'  => 'GET',
        'callback' => 'zaryab_get_poems_by_collection',
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



/**
 * Callback function for retrieving a single poem by slug.
 *
 * Fields returned:
 * - title
 * - taxonomy (poem_collection)
 * - date (acf field)
 * - time (acf field)
 * - taxonomy (poem_type)
 * - content (first 3 lines)
 * - author (detailed post object)
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response JSON response.
 */
function zaryab_get_single_poem(WP_REST_Request $request) {
    $slug = $request->get_param('slug');

    // Query the poem by slug.
    $poem = get_page_by_path($slug, OBJECT, 'poem');
    if (!$poem) {
        return new WP_Error('no_poem', 'No poem found with the provided slug', array('status' => 404));
    }
    $poem_id = $poem->ID;

    // Extract first 3 lines of content based on <br> tags.
    $content = get_the_content(null, false, $poem_id);

    // Retrieve poem_collection taxonomy.
    $poem_collections = get_the_terms($poem_id, 'poem_collection');
    $poem_collection_list = zaryab_format_taxonomy($poem_collections);

    // Retrieve poem_type taxonomy.
    $poem_types = get_the_terms($poem_id, 'poem_type');
    $poem_type_list = zaryab_format_taxonomy($poem_types);

    // Retrieve the author post object.
    $author_field = get_field('author', $poem_id);
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

    // Build the final response.
    $data = array(
        'title'          => get_the_title($poem_id),
        'poem_collection'=> $poem_collection_list,
        'date'           => get_field('date', $poem_id),
        'time'           => get_field('time', $poem_id),
        'poem_type'      => $poem_type_list,
        'content'        => $content,
        'author'         => $author_data,
    );

    return new WP_REST_Response($data, 200);
}
/**
 * Retrieve similar poems, excluding the provided poem by slug.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response JSON response with similar poems.
 */
function zaryab_get_similar_poems(WP_REST_Request $request) {
    $slug     = $request->get_param('slug');
    $page     = (int) $request->get_param('page') ?: 1;
    $per_page = (int) $request->get_param('per_page') ?: 10;

    // Get the poem ID by slug
    $poem = get_page_by_path($slug, OBJECT, 'poem');
    if (!$poem) {
        return new WP_Error('no_poem', 'No poem found with the provided slug', array('status' => 404));
    }
    $exclude_id = $poem->ID;

    // Modify query to exclude this poem
    $args = array(
        'post_type'      => 'poem',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'post__not_in'   => array($exclude_id), // Exclude this poem
    );

    $query = new WP_Query($args);
    $poems = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $poem_id = get_the_ID();

            // Extract first 3 lines of content based on <br> tags
            $content = get_the_content(null, false, $poem_id);
            $excerpt = zaryab_get_excerpt_by_br($content, 3);

            // Retrieve the author post object
            $author_field = get_field('author', $poem_id);
            $author_name  = is_object($author_field) ? get_the_title($author_field->ID) : '';

            // Retrieve taxonomy terms (poem_type)
            $terms = get_the_terms($poem_id, 'poem_type');
            $poem_types = zaryab_format_taxonomy($terms);

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

    // Prepare response with pagination meta
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
 * Retrieve poems filtered by poem_collection.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response JSON response with paginated poems in the collection.
 */
function zaryab_get_poems_by_collection(WP_REST_Request $request) {
    $slug     = $request->get_param('slug');
    $page     = (int) $request->get_param('page') ?: 1;
    $per_page = (int) $request->get_param('per_page') ?: 10;

    // Query poems that belong to the provided poem_collection
    $args = array(
        'post_type'      => 'poem',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'tax_query'      => array(
            array(
                'taxonomy' => 'poem_collection',
                'field'    => 'slug',
                'terms'    => $slug,
            ),
        ),
    );

    $query = new WP_Query($args);
    $poems = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $poem_id = get_the_ID();

            // Extract first 3 lines of content based on <br> tags
            $content = get_the_content(null, false, $poem_id);
            $excerpt = zaryab_get_excerpt_by_br($content, 3);

            // Retrieve the author post object
            $author_field = get_field('author', $poem_id);
            $author_name  = is_object($author_field) ? get_the_title($author_field->ID) : '';

            // Retrieve taxonomy terms (poem_type)
            $terms = get_the_terms($poem_id, 'poem_type');
            $poem_types = zaryab_format_taxonomy($terms);

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

    // Prepare response with pagination meta
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
