<?php

require_once dirname( __FILE__ ) . '/TinyTestCase.php';

class Tiny_Image_Test extends Tiny_TestCase {
	public function set_up() {
		parent::set_up();

		$this->wp->createImagesFromJSON( $this->json( 'virtual_images' ) );
		$this->wp->setTinyMetadata( 1, $this->json( 'tiny_compress_images' ) );
		$this->subject = new Tiny_Image( 1, $this->json( '_wp_attachment_metadata' ) );
	}

	public function test_update_wp_metadata_should_not_update_with_no_resized_original() {
		$tiny_image = new Tiny_Image( 150, $this->json( '_wp_attachment_metadata' ) );
		$tiny_image_metadata = $tiny_image->get_wp_metadata();
		$this->assertEquals( 1256, $tiny_image_metadata['width'] );
		$this->assertEquals( 1256, $tiny_image_metadata['height'] );
	}

	public function test_update_wp_metadata_should_update_with_resized_original() {
		$tiny_image = new Tiny_Image( 150, $this->json( '_wp_attachment_metadata' ) );
		$response = array( 'output' => array( 'width' => 200, 'height' => 100 ) );
		$tiny_image->get_image_size()->add_request();
		$tiny_image->get_image_size()->add_response( $response );
		$tiny_image->update_wp_metadata( Tiny_Image::ORIGINAL, $response );
		$tiny_image_metadata = $tiny_image->get_wp_metadata();
		$this->assertEquals( 200, $tiny_image_metadata['width'] );
		$this->assertEquals( 100, $tiny_image_metadata['height'] );
	}

	public function test_get_images_should_return_all_images() {
		$this->assertEquals( array(
			Tiny_Image::ORIGINAL,
			'medium',
			'thumbnail',
			'twentyfourteen-full-width',
			'failed',
			'large',
			'small',
		), array_keys( $this->subject->get_image_sizes() ) );
	}

	public function test_filter_images_should_filter_correctly() {
		$this->assertEquals( array(
			Tiny_Image::ORIGINAL,
			'medium',
			'thumbnail',
		), array_keys( $this->subject->filter_image_sizes( 'compressed' ) ) );
	}

	public function test_filter_images_should_filter_correctly_when_sizes_are_given() {
		$this->assertEquals( array(
			Tiny_Image::ORIGINAL
			), array_keys( $this->subject->filter_image_sizes( 'compressed', array( Tiny_Image::ORIGINAL, 'invalid') ) )
		);
	}

	public function test_get_count_should_add_count_correctly() {
		$this->assertEquals(array(
			'compressed' => 3,
			'resized' => 1,
			), $this->subject->get_count( array( 'compressed', 'resized') )
		);
	}

	public function test_get_count_should_add_count_correctly_when_sizes_are_given() {
		$this->assertEquals(array(
			'compressed' => 1,
			'resized' => 1,
			), $this->subject->get_count( array( 'compressed', 'resized'), array(Tiny_Image::ORIGINAL, 'invalid') )
		);
	}

	public function test_get_latest_error_should_return_message() {
		$this->subject->get_image_size()->add_request( 'large' );
		$this->subject->get_image_size()->add_exception( new Tiny_Exception( 'Could not download output', 'OutputError' ), 'large' );
		$this->assertEquals( 'Could not download output', $this->subject->get_latest_error() );
	}

	public function test_get_statistics() {
		$this->assertEquals( array(
			'initial_total_size' => 360542,
			'optimized_total_size' => 328670,
			'image_sizes_optimized' => 3,
			'available_unoptimised_sizes' => 1,
		), $this->subject->get_statistics() );
	}

	public function test_get_image_sizes_available_for_compression_when_file_modified() {
		$this->wp->createImage( 37857, '2015/09', 'tinypng_gravatar-150x150.png' );
		$statistics = $this->subject->get_statistics();
		$this->assertEquals( 2, $statistics['available_unoptimised_sizes'] );
	}

	public function test_get_savings() {
		$this->assertEquals( 8.8, $this->subject->get_savings( $this->subject->get_statistics() ) );
	}

	public function test_get_optimization_statistics() {
		$wpdb_wp_metadata = serialize( $this->json( '_wp_attachment_metadata' ) );
		$wpdb_tiny_metadata = serialize( $this->json( 'tiny_compress_images' ) );
		$wpdb_results = array(
			array( 'ID' => 1, 'post_title' => 'I am the one and only', 'meta_value' => $wpdb_wp_metadata, 'tiny_meta_value' => $wpdb_wp_metadata ),
			array( 'ID' => 3628, 'post_title' => 'Ferrari.jpeg', 'meta_value' => '', 'tiny_meta_value' => '' ),
			array( 'ID' => 4350, 'post_title' => 'IMG 3092', 'meta_value' => '', 'tiny_meta_value' => '' ),
		);
		$this->assertEquals(
			array(
				'uploaded-images' => 3,
				'optimized-image-sizes' => 0,
				'available-unoptimised-sizes' => 4,
				'optimized-library-size' => 328670,
				'unoptimized-library-size' => 328670,
				'available-for-optimization' => array( array( 'ID' => 1, 'post_title' => 'I am the one and only' ) ),
			),
			Tiny_Image::get_optimization_statistics( $wpdb_results )
		);
	}
}
