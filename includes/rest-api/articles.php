<?php
/**
 * Articles Endpoints
 *
 * This file registers REST API endpoints for the "articles" post type:
 * - List of articles: /articles
 * - Similar articles (exclude one by slug): /articles/{slug}/similar
 * - Single article details: /articles/{slug}
 *
 * All endpoints support pagination (for list and similar) via the `page` and `per_page` query parameters.
 */

// -------------------------------
// LIST & SIMILAR ENDPOINTS
// -------------------------------

/**
 * Helper function to assemble article data for list and similar endpoints.
 *
 * Fields returned:
 * - image: Featured image URL.
 * - title: Post title.
 * - excerpt: Post excerpt.
 * - slug: Post slug.
 * - author: Title of the ACF post object field "author".
 * - date_shamsi: ACF field.
 * - time: ACF field.
 * - categories: Terms from taxonomy "categories".
 *
 * @param int $post_id The article post ID.
 * @return array The article data.
 */
function get_article_data($post_id) {
    // Featured image.
    $image_url = get_the_post_thumbnail_url($post_id, 'full');

    // Basic post fields.
    $title   = get_the_title($post_id);
    $excerpt = get_the_excerpt($post_id);
    $slug    = get_post_field('post_name', $post_id);

    // "author" ACF field (post object) – return its title.
    $author_field = get_field('author', $post_id);
    $author_title = '';
    if ($author_field) {
        if (is_object($author_field)) {
            $author_title = $author_field->post_title;
        } elseif (is_array($author_field) && isset($author_field['post_title'])) {
            $author_title = $author_field['post_title'];
        }
    }

    // ACF fields.
    $date_shamsi = get_field('date_shamsi', $post_id);
    $time        = get_field('time', $post_id);

    // Retrieve taxonomy terms from "categories".
    $terms = get_the_terms($post_id, 'categories');
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

    return array(
        'image'       => $image_url,
        'title'       => $title,
        'excerpt'     => $excerpt,
        'slug'        => $slug,
        'author'      => $author_title,
        'date_shamsi' => $date_shamsi,
        'time'        => $time,
        'categories'  => $categories,
    );
}

/**
 * Endpoint: List Articles
 * URL: /wp-json/v1/articles
 *
 * Supports pagination:
 * - page (default: 1)
 * - per_page (default: 10)
 */
add_action('rest_api_init', function () {
    register_rest_route('v1', '/articles', array(
        'methods'  => 'GET',
        'callback' => 'zaryab_get_articles',
    ));
});

function zaryab_get_articles(WP_REST_Request $request) {
    $page     = (int) $request->get_param('page') ?: 1;
    $per_page = (int) $request->get_param('per_page') ?: 10;

    $args = array(
        'post_type'      => 'articles',
        'posts_per_page' => $per_page,
        'paged'          => $page,
    );

    $query = new WP_Query($args);
    $articles = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $articles[] = get_article_data(get_the_ID());
        }
        wp_reset_postdata();
    }

    $response = array(
        'data' => $articles,
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
 * Endpoint: Similar Articles
 * URL: /wp-json/v1/articles/{slug}/similar
 *
 * Excludes the article identified by the provided slug.
 * Supports pagination via query parameters.
 */
add_action('rest_api_init', function () {
    register_rest_route('v1', '/articles/similar/(?P<slug>[a-z0-9-]+)', array(
        'methods'  => 'GET',
        'callback' => 'zaryab_get_similar_articles',
    ));
});

function zaryab_get_similar_articles(WP_REST_Request $request) {
    $slug = $request->get_param('slug');

    // Retrieve the article by slug.
    $article_post = get_page_by_path($slug, OBJECT, 'articles');
    if (!$article_post) {
        return new WP_Error('no_article', 'No article found with the provided slug', array('status' => 404));
    }
    $exclude_id = $article_post->ID;

    $page     = (int) $request->get_param('page') ?: 1;
    $per_page = (int) $request->get_param('per_page') ?: 10;

    $args = array(
        'post_type'      => 'articles',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'post__not_in'   => array($exclude_id),
    );

    $query = new WP_Query($args);
    $articles = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $articles[] = get_article_data(get_the_ID());
        }
        wp_reset_postdata();
    }

    $response = array(
        'data' => $articles,
        'meta' => array(
            'total'    => (int) $query->found_posts,
            'pages'    => (int) $query->max_num_pages,
            'page'     => $page,
            'per_page' => $per_page,
        ),
    );

    return new WP_REST_Response($response, 200);
}

// -------------------------------
// SINGLE ARTICLE ENDPOINT
// -------------------------------

/**
 * Endpoint: Single Article
 * URL: /wp-json/v1/articles/{slug}
 *
 * Returns the following fields:
 * - big_image: ACF field returning an image array for SEO (includes URL, alt, etc.).
 * - title: Post title.
 * - date_shamsi: ACF field.
 * - time: ACF field.
 * - categories: Terms from taxonomy "categories".
 * - author: Detailed data from the ACF post object "author":
 *      - featured_image, name, location, job, total_letters, age, facebook, instagram, telegram, youtube.
 * - content: The post content.
 */
add_action('rest_api_init', function () {
    register_rest_route('v1', '/articles/(?P<slug>[a-z0-9-]+)', array(
        'methods'  => 'GET',
        'callback' => 'zaryab_get_single_article',
    ));
});

function zaryab_get_single_article(WP_REST_Request $request) {
    $slug = $request->get_param('slug');

    $article_post = get_page_by_path($slug, OBJECT, 'articles');
    if (!$article_post) {
        return new WP_Error('no_article', 'No article found with the provided slug', array('status' => 404));
    }
    $article_id = $article_post->ID;

    // Retrieve the big_image ACF field (returns an array for SEO purposes).
    $big_image = get_field('big_image', $article_id)['url'];
    $title = get_the_title($article_id);
    $date_shamsi = get_field('date_shamsi', $article_id);
    $time = get_field('time', $article_id);

    // Retrieve taxonomy terms from "categories".
    $terms = get_the_terms($article_id, 'categories');
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

    // Retrieve detailed author data from the ACF post object field "author".
    $author_field = get_field('author', $article_id);
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

    $content = apply_filters('the_content', $article_post->post_content);

    $data = array(
        'big_image'   => $big_image,
        'title'       => $title,
        'date_shamsi' => $date_shamsi,
        'time'        => $time,
        'categories'  => $categories,
        'author'      => $author_data,
        'content'     => $content,
    );

    return new WP_REST_Response($data, 200);
}
