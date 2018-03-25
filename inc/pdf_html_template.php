<?php
if(!isset($post, $options) || !$post instanceof WP_Post || !$options instanceof Dompdf\Options) {
    return;
}

$title = get_the_title($post);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo esc_html($title) ?></title>
    <?php
    /**
     * Fires inside the <head> tag of the HTML that will be converted to PDF.
     *
     * The right place to add styles or script to the PDF content.
     *
     * @since 1.0.0
     *
     * @param \WP_Post $post          The post the PDF is generated for.
     * @param Dompdf\Options $options The Dompdf options object that will be used.
     */
    do_action('gmazzap_as_pdf_in_pdf_head', $post, $options);
    ?>
</head>
<body <?php body_class('pdf') ?>>
<?php
/**
 * Fires right after the <body> tag of the HTML that will be converted to PDF.
 *
 * @since 1.0.0
 *
 * @param \WP_Post $post          The post the PDF is generated for.
 * @param Dompdf\Options $options The Dompdf options object that will be used.
 */
do_action('gmazzap_as_pdf_in_pdf_body_open', $post, $options);
?>
<header class="main-title-wrapper">
    <h1 id="main-title-<?php echo $post->ID ?>" class="main-title">
        <?php echo esc_html($title) ?>
    </h1>
</header>
<?php
/**
 * Fires right after the <header> tag of the HTML that will be converted to PDF.
 *
 * @since 1.0.0
 *
 * @param \WP_Post $post          The post the PDF is generated for.
 * @param Dompdf\Options $options The Dompdf options object that will be used.
 */
do_action('gmazzap_as_pdf_in_pdf_before_content', $post, $options);

?>
<section id="main-content-<?php echo $post->ID ?>" <?php post_class('pdf') ?>>
    <?php
    /**
     * Fires right before the post content inside HTML that will be converted to PDF.
     *
     * @since 1.0.0
     *
     * @param \WP_Post $post          The post the PDF is generated for.
     * @param Dompdf\Options $options The Dompdf options object that will be used.
     */
    do_action('gmazzap_as_pdf_in_pdf_before_content', $post, $options);

    the_content();

    /**
     * Fires right after the post content inside HTML that will be converted to PDF.
     *
     * @since 1.0.0
     *
     * @param \WP_Post $post          The post the PDF is generated for.
     * @param Dompdf\Options $options The Dompdf options object that will be used.
     */
    do_action('gmazzap_as_pdf_in_pdf_after_content', $post, $options);
    ?>
</section>
<?php
/**
 * Fires right before the closing <body> tag of the HTML that will be converted to PDF.
 *
 * @since 1.0.0
 *
 * @param \WP_Post $post          The post the PDF is generated for.
 * @param Dompdf\Options $options The Dompdf options object that will be used.
 */
do_action('gmazzap_as_pdf_in_pdf_body_close', $post, $options);

?>
</body>
</html>
