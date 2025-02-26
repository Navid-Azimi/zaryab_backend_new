<?php
// Register the REST API route for listing all categories.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/categories', array(
        'methods'  => 'GET',
        'callback' => 'zaryab_get_all_categories',
    ));
});

/**
 * Callback function for retrieving all categories.
 *
 * This endpoint returns:
 * - id (Term ID)
 * - name (Category name)
 * - slug (Category slug)
 * - count (Number of posts in the category)
 * - description (Category description)
 *
 * @return WP_REST_Response JSON response containing the categories.
 */
function zaryab_get_all_categories() {
    $categories = get_terms(array(
        'taxonomy'   => 'categories', // Ensure this is your actual taxonomy slug.
        'hide_empty' => false,        // Include categories even if they have no posts.
    ));

    if (empty($categories) || is_wp_error($categories)) {
        return new WP_Error('no_categories', 'No categories found', array('status' => 404));
    }

    $category_list = array();
    foreach ($categories as $category) {
        $category_list[] = array(
            'id'          => $category->term_id,
            'name'        => $category->name,
            'slug'        => $category->slug,
            'count'       => $category->count, // Number of posts in this category.
        );
    }

    return new WP_REST_Response($category_list, 200);
}
