<?php
// Register the REST API route for subscribing to the newsletter.
add_action('rest_api_init', function () {
    register_rest_route('v1', '/newsletter/', array(
        'methods' => 'POST',
        'callback' => 'zaryab_newsletter_subscribe',
        'args' => array(
            'email' => array(
                'required' => true,
                'validate_callback' => function ($param) {
                    return filter_var($param, FILTER_VALIDATE_EMAIL);
                },
            ),
        ),
    ));
});

/**
 * Handle newsletter subscription.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response JSON response with status message.
 */
function zaryab_newsletter_subscribe(WP_REST_Request $request)
{
    $email = sanitize_email($request->get_param('email'));

    // Check if email is already subscribed
    $existing_subscriber = get_page_by_title($email, OBJECT, 'newsletter');
    if ($existing_subscriber) {
        return new WP_REST_Response(array('message' => 'Email already subscribed'), 409);
    }

    // Create new subscriber post
    $subscriber_id = wp_insert_post(array(
        'post_type' => 'newsletter',
        'post_title' => $email,
        'post_status' => 'publish',
    ));

    if (is_wp_error($subscriber_id)) {
        return new WP_REST_Response(array('message' => 'Failed to subscribe'), 500);
    }

    return new WP_REST_Response(array('message' => 'Subscription successful'), 200);
}
