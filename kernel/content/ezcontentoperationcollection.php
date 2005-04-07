<?php
//
// Definition of eZContentOperationCollection class
//
// Created on: <01-Nov-2002 13:51:17 amos>
//
// Copyright (C) 1999-2005 eZ systems as. All rights reserved.
//
// This source file is part of the eZ publish (tm) Open Source Content
// Management System.
//
// This file may be distributed and/or modified under the terms of the
// "GNU General Public License" version 2 as published by the Free
// Software Foundation and appearing in the file LICENSE included in
// the packaging of this file.
//
// Licencees holding a valid "eZ publish professional licence" version 2
// may use this file in accordance with the "eZ publish professional licence"
// version 2 Agreement provided with the Software.
//
// This file is provided AS IS with NO WARRANTY OF ANY KIND, INCLUDING
// THE WARRANTY OF DESIGN, MERCHANTABILITY AND FITNESS FOR A PARTICULAR
// PURPOSE.
//
// The "eZ publish professional licence" version 2 is available at
// http://ez.no/ez_publish/licences/professional/ and in the file
// PROFESSIONAL_LICENCE included in the packaging of this file.
// For pricing of this licence please contact us via e-mail to licence@ez.no.
// Further contact information is available at http://ez.no/company/contact/.
//
// The "GNU General Public License" (GPL) is available at
// http://www.gnu.org/copyleft/gpl.html.
//
// Contact licence@ez.no if any conditions of this licencing isn't clear to
// you.
//

/*! \file ezcontentoperationcollection.php
*/

/*!
  \class eZContentOperationCollection ezcontentoperationcollection.php
  \brief The class eZContentOperationCollection does

*/

class eZContentOperationCollection
{
    /*!
     Constructor
    */
    function eZContentOperationCollection()
    {
    }

    function readNode( $nodeID )
    {

    }

    function readObject( $nodeID, $userID, $languageCode )
    {
        if ( $languageCode != '' )
        {
            $node =& eZContentObjectTreeNode::fetch( $nodeID, $languageCode );
        }
        else
        {
            $node =& eZContentObjectTreeNode::fetch( $nodeID );
        }

        if ( $node === null )
//            return $Module->handleError( EZ_ERROR_KERNEL_NOT_AVAILABLE, 'kernel' );
            return false;


        $object = $node->attribute( 'object' );

        if ( $object === null )
//            return $Module->handleError( EZ_ERROR_KERNEL_ACCESS_DENIED, 'kernel' );
        {
            return false;
        }
/*
        if ( !$object->attribute( 'can_read' ) )
        {
//            return $Module->handleError( EZ_ERROR_KERNEL_ACCESS_DENIED, 'kernel' );
            return false;
        }
*/
        if ( $languageCode != '' )
        {
            $object->setCurrentLanguage( $languageCode );
        }
        return array( 'status' => true, 'object' => $object, 'node' => $node );
    }

    function loopNodes( $nodeID )
    {
        return array( 'parameters' => array( array( 'parent_node_id' => 3 ),
                                             array( 'parent_node_id' => 5 ),
                                             array( 'parent_node_id' => 12 ) ) );
    }

    function loopNodeAssignment( $objectID, $versionNum )
    {
        $object =& eZContentObject::fetch( $objectID );
        $version =& $object->version( $versionNum );
        $nodeAssignmentList =& $version->attribute( 'node_assignments' );

        $parameters = array();
        foreach ( array_keys( $nodeAssignmentList ) as $key )
        {
            $nodeAssignment =& $nodeAssignmentList[$key];
            if ( $nodeAssignment->attribute( 'parent_node' ) > 0 )
            {
                if ( $nodeAssignment->attribute( 'is_main' ) == 1 )
                {
                    $mainNodeID = $this->publishNode( $nodeAssignment->attribute( 'parent_node' ), $objectID, $versionNum, false );
                }
                else
                {
                    $parameters[] = array( 'parent_node_id' => $nodeAssignment->attribute( 'parent_node' ) );
                }
            }
        }
        for ( $i = 0; $i < count( $parameters ); $i++ )
        {
            $parameters[$i]['main_node_id'] = $mainNodeID;
        }

        return array( 'parameters' => $parameters );
    }

