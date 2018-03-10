<?php
/**
 * Notes:
 * ======
 * 
 * 		Ngg gallery:
 * 			gid
 * 		Ngg album:
 * 			id
 * 		Ngg image:
 * 			pid
 *  		imageURL
 *			thumbURL
 *			imagePath
 *			thumbPath
 *
 * Note: these endpoints must return something using Return() or Error() else there'll be a confusing error in LR.
 * 
 * ==========================================================================================================
 */
$rest = new ka_ngg_Rest();

class ka_ngg_Rest{ // class:

	private static $baseRoute = 'NggRest/v1';	// ToDo: use plugin name for this.
	private $WarningText = "";

	// endpoint callbacks
	private $Endpoints = [

		[ '/album/create', 		'CreateAlbum', 		'POST'],
		[ '/album/delete',		'DeleteAlbum',		'POST'],
		[ '/album/rename',		'RenameAlbum',		'POST'],

		[ '/gallery/delete',	'DeleteGallery',	'POST'],
		[ '/gallery/create',	'CreateGallery',	'POST'],
		[ '/gallery/rename',	'RenameGallery',	'POST'],
		[ '/gallery/sort',		'SortGallery',		'POST'],

		[ '/reparent',			'Reparent',			'POST'],

		[ '/image/upload',		'UploadImage',		'POST'],
		[ '/image/delete',		'DeleteImage',		'POST'],

		[ '/log/delete',		'DeleteLog',		'POST'],

		[ '/test', 				'Test',				'POST'],

	];

	function __construct() { //fn:
		add_action( 'rest_api_init', [$this,'register'] );
	}
	function Test($data) { //fn:
		// Note: http://imagelytheme/wp-json/NggRest/v1/test
		
		$params = json_decode( $data->get_body() );

		$this->Login($params->username, $params->password );

		$this->Return( ["Test Return" => "OK", 'a' => $a]  );
	}
	/**************************************************************************
	 * 		Register endpoints 
	 **************************************************************************/
	function register() { //fn:

		foreach( $this->Endpoints as $endpoint ) {
			$route = $endpoint[0];
			$callback = [$this, $endpoint[1]];

			register_rest_route( self::$baseRoute, $route, array(
				'methods' => $endpoint[2],
				'callback' =>  $callback,
			) );
		}
	
	}
	/**************************************************************************
	 * 
	 * Logs in to WP, checks user has at least Editor permissions.
	// ToDo: Doesn't handle mulit-sites. 
	// ToDo: Doesn't handle NG permissions.
	// ToDo: Bug: if a gallery is created in LR and this fails, we have a gallery
	//				in LR that isn't on NG. Only solvable by deleting the LR gallery.
	//
	 **************************************************************************/
	function Login( $username, $password ) { //fn:

		$user = wp_get_current_user();
		$nonce = $_SERVER['HTTP_X_WP_NONCE'];

		if ( $user->ID != 0 ) { 

			// user already logged in so verify the nonce
			$verified = wp_verify_nonce( $nonce, 'wp_rest' );
			if ( !$verified ) {
				$this->Error( "ka_ngg_Rest: Verification failed" );
			}

		} else {

			// no user logged in, try loging using username & password.
			$WPuser = wp_authenticate($username, $password);

			if( is_wp_error( $WPuser ) ) {
				$this->Error( "ka_ngg_Rest: Login to WP failed. Please check login credentials" );
			}
			$user = wp_set_current_user($WPuser->ID);
		
		}

		// at least editor privileges.
		if ( !current_user_can('editor') && !current_user_can('administrator') ) {
			$this->Error( "ka_ngg_rest: User must have at least Editor permissions" );
		} 
	}

