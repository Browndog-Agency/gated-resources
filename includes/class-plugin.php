<?php
namespace BrownDog\GatedResources;

class Plugin {

	public function boot() {
		$settings        = new Settings();
		$gate            = new Gate();
		$protected_files = new Protected_Files();
		$thumbnail       = new Thumbnail();
		$turnstile       = new Turnstile();
		$hubspot         = new HubSpot();
		$form            = new Form( $turnstile, $hubspot, $gate );

		( new CPT() )->register();
		( new Meta_Box( $thumbnail ) )->register();
		( new PDF_Upload( $protected_files, $thumbnail ) )->register();
		( new Shortcode( $thumbnail ) )->register();
		( new Assets( $turnstile ) )->register();

		$settings->register();
		$form->register();
		$protected_files->register( $gate );
		$gate->register();

		( new Templates() )->register();
	}
}
