--[[
	Main entry point for plugin.
]]

require 'strict'

local LrDialogs = import( 'LrDialogs' )
local LrApplication = import( 'LrApplication' )
local LrDate = import( 'LrDate' )


JSON=require 'JSON'

require 'Dialogs'
require 'Post'
require 'Process'
require 'Logger'

local publishServiceProvider = {}

publishServiceProvider.small_icon = "Small-icon.png"

publishServiceProvider.supportsIncrementalPublish = 'only'							-- only publish. No export facility
publishServiceProvider.allowFileFormats = { 'JPEG' } 								-- jpeg only
publishServiceProvider.hidePrintResolution = true									-- hide print res controls
publishServiceProvider.canExportVideo = false 										-- video is not supported through this plug-in
publishServiceProvider.hideSections = { 'exportLocation' }							-- hide export location

publishServiceProvider.processRenderedPhotos = processRenderedPhotos				-- see process.jua

publishServiceProvider.startDialog = dialogs.startDialog							-- see dialogs.lua
publishServiceProvider.sectionsForTopOfDialog = dialogs.sectionsForTopOfDialog

publishServiceProvider.exportPresetFields = {
	{ key = "siteURL", default = "" },
	{ key = "loginName", default = "" },
	{ key = "loginPassword", default = "" },
}

-- menu titles, Albums, Galleries per NG rather then Collections & Sets
publishServiceProvider.titleForPublishedCollection = "NextGen Gallery"
publishServiceProvider.titleForPublishedCollectionSet = "NextGen Album"
publishServiceProvider.titleForPublishedSmartCollection = "Smart NextGen Album" 

-- collection or collection set rename callback
function publishServiceProvider.renamePublishedCollection( publishSettings, info )

	local collection = info.publishedCollection
	local newName = info.name
	local remoteID = collection:getRemoteId()

	Log( "Rename: " .. collection:getName() .. " to " .. newName  )

	if collection:type() == 'LrPublishedCollectionSet' then
		Log( "Renaming set to: ", newName )
		local result = Post( "album/rename", { aid = remoteID, name = newName }, publishSettings )
		Log( "rename returns", result )
	elseif collection:type() == 'LrPublishedCollection' then
		Log( "Renaming collection to: ", newName )
		local result = Post( "gallery/rename", { gid = remoteID, name = newName }, publishSettings )
		Log( "rename returns", result )
	end
	
end

