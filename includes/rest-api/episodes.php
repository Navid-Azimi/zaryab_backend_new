<?php
// Register the REST API route for retrieving a single episode by slug.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/episodes/(?P<slug>[a-z0-9-]+)', array(
        'methods' => 'GET',
        'callback' => 'zaryab_get_single_episode',
    ));
});

/**
 * Callback function for retrieving a single episode by slug.
 *
 * Fields returned:
 * - title
 * - author (post object)
 * - categories (taxonomy, formatted)
 * - date (acf field)
 * - time (acf field)
 * - story_slug (linked story post object)
 * - content
 * - episode_title (acf field)
 * - previous_episode (slug of previous episode in same story)
 * - next_episode (slug of next episode in same story)
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response JSON response with episode details.
 */
function zaryab_get_single_episode(WP_REST_Request $request)
{
    $slug = $request->get_param('slug');

    // Retrieve the episode by slug
    $episode = get_page_by_path($slug, OBJECT, 'episodes');
    if (!$episode) {
        return new WP_Error('no_episode', 'No episode found with the provided slug', array('status' => 404));
    }
    $episode_id = $episode->ID;

    // Retrieve linked story (ACF post object)
    $story = get_field('story', $episode_id);
    $story_slug = ($story && is_object($story)) ? get_post_field('post_name', $story->ID) : '';

    // Retrieve author details (post object)
    $author_field = get_field('author', $story->ID);
    $author_data = null;
    if ($author_field) {
        $author_id = $author_field->ID;
        $author_data = array(
            'featured_image' => get_the_post_thumbnail_url($author_id, 'full'),
            'name' => get_the_title($author_id),
            'location' => get_field('location', $author_id),
            'job' => get_field('job', $author_id),
            'total_letters' => get_field('total_letters', $author_id),
            'age' => get_field('age', $author_id),
            'facebook' => get_field('facebook', $author_id),
            'instagram' => get_field('instagram', $author_id),
            'telegram' => get_field('telegram', $author_id),
            'youtube' => get_field('youtube', $author_id),
        );
    }

    // Retrieve taxonomy terms (categories)
    $terms = get_the_terms($episode_id, 'categories');
    $categories = zaryab_format_taxonomy($terms);

    // Retrieve episode number
    $episode_number = (int)get_field('episode_number', $episode_id);

    // Retrieve previous and next episode slugs
    $previous_episode_slug = zaryab_get_adjacent_episode_slug($story->ID, $episode_number, 'previous');
    $next_episode_slug = zaryab_get_adjacent_episode_slug($story->ID, $episode_number, 'next');

    // Retrieve taxonomy terms from `collection`
    $terms = get_the_terms($story->ID, 'collection');
    $collection = '';
    if ($terms && !is_wp_error($terms)) {
        $collection = zaryab_format_taxonomy($terms); // Return the first term's slug
    }

    // Build response
    $data = array(
        'title' => get_the_title($episode_id),
        'author' => $author_data,
        'collection' => $collection,
        'categories' => $categories,
        'date' => get_field('date', $episode_id),
        'time' => get_field('time', $episode_id),
        'story_slug' => $story_slug,
        'content' => apply_filters('the_content', get_the_content()),
        'episode_title' => get_field('episode_title', $episode_id),
        'previous_episode' => $previous_episode_slug,
        'next_episode' => $next_episode_slug,
    );

    return new WP_REST_Response($data, 200);
}

/**
 * Retrieves the slug of the previous or next episode in the same story.
 *
 * @param int $story_id The ID of the linked story.
 * @param int $current_episode_number The episode number of the current episode.
 * @param string $direction 'previous' or 'next' to find the adjacent episode.
 * @return string|null Slug of the adjacent episode, or null if none found.
 */
function zaryab_get_adjacent_episode_slug($story_id, $current_episode_number, $direction)
{
    $compare = ($direction === 'previous') ? '<' : '>';
    $order = ($direction === 'previous') ? 'DESC' : 'ASC';

    $args = array(
        'post_type' => 'episodes',
        'posts_per_page' => 1,
        'meta_query' => array(
            array(
                'key' => 'story',
                'value' => $story_id,
                'compare' => '=',
            ),
            array(
                'key' => 'episode_number',
                'value' => $current_episode_number,
                'compare' => $compare,
                'type' => 'NUMERIC',
            ),
        ),
        'meta_key' => 'episode_number',
        'orderby' => 'meta_value_num',
        'order' => $order,
    );

    $query = new WP_Query($args);
    $slug = null;

    if ($query->have_posts()) {
        $query->the_post();
        $slug = get_post_field('post_name', get_the_ID());
    }
    wp_reset_postdata();

    return $slug;
}
