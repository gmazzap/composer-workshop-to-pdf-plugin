<?php
/*
 * This file is part of the "Download as PDF" plugin package.
 *
 * (c) Giuseppe Mazzapica
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gmazzap\ComposerWorkshop\DownloadAsPdf;

use Dompdf;

if (defined(__NAMESPACE__ . '\\QUERY_VAR')) {
    return;
}

const QUERY_VAR = 'pdf';

/**
 * Check if given post is a valid WP post reference and its post types are inside
 * an (filterable) array of enabled values.
 *
 * @param int|\WP_Post|null $post
 * @return bool
 */
function enabledForPost($post)
{

    $post = get_post($post);
    if (!$post || !$post instanceof \WP_Post) {
        return false;
    }

    static $post_types;
    if (!is_array($post_types)) {
        $default = get_post_types(['publicly_queryable' => true, '_builtin' => false]);
        $default[] = 'post';
        $default[] = 'page';
        /**
         * Filters the post types that should have the "Download as PDF" link.
         *
         * @since 1.0.0
         *
         * @param string[] $post_types
         */
        $post_types = (array)apply_filters('gmazzap_as_pdf_post_types', $default);
    }

    if (!in_array($post->post_type, $post_types, true)) {
        return false;
    }
    /**
     * Filters if a specific post should have the "Download as PDF" link.
     *
     * @since 1.0.0
     *
     * @param bool $enabled
     * @param \WP_Post $post
     */
    $enabled = apply_filters('gmazzap_as_pdf_post_enabled', true, $post);

    return (bool)$enabled;
}

/**
 * Return the download PDF URL for give post, or empty string if post is not valid or not enabled.
 *
 * @param int|\WP_Post|null $post
 * @return string
 */
function pdfLinkForPost($post)
{

    $post = get_post($post);
    if (!enabledForPost($post)) {
        return '';
    }

    return add_query_arg(
        QUERY_VAR,
        wp_create_nonce(QUERY_VAR . $post->ID),
        get_permalink($post)
    );
}

/**
 * Return the complete HTML markup for a post.
 *
 * A various set of actions allow to customize the output.
 *
 * @param \WP_Post $post
 * @param Dompdf\Options|null $options Dompdf option object passed to hooks for more context.
 * @return string
 */
function generateHtml($post, Dompdf\Options $options = null)
{
    setup_postdata($post);

    /**
     * Filters the template to use to generate the HTML to be converted in PDF.
     *
     * @since 1.0.0
     *
     * @param string $template        The template to use to generate the HTML to be converted in PDF.
     * @param \WP_Post $post          The post the PDF is generated for.
     * @param Dompdf\Options $options The Dompdf options object that will be used.
     */
    $template = apply_filters(
        'gmazzap_as_pdf_html_template',
        __DIR__ . '/pdf_html_template.php',
        $post,
        $options
    );

    if (!$template || !is_string($template) || ! file_exists($template) || !is_readable($template)) {
        return '';
    }

    /**
     * Fires before the HTML generation for PDF begins.
     *
     * @since 1.0.0
     *
     * @param \WP_Post $post          The post the PDF is generated for.
     * @param string $template        The template to generate the HTML to be converted in PDF.
     * @param Dompdf\Options $options The Dompdf options object that will be used.
     */
    do_action('gmazzap_as_pdf_generate', $post, $template, $options);

    ob_start();
    include $template;

    return ob_get_clean();
}

/**
 * Load language files if not loaded already.
 */
function loadLocale()
{
    static $loaded;
    if (!$loaded) {
        $loaded = load_plugin_textdomain('gmazzap_as_pdf', false, basename(__DIR__) . '/languages');
    }
}

/**
 * Stream a PDF from string.
 *
 * @param string $pdf_string
 * @param string $filename
 */
function streamFromString($pdf_string, $filename = 'document.pdf')
{
    $filename = str_replace(array("\n", "'"), '', basename($filename, '.pdf')) . '.pdf';
    $encoding = mb_detect_encoding($filename);
    $fallbackFilename = mb_convert_encoding($filename, 'ISO-8859-1', $encoding);
    $fallbackFilename = str_replace('"', '', $fallbackFilename);
    $encodedFilename = rawurlencode($filename);

    $contentDisposition = "attachment; filename=\"$fallbackFilename\"";
    if ($fallbackFilename !== $filename) {
        $contentDisposition .= "; filename*=UTF-8''$encodedFilename";
    }

    $headers = [
        'Cache-Control' => 'private',
        'Content-Type' => 'application/pdf',
        'Content-Length' => mb_strlen($pdf_string, '8bit'),
        'Content-Disposition' => $contentDisposition,
    ];

    /**
     * Filters the HTTP headers to be used when streaming PDF from custom string.
     *
     * @since 1.0.0
     *
     * @param array $headers
     */
    $headers = apply_filters('gmazzap_as_pdf_stream_from_string_headers', $headers);

    if (headers_sent()) {
        flush();
        return;
    }

    foreach($headers as $name => $value) {
        header("{$name}: {$value}");
    }

    /**
     * Fires right before the a custom PDF content string is send to browser.
     *
     * @since 1.0.0
     *
     * @param string $pdf_string The PDF content string.
     */
    do_action('gmazzap_as_pdf_before_pdf_from_string_stream', $pdf_string);

    print $pdf_string;
    flush();
}

/**
 * Stream PDF whe the page is loaded for a post  with the plugin query var.
 *
 * @wp-hook template_redirect
 */