-- reparent collection or collection set callback
function publishServiceProvider.reparentPublishedCollection( publishSettings, info )

	local data = {}

	local collection = info.publishedCollection
	local thisRemoteId = collection:getRemoteId()

	if #info.parents ~= 0 then 
		local newRemoteParentId = info.parents[#info.parents].remoteCollectionId
		local newRemoteParentName = info.parents[#info.parents].name

		data.newparent = newRemoteParentId
		Log( "new parent: ", data.newparent )
	end

	local parent = collection:getParent()
	if parent ~= nil then 		-- not a root collection
		data.parent = parent:getRemoteId()
		Log( "old parent: ",  data.parent )
	end

	if collection:type() == 'LrPublishedCollectionSet' then

		Log( "Reparenting set/album: ", thisRemoteId )
		data.aid = thisRemoteId
		local result = Post( "reparent", data, publishSettings )

	elseif collection:type() == 'LrPublishedCollection' then

		Log( "Reparenting collection/gallery: ", thisRemoteId )
		data.gid = thisRemoteId
		local result = Post( "reparent",  data, publishSettings )

	end


end

-- image delete callback.
function publishServiceProvider.deletePhotosFromPublishedCollection( publishSettings, arrayOfPhotoIds, deletedCallback )

	for i, photoId in ipairs( arrayOfPhotoIds ) do

		Log( string.format( "Deleting id: %d", photoId ));
		local result = Post( "image/delete",  { pid = photoId }, publishSettings )
		
		-- call the delete callback even if it fails on the Wordpress end
		-- ToDo: Need to fix it so REST doesn't return an error if the delete fails
		--			there's still a potential conflict here if the image is out of
		--			kilter between the server and the local.
		--if result ~= nil then
			deletedCallback( photoId )
		--end

	end
end
-- called when a collection or collection set is deleted
function publishServiceProvider.deletePublishedCollection( publishSettings, info  )

	local collection = info.publishedCollection
	local remoteID = collection:getRemoteId();
	local collectionName = collection:getName()

	-- ToDo: LR quits the op if there's even one failure. Need to delete all we can !! is this fixed??
	if collection:type() == 'LrPublishedCollectionSet' then
		Log( "Deleting set/album: ", remoteID )
		local result = Post( "album/delete", { aid = remoteID, name = collectionName }, publishSettings )
	elseif collection:type() == 'LrPublishedCollection' then
		Log( "Deleting collection/gallery: ", remoteID )
		local result = Post( "gallery/delete", { gid = remoteID, name = collectionName }, publishSettings )
	end

end

-- called when  collection (gallery) is added or renamed.
function publishServiceProvider.updateCollectionSettings( publishSettings, info )
	local data = {}		-- the data table we'll be sending to WP

	local collection = info.publishedCollection
	local remoteID = collection:getRemoteId()	-- null if not yet published

	data.name = collection:getName()

	-- parenting
	-- WP needs to add the new album to the parent's list of children.
	local parentSet = collection:getParent()
	if parentSet then
		data.parent = parentSet:getRemoteId()
	end

	if remoteID == nil then	-- remote gallery doesn't exist yet, create new one

		Log( "Creating gallery", data.name )

		local result = Post( "gallery/create", data, publishSettings )

		if result ~= nil then
			local gid = result.gid
			local  catalog = LrApplication.activeCatalog()
			catalog:withWriteAccessDo( "setGID", function( context )
				collection:setRemoteId( gid ) -- set remote gallery id
				Log( "Set remote album id: ", gid )
				end )
		end
	else
		Log( "Remote Gallery Exists already. Doing nothing")
	end

end

-- called when a publish collection set (album) is added or changed. (renamed)
function publishServiceProvider.updateCollectionSetSettings( publishSettings, info ) 
	Log( "update Collection Set Settings, creating new album", info.publishedCollection )

	--LrTasks.startAsyncTask( function()

		local data = {}		-- the data table we'll be sending to WP

		local collection = info.publishedCollection
		local remoteID = collection:getRemoteId()	-- null if not yet published
		data.name = collection:getName()

		-- parenting
		-- WP needs to add the new album to the parent's list of children.
		local parentSet = collection:getParent()
		if parentSet then
			data.parent = parentSet:getRemoteId()
		end
	
		if remoteID == nil then	-- remote album doesn't exist yet, create new one
		
			local result = Post( "album/create", data, publishSettings )
	
			if result ~= nil then

				local aid = result.aid
				-- set the remote id for this collection set
				local  catalog = LrApplication.activeCatalog()
				catalog:withWriteAccessDo( "setAID", function( context )
					collection:setRemoteId( aid ) -- set remote gallery id
					Log( "Set remote album id: ", aid )
					end )
			end

		else	-- gallery has been changed in some other way. Rename, possibly?
			--LrDialogs.showBezel( "Remote Album Exists", 3 )
			Log( "Remote Album Exists already. Doing nothing")
		end
	
	--end) -- lrTasks
end

publishServiceProvider.supportsCustomSortOrder = true  -- this must be set for ordering
function publishServiceProvider.imposeSortOrderOnPublishedCollection( publishSettings, info, remoteIdSequence )

	-- ToDo: LR gives an empty id sequence if count of images is 2 or less. Maybe
	-- why 2??
	if #remoteIdSequence == 0 then
		Log( "Sort: zero length id sequence. Nothing to sort")
		return
	end
	local result = Post( "gallery/sort", { sequence = remoteIdSequence }, publishSettings )
end


return publishServiceProvider