    function setVersionStatus( $objectID, $versionNum, $status )
    {
        $object =& eZContentObject::fetch( $objectID );

        if ( !$versionNum )
        {
            $versionNum = $object->attribute( 'current_version' );
        }
        $version =& $object->version( $versionNum );
        if ( $version === null )
            return;
        switch ( $status )
        {
            case 1:
            {
                $statusName = 'pending';
                $version->setAttribute( 'status', EZ_VERSION_STATUS_PENDING );
            } break;
            case 2:
            {
                $statusName = 'archived';
                $version->setAttribute( 'status', EZ_VERSION_STATUS_ARCHIVED );
            } break;
            case 3:
            {
                $statusName = 'published';
                $version->setAttribute( 'status', EZ_VERSION_STATUS_PUBLISHED );
            } break;
            default:
                $statusName = 'none';
        }
        $version->store();
    }

    function setObjectStatusPublished( $objectID )
    {
        $object =& eZContentObject::fetch( $objectID );
        $object->setAttribute( 'status', EZ_CONTENT_OBJECT_STATUS_PUBLISHED );
        $object->store();
    }

    function attributePublishAction( $objectID, $versionNum )
    {
        $object =& eZContentObject::fetch( $objectID );
        $nodes =& $object->assignedNodes();
//         $dataMap =& $object->attribute( 'data_map' );
        $contentObjectAttributes =& $object->contentObjectAttributes( true, $versionNum, null, false );
        foreach ( array_keys( $contentObjectAttributes ) as $contentObjectAttributeKey )
        {
            $contentObjectAttribute =& $contentObjectAttributes[$contentObjectAttributeKey];
            $contentObjectAttribute->onPublish( $object, $nodes );
        }
    }

    /*!
     \static
     Generates the related viewcaches (PreGeneration) for the content object.
     It will only do this if [ContentSettings]/PreViewCache in site.ini is enabled.

     \param $objectID The ID of the content object to generate caches for.
    */
    function generateObjectViewCache( $objectID )
    {
        // Generate the view cache
        $ini =& eZINI::instance();
        $object = eZContentObject::fetch( $objectID );
        $user =& eZUser::currentUser();

        include_once( 'kernel/classes/eznodeviewfunctions.php' );
        eZDebug::accumulatorStart( 'generate_cache', '', 'Generating view cache' );
        if ( $ini->variable( 'ContentSettings', 'PreViewCache' ) == 'enabled' )
        {
            $preCacheSiteaccessArray = $ini->variable( 'ContentSettings', 'PreCacheSiteaccessArray' );

            $currentSiteAccess = $GLOBALS['eZCurrentAccess']['name'];

            // This is the default view parameters for content/view
            $viewParameters = array( 'offset' => 0,
                                     'year' => false,
                                     'month' => false,
                                     'day' => false );

            foreach ( $preCacheSiteaccessArray as $changeToSiteAccess )
            {
                $GLOBALS['eZCurrentAccess']['name'] = $changeToSiteAccess;

                if ( $GLOBALS['eZCurrentAccess']['type'] == EZ_ACCESS_TYPE_URI )
                {
                    eZSys::clearAccessPath();
                    eZSys::addAccessPath( $changeToSiteAccess );
                }

                include_once( 'kernel/common/template.php' );
                $tpl =& templateInit();
                $res =& eZTemplateDesignResource::instance();

                // Get the sitedesign for this siteaccess
                $siteini = eZINI::instance( 'site.ini', 'settings', null, null, false );
                $siteini->prependOverrideDir( "siteaccess/$changeToSiteAccess", false, 'siteaccess' );
                $siteini->loadCache();
                $designSetting = $siteini->variable( "DesignSettings", "SiteDesign" );
                $res->setDesignSetting( $designSetting, 'site' );

                $res->setOverrideAccess( $changeToSiteAccess );

                $language = false; // Needs to be specified if you want to generate the cache for a specific language
                $viewMode = 'full';

                $assignedNodes =& $object->assignedNodes();
                $assignedNodes_keys = array_keys( $assignedNodes );
                foreach ( $assignedNodes_keys as $key )
                {
                    $node =& $assignedNodes[$key];

                    // We want to generate the cache for the specified user
                    $previewCacheUsers = $ini->variable( 'ContentSettings', 'PreviewCacheUsers' );
                    foreach ( $previewCacheUsers as $previewCacheUserID )
                    {
                        // If the text is 'anon' we need to fetch the Anonymous user ID.
                        if ( $previewCacheUserID === 'anonymous' )
                        {
                            $previewCacheUserID = $siteini->variable( "UserSettings", "AnonymousUserID" );
                            $previewCacheUser =& eZUser::fetch( $previewCacheUserID  );
                        }
                        else if ( $previewCacheUserID === 'current' )
                        {
                            $previewCacheUser =& $user;
                        }
                        else
                        {
                            $previewCacheUser =& eZUser::fetch( $previewCacheUserID  );
                        }
                        if ( !$previewCacheUser )
                            continue;

                        // Before we generate the view cache we must change the currently logged in user to $previewCacheUser
                        // If not the templates might read in wrong personalized data (preferences etc.)
                        $previewCacheUser->setCurrentlyLoggedInUser( $previewCacheUser, $previewCacheUser->attribute( 'contentobject_id' ) );

                        // Cache the current node
                        $cacheFileArray = eZNodeviewfunctions::generateViewCacheFile( $previewCacheUser, $node->attribute( 'node_id' ), 0, false, $language, $viewMode, $viewParameters );
                        $tmpRes = eZNodeviewfunctions::generateNodeView( $tpl, $node, $node->attribute( 'object' ), $language, $viewMode, 0, $cacheFileArray['cache_dir'], $cacheFileArray['cache_path'], true );

                        // Cache the parent nodes
                        $parentNode =& $node->attribute( 'parent' );
                        $cacheFileArray = eZNodeviewfunctions::generateViewCacheFile( $previewCacheUser, $parentNode->attribute( 'node_id' ), 0, false, $language, $viewMode, $viewParameters );
                        $tmpRes = eZNodeviewfunctions::generateNodeView( $tpl, $parentNode, $parentNode->attribute( 'object' ), $language, $viewMode, 0, $cacheFileArray['cache_dir'], $cacheFileArray['cache_path'], true );
                    }
                }
            }
            // Restore the old user as the current one
            $user->setCurrentlyLoggedInUser( $user, $user->attribute( 'contentobject_id' ) );

            $GLOBALS['eZCurrentAccess']['name'] = $currentSiteAccess;
            $res->setDesignSetting( $currentSiteAccess, 'site' );
            $res->setOverrideAccess( false );
            if ( $GLOBALS['eZCurrentAccess']['type'] == EZ_ACCESS_TYPE_URI )
            {
                eZSys::clearAccessPath();
                eZSys::addAccessPath( $currentSiteAccess );
            }
        }

        eZDebug::accumulatorStop( 'generate_cache' );
    }

