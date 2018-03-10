<?php

/**
 * Wrappers for creation, deletion and modification of NGG albums, galleries & images
 * 
 * (c) Kim Aldis, 2018
 * 
 * ToDo: make sure all returns are C_Image, C_Gallery, etc.
 */
class ka_ngg_Album { //class:

	function __construct() {
	}
	// create a new ngg album. Doesn't save.
	// returns C_Album object or false
	static function New( $name ) { // fn:

		$mapper = C_Album_Mapper::get_instance();
		$album = $mapper->create(
				array(
					'name' => $name, 
					'previewpic' => 0, 
					'albumdesc' => "yes", 
					'sortorder' => [], 
					'pageid' => 0
				)
			);
		return $album;
	}
	// returns found C_Album object or false
	static function Find( $aid ) { // fn:
		$mapper = C_Album_Mapper::get_instance();
		return $mapper->find($aid, TRUE);
	}
	// Deletes an album, all its child albums & galleries
	// returns true on success, false on fail
	//
	// TODO: return from Delete is ignored on children
	// 		There may be only some children won't delete
	//		We should flag a warning. Or somewthing.
	static function Delete( $aid ) { // fn:

		if ( !$album = self::Find( $aid ) ) { return false; }

		$kids = $album->sortorder;

		foreach( $kids as $kid ) {

			// if 'a' prefix, strip 'a' & delete the album
			if ( substr($kid, 0, 1) == 'a' ) {
				$id = substr($kid, 1);
				self::Delete( $id );
			} else {
				// else delete the gallery
				ka_ngg_Gallery::Delete(  $kid );
			}
		}
		$album->destroy();

		return true;
	}
	// find all thealbums in the database.
	static function FindAll() { // fn:
		$mapper = C_Album_Mapper::get_instance();
		return $mapper->find_all();
	}
	// find the parent of gallery given by id, or false if it's in the root
	// Surely there has to be a better way than searching every album in the database??
	static function FindParent( $id, $prefix = "" ) { // fn:
		$albums = ka_ngg_Album::FindAll();
		foreach ( $albums as $album ) {
			if ( in_array( "$prefix$id", $album->sortorder )) {
				return $album;
			}
		}
		return false;
	}
	
}
class ka_ngg_Gallery { //class:

	// returns a new C_Gallery object or false
	static function New( $name ) { // fn:

		$mapper = C_Gallery_Mapper::get_instance();
		return $mapper->create(array('title' => $name));

	}
	// returns a found C_Gallery object or false
	static function Find( $gid ) { // fn:
		$mapper = C_Gallery_Mapper::get_instance();
		return $mapper->find($gid, TRUE);
	}
	// ToDo: there's no error checking on this.
	static function Delete( $gid ) { // fn:

		if( !$gallery = self::Find( $gid ) ) { return false; }

		// gallery->destroy() doesn't remove any image files or the
		// gallery directory or images from the database. 

		$images = $gallery->get_images();  // returns gallery objects.
		foreach( $images as $image ) {
			Logger::Log( "Deleting: " . $image->pid );
			ka_ngg_Image::Delete( $image->pid );
		}

		// php rmdir doesn't delete non-empty dirs
		// ka_ngg_Image::Delete() still leaves the thumb directory
		// & .DS_Store.
		self::delTree( ABSPATH . $gallery->path );

		$gallery->destroy();
		return true;
	}
	// find all the galleries in the database.
	static function FindAll() { // fn:
		$mapper = C_Gallery_Mapper::get_instance();
		$galleries = $mapper->find_all();

		// find_all() returns a list of stdClasses.
		// turn them into C_Gallery objects.
		$ret = [];
		foreach ( $galleries as $gallery ) {
			$ret[] = self::Find( $gallery->gid );
		}
		return $ret;
	}
	static function GetImages( $gid ) { //fn:

		if( !$gallery = self::Find( $gid ) ) { return false; }

		// turn the stdClass into CImage
		$ret = [];
		$images = $gallery->get_images();
		foreach ( $images as $image ) {
			$ret[] = ka_ngg_Image::Find( $image->pid );
		}
		return $ret;
	}

	// delete non-empty directories
	// ToDo: this shouldn't be in the gallery class def
	private static function delTree($dir) { // fn:
		$files = array_diff(scandir($dir), array('.','..'));
		 foreach ($files as $file) {
		   (is_dir("$dir/$file")) ? self::delTree("$dir/$file") : unlink("$dir/$file");
		 }
		 return rmdir($dir);
	   }
}
class ka_ngg_Image { // class:

	static function Find( $pid ) { // fn:

		$image_mapper = C_Image_Mapper::get_instance();
		if ($image = $image_mapper->find($pid, TRUE)) {

			$gallery_mapper = C_Gallery_Mapper::get_instance();
			if ($gallery = $gallery_mapper->find($image->galleryid)) {
				$storage = C_Gallery_Storage::get_instance();
				$image->imageURL = $storage->get_image_url($image, 'full', TRUE);
				$image->thumbURL = $storage->get_thumb_url($image, TRUE);
				$image->imagePath = $storage->get_image_abspath($image);
				$image->thumbPath = $storage->get_thumb_abspath($image);
				return $image;
			} else {
				Logger::Log( "ka_ngg_Image::Find( $pid ): Gallery {$image->galleryid} Not found" );
				return false;
			}
		} else {
			Logger::Log( "ka_ngg_Image::New( $pid ): Image Not found" );
			return false;
		}
	}
	static function Delete( $pid ) {// fn:

		try {
			if( $image = ka_ngg_Image::Find( $pid ) ) {

				// remove both the thumb & the image and the odd backup file that appears.
				// ToDo: ngg can create resized cache images. Look into removing these
				//			if they becomes a problem.
				if ( !unlink( $image->imagePath ) ) {
					Logger::Log( "ka_ngg_Image::Delete( $pid ): image file doesn't exist. Ignoring");
				}
				if ( !unlink( $image->imagePath . "_backup" ) ) { // odd backup file ???
					Logger::Log( "ka_ngg_Image::Delete( $pid ): image backup file doesn't exist. Ignoring");
				}
				if ( !unlink( $image->thumbPath ) ) {
					Logger::Log( "ka_ngg_Image::Delete( $pid ): image thumb file doesn't exist. Ignoring");
				}
				   
				$image->destroy();
				return true;
			}
		} catch( Exception $e ) {
			Logger::Log( "ka_ngg::Delete($pid) Faild: "  . $e->GetMessage() );
			return false;
		}
	}
	static function Upload( $gallery, $imageData, $name, $id, $replace ) { //fn:

		$storage = C_Gallery_Storage::get_instance();
		try {
			$image = $storage->upload_base64_image($gallery, $imageData, $name, $id, $replace );
			if ($image) {
				$storage = C_Gallery_Storage::get_instance();
				$image->imageURL = $storage->get_image_url($image);
				$image->thumbURL = $storage->get_thumb_url($image);
				$image->imagePath = $storage->get_image_abspath($image);
				$image->thumbPath = $storage->get_thumb_abspath($image);
				//Logger::Log( "UploadImage: Returning id " . $image->id() );
				return $image->id();  // ToDo: !! pid??
			} else {
				return "UploadImage($id): Couldn't do upload_base_image";
			 }
		} catch ( Exception $e ) {
			return "UploadImage($id): " . $e->GetMessage();
		}
	}

}