function streamPdf()
{

    // Only when plugin is active and for singular queries.
    if (!is_singular()) {
        return;
    }
    // In no query var, nothing to do.
    $query_var = filter_input(INPUT_GET, QUERY_VAR, FILTER_SANITIZE_STRING);
    if (!$query_var) {
        return;
    }

    // If queried object is not a post, or is not enabled, do nothing.
    $queried_object = get_queried_object();
    if (!$queried_object instanceof \WP_Post
        || !enabledForPost($queried_object)
    ) {
        return;
    }

    // Just a check we really want this post
    if (!wp_verify_nonce($query_var, QUERY_VAR . $queried_object->ID)) {
        return;
    }

    // Everything fine, let's stream the PDF instead of the post.

    global $post;
    /** @var \WP_Post $post */
    $post = $queried_object;

    add_action('gmazzap_as_pdf_after_pdf_stream', function () {
        die;
    }, PHP_INT_MAX);

    /**
     * Filters the full PDF content before is generated.
     *
     * Useful for cache purposes.
     *
     * @since 1.0.0
     *
     * @param string|null $pdf_content The PDF content.
     * @param \WP_Post    $post        The post the PDF should be generated for.
     */
    $pre = apply_filters('gmazzap_as_pdf_pre_pdf_generation', null, $post);
    if (is_string($pre) && $pre) {
        streamFromString($pre, "{$post->post_name}-{$post->ID}.pdf");
        /** This action is documented in gmazzap_as_pdf/functions.php */
        do_action('gmazzap_as_pdf_after_pdf_stream', $pre, $post);
    }

    // Autoload file for Dompdf.
    require_once dirname(__DIR__) . '/dompdf/vendor/autoload.php';

    $domPdf = new Dompdf\Dompdf();
    $options = new Dompdf\Options();
    $options->setIsRemoteEnabled(true); // necessary to show images
    $options->setDefaultPaperSize('A4');
    $options = apply_filters('gmazzap_as_pdf_dompdf_options', $options, $post);

    $html = generateHtml($post, $options);

    /**
     * Filters the full HTML that will be used to generate the PDF.
     *
     * @since 1.0.0
     *
     * @param string $html   The HTML string the PDF was generated from.
     * @param \WP_Post $post The post the PDF was generated from.
     */
    $html = (string)apply_filters('gmazzap_as_pdf_html', $html, $post);

    $domPdf->setOptions($options);
    $domPdf->loadHtml($html);
    $domPdf->render();

    /**
     * Fires right before the generated PDF content is send to browser.
     *
     * @since 1.0.0
     *
     * @param string $html           The HTML string the PDF was generated from.
     * @param \WP_Post $post         The post the PDF was generated from.
     * @param \Dompdf\Dompdf $domPdf The Dompdf instance used to generate the PDF.
     */
    do_action('gmazzap_as_pdf_before_pdf_stream', $html, $post, $domPdf);

    $domPdf->stream("{$post->post_name}-{$post->ID}.pdf");

    /**
     * Fires right after the generated PDF content is send to browser.
     *
     * Used internally to terminate request.
     *
     * @since 1.0.0
     *
     * @param string $html   The HTML string the PDF was generated from.
     * @param \WP_Post $post The post the PDF was generated from.
     */
    do_action('gmazzap_as_pdf_after_pdf_stream', $html, $post);
}

/**
 * Append a 'Download as PDF.' link a the end of the each post content.
 *
 * @wp-hook the_post
 *
 * @param \WP_Post $target_post
 */
function appendDownloadUrl($target_post)
{

    // We only target post of main query, while looped.
    if (!in_the_loop()) {
        return;
    }

    // If the post is not enabled, will return empty string.
    $url = pdfLinkForPost($target_post);
    if (!$url) {
        return;
    }

    // Load translations and get translated link anchor and format once per request.
    static $anchor, $link_format;
    if (!$anchor) {
        loadLocale();
        $anchor = __('Download as PDF', 'gmazzap_as_pdf');
        /**
         * Filters the `sprintf` format used to generate the "Download as PDF" link.
         *
         * @since 1.0.0
         */
        $link_format = apply_filters(
            'gmazzap_as_pdf_download_link_format',
            '<br><a href="%1$s">%2$s</a>'
        );
    }

    // Unfortunately, 'the_content' hook does not pass the post object,
    // so we use this kind of hack to ensure we filter the content of the right post.
    // Because closure is saved in variable, we can easily remove the filter later.
    $filter = function ($content) use ($target_post, $url, $anchor, $link_format) {

        global $post;
        if ($post === $target_post) {
            $content .= sprintf($link_format, esc_url($url), esc_html($anchor));
        }

        return $content;
    };

    add_filter('the_content', $filter, 999);

    // After the content is filtered, we can remove the filter callback.
    add_filter('the_content', function ($content) use ($filter) {

        remove_filter('the_content', $filter, 999);

        return $content;
    }, 1000);
}

/**
 * Print an admin notice telling that plugin have been disabled.
 *
 * @param string $reason
 * @param bool $onlyForAdmins
 */
function disablePluginNotice($reason, $onlyForAdmins = false)
{
    $capability = '';
    if ($onlyForAdmins) {
        $capability = 'activate_plugins';
        if (is_multisite()) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            $plugin = dirname(__DIR__) . '/gmazzap_as_pdf.php';
            is_plugin_active_for_network($plugin) and $capability = 'manage_network_plugins';
        }
    }
    if ($capability && !current_user_can($capability)) {
        return;
    }
    ?>
    <div class="notice notice-error">
        <p class="notice-title">
            <?php esc_html_e('"Download as PDF" as been disabled.', 'gmazzap_as_pdf') ?>
        </p>
        <p>
            <?php echo esc_html($reason) ?>
        </p>
    </div>
    <?php
}
