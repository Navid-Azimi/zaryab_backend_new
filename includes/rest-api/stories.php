<?php
// Register the REST API route for listing stories.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/stories', array(
        'methods' => 'GET',
        'callback' => 'zaryab_get_stories',
    ));
});


// Register the REST API route for retrieving episodes of a story.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/stories/(?P<slug>[a-z0-9-]+)', array(
        'methods' => 'GET',
        'callback' => 'zaryab_get_story_episodes',
    ));
});

// Register the REST API route for retrieving stories excluding a specific slug.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/stories/similar/(?P<slug>[a-z0-9-]+)', array(
        'methods' => 'GET',
        'callback' => 'zaryab_get_stories_excluding_slug',
    ));
});

// Register the REST API route for retrieving stories filtered by collection slug.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/stories/collection/(?P<slug>[a-z0-9-]+)', array(
        'methods' => 'GET',
        'callback' => 'zaryab_get_stories_by_collection',
    ));
});

/**
 * Retrieve a paginated list of stories with multi-slug filtering by `categories` and `story_type`.
 *
 * Query Parameters:
 * - page (default: 1)
 * - per_page (default: 10)
 * - categories (comma-separated taxonomy slugs, optional)
 * - story_type (comma-separated taxonomy slugs, optional)
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response JSON response with stories data and pagination.
 */
