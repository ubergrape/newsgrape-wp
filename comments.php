<?php

global $id;

$ngcp_id = get_post_meta($id, 'ngcp_id', true);
$ngcp_provider = "http://staging.newsgrape.com";

?>

<div id="ng_excomments"></div>

<script type="text/javascript">
<?php if ("" != $ngcp_id): ?>
	ng_article_id = <?php echo $ngcp_id; ?>;
<?php else: ?>
	ng_site_id = "<?php echo get_option('ngcp_blog_id'); ?>";
	ng_post_id = "<?php echo $id; ?>";
<?php endif; ?>
	ng_provider = "<?php echo $ngcp_provider; ?>";
	(function(d, t) {
		var ng  = d.createElement(t),
		s  = d.getElementsByTagName(t)[0];
		ng.async = ng.src = '<?php echo $ngcp_provider; ?>/static/js/excomments_consumer.js';
		s.parentNode.insertBefore(ng, s);
	}(document, 'script'));
</script>
