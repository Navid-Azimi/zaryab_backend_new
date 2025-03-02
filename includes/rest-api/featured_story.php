<?php
// Register the REST API route for retrieving the latest featured story.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/featured-story', array(
        'methods'  => 'GET',
        'callback' => 'zaryab_get_latest_featured_story',
    ));
});

/**
 * Retrieve the latest featured story.
 *
 * Fields returned:
 * - title, excerpt, featured image, slug, author name, date, duration, categories.
 *
 * @return WP_REST_Response JSON response with the featured story data.
 */
function zaryab_get_latest_featured_story() {
    // Query for the latest featured story
    $args = array(
        'post_type'      => 'featured_story',
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    $query = new WP_Query($args);

    if (!$query->have_posts()) {
        return new WP_Error('no_featured_story', 'No featured story found', array('status' => 404));
    }

    // Retrieve the latest featured story post
    $query->the_post();
    $featured_story_id = get_the_ID();

    // Retrieve the linked story post object from ACF field
    $story = get_field('story', $featured_story_id);
    if (!$story || !is_object($story)) {
        return new WP_Error('no_story', 'No story linked to the featured story', array('status' => 404));
    }

    $story_id = $story->ID;

    // Retrieve the author post object
    $author_field = get_field('author', $story_id);
    $author_name  = is_object($author_field) ? get_the_title($author_field->ID) : '';

    // Retrieve taxonomy terms (categories)
    $terms = get_the_terms($story_id, 'categories');
    $categories = zaryab_format_taxonomy($terms);

    // Build response
    $data = array(
        'title'          => get_the_title($story_id),
        'excerpt'        => get_the_excerpt($story_id),
        'featured_image' => get_the_post_thumbnail_url($story_id, 'full'),
        'slug'           => get_post_field('post_name', $story_id),
        'author'         => $author_name,
        'date'           => get_field('date', $story_id),
        'duration'       => get_field('duration', $story_id),
        'categories'     => $categories,
    );

    wp_reset_postdata();
    return new WP_REST_Response($data, 200);
}

