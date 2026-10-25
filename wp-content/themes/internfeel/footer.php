<?php
/**
 * Site footer. The four link columns from plan.md 5.4 are built on day 10.
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;
?>
</main>

<footer class="if-footer">
	<div class="if-container">
		<p><?php esc_html_e( 'InternFeel: peep before you leap', 'internfeel' ); ?></p>
		<p>
			<a href="mailto:contact@internfeel.com">contact@internfeel.com</a>
			&middot;
			<?php
			/* translators: %s: current year. */
			echo esc_html( sprintf( __( '© %s InternFeel', 'internfeel' ), wp_date( 'Y' ) ) );
			?>
		</p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
