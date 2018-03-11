NggPlusPlus ToDo list
=====================

* catch errors in JSON:decode() in Post() more betterer
* tags, metadata on image publish, with radio button for metadata
* there may be an issue with image sorting. Check.

* when an image is created the full sized url is used for the thumburl. I've hacked in a temporary fix but this should be looked at properly
* when the publish service is first created it includes a publish collection this should be created in NG.
* location of NG's gallery folder is hardcoded. We should get it from Wordpress
* option to delete all albums & galleries when the service is deleted.
* when a collection tree is being deleted, if a delete of one of the children fails, the delete dies leaving remaining galleries or albums un-deleted  