	/**************************************************************************
	 * 		Start of Callbacks for endpoints
	 **************************************************************************/
	function DeleteLog( $data ) {
		//Logger::$ClearLog = true;
		Logger::Delete();
		$this->Return( "Logfile cleared" );
	}
	/*************************************************************************
	 * 
	 * Create new album, given name & parent ID
	 *
	 **************************************************************************/
	function CreateAlbum( $data ) { //fn:

		$params = json_decode( $data->get_body() );

		$this->Login($params->username, $params->password );

		$albumName = $params->data->name;			// name of gallery
		$parentID = $params->data->parent;			// id of parent, if it has one, else empty

		if( ! $album = ka_ngg_Album::New( $albumName ) ) {
			$this->Error( "CreateAlbum( $albumName ) couldn't make new album" );
		}

		if ($album->save()) {

			$aid = $album->id();

			if ( $parentID ) {
				// add this new album's id to the parent's child list
				if ( $parent = ka_ngg_Album::Find( $parentID ) ) {
					$parent->sortorder[] = 'a' . $aid;
					$parent->save();
				}
			}
			$this->Return( [ 'aid' => $aid ] );
		} else {
			$this->Error( "CreateAlbum() couldn't save album {$albumName}");
		}
	}
	/**************************************************************************
	 * 
	 * Delete and Album along with all its child albums, galleries & images
	 * 
	 **************************************************************************/
	function DeleteAlbum( $data ) { //fn:

		$params = json_decode( $data->get_body() );

		$this->Login($params->username, $params->password );

		$aid = $params->data->aid;
		$name = $params->data->name;

		if ( !ka_ngg_Album::Delete( $aid ) ) {
			$this->Error( "NggRest->DeleteAlbum failed. Name=$name" );
		}

		$this->Return( "OK from DeleteAlbum $name, $aid" );

	
	}
	function RenameAlbum( $data ) { //fn:

		$params = json_decode( $data->get_body() );
		$this->Login($params->username, $params->password );

		$aid = $params->data->aid;
		$newName = $params->data->name;

		if ( !$album = ka_ngg_Album::Find( $aid ) ) {
			$this->Error( "RenameAlbum( $aid ) couldn't find album" );
		}
		$album->name = $newName;
		$album->title = $newName;
		$album->save();

		$this->Return( "OK from RenameAlbum" );

	}
	function Reparent( $data ) { //fn:

		// ToDo: Error Checking: if !aid && !gid, especially
		// ToDo: reparenting an album that contains galleries leaves the 
		//			galleries in the old parent album. ALthough I think this may be an LR thing.

		Logger::Log( "ReparentAlbum()" );

		$params = json_decode( $data->get_body() );
		$this->Login($params->username, $params->password );

		$aid = $params->data->aid;		// album id. Empty if it's a gallery
		$gid = $params->data->gid;		// galley id. Empty if it's an album.
		$prefix = $aid ? "a" : "";
		$thisID = $aid ? $aid : $gid;

		$oldParentID = $params->data->parent;
		$newParentID = $params->data->newparent;

		/*
		if ( $aid ) {
			Logger::Log( "This Album: " . $aid . " old parent: " . $oldParentID . " new parent: " . $newParentID  );
		} else {
			Logger::Log( "This Gallery: " . $gid . " old parent: " . $oldParentID . " new parent: " . $newParentID  );
		}
		*/
		if ( $newParentID ) { // moving to a new parent (as opposed to the root)
			$newParent = ka_ngg_Album::Find( $newParentID );
			$newParent->sortorder[] = $prefix . $thisID;
			$newParent->save();
		} 

		if ( $oldParentID ) { // currently has a parent - isn't in the root.
			$oldParent = ka_ngg_Album::Find( $oldParentID );
			if (($i = array_search( $prefix . $thisID, $oldParent->sortorder)) !== false) {
				unset($oldParent->sortorder[$i]);
				$oldParent->save();
			}
		}

		$this->Return( "OK from Reparent()" );
	}
	/**************************************************************************
	 * 
	 * Create a gallery given a name and a parent
	 * !! This isn't actually called until after the first publish op in LR
	 **************************************************************************/
	function CreateGallery( $data ) { //fn:

		$params = json_decode( $data->get_body() );

		$this->Login($params->username, $params->password );

		$name = $params->data->name;
		$parentID = $params->data->parent;

		if ( !$gallery = ka_ngg_Gallery::New( $name ) ) {
			$this->Error( "CreateGallery() couldn't create new gallery $name" );
		}
		if ( !$gallery->save() ) {
			$this->Error( "CreateGaller() couldn't save new gallery $name" );
		}
		$gid = $gallery->id();
		// find the parent album and update its sort order to include this new gallery
		if ( $parentID ) {
			if ( $parent = ka_ngg_Album::Find( $parentID ) ) {
				$parent->sortorder[] = $gid;
				$parent->save();
			} else {
				$this->Error( "CreateGallery() couldn't update parent's child list" );
			}
		}
		$this->Return( ['gid' => $gid] );
	}
	/***************************************************************************
	 * 
	 * Delete a gallery given its id.
	 * Also deletes all the gallery's images and associated files & folders in
	 * 		the wp-content/gallery folder
	 ***************************************************************************/ 
	function DeleteGallery( $data ) { //fn:

		$params = json_decode( $data->get_body() );

		$this->Login($params->username, $params->password );

		$gid = $params->data->gid;

		Logger::Log( "Deleting Gallery $gid");
		if ( !ka_ngg_Gallery::Delete( $gid ) ) {
			$this->Error( "DeleteGallery() couldn't delete gallery $gid" );
		}
		$this->Return( "OK from DeleteGallery" );

	}
	function RenameGallery( $data ) { //fn:

		$params = json_decode( $data->get_body() );
		$this->Login($params->username, $params->password );

		$gid = $params->data->gid;
		$newName = $params->data->name;

		if ( !$gallery = ka_ngg_Gallery::Find( $gid ) ) {
			$this->Error( "RenameGallery( $gid ) couldn't find gallery" );
		}

		// ToDo: difference between title & name here.
		//$gallery->name = $newName;
		$gallery->title = $newName;		// this is the one ngg seems to use,so what's name for?
		$gallery->save();

		$this->Return( "OK from RenameGallery. new name = $newName" );

	}
	/***************************************************************************
	 * 
	 * Sort a gallery given a list of image ids in sort order
	 * 
	 ***************************************************************************/
	function SortGallery( $data ) { //fn:
		 
		$params = json_decode( $data->get_body() );

		$this->Login($params->username, $params->password );

		// doesn't need gallery id.
		$sequence = $params->data->sequence;
		Logger::Log( "Sorting Gallery " );

		$order = 1;
		foreach ( $sequence as $id ) {
			$image = ka_ngg_Image::Find( $id );
			$image->sortorder = $order ++;
			$image->save();
		}

		$this->Return( "OK from Sort Gallery" );
		

	}
	function UploadImage( $data ) { // fn:

		$params = json_decode( $data->get_body() );

		$gid = $params->data->gid;
		$id = $params->data->id;
		$name = $params->data->name;
		$imageData = $params->data->imagedata;
		$count = $params->data->count;

		Logger::Log( "UploadImage(): Image Number in Upload sequence {$count} (pid=$id)" );

		$this->Login($params->username, $params->password );
		

		if ( !$gallery = ka_ngg_Gallery::Find( $gid ) ) {
			$this->Error( "UploadImage( $id ): Couldn't find gallery $gid" );
		}

		$replace = $id ? true : false;
		
		$pidOrError = ka_ngg_Image::Upload($gallery, $imageData, $name, $id, $replace );
		if ( is_string( $pidOrError ) ) {
			$this->Error( $pidOrError );
		} else {
			Logger::Log( "UploadImage(): returns pid=$pidOrError for image number $count" );
			$this->Return( ['id' => $pidOrError ] );
		}
		
	}
	function DeleteImage( $data ) { //fn:

		$params = json_decode( $data->get_body() );

		$this->Login($params->username, $params->password );

		$pid = $params->data->pid;

		Logger::Log( "Deleting Image $pid" );	

		if ( ka_ngg_Image::Delete( $pid ) ) {
			$this->Return( "OK From DeleteImage" );
		} else {
			$this->Error( "Couldn't Delete Image $pid" );
		}
	}
	private function tmpdir() { //fn:
		$dir = WP_CONTENT_DIR . "/ka_ngg_rest_tmp";

		if (!file_exists( $dir )) {
			mkdir( $dir, 0755, true);
		}
		return $dir;

	}

	/*************************************************************************
	 * 
	 * Returns, Error, Warns. All endpoints should call one of these
	 *
	 *************************************************************************/

	/*************************************************************************
	 * 
	 * Returns a JSON string:
	 *************************************************************************/

	 function Return( $data, $msg = "" ) { //fn:
		 $return = ['data' => $data];
		 if ( $msg != "" ) {
			 $return['message'] = $msg;
		}
		if ( $this->WarningText != "" ) {
			$return['warning'] = $this->warning;
		}

		 die( json_encode( $return ) );

	 }
	/***************************************************************************
	*		Error()
	*		returns array ['error' => msg]
	***************************************************************************/
	 function Error( $msg ) { // fn:
		 Logger::Log( "ka_ngg_Rest->Error($msg)" ); 
		 die( json_encode( ['error' => $msg ] ) );
	 }
	/***************************************************************************
	 * 		Warning()
	 * 		Adds text to the warning string. Warning string is returned with
	 * 		data in ->Return().
	 ***************************************************************************/
	 function Warn( $msg ) { // fn:
		 Logger::Log(  "ka_ngg_Rest->Warn( $msg )" );
		 $this->WarningText .= "\n" . $msg;
	 }

}