    /*!
     \static
     Clears the related viewcaches for the content object using the smart viewcache system.

     \param $objectID The ID of the content object to clear caches for
     \param $versionNum The version of the object to use or \c true for current version
     \param $additionalNodeList An array with node IDs to add to clear list,
                                or \c false for no additional nodes.
    */
    function clearObjectViewCache( $objectID, $versionNum, $additionalNodeList = false )
    {
        // WARNING !! modifing this function don't forget to modify
        // eZContentCacheManager::clearObjectViewCache() too.

        eZDebug::accumulatorStart( 'check_cache', '', 'Check cache' );

        $ini =& eZINI::instance();
        if ( $ini->variable( 'ContentSettings', 'ViewCaching' ) == 'enabled' ||
             $ini->variable( 'TemplateSettings', 'TemplateCache' ) == 'enabled' )
        {
            $viewCacheINI =& eZINI::instance( 'viewcache.ini' );
            if ( $viewCacheINI->variable( 'ViewCacheSettings', 'SmartCacheClear' ) == 'enabled' )
            {
                include_once( 'kernel/classes/ezcontentcachemanager.php' );
                eZContentCacheManager::clearViewCache( $objectID, $versionNum, $additionalNodeList );
            }
            else
            {
                eZContentObject::expireAllCache();
            }
        }
        eZDebug::accumulatorStop( 'check_cache' );
    }

