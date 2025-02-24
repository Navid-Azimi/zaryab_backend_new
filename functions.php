    <?php
    require_once get_template_directory() . '/includes/custom-post-types.php';
    require_once get_template_directory() . '/includes/acf-fields.php';

    // Load all REST API files dynamically
    foreach (glob(get_template_directory() . "/includes/rest-api/*.php") as $file) {
        require_once $file;
    }