function zaryab_get_stories(WP_REST_Request $request)
{
    $page = (int)$request->get_param('page') ?: 1;
    $per_page = (int)$request->get_param('per_page') ?: 10;
    $categories = $request->get_param('categories');  // Optional filter (comma-separated slugs)
    $story_type = $request->get_param('story_type');  // Optional filter (comma-separated slugs)

    $tax_query = array('relation' => 'AND');

    // Process `categories` filter (supports multiple slugs)
    if (!empty($categories)) {
        $category_slugs = explode(',', $categories); // Convert comma-separated string to array
        $tax_query[] = array(
            'taxonomy' => 'categories',
            'field' => 'slug',
            'terms' => $category_slugs,
            'operator' => 'IN', // Allows filtering by multiple slugs
        );
    }

    // Process `story_type` filter (supports multiple slugs)
    if (!empty($story_type)) {
        $story_type_slugs = explode(',', $story_type); // Convert comma-separated string to array
        $tax_query[] = array(
            'taxonomy' => 'story_type',
            'field' => 'slug',
            'terms' => $story_type_slugs,
            'operator' => 'IN', // Allows filtering by multiple slugs
        );
    }

    // Define query args
    $args = array(
        'post_type' => 'stories',
        'posts_per_page' => $per_page,
        'paged' => $page,
    );

    // Add taxonomy filtering if at least one filter is applied
    if (!empty($categories) || !empty($story_type)) {
        $args['tax_query'] = $tax_query;
    }

    $query = new WP_Query($args);
    $stories = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $story_id = get_the_ID();

            // Retrieve the author post object from ACF
            $author_field = get_field('author', $story_id);
            $author_name = is_object($author_field) ? get_the_title($author_field->ID) : '';

            // Retrieve taxonomy terms (categories)
            $terms = get_the_terms($story_id, 'categories');
            $categories = zaryab_format_taxonomy($terms);

            $stories[] = array(
                'featured_image' => get_the_post_thumbnail_url($story_id, 'full'),
                'title' => get_the_title(),
                'excerpt' => get_the_excerpt(),
                'slug' => get_post_field('post_name', $story_id),
                'author' => $author_name,
                'date' => get_field('date', $story_id),
                'duration' => get_field('duration', $story_id),
                'categories' => $categories,
            );
        }
        wp_reset_postdata();
    }

    // Prepare response with pagination meta
    $response = array(
        'data' => $stories,
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
 * Retrieve paginated episodes of a story with filtering by title, episode number, episode title, and categories.
 *
 * Query Parameters:
 * - page (default: 1)
 * - per_page (default: 10)
 * - keyword (title or episode_title, optional)
 * - episode_number (exact match, optional)
 * - episode_title (exact match, optional)
 * - categories (comma-separated taxonomy slugs, optional)
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response JSON response with the story title and its episodes.
 */
function zaryab_get_story_episodes(WP_REST_Request $request)
{
    $slug = $request->get_param('slug');
    $page = (int)$request->get_param('page') ?: 1;
    $per_page = (int)$request->get_param('per_page') ?: 10;
    $keyword = $request->get_param('keyword'); // Optional title search
    $episode_number = $request->get_param('episode_number'); // Optional exact episode number
    $episode_title = $request->get_param('episode_title'); // Optional exact episode title
    $categories = $request->get_param('categories'); // Optional taxonomy filter

    // Retrieve the story post by slug
    $story = get_page_by_path($slug, OBJECT, 'stories');
    if (!$story) {
        return new WP_Error('no_story', 'No story found with the provided slug', array('status' => 404));
    }
    $story_id = $story->ID;
    $story_title = get_the_title($story_id);

    // Query for episodes linked to this story
    $meta_query = array(
        array(
            'key' => 'story', // ACF post object field
            'value' => $story_id,
            'compare' => '=',
        ),
    );

    // Add `episode_number` filter if provided
    if (!empty($episode_number)) {
        $meta_query[] = array(
            'key' => 'episode_number',
            'value' => $episode_number,
            'compare' => '=',
            'type' => 'NUMERIC',
        );
    }

    // Add `episode_title` filter if provided
    if (!empty($episode_title)) {
        $meta_query[] = array(
            'key' => 'episode_title',
            'value' => $episode_title,
            'compare' => 'LIKE',
        );
    }

    // Search by keyword in `title` and `episode_title`
    $search_query = array();
    if (!empty($keyword)) {
        $search_query = array(
            'relation' => 'OR',
            array(
                'key' => 'episode_title',
                'value' => $keyword,
                'compare' => 'LIKE',
            ),
        );
    }

    // Taxonomy filtering (categories)
    $tax_query = array();
    if (!empty($categories)) {
        $category_slugs = explode(',', $categories);
        $tax_query[] = array(
            'taxonomy' => 'categories',
            'field' => 'slug',
            'terms' => $category_slugs,
            'operator' => 'IN',
        );
    }

    // Define query args
    $args = array(
        'post_type' => 'episodes',
        'posts_per_page' => $per_page,
        'paged' => $page,
        'meta_query' => $meta_query,
        'meta_key' => 'episode_number',
        'orderby' => 'meta_value_num',
        'order' => 'ASC',
    );

    // Add taxonomy filtering if categories filter is applied
    if (!empty($categories)) {
        $args['tax_query'] = $tax_query;
    }

    // Add keyword search
    if (!empty($keyword)) {
        $args['s'] = $keyword;
        $args['meta_query'][] = $search_query;
    }

    $query = new WP_Query($args);
    $episodes = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $episode_id = get_the_ID();

            $episodes[] = array(
                'featured_image' => get_the_post_thumbnail_url($episode_id, 'full'),
                'title' => get_the_title(),
                'slug' => get_post_field('post_name', $episode_id),
                'episode_title' => get_field('episode_title', $episode_id),
                'episode_number' => get_field('episode_number', $episode_id),
            );
        }
        wp_reset_postdata();
    }

    // Prepare response with pagination meta
    $response = array(
        'story_title' => $story_title,
        'data' => $episodes,
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
 * Retrieve a paginated list of stories, excluding the one with the provided slug.
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
function zaryab_get_stories_excluding_slug(WP_REST_Request $request)
{
    $slug = $request->get_param('slug');
    $page = (int)$request->get_param('page') ?: 1;
    $per_page = (int)$request->get_param('per_page') ?: 10;

    // Find the story ID by slug
    $story = get_page_by_path($slug, OBJECT, 'stories');
    $exclude_id = $story ? $story->ID : null;

    // Query stories excluding the specified slug
    $args = array(
        'post_type' => 'stories',
        'posts_per_page' => $per_page,
        'paged' => $page,
        'post__not_in' => $exclude_id ? array($exclude_id) : array(),
    );

    $query = new WP_Query($args);
    $stories = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $story_id = get_the_ID();

            // Retrieve the author post object from ACF
            $author_field = get_field('author', $story_id);
            $author_name = is_object($author_field) ? get_the_title($author_field->ID) : '';

            // Retrieve taxonomy terms (categories)
            $terms = get_the_terms($story_id, 'categories');
            $categories = zaryab_format_taxonomy($terms);

            $stories[] = array(
                'featured_image' => get_the_post_thumbnail_url($story_id, 'full'),
                'title' => get_the_title(),
                'excerpt' => get_the_excerpt(),
                'slug' => get_post_field('post_name', $story_id),
                'author' => $author_name,
                'date' => get_field('date', $story_id),
                'duration' => get_field('duration', $story_id),
                'categories' => $categories,
            );
        }
        wp_reset_postdata();
    }

    // Prepare response with pagination meta
    $response = array(
        'data' => $stories,
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
 * Retrieve a paginated list of stories filtered by a specific collection.
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
function zaryab_get_stories_by_collection(WP_REST_Request $request)
{
    $collection_slug = $request->get_param('slug');
    $page = (int)$request->get_param('page') ?: 1;
    $per_page = (int)$request->get_param('per_page') ?: 10;

    // Query stories belonging to the specified collection taxonomy
    $args = array(
        'post_type' => 'stories',
        'posts_per_page' => $per_page,
        'paged' => $page,
        'tax_query' => array(
            array(
                'taxonomy' => 'collection',
                'field' => 'slug',
                'terms' => $collection_slug,
            ),
        ),
    );

    $query = new WP_Query($args);
    $stories = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $story_id = get_the_ID();

            // Retrieve the author post object from ACF
            $author_field = get_field('author', $story_id);
            $author_name = is_object($author_field) ? get_the_title($author_field->ID) : '';

            // Retrieve taxonomy terms (categories)
            $terms = get_the_terms($story_id, 'categories');
            $categories = zaryab_format_taxonomy($terms);

            $stories[] = array(
                'featured_image' => get_the_post_thumbnail_url($story_id, 'full'),
                'title' => get_the_title(),
                'excerpt' => get_the_excerpt(),
                'slug' => get_post_field('post_name', $story_id),
                'author' => $author_name,
                'date' => get_field('date', $story_id),
                'duration' => get_field('duration', $story_id),
                'categories' => $categories,
            );
        }
        wp_reset_postdata();
    }

    // Prepare response with pagination meta
    $response = array(
        'data' => $stories,
        'meta' => array(
            'total' => (int)$query->found_posts,
            'pages' => (int)$query->max_num_pages,
            'page' => $page,
            'per_page' => $per_page,
        ),
    );

    return new WP_REST_Response($response, 200);
}