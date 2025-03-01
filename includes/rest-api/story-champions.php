<?php
// Register the REST API route for the latest story champion.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/story-champion/latest', array(
        'methods'  => 'GET',
        'callback' => 'zaryab_get_latest_story_champion',
    ));
});

/**
 * Callback function for retrieving the latest Story Champion.
 *
 * This endpoint returns:
 * - featured_image (URL)
 * - author:
 *      - name (Post title)
 *      - slug (Post slug)
 * - story:
 *      - excerpt (Post excerpt)
 *      - title (Post title)
 *      - slug (Post slug)
 *
 * @return WP_REST_Response JSON response with the latest Story Champion.
 */
function zaryab_get_latest_story_champion() {
    // Query for the latest story champion.
    $args = array(
        'post_type'      => 'story_champion',
        'posts_per_page' => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    $query = new WP_Query($args);

    if (!$query->have_posts()) {
        return new WP_Error('no_story_champion', 'No story champion found', array('status' => 404));
    }

    // Retrieve the latest story champion post.
    $query->the_post();
    $champion_id = get_the_ID();

    // Get the featured image.
    $featured_image = get_the_post_thumbnail_url($champion_id, 'full');

    // Retrieve the author post object.
    $author = get_field('author', $champion_id);
    $author_data = null;
    if ($author) {
        $author_data = array(
            'name' => get_the_title($author->ID),
            'slug' => get_post_field('post_name', $author->ID),
        );
    }

    // Retrieve the story post object.
    $story = get_field('story', $champion_id);
    $story_data = null;
    if ($story) {
        $story_data = array(
            'title'   => get_the_title($story->ID),
            'excerpt' => get_the_excerpt($story->ID),
            'slug'    => get_post_field('post_name', $story->ID),
        );
    }

    // Build the response.
    $data = array(
        'featured_image' => $featured_image,
        'author'         => $author_data,
        'story'          => $story_data,
    );

    wp_reset_postdata();
    return new WP_REST_Response($data, 200);
}