    function publishNode( $parentNodeID, $objectID, $versionNum, $mainNodeID )
    {
        $object =& eZContentObject::fetch( $objectID );
        $version =& $object->version( $versionNum );
        $nodeAssignment =& eZNodeAssignment::fetch( $objectID, $versionNum, $parentNodeID );
        $object->setAttribute( 'current_version', $versionNum );
        if ( $object->attribute( 'published' ) == 0 )
        {
            $object->setAttribute( 'published', mktime() );
        }
        $object->setAttribute( 'modified', mktime() );
        $object->store();

        $class =& eZContentClass::fetch( $object->attribute( 'contentclass_id' ) );
        $objectName = $class->contentObjectName( $object );

        $object->setName( $objectName, $versionNum );
//        $object->store();  // removed to reduce sql calls. restore if publish bugs occur, by kk

        $existingTranslations =& $version->translations( false );
        foreach( array_keys( $existingTranslations ) as $key )
        {
            $translation = $existingTranslations[$key];
            $translatedName = $class->contentObjectName( $object, $versionNum, $translation );
            $object->setName( $translatedName, $versionNum, $translation );
        }

        $fromNodeID = $nodeAssignment->attribute( 'from_node_id' );
        $originalObjectID = $nodeAssignment->attribute( 'contentobject_id' );

        $nodeID = $nodeAssignment->attribute( 'parent_node' );
        $parentNode =& eZContentObjectTreeNode::fetch( $nodeID );
        $parentNodeID = $parentNode->attribute( 'node_id' );
        $existingNode = null;

        if ( strlen( $nodeAssignment->attribute( 'parent_remote_id' ) ) > 0 )
        {
            $existingNode = eZContentObjectTreeNode::fetchByRemoteID( $nodeAssignment->attribute( 'parent_remote_id' ) );
        }
        if ( !$existingNode );
        {
            $existingNode =& eZContentObjectTreeNode::findNode( $nodeID , $object->attribute( 'id' ), true );
        }
        $updateSectionID = false;
        if ( $existingNode  == null )
        {
            if ( $fromNodeID == 0 || $fromNodeID == -1)
            {
                $parentNode =& eZContentObjectTreeNode::fetch( $nodeID );

                include_once( 'kernel/classes/ezcontentbrowserecent.php' );
                $user =& eZUser::currentUser();
                eZContentBrowseRecent::createNew( $user->id(), $parentNode->attribute( 'node_id' ), $parentNode->attribute( 'name' ) );

                $existingNode =& $parentNode->addChild( $object->attribute( 'id' ), 0, true );

                if ( $fromNodeID == -1 )
                {
                    $updateSectionID = true;
                }
            }
            else
            {
                // clear cache for old placement.
                $additionalNodeIDList = array( $fromNodeID );
                eZContentOperationCollection::clearObjectViewCache( $originalObjectID, $versionNum, $additionalNodeIDList );

                $originalNode =& eZContentObjectTreeNode::fetchNode( $originalObjectID, $fromNodeID );
                if ( $originalNode->attribute( 'main_node_id' ) == $originalNode->attribute( 'node_id' ) )
                {
                    $updateSectionID = true;
                }
                $originalNode->move( $parentNodeID );
                $existingNode =& eZContentObjectTreeNode::fetchNode( $originalObjectID, $parentNodeID );
            }
        }

        if ( strlen( $nodeAssignment->attribute( 'parent_remote_id' ) ) > 0 )
        {
            $existingNode->setAttribute( 'remote_id', $nodeAssignment->attribute( 'parent_remote_id' ) );
        }
        $existingNode->setAttribute( 'sort_field', $nodeAssignment->attribute( 'sort_field' ) );
        $existingNode->setAttribute( 'sort_order', $nodeAssignment->attribute( 'sort_order' ) );
        $existingNode->setAttribute( 'contentobject_version', $version->attribute( 'version' ) );
        $existingNode->setAttribute( 'contentobject_is_published', 1 );
        $existingNode->setName( $objectName );

        eZDebug::createAccumulatorGroup( 'nice_urls_total', 'Nice urls' );

        $existingNode->updateSubTreePath();

        if ( $mainNodeID > 0 )
        {
            $existingNodeID = $existingNode->attribute( 'node_id' );
            if ( $existingNodeID != $mainNodeID )
            {
                include_once( 'kernel/classes/ezcontentbrowserecent.php' );
                eZContentBrowseRecent::updateNodeID( $existingNodeID, $mainNodeID );
            }
            $existingNode->setAttribute( 'main_node_id', $mainNodeID );
        }
        else
        {
            $existingNode->setAttribute( 'main_node_id', $existingNode->attribute( 'node_id' ) );
        }

        $version->setAttribute( 'status', EZ_VERSION_STATUS_PUBLISHED );
        $version->store();

        $object->store();
        $existingNode->store();

        if ( $updateSectionID )
        {
            eZDebug::writeDebug( "will  update section ID " );
            eZContentOperationCollection::updateSectionID( $objectID, $versionNum );
        }

        // Clear cache after publish
        $ini =& eZINI::instance();
        $templateBlockCacheEnabled = ( $ini->variable( 'TemplateSettings', 'TemplateCache' ) == 'enabled' );

        if ( $templateBlockCacheEnabled )
        {
            include_once( 'kernel/classes/ezcontentobject.php' );
            eZContentObject::expireTemplateBlockCache();
        }

        if ( $mainNodeID == false )
        {
            return $existingNode->attribute( "node_id" );
        }
    }

