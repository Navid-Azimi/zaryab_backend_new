<?php
// Register the REST API route for the About Us page.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/about-us', array(
        'methods'  => 'GET',
        'callback' => 'zaryab_get_about_us',
    ));
});

/**
 * Callback function for retrieving the About Us page data.
 *
 * This endpoint fetches the About Us page by its slug and returns:
 * - title (page title)
 * - content (page content)
 * - slug
 * - questions (an array from the ACF repeater field "questions", each with "question" and "answer")
 *
 * @param WP_REST_Request $request The current request object.
 * @return WP_REST_Response|WP_Error JSON response with the page data or error if not found.
 */
function zaryab_get_about_us(WP_REST_Request $request) {
    // Retrieve the About Us page by its slug.
    $page = get_page_by_path('about-us');
    if (!$page) {
        return new WP_Error('no_page', 'No About Us page found', array('status' => 404));
    }
    $page_id = $page->ID;

    // Build the basic page data.
    $data = array(
        'title'   => get_the_title($page_id),
        'content' => apply_filters('the_content', $page->post_content),
        'slug'    => $page->post_name,
    );

    // Retrieve the repeater field "questions" using ACF.
    $questions = array();
    if (have_rows('questions', $page_id)) {
        while (have_rows('questions', $page_id)) {
            the_row();
            $questions[] = array(
                'question' => get_sub_field('question'),
                'answer'   => get_sub_field('answer'),
            );
        }
    }
    $data['questions'] = $questions;

    // Return the response.
    return new WP_REST_Response($data, 200);
}
