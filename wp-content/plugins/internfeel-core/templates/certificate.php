<?php
/**
 * Certificate template for Dompdf, from docs/approved-designs/certificate.html. A4 landscape,
 * 1123 × 794 px at 96 dpi. Dompdf has no flexbox, so rows are tables and the strip is positioned.
 *
 * Variables from Pdf::render(): $data (name, title, details, published, number, verify_label),
 * $assets (absolute path of the plugin's assets folder) and $qr (PNG data URI, or empty).
 *
 * @package InternFeel
 */

defined( 'ABSPATH' ) || exit;

/**
 * Set by Pdf::render().
 *
 * @var array<string, string> $data
 * @var string                $assets
 * @var string                $qr
 */

$if_font = static fn( string $file ): string => esc_attr( $assets . '/fonts/' . $file );
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
	@font-face { font-family: "Schibsted"; font-weight: 800; src: url("<?php echo $if_font( 'schibsted-grotesk-800.ttf' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>") format("truetype"); }
	@font-face { font-family: "Atkinson"; font-weight: 400; src: url("<?php echo $if_font( 'atkinson-hyperlegible-next-400.ttf' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>") format("truetype"); }
	@font-face { font-family: "Atkinson"; font-weight: 700; src: url("<?php echo $if_font( 'atkinson-hyperlegible-next-700.ttf' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>") format("truetype"); }
	@font-face { font-family: "Plex Mono"; font-weight: 500; src: url("<?php echo $if_font( 'ibm-plex-mono-500.ttf' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>") format("truetype"); }
	@page { size: 1123px 794px; margin: 0; }
	html, body { margin: 0; padding: 0; }
	body { width: 1123px; height: 794px; position: relative; overflow: hidden; background: #ffffff; color: #1d1c1c; font-family: "Atkinson", sans-serif; font-size: 16px; }
	.strip { position: absolute; left: 0; top: 0; width: 200px; height: 794px; overflow: hidden; background: #eef7f7; }
	.strip div { position: absolute; }
	.ribbon { left: -40px; width: 300px; height: 28px; border-radius: 14px; background: #ffffff; transform: rotate(-7deg); }
	.main { position: absolute; left: 272px; top: 64px; width: 779px; height: 674px; }
	.logo { height: 44px; width: auto; }
	.label { display: block; margin-top: 44px; font-size: 14px; font-weight: 700; letter-spacing: 1.7px; text-transform: uppercase; color: #1f5c5c; }
	.intro { display: block; margin-top: 14px; font-size: 18px; color: #5f5c5d; }
	.name { display: block; margin-top: 14px; font-family: "Schibsted", sans-serif; font-weight: 800; font-size: 56px; line-height: 60px; letter-spacing: -1px; }
	.line { margin: 14px 0 0; width: 640px; font-size: 18px; line-height: 20px; }
	.work { margin-top: 28px; width: 656px; padding: 20px 24px; border-radius: 8px; background: #eef7f7; }
	.work-title { display: block; font-family: "Atkinson", sans-serif; font-weight: 700; font-size: 22px; line-height: 22px; }
	.work-details { display: block; margin-top: 6px; font-size: 15px; color: #5f5c5d; }
	.foot { position: absolute; left: 0; bottom: 0; width: 779px; border-top: 1px solid #dedbdc; padding-top: 24px; }
	.foot table { width: 100%; border-collapse: collapse; }
	.foot td { vertical-align: bottom; padding: 0; }
	.facts td { padding: 3px 24px 3px 0; font-size: 14px; vertical-align: top; }
	.facts .term { color: #5f5c5d; }
	.facts .value { font-weight: 700; }
	.mono { font-family: "Plex Mono", monospace; font-weight: 500; }
	.qr { width: 96px; height: 96px; }
	.mark { width: auto; height: 72px; }
	.issued { display: block; margin-top: 6px; font-weight: 700; font-size: 14px; text-align: center; }
</style>
</head>
<body>
	<div class="strip">
		<div style="left: 0; top: 0; width: 120px; height: 320px; background: #339999;"></div>
		<div style="left: 120px; top: 0; width: 80px; height: 200px; background: #297777;"></div>
		<div style="left: 0; top: 600px; width: 96px; height: 194px; background: #3a3839;"></div>
		<div class="ribbon" style="top: 230px;"></div>
		<div class="ribbon" style="top: 290px;"></div>
	</div>

	<div class="main">
		<img class="logo" src="<?php echo esc_attr( $assets . '/img/internfeel-logo.png' ); ?>" alt="InternFeel">

		<span class="label">Certificate of contribution</span>
		<span class="intro">This certifies that</span>
		<span class="name"><?php echo esc_html( $data['name'] ); ?></span>
		<p class="line">shared a first-hand account of their internship on InternFeel, reviewed and published so other students can learn from it.</p>

		<div class="work">
			<span class="work-title"><?php echo esc_html( $data['title'] ); ?></span>
			<?php if ( '' !== $data['details'] ) : ?>
				<span class="work-details"><?php echo esc_html( $data['details'] ); ?></span>
			<?php endif; ?>
		</div>

		<div class="foot">
			<table>
				<tr>
					<td>
						<table class="facts">
							<tr><td class="term">Published</td><td class="value"><?php echo esc_html( $data['published'] ); ?></td></tr>
							<tr><td class="term">Certificate no.</td><td class="mono"><?php echo esc_html( $data['number'] ); ?></td></tr>
							<tr><td class="term">Verify at</td><td><?php echo esc_html( $data['verify_label'] ); ?></td></tr>
						</table>
					</td>
					<td style="width: 96px; padding-right: 24px;">
						<?php if ( '' !== $qr ) : ?>
							<img class="qr" src="<?php echo esc_attr( $qr ); ?>" alt="">
						<?php endif; ?>
					</td>
					<td style="width: 140px; text-align: center;">
						<img class="mark" src="<?php echo esc_attr( $assets . '/img/if-mark.png' ); ?>" alt="">
						<span class="issued">Issued by InternFeel</span>
					</td>
				</tr>
			</table>
		</div>
	</div>
</body>
</html>