    function updateSectionID( $objectID, $versionNum )
    {
        if ( $versionNum == 1  )
            return;

        $object =& eZContentObject::fetch( $objectID );
        $version =& $object->version( $versionNum );

        if ( $object->attribute( 'current_version' ) == $versionNum )
            return;

        list( $newMainAssignment ) = eZNodeAssignment::fetchForObject( $objectID, $versionNum, 1 );

        $currentVersion =& $object->attribute( 'current' );
        list( $oldMainAssignment ) = eZNodeAssignment::fetchForObject( $objectID, $object->attribute( 'current_version' ), 1 );

        if ( $newMainAssignment && $oldMainAssignment
             &&  $newMainAssignment->attribute( 'parent_node' ) != $oldMainAssignment->attribute( 'parent_node' ) )
        {
            $oldMainParentNode =& $oldMainAssignment->attribute( 'parent_node_obj' );
            if ( $oldMainParentNode )
            {
                $oldParentObject =& $oldMainParentNode->attribute( 'object' );
                $oldParentObjectSectionID = $oldParentObject->attribute( 'section_id' );
                if ( $oldParentObjectSectionID == $object->attribute( 'section_id' ) )
                {
                    $newParentNode =& $newMainAssignment->attribute( 'parent_node_obj' );
                    if ( !$newParentNode )
                        return;
                    $newParentObject =& $newParentNode->attribute( 'object' );
                    if ( !$newParentObject )
                        return;

                    $newSectionID = $newParentObject->attribute( 'section_id' );

                    if ( $newSectionID != $object->attribute( 'section_id' ) )
                    {
                        $oldSectionID = $object->attribute( 'section_id' );
                        $object->setAttribute( 'section_id', $newSectionID );
                        $object->store();
                        $mainNodeID = $object->attribute( 'main_node_id' );
                        if ( $mainNodeID > 0 )
                            eZContentObjectTreeNode::assignSectionToSubTree( $mainNodeID,
                                                                             $newSectionID,
                                                                             $oldSectionID );

                    }
                }
            }
        }
    }

    function removeOldNodes( $objectID, $versionNum )
    {
        $object =& eZContentObject::fetch( $objectID );
        $version =& $object->version( $versionNum );

        $assignedExistingNodes =& $object->attribute( 'assigned_nodes' );

        $curentVersionNodeAssignments = $version->attribute( 'node_assignments' );
        $versionParentIDList = array();
        foreach ( array_keys( $curentVersionNodeAssignments ) as $key )
        {
            $nodeAssignment =& $curentVersionNodeAssignments[$key];
            $versionParentIDList[] = $nodeAssignment->attribute( 'parent_node' );
        }
        foreach ( array_keys( $assignedExistingNodes )  as $key )
        {
            $node =& $assignedExistingNodes[$key];
            if ( $node->attribute( 'contentobject_version' ) < $version->attribute( 'version' ) &&
                 !in_array( $node->attribute( 'parent_node_id' ), $versionParentIDList ) )
            {
                $node->remove();
            }
        }
    }

    function registerSearchObject( $objectID, $versionNum )
    {
        eZDebug::createAccumulatorGroup( 'search_total', 'Search Total' );

        include_once( "lib/ezutils/classes/ezini.php" );

        $ini =& eZINI::instance( 'site.ini' );
        $delayedIndexing = ( $ini->variable( 'SearchSettings', 'DelayedIndexing' ) == 'enabled' );

        if ( $delayedIndexing )
        {
            include_once( "lib/ezdb/classes/ezdb.php" );

            $db =& eZDB::instance();
            $db->query( 'INSERT INTO ezpending_actions( action, param ) VALUES ( "index_object", '. (int)$objectID. ' )' );
        }
        else
        {
            include_once( "kernel/classes/ezsearch.php" );
            $object =& eZContentObject::fetch( $objectID );
            // Register the object in the search engine.
            eZDebug::accumulatorStart( 'remove_object', 'search_total', 'remove object' );
            eZSearch::removeObject( $object );
            eZDebug::accumulatorStop( 'remove_object' );
            eZDebug::accumulatorStart( 'add_object', 'search_total', 'add object' );
            eZSearch::addObject( $object );
            eZDebug::accumulatorStop( 'add_object' );
        }
    }


    function createNotificationEvent( $objectID, $versionNum )
    {
        include_once( 'kernel/classes/notification/eznotificationevent.php' );
        $event =& eZNotificationEvent::create( 'ezpublish', array( 'object' => $objectID,
                                                                   'version' => $versionNum ) );
        $event->store();
    }
}

?>
